<?php

namespace App\Http\Controllers;

use App\Exports\AssetsExport;
use App\Models\Asset;
use App\Models\Category;
use App\Models\Department;
use App\Models\Location;
use Auth;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Maatwebsite\Excel\Facades\Excel;
use Storage;

class AssetController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth']);
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Asset::query();

        // Search by 'name' or 'tag' fields if a search term is provided
        if ($request->filled('search')) {
            $searchTerm = $request->input('search');
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'ilike', '%'.$searchTerm.'%')
                    ->orWhere('tag', 'ilike', '%'.$searchTerm.'%');
            });
        }

        // Filter by category
        if ($request->filled('category')) {
            $query->where('category_id', $request->category);
        }

        // Pembatasan akses ke departemen lain (non-admin, non-super-admin)
        if (! Auth::user()->isSuperAdmin() && ! Auth::user()->isRole('admin') && ! Auth::user()->hasExecutiveOversight()) {
            $query->where('department_id', Auth::user()->department_id);
        }

        // Filter by department
        if ($request->filled('department')) {
            $query->where('department_id', $request->department);
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Sort
        if ($request->filled('sort')) {
            $query->orderBy($request->sort);
        } else {
            $query->latest();
        }

        $assets = $query->with(['category', 'department', 'location', 'attachments', 'property'])->paginate(15)->withQueryString();
        $categories = Category::with('property')->get();
        $departments = Department::with('property')->get();

        return view('assets.index', ['assets' => $assets, 'categories' => $categories, 'departments' => $departments]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $this->authorize('create', Asset::class);

        return view('assets.create', [
            'categories'   => Category::with('property')->get(),
            'departments'  => Department::with('property')->get(),
            'locations'    => Location::with('property')->orderBy('name')->get(),
            'existingTags' => Asset::select('tag')->distinct()->orderBy('tag')->get(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->authorize('create', Asset::class);
        $data = $request->validate([
            'tag' => 'required|string|max:64',
            'name' => 'required|string|max:255',
            'category_id' => 'required|exists:categories,id',
            'department_id' => 'required|exists:departments,id',
            'location_id' => 'nullable|exists:locations,id',
            'status' => 'in:in_service,out_of_service,disposed',
            'serial_number' => 'nullable|string|max:255',
            'purchase_date' => 'nullable|date',
            'warranty_duration' => 'in:none,6m,1y,2y,3y',
            'purchase_cost' => 'nullable|numeric',
            'vendor' => 'nullable|string|max:255',
            'remarks' => 'nullable|string|max:120',
            'attachment' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048', // Max 2MB
        ]);
        $data['purchase_cost'] = $request->filled('purchase_cost') ? $request->input('purchase_cost') : null;
        $data['editor'] = Auth::id();

        // Warranty Calculation
        $purchaseDate = $request->filled('purchase_date') ? Carbon::parse($request->purchase_date) : null;
        $warrantyDate = null;

        if ($purchaseDate instanceof \Carbon\Carbon) {
            switch ($request->warranty_duration) {
                case '6m':
                    $warrantyDate = $purchaseDate->copy()->addMonths(6);
                    break;
                case '1y':
                    $warrantyDate = $purchaseDate->copy()->addYear();
                    break;
                case '2y':
                    $warrantyDate = $purchaseDate->copy()->addYears(2);
                    break;
                case '3y':
                    $warrantyDate = $purchaseDate->copy()->addYears(3);
                    break;
            }
        }

        $data['purchase_date'] = $purchaseDate;
        $data['warranty_date'] = $warrantyDate;

        // Remove fields not in $fillable — handled separately
        unset($data['attachment'], $data['warranty_duration']);

        // property_id is auto-assigned by BelongsToProperty trait
        $asset = Asset::create($data);

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $extension = $file->getClientOriginalExtension();
            if (! in_array(strtolower($extension), ['jpg', 'jpeg', 'png', 'webp'])) {
                $extension = 'jpg';
            }
            $filename = uniqid('asset_').'.'.$extension;
            $relativePath = 'attachments/'.$filename;

            // Create ImageManager instance
            $manager = new ImageManager(new Driver);

            // Read, Resize & Compress
            $image = $manager->read($file->getRealPath());
            $image->scaleDown(width: 1920);
            $encoded = $image->toJpeg(80);

            // Store physically
            Storage::disk('public')->put($relativePath, $encoded->toString());

            $asset->attachments()->create([
                'path' => $relativePath,
                'type' => 'image/jpeg',
            ]);
        }

        return redirect()->route('assets.show', $asset)->with('ok', 'Asset Created');
    }

    /**
     * Display the specified resource.
     */
    public function show(Asset $asset)
    {
        $this->authorize('view', $asset);
        $asset->load([
            'category',
            'department',
            'location',
            'attachments',
        ]);
        $assetClass = Asset::class;
        $recentHistories = \App\Models\AssetHistory::where('asset_id', $asset->id)
            ->with('user')
            ->latest()
            ->take(3)
            ->get();

        return view('assets.show', ['asset' => $asset, 'assetClass' => $assetClass, 'recentHistories' => $recentHistories]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Asset $asset)
    {
        $this->authorize('update', $asset);

        // Eager-load asset relations used in the view
        $asset->loadMissing(['attachments', 'department']);

        return view('assets.edit', [
            'asset'        => $asset,
            'categories'   => Category::with('property')->get(),
            'departments'  => Department::with('property')->get(),
            'locations'    => Location::with('property')->orderBy('name')->get(),
            'existingTags' => Asset::select('tag')->distinct()->orderBy('tag')->get(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Asset $asset)
    {
        $this->authorize('update', $asset);
        $data = $request->validate([
            'tag' => 'required|string|max:64',
            'name' => 'required|string|max:255',
            'category_id' => 'required|exists:categories,id',
            'department_id' => 'nullable|exists:departments,id',
            'location_id' => 'nullable|exists:locations,id',
            'status' => 'in:in_service,out_of_service,disposed',
            'serial_number' => 'nullable|string|max:255',
            'purchase_date' => 'nullable|date',
            'warranty_duration' => 'in:none,6m,1y,2y,3y',
            'purchase_cost' => 'nullable|numeric',
            'vendor' => 'nullable|string|max:255',
            'remarks' => 'nullable|string|max:120',
            'attachment' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048', // Max 2MB
        ]);
        if ($request->has('purchase_cost')) {
            $data['purchase_cost'] = $request->filled('purchase_cost') ? $request->input('purchase_cost') : null;
        }

        $data['editor'] = Auth::id();

        // Warranty Calculation (Only if purchase_date is present in the request)
        if ($request->has('purchase_date')) {
            $purchaseDate = $request->filled('purchase_date') ? Carbon::parse($request->purchase_date) : null;
            $warrantyDate = null;

            if ($purchaseDate instanceof \Carbon\Carbon) {
                switch ($request->warranty_duration) {
                    case '6m':
                        $warrantyDate = $purchaseDate->copy()->addMonths(6);
                        break;
                    case '1y':
                        $warrantyDate = $purchaseDate->copy()->addYear();
                        break;
                    case '2y':
                        $warrantyDate = $purchaseDate->copy()->addYears(2);
                        break;
                    case '3y':
                        $warrantyDate = $purchaseDate->copy()->addYears(3);
                        break;
                }
            }

            $data['purchase_date'] = $purchaseDate;
            $data['warranty_date'] = $warrantyDate;
        }

        // Remove fields not in $fillable — handled separately
        unset($data['attachment'], $data['warranty_duration']);

        $asset->update($data);

        // Attachment
        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $extension = $file->getClientOriginalExtension();
            if (! in_array(strtolower($extension), ['jpg', 'jpeg', 'png', 'webp'])) {
                $extension = 'jpg';
            }
            $filename = uniqid('asset_').'.'.$extension;
            $relativePath = 'attachments/'.$filename;

            // Create ImageManager instance
            $manager = new ImageManager(new Driver);

            // Read, Resize & Compress
            $image = $manager->read($file->getRealPath());
            $image->scaleDown(width: 1920);
            $encoded = $image->toJpeg(80);

            // Store physically
            Storage::disk('public')->put($relativePath, $encoded->toString());

            // If the asset already has an attachment, delete the old file + record
            if ($asset->attachments()->exists()) {
                $oldAttachment = $asset->attachments()->first();

                // Delete the old file from storage
                if ($oldAttachment && \Storage::disk('public')->exists($oldAttachment->path)) {
                    \Storage::disk('public')->delete($oldAttachment->path);
                }

                // Update the existing record instead of creating a new one
                $oldAttachment->update([
                    'path' => $relativePath,
                    'type' => 'image/jpeg',
                ]);
            } else {
                // If no attachment exists, just create a new one
                $asset->attachments()->create([
                    'path' => $relativePath,
                    'type' => 'image/jpeg',
                ]);
            }
        }

        return redirect()->route('assets.show', $asset)->with('ok', 'Updated');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Asset $asset)
    {
        $this->authorize('delete', $asset);
        $asset->forceDelete(); // Hard delete

        return redirect()->route('assets.index')->with('success', __('messages.asset_deleted'));
    }

    public function export(Request $request)
    {
        $query = Asset::query();
        $appliedFilters = [];

        // Search
        if ($request->filled('search')) {
            $searchTerm = $request->input('search');
            $appliedFilters['search'] = $searchTerm;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'ilike', '%'.$searchTerm.'%')
                    ->orWhere('tag', 'ilike', '%'.$searchTerm.'%');
            });
        }

        // Filter by category
        if ($request->filled('category')) {
            $cat = Category::find($request->category);
            $appliedFilters['category'] = $cat ? $cat->name : $request->category;
            $query->where('category_id', $request->category);
        }

        // Pembatasan akses ke departemen lain (non-admin, non-super-admin)
        if (! Auth::user()->isSuperAdmin() && ! Auth::user()->isRole('admin') && ! Auth::user()->hasExecutiveOversight()) {
            $appliedFilters['department_scope'] = Auth::user()->loadMissing('department')->department->name;
            $query->where('department_id', Auth::user()->department_id);
        }

        // Filter by department
        if ($request->filled('department')) {
            $dept = Department::find($request->department);
            $appliedFilters['department'] = $dept ? $dept->name : $request->department;
            $query->where('department_id', $request->department);
        }

        // Filter by status
        if ($request->filled('status')) {
            $appliedFilters['status'] = str_replace('_', ' ', ucfirst($request->status));
            $query->where('status', $request->status);
        }

        // Sort
        if ($request->filled('sort')) {
            $appliedFilters['sort'] = ucfirst($request->sort);
            $query->orderBy($request->sort);
        } else {
            $query->latest();
        }

        $assetsToExport = $query->with(['category', 'department', 'location'])->get();
        $property = Auth::user()->isSuperAdmin() && session('active_property_id') ? \App\Models\Property::find(session('active_property_id'))?->name ?? 'All Properties' : (Auth::user()->isSuperAdmin() ? 'All Properties' : Auth::user()->loadMissing('property')->property->name);

        if ($request->input('format') === 'pdf') {
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('assets.pdf', [
                'assets' => $assetsToExport,
                'propertyName' => $property,
                'filters' => $appliedFilters,
            ])->setPaper('a4', 'landscape');

            return $pdf->stream('assets.pdf');
        }

        return Excel::download(new AssetsExport($assetsToExport, $appliedFilters, $property), 'assets.xlsx');
    }

    public function history(Asset $asset)
    {
        $this->authorize('view', $asset);
        $histories = \App\Models\AssetHistory::where('asset_id', $asset->id)->with('user')->latest()->paginate(15);

        return view('assets.history', ['asset' => $asset, 'histories' => $histories]);
    }

    public function exportHistory(Asset $asset)
    {
        $this->authorize('view', $asset);
        $histories = \App\Models\AssetHistory::where('asset_id', $asset->id)->with('user')->latest()->get();

        $callback = function () use ($histories) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['ID', 'Date', 'Action', 'User', 'Details']);
            foreach ($histories as $history) {
                $details = $history->changes ? json_encode($history->changes) : '';
                fputcsv($file, [
                    $history->id,
                    $history->created_at->format('Y-m-d H:i:s'),
                    $history->action,
                    $history->user ? $history->user->name : 'System',
                    $details,
                ]);
            }
            fclose($file);
        };
        $filename = "asset_{$asset->tag}_history.csv";

        return response()->streamDownload($callback, $filename, [
            'Content-type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=$filename",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ]);
    }
}
