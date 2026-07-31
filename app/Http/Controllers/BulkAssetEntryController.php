<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Category;
use App\Models\Department;
use App\Traits\SanitizesImportDates;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Bulk Add Manual — typing several assets straight into a spreadsheet-like grid.
 *
 * This is a standalone feature, deliberately kept apart from the Smart Import
 * pipeline (upload → column mapping → background job → temporary_asset_imports
 * → review → storeBatch). Nothing here reads or writes the staging table.
 *
 * The separation is not cosmetic: the manual form used to render Smart Import's
 * review page, whose confirm button always called storeBatch(). Because the
 * manual flow never populates the staging table, storeBatch() saw zero valid
 * rows and returned success without inserting anything — the user got a success
 * message and no data. Submitting natively to store() is what makes that class
 * of bug impossible.
 */
class BulkAssetEntryController extends Controller
{
    use SanitizesImportDates;

    /**
     * Number of blank rows the grid starts with.
     */
    private const DEFAULT_ROWS = 5;

    /**
     * Render the manual bulk-entry grid.
     */
    public function create(Request $request)
    {
        $this->authorize('create', Asset::class);

        $propertyId = $this->activePropertyId();

        // Eager-load property: the option labels append the property name for
        // super-admins, and Model::shouldBeStrict() forbids lazy loading it.
        $categories = $propertyId
            ? Category::with('property')->where('property_id', $propertyId)->orderBy('name')->get()
            : collect();
        $departments = $propertyId
            ? Department::with('property')->where('property_id', $propertyId)->orderBy('name')->get()
            : collect();

        // Staff without executive oversight may only file assets under their own
        // department, mirroring the single-asset create form. null means "let the
        // user choose" — which also covers a user who has no department at all.
        $lockedDepartmentId = auth()->user()->hasExecutiveOversight()
            ? null
            : optional(auth()->user()->department)->id;

        $blank = [
            'tag' => '', 'name' => '', 'category_id' => '',
            'department_id' => $lockedDepartmentId ?? '',
            'status' => 'in_service', 'model' => '', 'serial_number' => '',
            'purchase_date' => '', 'purchase_cost' => '', 'remarks' => '',
        ];

        // Repopulate from old() so a validation failure doesn't wipe the grid.
        $rows = array_values(old('assets', array_fill(0, self::DEFAULT_ROWS, $blank)));

        // $propertyId goes to the view so it can tell "no property selected" apart
        // from "property selected but it has no categories yet" — two different
        // problems that need two different messages.
        return view('assets.bulk-manual', compact(
            'categories',
            'departments',
            'rows',
            'lockedDepartmentId',
            'propertyId'
        ));
    }

    /**
     * Insert the assets submitted from the manual bulk-entry grid.
     *
     * Rows are validated and inserted directly — category/department IDs are
     * cross-checked against the active property so a tampered payload cannot
     * attach an asset to another tenant's entities.
     */
    public function store(Request $request)
    {
        $this->authorize('create', Asset::class);

        // Idempotency guard. assets.tag has no unique constraint and uuid is
        // regenerated per insert, so nothing at the database level would stop a
        // double-click or a back-then-resubmit from duplicating every row.
        // Cache::add() is atomic (set-if-not-exists), so the first submit wins.
        // An absent token intentionally does not block: callers that don't render
        // the form stay usable.
        $formId = (string) $request->input('_form_id', '');
        if ($formId !== '') {
            $claimKey = 'bulk_manual_form_' . auth()->id() . '_' . $formId;
            if (! Cache::add($claimKey, true, 300)) {
                return redirect()->route('assets.index')
                    ->with('warning', __('assets.duplicate_submission'));
            }
        }

        $request->validate([
            'assets'                 => 'required|array',
            'assets.*.name'          => 'required|string|max:255',
            'assets.*.tag'           => 'required|string|max:64',
            'assets.*.category_id'   => 'required|integer',
            'assets.*.department_id' => 'nullable|integer',
            'assets.*.status'        => 'required|in:in_service,out_of_service,disposed',
            // These reach DB::table()->insert() unfiltered, so an over-long value
            // used to surface as a raw SQL error rather than a validation message.
            'assets.*.model'         => 'nullable|string|max:255',
            'assets.*.serial_number' => 'nullable|string|max:255',
            'assets.*.purchase_date' => 'nullable|string|max:32',
            'assets.*.purchase_cost' => 'nullable|numeric',
            'assets.*.remarks'       => 'nullable|string|max:120',
        ]);

        $editorId   = auth()->id();
        $propertyId = $this->activePropertyId();

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

            $tag = !empty($item['tag']) ? $item['tag'] : ('AST-' . strtoupper(substr(uniqid(), -6)));

            // model used to be smuggled in here ('Imported. Model: X') because
            // assets had no model column. It has one now, so remarks stays clean.
            $remarks = !empty($item['remarks']) ? $item['remarks'] : 'Imported.';
            if (strlen($remarks) > 120) $remarks = substr($remarks, 0, 117) . '...';

            $insertRows[] = [
                'uuid'          => (string) Str::orderedUuid(),
                'name'          => $item['name'],
                'tag'           => $tag,
                'category_id'   => $catId,
                'department_id' => $deptId,
                'status'        => $item['status'] ?? 'in_service',
                'serial_number' => $item['serial_number'] ?? null,
                'model'         => $item['model'] ?? null,
                'purchase_date' => $this->sanitizeDate($item['purchase_date'] ?? null),
                'purchase_cost' => is_numeric($item['purchase_cost'] ?? '') ? $item['purchase_cost'] : null,
                'remarks'       => $remarks,
                'editor'        => $editorId,
                'property_id'   => $propertyId,
                'created_at'    => now(),
                'updated_at'    => now(),
            ];
        }

        DB::beginTransaction();
        try {
            foreach (array_chunk($insertRows, 500) as $chunk) {
                DB::table('assets')->insert($chunk);
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Bulk Manual Insert Failed: ' . $e->getMessage());
            return back()->withInput()->withErrors(['error' => 'Failed to save assets. ' . $e->getMessage()]);
        }

        // Clear session cache entries left over from the parse/mapping phase.
        Cache::forget('import_state_' . auth()->id());
        Cache::forget('import_review_' . auth()->id()); // legacy key, kept for safety

        return redirect()->route('assets.index')->with('ok', __('assets.import_success'));
    }

    /**
     * Resolve the tenant this entry belongs to: super-admins target the
     * session-selected property, everyone else their own.
     */
    private function activePropertyId(): ?int
    {
        $propertyId = auth()->user()->isSuperAdmin()
            ? session('active_property_id')
            : auth()->user()->property_id;

        return $propertyId ? (int) $propertyId : null;
    }
}
