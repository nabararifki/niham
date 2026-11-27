<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

trait HandlesDataGridMutations
{
    private const DATAGRID_BULK_LIMIT = 200;

    /**
     * Execute a tenant-scoped, RBAC-authorized bulk column update on a model.
     *
     * @param  array<int, string>  $allowedFields
     * @param  array<string, string|array<int, mixed>>  $fieldRules
     */
    protected function processBulkUpdate(
        Request $request,
        Model $model,
        array $allowedFields,
        array $fieldRules = [],
        ?callable $scopeCallback = null,
        ?callable $beforeItemUpdate = null
    ): JsonResponse {
        $validated = $request->validate([
            'row_ids'    => 'required|array|min:1|max:' . self::DATAGRID_BULK_LIMIT,
            'row_ids.*'  => 'required|integer|min:1',
            'field_name' => 'required|string',
            'new_value'  => 'nullable',
        ]);

        $fieldName = $validated['field_name'];
        if (! in_array($fieldName, $allowedFields, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Field is not allowed for bulk update.',
            ], 422);
        }

        $newValue = $validated['new_value'] ?? null;
        if (isset($fieldRules[$fieldName])) {
            Validator::make(
                ['new_value' => $newValue],
                ['new_value' => $fieldRules[$fieldName]]
            )->validate();
        }

        $ids = array_values(array_unique(array_map('intval', $validated['row_ids'])));

        $query = $model->newQuery();
        $this->applyTenantDataGridScope($query, $model);

        if ($scopeCallback !== null) {
            $scopeCallback($query, $request);
        }

        $records = $query->whereIn($model->getKeyName(), $ids)->get();

        if ($records->isEmpty()) {
            return response()->json([
                'success'      => true,
                'updatedCount' => 0,
            ]);
        }

        $updatedCount = 0;

        DB::transaction(function () use ($records, $fieldName, $newValue, $beforeItemUpdate, &$updatedCount): void {
            foreach ($records as $record) {
                $this->authorize('update', $record);

                $attributes = [$fieldName => $newValue];
                if ($beforeItemUpdate !== null) {
                    $attributes = $beforeItemUpdate($record, $fieldName, $newValue, $attributes);
                }

                $record->update($attributes);
                $updatedCount++;
            }
        });

        return response()->json([
            'success'      => true,
            'updatedCount' => $updatedCount,
        ]);
    }

    /**
     * Execute a tenant-scoped, RBAC-authorized bulk delete on a model.
     */
    protected function processBulkDelete(
        Request $request,
        Model $model,
        ?callable $scopeCallback = null,
        ?callable $deleteCallback = null
    ): JsonResponse {
        $validated = $request->validate([
            'row_ids'   => 'required|array|min:1|max:' . self::DATAGRID_BULK_LIMIT,
            'row_ids.*' => 'required|integer|min:1',
        ]);

        $ids = array_values(array_unique(array_map('intval', $validated['row_ids'])));

        $query = $model->newQuery();
        $this->applyTenantDataGridScope($query, $model);

        if ($scopeCallback !== null) {
            $scopeCallback($query, $request);
        }

        $records = $query->whereIn($model->getKeyName(), $ids)->get();

        if ($records->isEmpty()) {
            return response()->json([
                'success'      => true,
                'deletedCount' => 0,
            ]);
        }

        $deletedCount = 0;

        DB::transaction(function () use ($records, $deleteCallback, &$deletedCount): void {
            foreach ($records as $record) {
                $this->authorize('delete', $record);

                if ($deleteCallback !== null) {
                    $deleteCallback($record);
                } else {
                    $record->delete();
                }

                $deletedCount++;
            }
        });

        return response()->json([
            'success'      => true,
            'deletedCount' => $deletedCount,
        ]);
    }

    /**
     * Enforce PropertyScope / property_id tenant boundary explicitly on DataGrid queries.
     */
    private function applyTenantDataGridScope(Builder $query, Model $model): void
    {
        $user = Auth::user();
        if (! $user) {
            abort(401);
        }

        if (! Schema::hasColumn($model->getTable(), 'property_id')) {
            return;
        }

        if ($user->isSuperAdmin()) {
            $activePropertyId = session('active_property_id');
            if ($activePropertyId) {
                $query->where($model->qualifyColumn('property_id'), $activePropertyId);
            }

            return;
        }

        $query->where($model->qualifyColumn('property_id'), $user->property_id);
    }
}
