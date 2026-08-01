<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessImportJob;
use App\Models\Category;
use App\Models\Department;
use App\Services\AssetImportService;
use App\Services\EntityCodeGeneratorService;
use App\Traits\SanitizesImportDates;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AssetImportController extends Controller
{
    use SanitizesImportDates;

    /**
     * How many rows the Header Row selector offers.
     *
     * Matches the 15-row sample AssetImportService::peek() reads — offering a row
     * the peek never looked at would just produce an out-of-range error.
     */
    private const HEADER_ROW_LIMIT = 15;

    /**
     * Staging columns the review UI is permitted to write.
     *
     * Shared by the single-cell and bulk edit paths. field_name reaches SQL as a
     * column name, so this is the injection barrier — and keeping one list means
     * the bulk path can't quietly accept something the single path refuses.
     */
    private const EDITABLE_FIELDS = [
        'tag', 'name', 'category_id', 'department_id', 'status',
        'model', 'serial_number', 'purchase_date', 'purchase_cost', 'remarks',
    ];

    /**
     * Selecting every row on a review page tops out at the 50/page slice. The
     * cap is headroom against a future perPage, not a UI limit — it exists so a
     * hand-rolled request can't hand us an unbounded IN list.
     */
    private const BULK_ROW_LIMIT = 200;

    private AssetImportService $importService;

    public function __construct(AssetImportService $importService)
    {
        $this->importService = $importService;
    }

    /**
     * AJAX: Parse uploaded XLSX/CSV file using native heuristic engine.
     * Returns JSON with cache key for the review page redirect.
     */
    public function parse(Request $request)
    {
        // Raise limits for large files (100K+ rows)
        @set_time_limit(120);
        @ini_set('memory_limit', '256M');

        $request->validate([
            'import_file' => 'required|file|mimes:csv,xlsx,txt|max:25600',
        ]);

        $file = $request->file('import_file');
        $extension = strtolower($file->getClientOriginalExtension());

        // Normalize extension
        if (! in_array($extension, ['csv', 'xlsx'])) {
            $extension = 'csv'; // Fallback for .txt
        }

        try {
            // Store uploaded file into local temp storage
            $fileName = uniqid('import_') . '.' . $extension;
            $path = $file->storeAs('temp', $fileName, 'local');
            if (!$path) {
                throw new \Exception('Failed to store the uploaded file.');
            }
            $fullPath = Storage::disk('local')->path($path);

            // Extract sample & true header (Hybrid Pipeline)
            $peekResult = $this->importService->peek($fullPath, $extension);

            $dataArray = [
                'temp_file_path'    => $path,
                'sheets'            => $peekResult['sheets'],
                'true_header'       => $peekResult['true_header'],
                'preview_data'      => $peekResult['preview_data'],
                'mapping_proposals' => $peekResult['mapping_proposals'],
                'current_sheet_index' => 0,
                // A fresh upload always starts on auto-detection; the user only
                // overrides it if the guess turns out wrong.
                'header_row_index'  => null,
                'header_row_choice' => 'auto',
            ];

            Cache::put('import_state_' . auth()->id(), $dataArray, 1800);

            return response()->json([
                'success'      => true,
                'redirect_url' => route('assets.import-mapping'),
            ]);

        } catch (\Exception $e) {
            Log::error('Import Parse Failure: ' . $e->getMessage());

            if (isset($path)) {
                Storage::disk('local')->delete($path);
            }

            return response()->json([
                'success' => false,
                'message' => __('assets.import_parse_error', ['message' => $e->getMessage()]),
            ], 422);
        }
    }

    /**
     * Render the mapping page.
     * Accepts optional ?sheet= query parameter to switch Excel sheets.
     */
    public function mapping(Request $request)
    {
        $cacheKey = 'import_state_' . auth()->id();
        $cachedData = Cache::get($cacheKey);

        if (!$cachedData) {
            return redirect()->route('assets.index', ['open_modal' => 'true'])
                ->with('warning', __('assets.import_parse_error', ['message' => __('assets.temporary_file_missing')]));
        }

        $tempFilePath = $cachedData['temp_file_path'] ?? '';
        if (empty($tempFilePath) || !\Illuminate\Support\Facades\Storage::disk('local')->exists($tempFilePath)) {
            return redirect()->route('assets.index', ['open_modal' => 'true'])
                ->with('warning', __('assets.import_parse_error', ['message' => __('assets.temporary_file_missing')]));
        }

        // Re-peek when the user changed the sheet, the header row, or both.
        //
        // Both controls navigate here with query parameters rather than posting to
        // an endpoint of their own — the sheet selector has always worked this way,
        // and giving the header row its own path would mean two re-peek code paths
        // that can drift apart.
        $peekWarning = null;

        if ($request->has('sheet') || $request->has('header_row')) {
            // ── Resolve the requested sheet (index or name) ────────────────
            $sheetsList     = $cachedData['sheets'] ?? [];
            $currentSheetVal = $cachedData['selected_sheet']
                ?? $cachedData['current_sheet_index']
                ?? 0;

            $requestedSheet = $request->has('sheet') ? $request->query('sheet') : $currentSheetVal;

            $sheetIndex = 0;
            if (is_numeric($requestedSheet)) {
                $sheetIndex = (int) $requestedSheet;
            } else {
                $foundIndex = array_search($requestedSheet, $sheetsList);
                if ($foundIndex !== false) {
                    $sheetIndex = $foundIndex;
                }
            }

            $sheetChanged = is_numeric($requestedSheet) && is_numeric($currentSheetVal)
                ? ((int) $requestedSheet !== (int) $currentSheetVal)
                : ($requestedSheet != $currentSheetVal);

            // ── Resolve the requested header row ──────────────────────────
            // The UI is 1-based ("Row 3") and offers 'auto'; internally the row is
            // a 0-based index and 'auto' is null, meaning "run the heuristic".
            $currentChoice   = $cachedData['header_row_choice'] ?? 'auto';
            $requestedChoice = $request->has('header_row')
                ? (string) $request->query('header_row')
                : (string) $currentChoice;

            $headerRowIndex = null;
            if ($requestedChoice !== 'auto') {
                $rowNumber = (int) $requestedChoice;
                if ($rowNumber < 1 || $rowNumber > self::HEADER_ROW_LIMIT) {
                    $requestedChoice = 'auto';
                } else {
                    $headerRowIndex = $rowNumber - 1;
                }
            }

            $headerChanged = $requestedChoice !== (string) $currentChoice;

            if ($sheetChanged || $headerChanged) {
                if (\Illuminate\Support\Facades\Storage::disk('local')->exists($tempFilePath)) {
                    $fullPath  = \Illuminate\Support\Facades\Storage::disk('local')->path($tempFilePath);
                    $extension = pathinfo($fullPath, PATHINFO_EXTENSION);

                    try {
                        $peekResult = $this->importService->peek($fullPath, $extension, $sheetIndex, $headerRowIndex);
                        $cachedData['true_header']       = $peekResult['true_header'];
                        $cachedData['preview_data']      = $peekResult['preview_data'];
                        $cachedData['mapping_proposals'] = $peekResult['mapping_proposals'];
                        $cachedData['current_sheet_index'] = $sheetIndex;
                        $cachedData['selected_sheet']      = $requestedSheet;
                        // header_row_index is what ProcessImportJob reads; the choice
                        // is what repopulates the select on the next render.
                        $cachedData['header_row_index']  = $headerRowIndex;
                        $cachedData['header_row_choice'] = $requestedChoice;

                        Cache::put($cacheKey, $cachedData, 1800);
                    } catch (\Exception $e) {
                        Log::warning('Sheet/header re-peek failed: ' . $e->getMessage());

                        // A manual header pick that lands on a blank row would
                        // otherwise look like a broken control: the page reloads,
                        // nothing changes, and nothing says why.
                        if ($headerRowIndex !== null) {
                            $peekWarning = __('assets.header_row_invalid', ['row' => $headerRowIndex + 1]);
                        }
                    }
                }
            }
        }

        $cachedData['header_row_choice'] = $cachedData['header_row_choice'] ?? 'auto';
        $cachedData['header_row_limit']  = self::HEADER_ROW_LIMIT;
        $cachedData['header_row_warning'] = $peekWarning;

        return view('assets.import.mapping-page', $cachedData);
    }

    /**
     * Validate the user's column mapping and dispatch the background import job.
     *
     * Validates path ownership to prevent path-traversal attacks, then dispatches
     * ProcessImportJob which streams rows into the staging table in 500-row chunks.
     * Returns a JSON response; the frontend polls /import/status for progress.
     */
    public function processMapping(Request $request)
    {
        $this->authorize('create', \App\Models\Asset::class);

        $request->validate([
            'payload' => 'required|string',
        ]);

        $payload = json_decode($request->input('payload'), true);
        if (!$payload || empty($payload['temp_file_path'])) {
            return response()->json([
                'success' => false,
                'message' => __('assets.import_parse_error', ['message' => 'Invalid or missing mapping payload.']),
            ], 422);
        }

        $mapping = $payload['mapping'] ?? [];

        // Validate: at least 1 mapped field (not only 'ignored')
        $hasMappedField = collect($mapping)
            ->except('ignored')
            ->filter(fn($zone) => !empty($zone['columns']))
            ->isNotEmpty();

        if (!$hasMappedField) {
            return response()->json([
                'success' => false,
                'message' => __('assets.mapping_required_alert'),
            ], 422);
        }

        $tempFilePath = $payload['temp_file_path'];

        // Guard against path-traversal: only allow paths matching the exact
        // format produced by parse() — prefix "temp/import_", hex uniqid, known extension.
        if (!preg_match('/^temp\/import_[a-f0-9]+\.(csv|xlsx)$/', $tempFilePath)) {
            return response()->json([
                'success' => false,
                'message' => __('assets.import_parse_error', ['message' => 'Invalid file path format.']),
            ], 422);
        }

        // Cross-check ownership: the submitted path must exactly match the one
        // stored by parse() in this user's own import_state cache entry.
        // Prevents one user from hijacking another user's temp file.
        $sessionState = Cache::get('import_state_' . auth()->id());
        if (!$sessionState || ($sessionState['temp_file_path'] ?? '') !== $tempFilePath) {
            return response()->json([
                'success' => false,
                'message' => __('assets.import_parse_error', ['message' => 'File path does not match your current import session.']),
            ], 403);
        }

        if (!Storage::disk('local')->exists($tempFilePath)) {
            return response()->json([
                'success' => false,
                'message' => __('assets.import_parse_error', ['message' => __('assets.temporary_file_missing')]),
            ], 422);
        }

        // Ensure the temp file is group-readable so the queue worker can access it
        $absolutePath = Storage::disk('local')->path($tempFilePath);
        @chmod($absolutePath, 0664);

        // Verify file is fully readable before handing off
        clearstatcache(true, $absolutePath);
        if (!is_readable($absolutePath) || filesize($absolutePath) === 0) {
            return response()->json([
                'success' => false,
                'message' => __('assets.import_parse_error', ['message' => 'Temp file is unreadable or empty.']),
            ], 422);
        }

        $selectedSheet = $payload['selected_sheet'] ?? 0;

        // Resolve the tenant property. Super-admins target the session-selected
        // property; regular users are scoped to their own assigned property.
        // We persist property_id back into the session cache so the background
        // worker can read it without needing the HTTP session.
        $propertyId = auth()->user()->isSuperAdmin()
            ? session('active_property_id')
            : auth()->user()->property_id;

        if (!$propertyId) {
            return response()->json([
                'success' => false,
                'message' => __('assets.import_parse_error', ['message' => 'No active property selected. Please select a property before importing.']),
            ], 422);
        }

        $importState = Cache::get('import_state_' . auth()->id(), []);
        $importState['property_id'] = $propertyId;
        Cache::put('import_state_' . auth()->id(), $importState, 1800);

        // Stamp this dispatch with its own identity. The scope is deliberately the
        // *dispatch*, not the upload: cancelling and re-submitting the same file
        // from the mapping page is a new attempt, and the abandoned job must not
        // recognise itself in the new one's progress record.
        //
        // Everything in this flow is keyed by user id alone, so without this the
        // seed below silently erases a pending cancellation and hands the old job
        // permission to keep writing over the new import's progress and staging rows.
        $attemptId = (string) Str::uuid();

        // Seed initial progress cache (all keys present for frontend)
        $progressKey = 'import_progress_' . auth()->id();
        Cache::put($progressKey, [
            'status'     => 'processing',
            'percentage' => 0,
            'processed'  => 0,
            'total'      => 0,
            'error'      => '',
            'import_id'  => $attemptId,
        ], 600);

        try {
            // Dispatch the streaming job with the RELATIVE storage path
            ProcessImportJob::dispatch(
                auth()->id(),
                $tempFilePath,
                $payload,
                $selectedSheet,
                $attemptId,
            );
        } catch (\Throwable $e) {
            Log::error('Failed to dispatch ProcessImportJob: ' . $e->getMessage());
            Cache::put($progressKey, [
                'status'     => 'failed',
                'percentage' => 0,
                'processed'  => 0,
                'total'      => 0,
                'error'      => '',
                'error_code' => \App\Exceptions\ImportFailure::GENERIC,
                'import_id'  => $attemptId,
            ], 600);

            return response()->json([
                'success' => false,
                'message' => __('assets.import_parse_error', ['message' => 'Failed to queue import job.']),
            ], 500);
        }

        return response()->json([
            'success'    => true,
            'status_url' => route('assets.import-status'),
        ]);
    }

    /**
     * AJAX: Return current import progress from cache.
     */
    public function status()
    {
        $default = [
            'status'     => 'pending',
            'percentage' => 0,
            'processed'  => 0,
            'total'      => 0,
            'error'      => '',
            'error_hint' => '',
        ];

        $progress = Cache::get('import_progress_' . auth()->id());

        if (!is_array($progress)) {
            return response()->json($default);
        }

        // Normalise onto the full shape — writers have historically stored partial
        // records — and keep import_id internal; it is bookkeeping for the worker,
        // not something the progress modal needs.
        $payload = array_merge($default, Arr::except($progress, ['import_id', 'error_code']));

        // ProcessImportJob runs in a queue worker with no session, so it cannot
        // resolve the importer's locale. It stores a translation key instead and
        // this request — which *does* have the locale — turns it into prose.
        //
        // A record with a free-text 'error' and no code is left untouched: records
        // written before this change are still in cache during a deploy, and a
        // stale English sentence beats a blank error box.
        $code = $progress['error_code'] ?? null;
        if ($code) {
            $payload['error']      = __('assets.' . $code);
            $payload['error_hint'] = __('assets.' . $code . '_hint');
        }

        return response()->json($payload);
    }

    /**
     * Intercept post-import flow to handle unknown category/department names.
     *
     * The import job stores raw text hints (_category_hint, _department_hint)
     * for values it could not resolve to existing IDs. This method reads those
     * hints from the staging table, matches them against the database, updates
     * staging rows with the resolved IDs, and — if any names are still missing
     * — renders the rapid-add form for the user to create them on the fly.
     */
    public function rapidAdd(Request $request)
    {
        // Super-admin targets the session-selected property; regular users use their own.
        $propertyId = auth()->user()->isSuperAdmin()
            ? session('active_property_id')
            : auth()->user()->property_id;

        if (!$propertyId) {
            return redirect()->route('assets.index')
                ->with('warning', __('assets.import_parse_error', ['message' => 'No active property selected.']));
        }

        $userId   = auth()->id();
        $stagingQ = \DB::table('temporary_asset_imports')
            ->where('user_id', $userId)
            ->where('property_id', $propertyId);

        if ($stagingQ->doesntExist()) {
            return redirect()->route('assets.index')
                ->with('warning', __('assets.import_parse_error', ['message' => 'Import session expired or not found.']));
        }

        // 1. Extract unique hints from the staging table
        $categoryHints = $stagingQ->clone()
            ->whereNotNull('_category_hint')
            ->where('_category_hint', '<>', '')
            ->distinct()->pluck('_category_hint')
            ->map(fn($v) => trim($v))->filter()->unique()->values()->toArray();

        $departmentHints = $stagingQ->clone()
            ->whereNotNull('_department_hint')
            ->where('_department_hint', '<>', '')
            ->distinct()->pluck('_department_hint')
            ->map(fn($v) => trim($v))->filter()->unique()->values()->toArray();

        // Case-insensitive look-up against entities that already exist in this property.
        $existingCategories = Category::where('property_id', $propertyId)
            ->whereIn(\DB::raw('LOWER(name)'), array_map('strtolower', $categoryHints))
            ->pluck('name')->toArray();

        $existingDepartments = Department::where('property_id', $propertyId)
            ->whereIn(\DB::raw('LOWER(name)'), array_map('strtolower', $departmentHints))
            ->pluck('name')->toArray();

        $missingCategories  = array_values(array_udiff($categoryHints, $existingCategories, 'strcasecmp'));
        $missingDepartments = array_values(array_udiff($departmentHints, $existingDepartments, 'strcasecmp'));

        $warnings             = [];
        $hasMissingCategories = !empty($missingCategories);
        $hasMissingDepartments = !empty($missingDepartments);

        // If the user lacks permission to create categories, clear the unresolved hints
        // from staging rows so those rows won't be blocked at storeBatch time.
        if ($hasMissingCategories && !auth()->user()->can('create', Category::class)) {
            $warnings[] = __('assets.import_unauthorized_category_add', ['count' => count($missingCategories)]);
            foreach ($missingCategories as $missingCat) {
                \DB::table('temporary_asset_imports')
                    ->where('user_id', $userId)->where('property_id', $propertyId)
                    ->whereRaw('LOWER(_category_hint) = ?', [strtolower($missingCat)])
                    ->update(['_category_hint' => '']);
            }
            $missingCategories = [];
        }

        if ($hasMissingDepartments && !auth()->user()->can('create', Department::class)) {
            $warnings[] = __('assets.import_unauthorized_department_add', ['count' => count($missingDepartments)]);
            foreach ($missingDepartments as $missingDept) {
                \DB::table('temporary_asset_imports')
                    ->where('user_id', $userId)->where('property_id', $propertyId)
                    ->whereRaw('LOWER(_department_hint) = ?', [strtolower($missingDept)])
                    ->update(['_department_hint' => '']);
            }
            $missingDepartments = [];
        }

        // Eagerly resolve existing entities and write their IDs into the staging rows
        // so that storeBatch() does not need to re-resolve them later.
        $existingCatModels  = Category::where('property_id', $propertyId)
            ->whereIn(\DB::raw('LOWER(name)'), array_map('strtolower', $categoryHints))->get();
        $existingDeptModels = Department::where('property_id', $propertyId)
            ->whereIn(\DB::raw('LOWER(name)'), array_map('strtolower', $departmentHints))->get();

        foreach ($existingCatModels as $cat) {
            \DB::table('temporary_asset_imports')
                ->where('user_id', $userId)->where('property_id', $propertyId)
                ->whereRaw('LOWER(_category_hint) = ?', [strtolower($cat->name)])
                ->update(['category_id' => $cat->id]);
        }
        foreach ($existingDeptModels as $dept) {
            \DB::table('temporary_asset_imports')
                ->where('user_id', $userId)->where('property_id', $propertyId)
                ->whereRaw('LOWER(_department_hint) = ?', [strtolower($dept->name)])
                ->update(['department_id' => $dept->id]);
        }

        // Recalculate is_invalid for all staging rows after entity mapping
        \DB::table('temporary_asset_imports')
            ->where('user_id', $userId)->where('property_id', $propertyId)
            ->update([
                'is_invalid' => \DB::raw("(name = '' OR name IS NULL OR category_id IS NULL)"),
            ]);

        // If none are missing (or were stripped), bypass and go to standard review
        if (empty($missingCategories) && empty($missingDepartments)) {
            $redirect = redirect()->route('assets.import-review');
            if (!empty($warnings)) {
                $redirect->with('warning', implode(' ', $warnings));
            }
            return $redirect;
        }

        return view('assets.import.rapid-add', compact('missingCategories', 'missingDepartments'));
    }

    /**
     * Persist the categories/departments created via the Rapid-Add form,
     * then write their new IDs back into the matching staging table rows.
     *
     * After this method, every staging row with a valid name + category_id
     * is flagged as is_invalid = false and ready for storeBatch().
     */
    public function storeRapidAdd(Request $request, EntityCodeGeneratorService $codeGen)
    {
        $request->validate([
            'categories'    => 'nullable|array',
            'categories.*'  => 'string|max:255',
            'departments'   => 'nullable|array',
            'departments.*' => 'string|max:255',
        ]);

        $propertyId = auth()->user()->isSuperAdmin()
            ? session('active_property_id')
            : auth()->user()->property_id;

        if (!$propertyId) {
            return redirect()->route('assets.index')
                ->with('warning', __('assets.import_parse_error', ['message' => 'No active property selected.']));
        }

        $userId = auth()->id();

        $stagingExists = \DB::table('temporary_asset_imports')
            ->where('user_id', $userId)
            ->where('property_id', $propertyId)
            ->exists();

        if (!$stagingExists) {
            return redirect()->route('assets.index')
                ->with('warning', __('assets.import_parse_error', ['message' => 'Import session expired or not found.']));
        }

        $createdCategories  = [];
        $createdDepartments = [];

        if ($request->filled('categories') && auth()->user()->can('create', Category::class)) {
            foreach ($request->categories as $name) {
                $code     = $codeGen->generateUniqueCode($name, Category::class, $propertyId);
                $category = Category::create([
                    'name'        => $name,
                    'code'        => $code,
                    'property_id' => $propertyId,
                ]);
                $createdCategories[$name] = $category->id;
            }
        }

        if ($request->filled('departments') && auth()->user()->can('create', Department::class)) {
            foreach ($request->departments as $name) {
                $code       = $codeGen->generateUniqueCode($name, Department::class, $propertyId);
                $department = Department::create([
                    'name'        => $name,
                    'code'        => $code,
                    'property_id' => $propertyId,
                ]);
                $createdDepartments[$name] = $department->id;
            }
        }

        // Write the freshly-created IDs back into the staging rows that referenced
        // these names as hints, then clear the hint column so the row is resolved.
        foreach ($createdCategories as $name => $catId) {
            \DB::table('temporary_asset_imports')
                ->where('user_id', $userId)
                ->where('property_id', $propertyId)
                ->whereRaw('LOWER(_category_hint) = ?', [strtolower($name)])
                ->update(['category_id' => $catId, '_category_hint' => '']);
        }

        foreach ($createdDepartments as $name => $deptId) {
            \DB::table('temporary_asset_imports')
                ->where('user_id', $userId)
                ->where('property_id', $propertyId)
                ->whereRaw('LOWER(_department_hint) = ?', [strtolower($name)])
                ->update(['department_id' => $deptId, '_department_hint' => '']);
        }

        // Re-evaluate validity for all rows in a single SQL UPDATE now that IDs are resolved.
        \DB::table('temporary_asset_imports')
            ->where('user_id', $userId)
            ->where('property_id', $propertyId)
            ->update([
                'is_invalid' => \DB::raw("(name = '' OR name IS NULL OR category_id IS NULL)"),
            ]);

        return redirect()->route('assets.import-review');
    }

    /**
     * Render the import review page with paginated staging rows.
     *
     * Reads directly from the staging table (50 rows per page), so only the
     * current page slice is held in PHP memory regardless of import size.
     * Categories and departments are pre-fetched once per request and keyed
     * by ID to avoid N+1 queries inside the Blade loop.
     * Computes the set of page numbers that contain invalid rows so the
     * pagination UI can show a visual warning indicator ("heatmap").
     */
    public function review(Request $request)
    {
        $propertyId = auth()->user()->isSuperAdmin()
            ? session('active_property_id')
            : auth()->user()->property_id;

        if (!$propertyId) {
            return redirect()->route('assets.index')
                ->with('warning', __('assets.import_parse_error', ['message' => 'No active property selected.']));
        }

        $userId   = auth()->id();
        $warning  = null;
        $perPage  = 50;

        $stagingBase = \DB::table('temporary_asset_imports')
            ->where('user_id', $userId)
            ->where('property_id', $propertyId);

        $total = $stagingBase->clone()->count();

        if ($total === 0) {
            return redirect()->route('assets.index')
                ->with('warning', __('assets.import_parse_error', ['message' => 'Import session expired or not found.']));
        }

        $validCount   = $stagingBase->clone()->where('is_invalid', false)
            ->where(function ($q) { $q->whereNotNull('name')->where('name', '<>', ''); })
            ->count();
        $invalidCount = $total - $validCount;

        // Which page numbers contain invalid rows (for the pagination heatmap)
        $invalidPages = $this->invalidPageNumbers($stagingBase, $perPage);

        // ── Paginate directly from DB ─────────────────────────────────
        $currentPage = LengthAwarePaginator::resolveCurrentPage();
        $pageItems   = $stagingBase->clone()
            ->orderBy('id')
            ->offset(($currentPage - 1) * $perPage)
            ->limit($perPage)
            ->get()
            ->map(fn($row) => (array) $row)
            ->toArray();

        $paginatedData = new LengthAwarePaginator(
            $pageItems,
            $total,
            $perPage,
            $currentPage,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        // Pre-fetch all categories and departments for this property once,
        // then pass keyed maps to the view to avoid per-row DB calls.
        $categories     = Category::where('property_id', $propertyId)->orderBy('name')->get();
        $departments    = Department::where('property_id', $propertyId)->orderBy('name')->get();
        $categoriesMap  = $categories->keyBy('id');
        $departmentsMap = $departments->keyBy('id');

        $pageOffset = ($currentPage - 1) * $perPage;

        return view('assets.import.review', compact(
            'paginatedData',
            'categories',
            'departments',
            'categoriesMap',
            'departmentsMap',
            'warning',
            'pageOffset',
            'total',
            'validCount',
            'invalidCount',
            'invalidPages'
        ));
    }

    /**
     * AJAX: Return live validation counts for the review page footer.
     *
     * Called by the Smart Import review page (triggerPreflight) on submit to keep
     * the valid/invalid counters in sync before the confirm modal is shown.
     *
     * NOTE: Manual bulk entry no longer reaches this endpoint — it moved to
     * BulkAssetEntryController (route assets.bulk-manual) and posts natively
     * without any pre-flight AJAX. The form-payload fallback below is therefore
     * dead on the manual path; it now only fires in an edge case where the staging
     * rows vanish between page render and submit (session cleanup by
     * app:clean-abandoned-imports, a parallel tab finishing storeBatch, or the user
     * deleting every row). Kept as a defensive no-op — it returns zero counts for a
     * staging-backed page, which is the correct answer in that race. Do not mistake
     * it for a live manual-entry code path.
     */
    public function calculateValidation(Request $request)
    {
        $propertyId = auth()->user()->isSuperAdmin()
            ? session('active_property_id')
            : auth()->user()->property_id;

        // Fallback: no staging rows for this user+property (see NOTE above — not the manual path)
        if (!$propertyId || !\DB::table('temporary_asset_imports')
                ->where('user_id', auth()->id())
                ->where('property_id', $propertyId)
                ->exists()) {
            // Count from the submitted form payload; yields zero for a staging-backed page
            $formAssets   = $request->input('assets', []);
            $validCount   = 0;
            $invalidCount = 0;
            foreach ($formAssets as $item) {
                $isEmpty = empty($item['name']) && empty($item['tag']) && empty($item['category_id']);
                if ($isEmpty) continue;
                if (empty($item['name']) || empty($item['category_id'])) $invalidCount++;
                else $validCount++;
            }
            return response()->json(['success' => true, 'validCount' => $validCount, 'invalidCount' => $invalidCount]);
        }

        $q            = \DB::table('temporary_asset_imports')
            ->where('user_id', auth()->id())->where('property_id', $propertyId);
        $total        = $q->clone()->count();
        $validCount   = $q->clone()->where('is_invalid', false)
            ->where(function ($q) { $q->whereNotNull('name')->where('name', '<>', ''); })->count();
        $invalidCount = $total - $validCount;

        return response()->json(['success' => true, 'validCount' => $validCount, 'invalidCount' => $invalidCount]);
    }

    /**
     * AJAX: Persist a single cell edit from the review table into the staging row.
     *
     * The review page sends edits as they happen (auto-save). This method
     * locates the staging row by its primary key, applies the update,
     * recalculates the row's is_invalid flag, and returns updated global
     * counts + heatmap page list so the frontend can refresh without a reload.
     *
     * Rows are addressed by id, never by position. Position was ambiguous the
     * moment anything before it was deleted: a debounced auto-save still holding
     * its pre-delete index would silently write to whichever row had shifted into
     * that slot. Since the id now arrives from the client, the user_id +
     * property_id predicates below are what keep it in-tenant.
     *
     * The field_name input is validated against a whitelist before being used
     * as a column name to prevent SQL injection.
     */
    public function updateSingleRow(Request $request)
    {
        $request->validate([
            'row_id'     => 'required|integer|min:1',
            'field_name' => 'required|string',
            'new_value'  => 'nullable|string',
        ]);

        $fieldName = $request->input('field_name');
        $newValue  = $request->input('new_value');

        // Normalize field_name if it comes as "assets[x][field]"
        if (preg_match('/^assets\[\d+\]\[(.+)\]$/', $fieldName, $matches)) {
            $fieldName = $matches[1];
        }

        if (!in_array($fieldName, self::EDITABLE_FIELDS, true)) {
            return response()->json(['success' => false, 'message' => 'Invalid field name.'], 422);
        }

        $userId     = auth()->id();
        $propertyId = auth()->user()->isSuperAdmin()
            ? session('active_property_id')
            : auth()->user()->property_id;

        if (!$propertyId) {
            return response()->json(['success' => false, 'message' => 'No active property.'], 403);
        }

        // For FK fields, confirm the submitted ID exists within the active property
        // before writing it — prevents assigning an entity from another tenant.
        if (in_array($fieldName, ['category_id', 'department_id']) && !empty($newValue)) {
            $table  = $fieldName === 'category_id' ? 'categories' : 'departments';
            $exists = \DB::table($table)
                ->where('id', (int) $newValue)
                ->where('property_id', $propertyId)
                ->exists();
            if (!$exists) {
                return response()->json(['success' => false, 'message' => 'Invalid entity for this property.'], 422);
            }
        }

        // Locate the staging row by primary key, scoped to the caller's tenant.
        // An id owned by another user or property simply misses and falls into
        // the same 'Row not found' below that a stale id gets, so the response
        // can't be used to probe whether a foreign id exists.
        $stagingRow = \DB::table('temporary_asset_imports')
            ->where('id', (int) $request->input('row_id'))
            ->where('user_id', $userId)
            ->where('property_id', $propertyId)
            ->first();

        if (!$stagingRow) {
            return response()->json(['success' => false, 'message' => 'Row not found.'], 422);
        }

        // Update the specific column
        $updateValue = ($fieldName === 'category_id' || $fieldName === 'department_id')
            ? (!empty($newValue) ? (int) $newValue : null)
            : $newValue;

        \DB::table('temporary_asset_imports')
            ->where('id', $stagingRow->id)
            ->where('user_id', $userId)
            ->where('property_id', $propertyId)
            ->update([
                $fieldName   => $updateValue,
                'updated_at' => now(),
            ]);

        // Recalculate is_invalid for this row
        $updatedRow  = \DB::table('temporary_asset_imports')->find($stagingRow->id);
        $isEmpty     = empty($updatedRow->name) && empty($updatedRow->tag);
        $isInvalid   = $isEmpty ? false : (empty($updatedRow->name) || empty($updatedRow->category_id));

        \DB::table('temporary_asset_imports')
            ->where('id', $stagingRow->id)
            ->update(['is_invalid' => $isInvalid, 'updated_at' => now()]);

        // ── Recalculate global stats from DB (no full array needed) ────────
        $baseQ        = \DB::table('temporary_asset_imports')
            ->where('user_id', $userId)->where('property_id', $propertyId);
        $totalCount   = $baseQ->clone()->count();
        $validCount   = $baseQ->clone()->where('is_invalid', false)
            ->where(function ($q) { $q->whereNotNull('name')->where('name', '<>', ''); })->count();
        $invalidCount = $totalCount - $validCount;

        $perPage      = 50;
        $invalidPages = $this->invalidPageNumbers($baseQ, $perPage);

        return response()->json([
            'success'      => true,
            'is_invalid'   => $isInvalid,
            'invalidPages' => $invalidPages,
            'validCount'   => $validCount,
            'invalidCount' => $invalidCount,
        ]);
    }

    /**
     * Reduce a client-supplied id list to the ones this user actually owns.
     *
     * Every bulk path funnels through here. The ids arrive from the browser, so
     * this single query is what stands between a hand-edited request and another
     * tenant's staging rows. Unowned ids are dropped silently rather than
     * reported — the caller gets a count of what was touched, never a signal
     * about whether an id it doesn't own exists.
     *
     * @param  array<int, mixed>  $rowIds
     * @return array<int, int>
     */
    private function resolveOwnedRowIds(array $rowIds, int $userId, int $propertyId): array
    {
        $rowIds = array_slice(array_unique(array_map('intval', $rowIds)), 0, self::BULK_ROW_LIMIT);

        if (empty($rowIds)) {
            return [];
        }

        return \DB::table('temporary_asset_imports')
            ->whereIn('id', $rowIds)
            ->where('user_id', $userId)
            ->where('property_id', $propertyId)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Re-derive is_invalid for a set of rows in one statement.
     *
     * Mirrors the per-row rule in updateSingleRow() exactly, including the part
     * that is easy to miss: a row with neither name nor tag is treated as a
     * blank placeholder and left VALID, not flagged. Two other places in this
     * controller (post-mapping recalculation) use a simpler expression that
     * marks such rows invalid — a pre-existing inconsistency. Bulk edit must
     * agree with single edit, or the same data would flag differently depending
     * on how many rows the user happened to select.
     *
     * @param  array<int, int>  $rowIds
     */
    private function recalculateInvalidFlags(array $rowIds): void
    {
        if (empty($rowIds)) {
            return;
        }

        \DB::table('temporary_asset_imports')
            ->whereIn('id', $rowIds)
            ->update([
                'is_invalid' => \DB::raw(
                    "CASE
                        WHEN (name IS NULL OR name = '') AND (tag IS NULL OR tag = '') THEN FALSE
                        WHEN (name IS NULL OR name = '') OR category_id IS NULL         THEN TRUE
                        ELSE FALSE
                    END"
                ),
                'updated_at' => now(),
            ]);
    }

    /**
     * Global valid/invalid counts + heatmap pages for one user+property.
     *
     * Extracted from the three copies that already existed inline so the bulk
     * endpoints report identically to the single-row ones.
     *
     * @return array<string, mixed>
     */
    private function stagingTotals(int $userId, int $propertyId): array
    {
        $baseQ = \DB::table('temporary_asset_imports')
            ->where('user_id', $userId)->where('property_id', $propertyId);

        $totalCount = $baseQ->clone()->count();
        $validCount = $baseQ->clone()->where('is_invalid', false)
            ->where(function ($q) { $q->whereNotNull('name')->where('name', '<>', ''); })->count();

        return [
            'totalCount'   => $totalCount,
            'validCount'   => $validCount,
            'invalidCount' => $totalCount - $validCount,
            'invalidPages' => $this->invalidPageNumbers($baseQ, 50),
        ];
    }

    /**
     * AJAX: Apply one column's value across every selected staging row.
     *
     * Backs the bulk-edit inputs that appear in the review table's column
     * headers once rows are selected. Deliberately one request for the whole
     * selection: driving this through updateSingleRow() per row would reinstate
     * the request-per-cell cost this feature already removed elsewhere.
     *
     * Only the named column is written. Every other column on the selected rows,
     * and every unselected row, is left alone.
     */
    public function bulkUpdateRows(Request $request)
    {
        $request->validate([
            'row_ids'    => 'required|array|min:1|max:'.self::BULK_ROW_LIMIT,
            'row_ids.*'  => 'required|integer|min:1',
            'field_name' => 'required|string',
            'new_value'  => 'nullable|string',
        ]);

        $fieldName = $request->input('field_name');
        $newValue  = $request->input('new_value');

        if (!in_array($fieldName, self::EDITABLE_FIELDS, true)) {
            return response()->json(['success' => false, 'message' => 'Invalid field name.'], 422);
        }

        $userId     = auth()->id();
        $propertyId = auth()->user()->isSuperAdmin()
            ? session('active_property_id')
            : auth()->user()->property_id;

        if (!$propertyId) {
            return response()->json(['success' => false, 'message' => 'No active property.'], 403);
        }

        // Identical FK check to updateSingleRow(). Runs BEFORE any write, so a
        // rejected value cannot land on part of the selection — and so the bulk
        // path is not a way around a rule the single-cell path enforces.
        if (in_array($fieldName, ['category_id', 'department_id']) && !empty($newValue)) {
            $table  = $fieldName === 'category_id' ? 'categories' : 'departments';
            $exists = \DB::table($table)
                ->where('id', (int) $newValue)
                ->where('property_id', $propertyId)
                ->exists();
            if (!$exists) {
                return response()->json(['success' => false, 'message' => 'Invalid entity for this property.'], 422);
            }
        }

        $ownedIds = $this->resolveOwnedRowIds($request->input('row_ids', []), $userId, (int) $propertyId);

        if (empty($ownedIds)) {
            return response()->json(array_merge(
                ['success' => true, 'updatedCount' => 0, 'rowFlags' => []],
                $this->stagingTotals($userId, (int) $propertyId)
            ));
        }

        $updateValue = ($fieldName === 'category_id' || $fieldName === 'department_id')
            ? (!empty($newValue) ? (int) $newValue : null)
            : $newValue;

        \DB::table('temporary_asset_imports')
            ->whereIn('id', $ownedIds)
            ->update([
                $fieldName   => $updateValue,
                'updated_at' => now(),
            ]);

        $this->recalculateInvalidFlags($ownedIds);

        // Hand back each row's new flag so the page can repaint the invalid
        // highlighting in place, without a reload that would drop the selection.
        $rowFlags = \DB::table('temporary_asset_imports')
            ->whereIn('id', $ownedIds)
            ->pluck('is_invalid', 'id')
            ->map(fn ($flag) => (bool) $flag)
            ->all();

        return response()->json(array_merge(
            [
                'success'      => true,
                'updatedCount' => count($ownedIds),
                'rowFlags'     => $rowFlags,
            ],
            $this->stagingTotals($userId, (int) $propertyId)
        ));
    }

    /**
     * AJAX: Move a slice of valid staging rows into the assets table.
     *
     * The frontend calls this in chunks (offset + limit) so the browser
     * shows a progress bar for very large imports. Each call is protected
     * by an atomic cache lock keyed to (user, offset) to prevent a duplicate
     * request from inserting the same rows twice (e.g., double-click, retry).
     *
     * On the final chunk: sends a notification, then purges all staging rows
     * for this user+property session.
     */
    public function storeBatch(Request $request)
    {
        $this->authorize('create', \App\Models\Asset::class);

        $offset = (int) $request->input('offset', 0);
        $limit  = (int) $request->input('limit', 500);

        $userId     = auth()->id();
        $propertyId = auth()->user()->isSuperAdmin()
            ? session('active_property_id')
            : auth()->user()->property_id;

        abort_if(!$propertyId, 403, 'No active property selected.');

        // Atomic lock per (user, offset) — if the same chunk arrives twice
        // (network retry, double-submit), the second request exits immediately.
        $lockKey = 'import_store_lock_' . $userId . '_' . $offset;
        $lock    = Cache::lock($lockKey, 30);

        if (!$lock->get()) {
            return response()->json([
                'success' => false,
                'message' => 'Duplicate request detected. Please wait.',
            ], 409);
        }

        try {
            $stagingBase = \DB::table('temporary_asset_imports')
                ->where('user_id', $userId)
                ->where('property_id', $propertyId)
                ->where('is_invalid', false)
                ->whereNotNull('name')->where('name', '<>', '');

            $totalValid = $stagingBase->clone()->count();

            if ($totalValid === 0) {
                return response()->json(['success' => true, 'processed_count' => 0, 'is_completed' => true]);
            }

            // Build allowlists of valid FK IDs for this property. Staging rows
            // that reference an ID outside these sets are silently skipped.
            $validCategoryIds   = Category::where('property_id', $propertyId)->pluck('id')->toArray();
            $validDepartmentIds = Department::where('property_id', $propertyId)->pluck('id')->toArray();

            // Fetch the batch slice of valid staging rows
            $rows = $stagingBase->clone()
                ->orderBy('id')
                ->skip($offset)->limit($limit)
                ->get();

            $editorId   = $userId;
            $insertRows = [];
            $now        = now();

            foreach ($rows as $row) {
                $catId  = in_array($row->category_id, $validCategoryIds)   ? (int) $row->category_id  : null;
                $deptId = in_array($row->department_id, $validDepartmentIds) ? (int) $row->department_id : null;

                if (!$catId) continue; // category_id is required — skip rows with cross-tenant FK

                $tag = !empty($row->tag) ? $row->tag : ('AST-' . strtoupper(substr(uniqid(), -6)));

                // model used to be smuggled in here ('Imported. Model: X') because
                // assets had no model column. It has one now, so remarks stays clean.
                $remarks = !empty($row->remarks) ? $row->remarks : 'Imported.';
                if (strlen($remarks) > 120) $remarks = substr($remarks, 0, 117) . '...';

                $insertRows[] = [
                    'uuid'          => (string) \Illuminate\Support\Str::orderedUuid(),
                    'name'          => $row->name,
                    'tag'           => $tag,
                    'category_id'   => $catId,
                    'department_id' => $deptId,
                    'status'        => $row->status ?? 'in_service',
                    'serial_number' => $row->serial_number ?: null,
                    'model'         => $row->model ?: null,
                    'purchase_date' => $this->sanitizeDate($row->purchase_date),
                    'purchase_cost' => is_numeric($row->purchase_cost ?? '') ? $row->purchase_cost : null,
                    'remarks'       => $remarks,
                    'editor'        => $editorId,
                    'property_id'   => $propertyId,
                    'created_at'    => $now,
                    'updated_at'    => $now,
                ];
            }

            $processedCount = count($insertRows);

            if (!empty($insertRows)) {
                \DB::beginTransaction();
                try {
                    \DB::table('assets')->insert($insertRows);
                    \DB::commit();
                } catch (\Exception $e) {
                    \DB::rollBack();
                    Log::error('Batch Insert Failed: ' . $e->getMessage());
                    return response()->json([
                        'success' => false,
                        'message' => 'Failed to save assets batch. ' . $e->getMessage(),
                    ], 500);
                }
            }

            $isCompleted = ($offset + $limit) >= $totalValid;

            if ($isCompleted) {
                if ($totalValid > 0) {
                    auth()->user()->notify(new \App\Notifications\BulkImportSuccessfulNotification($totalValid));
                }
                // Purge staging rows and session cache entries for this import.
                \DB::table('temporary_asset_imports')
                    ->where('user_id', $userId)
                    ->where('property_id', $propertyId)
                    ->delete();
                Cache::forget('import_state_' . $userId);
                Cache::forget('import_review_' . $userId); // legacy key, kept for safety
            }

            return response()->json([
                'success'         => true,
                'processed_count' => $processedCount,
                'is_completed'    => $isCompleted,
            ]);

        } finally {
            $lock->release();
        }
    }

    /**
     * AJAX: Delete the selected staging rows from temporary_asset_imports.
     *
     * Takes a list because the review page deletes through the row selection —
     * context menu or the "Delete Selected" button — and a single row is simply
     * a selection of one. There is no separate single-row path to drift from.
     *
     * Rows are addressed by id, never by page position: an index is only valid
     * until something before it is deleted, which is exactly the operation this
     * method performs. Unowned ids are dropped by resolveOwnedRowIds() and
     * reported only as a smaller deletedCount.
     */
    public function deleteRows(Request $request)
    {
        $request->validate([
            'row_ids'   => 'required|array|min:1|max:'.self::BULK_ROW_LIMIT,
            'row_ids.*' => 'required|integer|min:1',
        ]);

        $userId     = auth()->id();
        $propertyId = auth()->user()->isSuperAdmin()
            ? session('active_property_id')
            : auth()->user()->property_id;

        if (!$propertyId) {
            return response()->json(['success' => false, 'message' => 'No active property.'], 403);
        }

        $ownedIds = $this->resolveOwnedRowIds($request->input('row_ids', []), $userId, (int) $propertyId);

        if (!empty($ownedIds)) {
            \DB::table('temporary_asset_imports')->whereIn('id', $ownedIds)->delete();
        }

        return response()->json(array_merge(
            ['success' => true, 'deletedCount' => count($ownedIds)],
            $this->stagingTotals($userId, (int) $propertyId)
        ));
    }


    /**
     * Cancel/Abort the ongoing import job.
     */
    public function cancel(Request $request)
    {
        $userId      = auth()->id();
        $progressKey = 'import_progress_' . $userId;

        // Signal cancellation in cache (background job will self-destruct).
        //
        // Carry the existing import_id across: the flag has to say *which* attempt
        // was cancelled. Without it the record is indistinguishable from a fresh
        // dispatch, and the job it was meant to stop would keep running. The
        // counters are preserved too so the record keeps the same shape as every
        // other writer — status() and the progress modal both read all five keys.
        $current = Cache::get($progressKey);
        $current = is_array($current) ? $current : [];

        Cache::put($progressKey, [
            'status'     => 'cancelled',
            'percentage' => $current['percentage'] ?? 0,
            'processed'  => $current['processed'] ?? 0,
            'total'      => $current['total'] ?? 0,
            'error'      => '',
            'import_id'  => $current['import_id'] ?? null,
        ], 1800);

        // We do NOT delete the temporary file here because the user
        // will reload/go back to the mapping page to do corrections,
        // and we need the file intact to allow them to re-apply the mapping.

        return response()->json(['success' => true]);
    }

    /**
     * Page numbers (1-based) that contain at least one invalid staging row.
     *
     * Drives the red "heatmap" dots on the review paginator. Row position is
     * defined by ORDER BY id, matching how review() slices its pages.
     *
     * This used to pull every staging id into PHP and array_flip() it — O(N)
     * memory per request on a table sized for 100K+ row imports, and paid again
     * on every single-cell auto-save. ROW_NUMBER() keeps the numbering in the
     * database and returns only the handful of page numbers actually needed.
     *
     * @param  \Illuminate\Database\Query\Builder  $base  scoped to one user+property
     * @return array<int, int>
     */
    private function invalidPageNumbers($base, int $perPage): array
    {
        // $perPage is an internal constant, never user input — inlined rather than
        // bound so the sub-query's own bindings keep their position.
        $perPage = max(1, (int) $perPage);

        $numbered = $base->clone()
            ->select('is_invalid')
            ->selectRaw('ROW_NUMBER() OVER (ORDER BY id) AS rn');

        return \DB::query()
            ->fromSub($numbered, 't')
            ->where('t.is_invalid', true)
            ->selectRaw('DISTINCT FLOOR((t.rn - 1) / ' . $perPage . ') + 1 AS page')
            ->orderBy('page')
            ->pluck('page')
            ->map(fn ($page) => (int) $page)
            ->all();
    }
}
