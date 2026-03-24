<?php

namespace App\Http\Controllers;

use App\Models\Property;
use Illuminate\Http\Request;

class PropertyController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth']);
    }

    /**
     * Display a listing of properties.
     */
    public function index()
    {
        $this->authorize('viewAny', Property::class);
        $properties = Property::withCount(['users', 'assets', 'departments', 'categories'])
            ->orderBy('name')
            ->paginate(15);

        return view('properties.index', ['properties' => $properties]);
    }

    /**
     * Show the form for creating a new property.
     */
    public function create()
    {
        $this->authorize('create', Property::class);

        return view('properties.create');
    }

    /**
     * Store a newly created property.
     */
    public function store(Request $request)
    {
        $this->authorize('create', Property::class);
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:properties,code',
            'address' => 'nullable|string|max:500',
            'accent_color' => 'nullable|string|max:7',
            'logo' => 'nullable|image|max:2048',
            'background_image' => 'nullable|image|max:5120',
        ]);

        if ($request->hasFile('logo')) {
            $data['logo_path'] = $request->file('logo')->store('branding', 'public');
        }
        if ($request->hasFile('background_image')) {
            $data['background_image_path'] = $request->file('background_image')->store('branding', 'public');
        }

        $data['code'] = strtoupper((string) $data['code']);

        Property::create($data);

        return redirect()->route('properties.index')->with('ok', 'Property Created');
    }

    /**
     * Display the specified property.
     */
    public function show(Property $property)
    {
        $this->authorize('view', $property);
        $property->loadCount(['users', 'assets', 'departments', 'categories']);

        $users = $property->users()->with('role')->paginate(10, ['*'], 'users_page');
        $departments = $property->departments()->paginate(10, ['*'], 'depts_page');

        return view('properties.show', ['property' => $property, 'users' => $users, 'departments' => $departments]);
    }

    /**
     * Show the form for editing the specified property.
     */
    public function edit(Property $property)
    {
        $this->authorize('update', $property);

        return view('properties.edit', ['property' => $property]);
    }

    /**
     * Update the specified property.
     */
    public function update(Request $request, Property $property)
    {
        $this->authorize('update', $property);
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:properties,code,'.$property->id,
            'address' => 'nullable|string|max:500',
            'accent_color' => 'nullable|string|max:7',
            'logo' => 'nullable|image|max:2048',
            'background_image' => 'nullable|image|max:5120',
        ]);

        if ($request->hasFile('logo')) {
            if ($property->logo_path) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($property->logo_path);
            }
            $data['logo_path'] = $request->file('logo')->store('branding', 'public');
        }
        if ($request->hasFile('background_image')) {
            if ($property->background_image_path) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($property->background_image_path);
            }
            $data['background_image_path'] = $request->file('background_image')->store('branding', 'public');
        }

        $data['code'] = strtoupper((string) $data['code']);

        // Remove raw file fields — not in $fillable; paths were already stored above
        unset($data['logo'], $data['background_image']);

        $property->update($data);

        return redirect()->route('properties.show', $property)->with('ok', 'Property Updated');
    }

    /**
     * Remove the specified property and ALL associated tenant data.
     *
     * - Requires the user to type the property code as confirmation.
     * - Automatically triggers a backup download before destruction.
     * - Cascade-deletes: asset_histories → attachments → assets → departments
     *   → categories → roles → users → branding files → property.
     */
    public function destroy(Request $request, Property $property)
    {
        $this->authorize('delete', $property);

        // ── Layer 1: Code confirmation ──
        $request->validate([
            'confirm_code' => ['required', 'string', function ($attr, $value, $fail) use ($property) {
                if (strtoupper(trim($value)) !== strtoupper($property->code)) {
                    $fail(__('messages.delete_property_code_mismatch'));
                }
            }],
        ]);

        // ── Cascade-delete all tenant data inside a transaction ──
        // Bypass PropertyScope to ensure we get ALL records for this property,
        // regardless of the super admin's current active property session.
        $scope = \App\Models\Scopes\PropertyScope::class;

        \Illuminate\Support\Facades\DB::transaction(function () use ($property, $scope) {
            // 1. Asset children (histories & attachments) — include soft-deleted
            $assetIds = $property->assets()->withoutGlobalScope($scope)->withTrashed()->pluck('id');

            if ($assetIds->isNotEmpty()) {
                // Delete attachment files from disk
                $attachments = \App\Models\Attachment::whereIn('asset_id', $assetIds)->get();
                foreach ($attachments as $att) {
                    \Illuminate\Support\Facades\Storage::disk('public')->delete($att->path);
                }
                \App\Models\Attachment::whereIn('asset_id', $assetIds)->delete();
                \App\Models\AssetHistory::whereIn('asset_id', $assetIds)->delete();
            }

            // 2. Assets (force-delete to bypass soft-deletes)
            $property->assets()->withoutGlobalScope($scope)->withTrashed()->forceDelete();

            // 3. Departments, Categories, Roles
            $property->departments()->withoutGlobalScope($scope)->delete();
            $property->categories()->withoutGlobalScope($scope)->delete();
            $property->roles()->withoutGlobalScope($scope)->delete();

            // 4. Users (no PropertyScope, but clear for safety)
            $property->users()->delete();

            // 5. Branding files
            if ($property->logo_path) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($property->logo_path);
            }
            if ($property->background_image_path) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($property->background_image_path);
            }

            // 6. The property itself
            $property->delete();
        });

        // Clear session if super admin was viewing this property
        if (session('active_property_id') == $property->id) {
            session()->forget('active_property_id');
        }

        return redirect()->route('properties.index')
            ->with('ok', __('messages.property_deleted_success', ['name' => $property->name]));
    }

    /**
     * Switch active property for super admin (stored in session).
     */
    public function switchProperty(Request $request)
    {
        if (! \Illuminate\Support\Facades\Auth::user()->isSuperAdmin()) {
            abort(403, 'Unauthorized.');
        }

        $request->validate([
            'property_id' => 'nullable|exists:properties,id',
        ]);

        if ($request->property_id) {
            session(['active_property_id' => (int) $request->property_id]);
        } else {
            session()->forget('active_property_id');
        }

        return back()->with('ok', $request->property_id
            ? 'Switched to '.Property::find($request->property_id)->name
            : 'Viewing all properties');
    }
}
