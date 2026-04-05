<?php

namespace App\Http\Controllers;

use App\Models\Location;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth']);
    }

    public function index()
    {
        $this->authorize('viewAny', Location::class);
        $locations = Location::with(['assets', 'property'])->orderBy('name')->paginate(15);

        return view('locations.index', ['locations' => $locations]);
    }

    public function create()
    {
        $this->authorize('create', Location::class);
        return view('locations.create');
    }

    public function store(Request $request)
    {
        $this->authorize('create', Location::class);
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:255',
        ]);

        $data['name'] = strtoupper((string) $data['name']);
        if (isset($data['code'])) {
            $data['code'] = strtoupper((string) $data['code']);
        }

        Location::create($data);

        return redirect()->route('locations.index')->with('ok', 'Location Created');
    }

    public function show(Location $location)
    {
        $this->authorize('view', $location);
        $assets = $location->assets()->with('category')->paginate(5, ['*'], 'assets_page');

        return view('locations.show', ['location' => $location, 'assets' => $assets]);
    }

    public function edit(Location $location)
    {
        $this->authorize('update', $location);
        return view('locations.edit', [
            'location' => $location,
        ]);
    }

    public function update(Request $request, Location $location)
    {
        $this->authorize('update', $location);
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:255',
        ]);

        $data['name'] = strtoupper((string) $data['name']);
        if (isset($data['code'])) {
            $data['code'] = strtoupper((string) $data['code']);
        }

        $location->update($data);

        return redirect()->route('locations.show', $location)->with('ok', 'Updated');
    }

    public function destroy(Location $location)
    {
        $this->authorize('delete', $location);
        $location->delete();

        return redirect()->route('locations.index')->with('ok', 'Deleted');
    }
}
