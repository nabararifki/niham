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
            // FASE 1: Simpan file ke direktori temp (Jangan di-unlink/dihapus)
            $fileName = uniqid('import_') . '.' . $extension;
            $path = $file->storeAs('temp', $fileName, 'local');
            if (!$path) {
                throw new \Exception('Failed to store the uploaded file.');
            }
            $fullPath = \Illuminate\Support\Facades\Storage::disk('local')->path($path);

            $realPath = $file->getRealPath();
            clearstatcache();
            if ($realPath && file_exists($realPath)) {
                @unlink($realPath);
            }

            // FASE 1: Ekstraksi Sampel & True Header (Hybrid Pipeline)
            $peekResult = $this->importService->peek($fullPath, $extension);

            $dataArray = [
                'temp_file_path' => $path,
                'sheets' => $peekResult['sheets'],
                'true_header' => $peekResult['true_header'],
                'preview_data' => $peekResult['preview_data'],
                'mapping_proposals' => $peekResult['mapping_proposals'],
                'current_sheet_index' => 0,
            ];

            \Illuminate\Support\Facades\Cache::put('import_state_'.auth()->id(), $dataArray, 1800);

            // FASE 1: Kembalikan JSON dengan redirect URL
            return response()->json([
                'success' => true,
                'redirect_url' => route('assets.import-mapping'),
            ]);

        } catch (\Exception $e) {
            Log::error('Import Parse Failure: '.$e->getMessage());

            if (isset($path)) {
                \Illuminate\Support\Facades\Storage::disk('local')->delete($path);
            }
            if (isset($file) && method_exists($file, 'getRealPath')) {
                $realPath = $file->getRealPath();
                clearstatcache();
                if ($realPath && file_exists($realPath)) {
                    @unlink($realPath);
                }
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
     * Process the manual mapping submitted by user.
     * Dispatches a queued job for async streaming; returns JSON for frontend polling.
     */
    public function processMapping(Request $request)
    {
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
     * Rapid Add interception: cross-reference category/department hints
     * against the database. If missing, proceed to rapid-add workflow.
     */
    public function rapidAdd(Request $request)
    {
        $cacheKey = 'import_review_'.auth()->id();
        $data = Cache::get($cacheKey);

        if ($data === null) {
            return redirect()->route('assets.index')
                ->with('warning', __('assets.import_parse_error', ['message' => 'Import session expired or not found.']));
        }

        // 1. Extract unique hints from the cached data
        $categoryHints = collect($data)
            ->pluck('_category_hint')
            ->map(fn($v) => trim($v))
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        $departmentHints = collect($data)
            ->pluck('_department_hint')
            ->map(fn($v) => trim($v))
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        // 2. Query existing names case-insensitively
        // Using LOWER() for PostgreSQL compatibility to match hints effectively
        $existingCategories = Category::whereIn(\DB::raw('LOWER(name)'), array_map('strtolower', $categoryHints))
            ->pluck('name')
            ->toArray();

        $existingDepartments = Department::whereIn(\DB::raw('LOWER(name)'), array_map('strtolower', $departmentHints))
            ->pluck('name')
            ->toArray();

        // 3. Find missing items (case-insensitive difference)
        $missingCategories = array_values(array_udiff($categoryHints, $existingCategories, 'strcasecmp'));
        $missingDepartments = array_values(array_udiff($departmentHints, $existingDepartments, 'strcasecmp'));

        $warnings = [];
        $hasMissingCategories = !empty($missingCategories);
        $hasMissingDepartments = !empty($missingDepartments);

        // Check category auth
        if ($hasMissingCategories && !auth()->user()->can('create', Category::class)) {
            $warnings[] = __('assets.import_unauthorized_category_add', ['count' => count($missingCategories)]);
            // Strip missing category hints
            foreach ($data as &$row) {
                $hint = trim($row['_category_hint']);
                $isMissing = collect($missingCategories)->contains(fn($c) => strcasecmp($c, $hint) === 0);
                if ($isMissing) {
                    $row['_category_hint'] = '';
                }
            }
            $missingCategories = [];
        }

        // Check department auth
        if ($hasMissingDepartments && !auth()->user()->can('create', Department::class)) {
            $warnings[] = __('assets.import_unauthorized_department_add', ['count' => count($missingDepartments)]);
            // Strip missing department hints
            foreach ($data as &$row) {
                $hint = trim($row['_department_hint']);
                $isMissing = collect($missingDepartments)->contains(fn($c) => strcasecmp($c, $hint) === 0);
                if ($isMissing) {
                    $row['_department_hint'] = '';
                }
            }
            $missingDepartments = [];
        }

        // --- MAP EXISTING ENTITIES IMMEDIATELY ---
        // Fetch full collections of existing models matching names case-insensitively
        $existingCatModels = Category::whereIn(\DB::raw('LOWER(name)'), array_map('strtolower', $categoryHints))->get();
        $existingDeptModels = Department::whereIn(\DB::raw('LOWER(name)'), array_map('strtolower', $departmentHints))->get();

        foreach ($data as &$row) {
            $cHint = trim($row['_category_hint']);
            $dHint = trim($row['_department_hint']);

            if (!empty($cHint)) {
                $matched = $existingCatModels->first(fn($c) => strcasecmp($c->name, $cHint) === 0);
                if ($matched) {
                    $row['category_id'] = $matched->id;
                }
            }

            if (!empty($dHint)) {
                $matched = $existingDeptModels->first(fn($d) => strcasecmp($d->name, $dHint) === 0);
                if ($matched) {
                    $row['department_id'] = $matched->id;
                }
            }
        }
        // -----------------------------------------

        // Re-cache data with mapped existing IDs (and potentially stripped unauthorized hints)
        Cache::put($cacheKey, $data, now()->addMinutes(30));

        // If none are missing (or were stripped), bypass and go to standard review
        if (empty($missingCategories) && empty($missingDepartments)) {
            $redirect = redirect()->route('assets.import-review');
            if (!empty($warnings)) {
                $redirect->with('warning', implode(' ', $warnings));
            }
            return $redirect;
        }

        // Return view (for rapid add display, which will be implemented in Batch 3)
        if (view()->exists('assets.import-rapid-add')) {
            return view('assets.import-rapid-add', compact('missingCategories', 'missingDepartments'));
        }

        // Demo output for Batch 1/2 Verification
        return response()->json([
            'status' => 'intercepted_for_rapid_add',
            'missing_categories' => $missingCategories,
            'missing_departments' => $missingDepartments,
            'warnings' => $warnings,
        ]);
    }

    /**
     * Process Rapid Add submissions.
     */
    public function storeRapidAdd(Request $request, EntityCodeGeneratorService $codeGen)
    {
        $request->validate([
            'categories' => 'nullable|array',
            'categories.*' => 'string|max:255',
            'departments' => 'nullable|array',
            'departments.*' => 'string|max:255',
        ]);

        $cacheKey = 'import_review_'.auth()->id();
        $data = Cache::get($cacheKey);

        if ($data === null) {
            return redirect()->route('assets.index')
                ->with('warning', __('assets.import_parse_error', ['message' => 'Import session expired or not found.']));
        }

        $propertyId = auth()->user()->isSuperAdmin() ? session('active_property_id') : auth()->user()->property_id;

        $createdCategories = [];
        $createdDepartments = [];

        // Create Categories
        if ($request->filled('categories') && auth()->user()->can('create', Category::class)) {
            foreach ($request->categories as $name) {
                $code = $codeGen->generateUniqueCode($name, Category::class, $propertyId);
                $category = Category::create([
                    'name' => $name,
                    'code' => $code,
                    'property_id' => $propertyId,
                ]);
                $createdCategories[$name] = $category->id;
            }
        }

        // Create Departments
        if ($request->filled('departments') && auth()->user()->can('create', Department::class)) {
            foreach ($request->departments as $name) {
                $code = $codeGen->generateUniqueCode($name, Department::class, $propertyId);
                $department = Department::create([
                    'name' => $name,
                    'code' => $code,
                    'property_id' => $propertyId,
                ]);
                $createdDepartments[$name] = $department->id;
            }
        }

        // Map the IDs back
        foreach ($data as &$row) {
            $catHint = trim($row['_category_hint']);
            $deptHint = trim($row['_department_hint']);

            $matchedCat = collect($createdCategories)->first(function ($id, $name) use ($catHint) {
                return strcasecmp($name, $catHint) === 0;
            });
            if ($matchedCat) {
                $row['category_id'] = $matchedCat;
                $row['_category_hint'] = '';
            }

            $matchedDept = collect($createdDepartments)->first(function ($id, $name) use ($deptHint) {
                return strcasecmp($name, $deptHint) === 0;
            });
            if ($matchedDept) {
                $row['department_id'] = $matchedDept;
                $row['_department_hint'] = '';
            }
        }

        // Re-cache updated data
        Cache::put($cacheKey, $data, now()->addMinutes(30));

        return redirect()->route('assets.import-review');
    }

    /**
     * Review page: paginate the cached array with LengthAwarePaginator.
     *
     * Only the current page slice (50 rows) is passed to the view,
     * preventing OOM crashes on 100K-row imports.
     * Categories and departments are fetched ONCE and keyed into
     * associative maps — eliminating N+1 queries inside the Blade loop.
     */
    public function review(Request $request)
    {
        $cacheKey = 'import_review_' . auth()->id();
        $allData  = Cache::get($cacheKey);
        $warning  = null;

        if ($allData === null) {
            return redirect()->route('assets.index')
                ->with('warning', __('assets.import_parse_error', ['message' => 'Import session expired or not found.']));
        }

        if (empty($allData)) {
            $allData = array_fill(0, 5, [
                'tag'           => '',
                'name'          => '',
                'category_id'   => '',
                'department_id' => '',
                'status'        => 'in_service',
                'model'         => '',
                'serial_number' => '',
                'purchase_date' => '',
                'purchase_cost' => '',
                'remarks'       => '',
            ]);
            $warning = __('assets.no_data_found');
        }

        // Loop through cached array to calculate valid and invalid counts
        $validCount = 0;
        $invalidCount = 0;
        $invalidPages = [];
        $perPage     = 50;
        foreach ($allData as $index => &$item) {
            $isEmpty = empty($item['name']) && empty($item['tag']) && empty($item['category_id']) && empty($item['department_id']);
            if ($isEmpty) {
                continue;
            }
            if (empty($item['name']) || empty($item['category_id'])) {
                $invalidCount++;
                $item['is_invalid'] = true;
                $pageNumber = (int) ceil(($index + 1) / $perPage);
                if (!in_array($pageNumber, $invalidPages)) {
                    $invalidPages[] = $pageNumber;
                }
            } else {
                $validCount++;
                $item['is_invalid'] = false;
            }
        }
        unset($item);

        // ── Paginate the cached array (50 rows per page) ─────────────────────
        $currentPage = LengthAwarePaginator::resolveCurrentPage();
        $total       = count($allData);
        $pageItems   = array_slice($allData, ($currentPage - 1) * $perPage, $perPage);

        $paginatedData = new LengthAwarePaginator(
            $pageItems,
            $total,
            $perPage,
            $currentPage,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        // ── Pre-fetch entity maps (1 query each, keyed by ID) ─────────────────
        // This eliminates N+1 queries: no DB is touched inside the Blade loop.
        $categories    = Category::orderBy('name')->get();
        $departments   = Department::orderBy('name')->get();
        $categoriesMap = $categories->keyBy('id');   // id => Category model
        $departmentsMap = $departments->keyBy('id'); // id => Department model

        // Compute the global 0-based offset for the first row of this page
        // so that form input names (assets[N][...]) are globally unique
        // across pages and the store() can process submitted edits correctly.
        $pageOffset = ($currentPage - 1) * $perPage;

        return view('assets.import-review', compact(
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

        // Calculate counts for manual entry (initially completely empty)
        $validCount = 0;
        $invalidCount = 0;
        foreach ($allData as $item) {
            $isEmpty = empty($item['name']) && empty($item['tag']) && empty($item['category_id']) && empty($item['department_id']);
            if ($isEmpty) {
                continue;
            }
            if (empty($item['name']) || empty($item['category_id'])) {
                $invalidCount++;
            } else {
                $validCount++;
            }
        }

        $perPage       = 50;
        $currentPage   = 1;
        $total         = count($allData);
        $paginatedData = new LengthAwarePaginator(
            $allData, $total, $perPage, $currentPage,
            ['path' => $request->url(), 'query' => $request->query()]
        );
        $categories     = Category::orderBy('name')->get();
        $departments    = Department::orderBy('name')->get();
        $categoriesMap  = $categories->keyBy('id');
        $departmentsMap = $departments->keyBy('id');
        $warning        = null;
        $pageOffset     = 0;
        $invalidPages   = [];

        return view('assets.import-review', compact(
            'paginatedData', 'categories', 'departments',
            'categoriesMap', 'departmentsMap', 'warning', 'pageOffset', 'total',
            'validCount', 'invalidCount', 'invalidPages'
        ));
    }

    /**
     * Final DB insertion — reads from cache, not form payload.
     *
     * Because the review page is paginated, the HTML form only contains
     * the current page's rows. Reading from the cache guarantees we insert
     * ALL rows regardless of which page the user was on when they submitted.
     * Row-level edits on the current page are merged back before insertion.
     */
    public function store(Request $request)
    {
        $cacheKey = 'import_review_' . auth()->id();
        $allData  = Cache::get($cacheKey);

        // Fallback: manual bulk entry has no cache, use form data directly
        if ($allData === null) {
            $request->validate([
                'assets' => 'required|array',
                'assets.*.name' => 'required|string|max:255',
                'assets.*.tag' => 'required|string|max:64',
                'assets.*.category_id' => 'required|exists:categories,id',
                'assets.*.department_id' => 'required|exists:departments,id',
                'assets.*.status' => 'required|in:in_service,out_of_service,disposed',
            ]);
            $allData = collect($request->input('assets', []))->values()->toArray();
            if (empty($allData)) {
                return redirect()->route('assets.index')
                    ->with('warning', __('assets.import_parse_error', ['message' => 'Import session expired or not found.']));
            }
        } else {
            // Merge current-page form edits back into the cached dataset
            $formAssets = $request->input('assets', []);
            $pageOffset = (int) $request->input('page_offset', 0);
            foreach ($formAssets as $localIdx => $formRow) {
                $globalIdx = $pageOffset + (int) $localIdx;
                if (isset($allData[$globalIdx])) {
                    $allData[$globalIdx] = array_merge($allData[$globalIdx], $formRow);
                }
            }
        }

        $editorId = auth()->id();

        // Filter valid data to perform graceful partial import
        $validData = [];
        foreach ($allData as $item) {
            $isEmpty = empty($item['name']) && empty($item['tag']) && empty($item['category_id']) && empty($item['department_id']);
            if ($isEmpty) {
                continue;
            }
            // Skip invalid rows violating PostgreSQL NOT NULL constraints
            if (empty($item['name']) || empty($item['category_id'])) {
                continue;
            }
            $validData[] = $item;
        }

        \DB::beginTransaction();
        try {
            // Chunk into batches of 500 to avoid DB parameter limits
            foreach (array_chunk($validData, 500) as $chunk) {
                foreach ($chunk as $item) {
                    \App\Models\Asset::create([
                        'name'          => $item['name'] ?? '',
                        'tag'           => !empty($item['tag']) ? $item['tag'] : ('AST-' . strtoupper(substr(uniqid(), -6))),
                        'category_id'   => !empty($item['category_id']) ? $item['category_id'] : null,
                        'department_id' => !empty($item['department_id']) ? $item['department_id'] : null,
                        'status'        => $item['status'] ?? 'in_service',
                        'serial_number' => $item['serial_number'] ?? null,
                        'purchase_date' => !empty($item['purchase_date']) ? $item['purchase_date'] : null,
                        'purchase_cost' => is_numeric($item['purchase_cost'] ?? '') ? $item['purchase_cost'] : null,
                        'remarks'       => !empty($item['remarks'])
                            ? $item['remarks']
                            : (!empty($item['model']) ? 'Imported. Model: ' . $item['model'] : 'Imported.'),
                        'editor'        => $editorId,
                    ]);
                }
            }
            \DB::commit();
        } catch (\Exception $e) {
            \DB::rollBack();
            Log::error('Bulk Insert Failed: ' . $e->getMessage());

            return back()->withInput()->withErrors(['error' => 'Failed to save assets. ' . $e->getMessage()]);
        }

        // Clean both cache keys after successful import
        Cache::forget($cacheKey);
        Cache::forget('import_state_' . auth()->id());

        return redirect()->route('assets.index')->with('ok', __('assets.import_success'));
    }

    /**
     * AJAX: Calculate validation counts based on latest cache and form edits.
     */
    public function calculateValidation(Request $request)
    {
        $cacheKey = 'import_review_' . auth()->id();
        $allData  = Cache::get($cacheKey);

        if ($allData === null) {
            // Fallback for manual bulk entry or expired cache
            $allData = collect($request->input('assets', []))->values()->toArray();
        } else {
            // Merge current-page form edits back into the cached dataset
            $formAssets = $request->input('assets', []);
            $pageOffset = (int) $request->input('page_offset', 0);
            foreach ($formAssets as $localIdx => $formRow) {
                $globalIdx = $pageOffset + (int) $localIdx;
                if (isset($allData[$globalIdx])) {
                    $allData[$globalIdx] = array_merge($allData[$globalIdx], $formRow);
                }
            }
            // Save the merged data back to cache to sync it
            Cache::put($cacheKey, $allData, now()->addMinutes(30));
        }

        $validCount = 0;
        $invalidCount = 0;

        foreach ($allData as $item) {
            $isEmpty = empty($item['name']) && empty($item['tag']) && empty($item['category_id']) && empty($item['department_id']);
            if ($isEmpty) {
                continue;
            }
            if (empty($item['name']) || empty($item['category_id'])) {
                $invalidCount++;
            } else {
                $validCount++;
            }
        }

        return response()->json([
            'success' => true,
            'validCount' => $validCount,
            'invalidCount' => $invalidCount,
        ]);
    }

    /**
     * AJAX: Auto-save modifications to a single cell/field within the review cache.
     */
    public function updateSingleRow(Request $request)
    {
        $request->validate([
            'absolute_index' => 'required|integer|min:0',
            'field_name' => 'required|string',
            'new_value' => 'nullable|string',
        ]);

        $absoluteIndex = (int) $request->input('absolute_index');
        $fieldName = $request->input('field_name');
        $newValue = $request->input('new_value');

        // Normalize field_name if it comes as "assets[x][field]"
        if (preg_match('/^assets\[\d+\]\[(.+)\]$/', $fieldName, $matches)) {
            $fieldName = $matches[1];
        }

        $cacheKey = 'import_review_' . auth()->id();
        $allData = Cache::get($cacheKey);

        if ($allData === null || !isset($allData[$absoluteIndex])) {
            return response()->json([
                'success' => false,
                'message' => 'Cache session expired or index not found.',
            ], 422);
        }

        // Apply mutation
        $item = $allData[$absoluteIndex];
        $item[$fieldName] = $newValue;

        // Recalculate is_invalid flag for this specific row based on NOT NULL constraints
        $isEmpty = empty($item['name']) && empty($item['tag']) && empty($item['category_id']) && empty($item['department_id']);
        if ($isEmpty) {
            $item['is_invalid'] = false;
        } else {
            if (empty($item['name']) || empty($item['category_id'])) {
                $item['is_invalid'] = true;
            } else {
                $item['is_invalid'] = false;
            }
        }
        $allData[$absoluteIndex] = $item;

        // Save entire array back to Cache
        Cache::put($cacheKey, $allData, now()->addMinutes(30));

        // Recalculate invalid pages and count totals
        $validCount = 0;
        $invalidCount = 0;
        $invalidPages = [];
        $perPage = 50;

        foreach ($allData as $index => $row) {
            $rowEmpty = empty($row['name']) && empty($row['tag']) && empty($row['category_id']) && empty($row['department_id']);
            if ($rowEmpty) {
                continue;
            }
            if (empty($row['name']) || empty($row['category_id'])) {
                $invalidCount++;
                $pageNumber = (int) ceil(($index + 1) / $perPage);
                if (!in_array($pageNumber, $invalidPages)) {
                    $invalidPages[] = $pageNumber;
                }
            } else {
                $validCount++;
            }
        }

        return response()->json([
            'success' => true,
            'is_invalid' => (bool) ($item['is_invalid'] ?? false),
            'invalidPages' => $invalidPages,
            'validCount' => $validCount,
            'invalidCount' => $invalidCount,
        ]);
    }

    /**
     * AJAX: Synchronously save a batch of assets to prevent timeouts.
     */
    public function storeBatch(Request $request)
    {
        $offset = (int) $request->input('offset', 0);
        $limit = (int) $request->input('limit', 500);

        $cacheKey = 'import_review_' . auth()->id();
        $allData = Cache::get($cacheKey);

        if ($allData === null) {
            return response()->json([
                'success' => false,
                'message' => __('assets.import_parse_error', ['message' => 'Import session expired or not found.']),
            ], 422);
        }

        // Filter valid data to perform graceful partial import
        $validRows = [];
        foreach ($allData as $item) {
            $isEmpty = empty($item['name']) && empty($item['tag']) && empty($item['category_id']) && empty($item['department_id']);
            if ($isEmpty) {
                continue;
            }
            if (empty($item['name']) || empty($item['category_id'])) {
                continue;
            }
            $validRows[] = $item;
        }

        $totalValid = count($validRows);
        $chunk = array_slice($validRows, $offset, $limit);
        $processedCount = count($chunk);

        $editorId = auth()->id();
        $propertyId = null;
        if (auth()->check()) {
            $user = auth()->user();
            if ($user->isSuperAdmin()) {
                $propertyId = session('active_property_id');
            } else {
                $propertyId = $user->property_id;
            }
        }

        $insertRows = [];
        foreach ($chunk as $item) {
            $tag = !empty($item['tag']) ? $item['tag'] : ('AST-' . strtoupper(substr(uniqid(), -6)));
            
            $remarks = !empty($item['remarks'])
                ? $item['remarks']
                : (!empty($item['model']) ? 'Imported. Model: ' . $item['model'] : 'Imported.');
            if (strlen($remarks) > 120) {
                $remarks = substr($remarks, 0, 117) . '...';
            }

            $insertRows[] = [
                'uuid'          => (string) \Illuminate\Support\Str::orderedUuid(),
                'name'          => $item['name'] ?? '',
                'tag'           => $tag,
                'category_id'   => !empty($item['category_id']) ? (int) $item['category_id'] : null,
                'department_id' => !empty($item['department_id']) ? (int) $item['department_id'] : null,
                'status'        => $item['status'] ?? 'in_service',
                'serial_number' => $item['serial_number'] ?? null,
                'purchase_date' => !empty($item['purchase_date']) ? $item['purchase_date'] : null,
                'purchase_cost' => is_numeric($item['purchase_cost'] ?? '') ? $item['purchase_cost'] : null,
                'remarks'       => $remarks,
                'editor'        => $editorId,
                'property_id'   => $propertyId,
                'created_at'    => now(),
                'updated_at'    => now(),
            ];
        }

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
            Cache::forget($cacheKey);
            Cache::forget('import_state_' . auth()->id());
        }

        return response()->json([
            'success'         => true,
            'processed_count' => $processedCount,
            'is_completed'    => $isCompleted,
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


