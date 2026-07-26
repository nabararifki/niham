<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessImportJob;
use App\Models\Category;
use App\Models\Department;
use App\Services\AssetImportService;
use App\Services\EntityCodeGeneratorService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AssetImportController extends Controller
{
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

        // If user selected a different sheet, re-peek the file for that sheet
        if ($request->has('sheet') && !empty($cachedData['temp_file_path'])) {
            $requestedSheet = $request->query('sheet');
            
            // Resolve requested sheet index (could be integer index or name)
            $sheetIndex = 0;
            $sheetsList = $cachedData['sheets'] ?? [];
            if (is_numeric($requestedSheet)) {
                $sheetIndex = (int) $requestedSheet;
            } else {
                // If it is a string sheet name, find its index in the sheets list
                $foundIndex = array_search($requestedSheet, $sheetsList);
                if ($foundIndex !== false) {
                    $sheetIndex = $foundIndex;
                }
            }

            // Get currently active sheet from cache
            $currentSheetVal = isset($cachedData['selected_sheet']) 
                ? $cachedData['selected_sheet'] 
                : (isset($cachedData['current_sheet_index']) ? $cachedData['current_sheet_index'] : 0);

            // Compare requested sheet with current sheet
            $isDifferent = false;
            if (is_numeric($requestedSheet) && is_numeric($currentSheetVal)) {
                $isDifferent = ((int)$requestedSheet !== (int)$currentSheetVal);
            } else {
                $isDifferent = ($requestedSheet != $currentSheetVal);
            }

            if ($isDifferent) {
                $tempFilePath = $cachedData['temp_file_path'];
                if (\Illuminate\Support\Facades\Storage::disk('local')->exists($tempFilePath)) {
                    $fullPath = \Illuminate\Support\Facades\Storage::disk('local')->path($tempFilePath);
                    $extension = pathinfo($fullPath, PATHINFO_EXTENSION);

                    try {
                        $peekResult = $this->importService->peek($fullPath, $extension, $sheetIndex);
                        $cachedData['true_header'] = $peekResult['true_header'];
                        $cachedData['preview_data'] = $peekResult['preview_data'];
                        $cachedData['mapping_proposals'] = $peekResult['mapping_proposals'];
                        $cachedData['current_sheet_index'] = $sheetIndex;
                        $cachedData['selected_sheet'] = $requestedSheet;
                        
                        // Update cache with fresh sheet data
                        Cache::put($cacheKey, $cachedData, 1800);
                    } catch (\Exception $e) {
                        Log::warning('Sheet re-peek failed: ' . $e->getMessage());
                    }
                }
            }
        }

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

        // Seed initial progress cache (all keys present for frontend)
        $progressKey = 'import_progress_' . auth()->id();
        Cache::put($progressKey, [
            'status'     => 'processing',
            'percentage' => 0,
            'processed'  => 0,
            'total'      => 0,
            'error'      => '',
        ], 600);

        try {
            // Dispatch the streaming job with the RELATIVE storage path
            ProcessImportJob::dispatch(
                auth()->id(),
                $tempFilePath,
                $payload,
                $selectedSheet,
            );
        } catch (\Throwable $e) {
            Log::error('Failed to dispatch ProcessImportJob: ' . $e->getMessage());
            Cache::put($progressKey, [
                'status'     => 'failed',
                'percentage' => 0,
                'processed'  => 0,
                'total'      => 0,
                'error'      => $e->getMessage(),
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
        $progress = Cache::get('import_progress_' . auth()->id());

        if (!$progress) {
            return response()->json([
                'status'     => 'pending',
                'percentage' => 0,
                'processed'  => 0,
                'total'      => 0,
                'error'      => '',
            ]);
        }

        return response()->json($progress);
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

        // Compute which page numbers contain invalid rows (for pagination heatmap)
        $invalidRows = $stagingBase->clone()
            ->where('is_invalid', true)
            ->orderBy('id')
            ->select('id')
            ->get();

        // Map row positions to page numbers using row_number equivalent
        $allIds     = $stagingBase->clone()->orderBy('id')->pluck('id')->toArray();
        $idToPosition = array_flip($allIds); // id => 0-based position
        $invalidPages = [];
        foreach ($invalidRows as $row) {
            $pos  = $idToPosition[$row->id] ?? null;
            if ($pos !== null) {
                $page = (int) ceil(($pos + 1) / $perPage);
                if (!in_array($page, $invalidPages)) {
                    $invalidPages[] = $page;
                }
            }
        }

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
     * Manual bulk entry page: 5 blank rows.
     */
    public function bulkManual(Request $request)
    {
        $allData = array_fill(0, 5, [
            'tag' => '', 'name' => '', 'category_id' => '', 'department_id' => '',
            'status' => 'in_service', 'model' => '', 'serial_number' => '', 'purchase_date' => '',
            'purchase_cost' => '', 'remarks' => '',
        ]);

        $validCount = 0; $invalidCount = 0;
        foreach ($allData as $item) {
            $isEmpty = empty($item['name']) && empty($item['tag']) && empty($item['category_id']) && empty($item['department_id']);
            if ($isEmpty) continue;
            if (empty($item['name']) || empty($item['category_id'])) $invalidCount++;
            else $validCount++;
        }

        $perPage       = 50;
        $currentPage   = 1;
        $total         = count($allData);
        $paginatedData = new LengthAwarePaginator(
            $allData, $total, $perPage, $currentPage,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        // Load entity lists scoped to the active property for the form dropdowns.
        $propertyId = auth()->user()->isSuperAdmin()
            ? session('active_property_id')
            : auth()->user()->property_id;

        $categories     = $propertyId
            ? Category::where('property_id', $propertyId)->orderBy('name')->get()
            : collect();
        $departments    = $propertyId
            ? Department::where('property_id', $propertyId)->orderBy('name')->get()
            : collect();
        $categoriesMap  = $categories->keyBy('id');
        $departmentsMap = $departments->keyBy('id');
        $warning        = null;
        $pageOffset     = 0;
        $invalidPages   = [];

        return view('assets.import.review', compact(
            'paginatedData', 'categories', 'departments',
            'categoriesMap', 'departmentsMap', 'warning', 'pageOffset', 'total',
            'validCount', 'invalidCount', 'invalidPages'
        ));
    }

    /**
     * Insert assets submitted from the manual bulk-entry form (not from staging).
     *
     * This path is used when the user fills out the manual 5-row form rather than
     * uploading a file. The submitted rows are validated and inserted directly;
     * category/department IDs are cross-checked against the active property to
     * prevent cross-tenant FK violations.
     */
    public function store(Request $request)
    {
        $this->authorize('create', \App\Models\Asset::class);

        // Manual bulk entry path: no staging rows exist — read from form directly
        $request->validate([
            'assets'                => 'required|array',
            'assets.*.name'         => 'required|string|max:255',
            'assets.*.tag'          => 'required|string|max:64',
            'assets.*.category_id'  => 'required|integer',
            'assets.*.department_id'=> 'nullable|integer',
            'assets.*.status'       => 'required|in:in_service,out_of_service,disposed',
        ]);

        $editorId   = auth()->id();
        $propertyId = auth()->user()->isSuperAdmin()
            ? session('active_property_id')
            : auth()->user()->property_id;

        abort_if(!$propertyId, 403, 'No active property selected.');

        // Build lookup sets of valid FK IDs for this property; rows referencing
        // IDs outside these sets are skipped rather than causing a DB error.
        $validCategoryIds   = Category::where('property_id', $propertyId)->pluck('id')->flip();
        $validDepartmentIds = Department::where('property_id', $propertyId)->pluck('id')->flip();

        $allData    = collect($request->input('assets', []))->values()->toArray();
        $insertRows = [];
        foreach ($allData as $item) {
            $isEmpty = empty($item['name']) && empty($item['tag']) && empty($item['category_id']);
            if ($isEmpty) continue;
            if (empty($item['name']) || empty($item['category_id'])) continue;

            $catId  = (int) $item['category_id'];
            $deptId = !empty($item['department_id']) ? (int) $item['department_id'] : null;

            // Skip rows referencing a category or department from another property.
            if (!isset($validCategoryIds[$catId])) continue;
            if ($deptId && !isset($validDepartmentIds[$deptId])) $deptId = null;

            $tag     = !empty($item['tag']) ? $item['tag'] : ('AST-' . strtoupper(substr(uniqid(), -6)));
            $remarks = !empty($item['remarks'])
                ? $item['remarks']
                : (!empty($item['model']) ? 'Imported. Model: ' . $item['model'] : 'Imported.');
            if (strlen($remarks) > 120) $remarks = substr($remarks, 0, 117) . '...';

            $insertRows[] = [
                'uuid'          => (string) \Illuminate\Support\Str::orderedUuid(),
                'name'          => $item['name'],
                'tag'           => $tag,
                'category_id'   => $catId,
                'department_id' => $deptId,
                'status'        => $item['status'] ?? 'in_service',
                'serial_number' => $item['serial_number'] ?? null,
                'purchase_date' => $this->sanitizeDate($item['purchase_date'] ?? null),
                'purchase_cost' => is_numeric($item['purchase_cost'] ?? '') ? $item['purchase_cost'] : null,
                'remarks'       => $remarks,
                'editor'        => $editorId,
                'property_id'   => $propertyId,
                'created_at'    => now(),
                'updated_at'    => now(),
            ];
        }

        \DB::beginTransaction();
        try {
            foreach (array_chunk($insertRows, 500) as $chunk) {
                \DB::table('assets')->insert($chunk);
            }
            \DB::commit();
        } catch (\Exception $e) {
            \DB::rollBack();
            Log::error('Bulk Insert Failed: ' . $e->getMessage());
            return back()->withInput()->withErrors(['error' => 'Failed to save assets. ' . $e->getMessage()]);
        }

        // Clear session cache entries left over from the parse/mapping phase.
        Cache::forget('import_state_' . auth()->id());
        Cache::forget('import_review_' . auth()->id()); // legacy key, kept for safety

        return redirect()->route('assets.index')->with('ok', __('assets.import_success'));
    }

    /**
     * AJAX: Return live validation counts for the review page footer.
     *
     * Called by the frontend on page load and after storeBatch() completes
     * to keep the valid/invalid counters in sync. Falls back to counting
     * from the submitted form payload when no staging rows exist (manual entry).
     */
    public function calculateValidation(Request $request)
    {
        $propertyId = auth()->user()->isSuperAdmin()
            ? session('active_property_id')
            : auth()->user()->property_id;

        // Fallback for manual bulk entry (no staging rows)
        if (!$propertyId || !\DB::table('temporary_asset_imports')
                ->where('user_id', auth()->id())
                ->where('property_id', $propertyId)
                ->exists()) {
            // Manual bulk entry path: count from submitted form data
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
     * locates the staging row by its 0-based insert order, applies the update,
     * recalculates the row's is_invalid flag, and returns updated global
     * counts + heatmap page list so the frontend can refresh without a reload.
     *
     * The field_name input is validated against a whitelist before being used
     * as a column name to prevent SQL injection.
     */
    public function updateSingleRow(Request $request)
    {
        $request->validate([
            'absolute_index' => 'required|integer|min:0',
            'field_name'     => 'required|string',
            'new_value'      => 'nullable|string',
        ]);

        $fieldName = $request->input('field_name');
        $newValue  = $request->input('new_value');

        // Normalize field_name if it comes as "assets[x][field]"
        if (preg_match('/^assets\[\d+\]\[(.+)\]$/', $fieldName, $matches)) {
            $fieldName = $matches[1];
        }

        // Whitelist of columns the user is permitted to edit via the review UI.
        $allowedFields = ['tag', 'name', 'category_id', 'department_id', 'status',
                          'model', 'serial_number', 'purchase_date', 'purchase_cost', 'remarks'];
        if (!in_array($fieldName, $allowedFields, true)) {
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

        // Locate the staging row by its 0-based insertion order (ORDER BY id OFFSET n).
        $absoluteIndex = (int) $request->input('absolute_index');
        $stagingRow    = \DB::table('temporary_asset_imports')
            ->where('user_id', $userId)
            ->where('property_id', $propertyId)
            ->orderBy('id')
            ->skip($absoluteIndex)
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
        $allIds       = $baseQ->clone()->orderBy('id')->pluck('id')->toArray();
        $invalidIds   = $baseQ->clone()->where('is_invalid', true)->orderBy('id')->pluck('id')->toArray();
        $idToPosition = array_flip($allIds);
        $invalidPages = [];
        foreach ($invalidIds as $iId) {
            $pos  = $idToPosition[$iId] ?? null;
            if ($pos !== null) {
                $page = (int) ceil(($pos + 1) / $perPage);
                if (!in_array($page, $invalidPages)) $invalidPages[] = $page;
            }
        }

        return response()->json([
            'success'      => true,
            'is_invalid'   => $isInvalid,
            'invalidPages' => $invalidPages,
            'validCount'   => $validCount,
            'invalidCount' => $invalidCount,
        ]);
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

                $tag     = !empty($row->tag) ? $row->tag : ('AST-' . strtoupper(substr(uniqid(), -6)));
                $remarks = !empty($row->remarks)
                    ? $row->remarks
                    : (!empty($row->model) ? 'Imported. Model: ' . $row->model : 'Imported.');
                if (strlen($remarks) > 120) $remarks = substr($remarks, 0, 117) . '...';

                $insertRows[] = [
                    'uuid'          => (string) \Illuminate\Support\Str::orderedUuid(),
                    'name'          => $row->name,
                    'tag'           => $tag,
                    'category_id'   => $catId,
                    'department_id' => $deptId,
                    'status'        => $row->status ?? 'in_service',
                    'serial_number' => $row->serial_number ?: null,
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
     * Sanitize a raw date string from a spreadsheet into a Y-m-d value or null.
     *
     * Spreadsheets often contain placeholder text such as "N/A", "-", "?", or
     * locale-specific formats that PostgreSQL cannot cast to the date type.
     * This method tries several common formats and returns null for anything
     * that cannot be parsed as a real date, preventing INSERT errors.
     */
    private function sanitizeDate(mixed $value): ?string
    {
        if (empty($value) || !is_string($value)) {
            return null;
        }

        $value = trim($value);

        if ($value === '' || $value === '-' || $value === 'N/A' || $value === '?' || $value === '0') {
            return null;
        }

        // Try multiple common date formats from spreadsheets
        $formats = ['Y-m-d', 'd/m/Y', 'm/d/Y', 'd-m-Y', 'Y/m/d', 'd.m.Y', 'j/n/Y', 'n/j/Y'];
        foreach ($formats as $format) {
            $dt = \DateTime::createFromFormat($format, $value);
            if ($dt && $dt->format($format) === $value) {
                return $dt->format('Y-m-d');
            }
        }

        // Last resort: PHP's strtotime for natural-language dates
        $ts = strtotime($value);
        if ($ts !== false && $ts > 0) {
            return date('Y-m-d', $ts);
        }

        return null;
    }

    /**
     * AJAX: Delete a single staging row from temporary_asset_imports database table.
     */
    public function deleteRow(Request $request)
    {
        $request->validate([
            'absolute_index' => 'required|integer|min:0',
        ]);

        $userId     = auth()->id();
        $propertyId = auth()->user()->isSuperAdmin()
            ? session('active_property_id')
            : auth()->user()->property_id;

        if (!$propertyId) {
            return response()->json(['success' => false, 'message' => 'No active property.'], 403);
        }

        $absoluteIndex = (int) $request->input('absolute_index');
        $stagingRow    = \DB::table('temporary_asset_imports')
            ->where('user_id', $userId)
            ->where('property_id', $propertyId)
            ->orderBy('id')
            ->skip($absoluteIndex)
            ->first();

        if (!$stagingRow) {
            return response()->json(['success' => false, 'message' => 'Row not found.'], 422);
        }

        \DB::table('temporary_asset_imports')
            ->where('id', $stagingRow->id)
            ->where('user_id', $userId)
            ->where('property_id', $propertyId)
            ->delete();

        // Recalculate global stats from DB using efficient count aggregation
        $baseQ        = \DB::table('temporary_asset_imports')
            ->where('user_id', $userId)->where('property_id', $propertyId);
        $totalCount   = $baseQ->clone()->count();
        $validCount   = $baseQ->clone()->where('is_invalid', false)
            ->where(function ($q) { $q->whereNotNull('name')->where('name', '<>', ''); })->count();
        $invalidCount = $totalCount - $validCount;

        $perPage      = 50;
        $allIds       = $baseQ->clone()->orderBy('id')->pluck('id')->toArray();
        $invalidIds   = $baseQ->clone()->where('is_invalid', true)->orderBy('id')->pluck('id')->toArray();
        $idToPosition = array_flip($allIds);
        $invalidPages = [];
        foreach ($invalidIds as $iId) {
            $pos  = $idToPosition[$iId] ?? null;
            if ($pos !== null) {
                $page = (int) ceil(($pos + 1) / $perPage);
                if (!in_array($page, $invalidPages)) {
                    $invalidPages[] = $page;
                }
            }
        }

        return response()->json([
            'success'      => true,
            'totalCount'   => $totalCount,
            'invalidPages' => $invalidPages,
            'validCount'   => $validCount,
            'invalidCount' => $invalidCount,
        ]);
    }


    /**
     * Cancel/Abort the ongoing import job.
     */
    public function cancel(Request $request)
    {
        $userId = auth()->id();

        // Signal cancellation in cache (background job will self-destruct)
        Cache::put('import_progress_' . $userId, ['status' => 'cancelled'], 300);

        // We do NOT delete the temporary file here because the user
        // will reload/go back to the mapping page to do corrections,
        // and we need the file intact to allow them to re-apply the mapping.

        return response()->json(['success' => true]);
    }
}
