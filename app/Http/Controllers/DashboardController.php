<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Property;

class DashboardController extends Controller
{
    public function index()
    {
        $assetQuery = Asset::query();
        
        if (!auth()->user()->hasExecutiveOversight() && !auth()->user()->isRole('admin') && !auth()->user()->isSuperAdmin()) {
            $assetQuery->where('department_id', auth()->user()->department_id);
        }

        return view('dashboard', [
            'totalAssets' => (clone $assetQuery)->count(),
            'isAssets' => (clone $assetQuery)->where('status', 'in_service')->count(),
            'oosAssets' => (clone $assetQuery)->where('status', 'out_of_service')->count(),
            'disposedAssets' => (clone $assetQuery)->where('status', 'disposed')->count(),
            'totalValue' => (clone $assetQuery)->sum('purchase_cost'),
            'assetsByDepartment' => (clone $assetQuery)->join('departments', 'assets.department_id', '=', 'departments.id')
                ->selectRaw('count(assets.id) as count, departments.name')
                ->groupBy('departments.name')
                ->pluck('count', 'name'),
            'recentAssets' => (clone $assetQuery)->with(['department', 'editorUser'])->orderByDesc('updated_at')->take(5)->get(),
            'activeProperty' => auth()->user()->isSuperAdmin()
                                    ? (session('active_property_id') ? Property::find(session('active_property_id')) : null)
                                    : auth()->user()->loadMissing('property')->property,
        ]);
    }
}
