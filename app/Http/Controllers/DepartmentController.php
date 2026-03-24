<?php

namespace App\Http\Controllers;

use App\Models\Department;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    // Extra security redundancy
    public function __construct()
    {
        $this->middleware(['auth']);
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $this->authorize('view', Department::class);
        $departments = Department::with(['users', 'assets', 'property'])->orderBy('name')->paginate(15);

        return view('departments.index', ['departments' => $departments]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $this->authorize('create', Department::class);

        return view('departments.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->authorize('create', Department::class);
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:255',
            'notes' => 'nullable|string|max:255',
            'is_executive_oversight' => 'nullable|boolean',
        ]);
        $data['is_executive_oversight'] = $request->has('is_executive_oversight');

        // Ensure Department name in upper case
        $data['name'] = strtoupper((string) $data['name']);
        $data['code'] = strtoupper((string) $data['code']);

        Department::create($data);

        return redirect()->route('departments.index')->with('ok', 'Department Created');
    }

    /**
     * Display the specified resource.
     */
    public function show(Department $department)
    {
        $this->authorize('view', $department);

        // Paginate related models separately
        $users = $department->users()->paginate(5, ['*'], 'users_page');
        $assets = $department->assets()->paginate(5, ['*'], 'assets_page');

        return view('departments.show', ['department' => $department, 'users' => $users, 'assets' => $assets]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Department $department)
    {
        $this->authorize('update', $department);

        return view('departments.edit', [
            'department' => $department,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Department $department)
    {
        $this->authorize('update', $department);
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:255',
            'notes' => 'nullable|string|max:255',
            'is_executive_oversight' => 'nullable|boolean',
        ]);
        $data['is_executive_oversight'] = $request->has('is_executive_oversight');

        // Ensure Department name in upper case
        $data['name'] = strtoupper((string) $data['name']);
        $data['code'] = strtoupper((string) $data['code']);

        $department->update($data);

        return redirect()->route('departments.show', $department)->with('ok', 'Updated');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Department $department)
    {
        $this->authorize('delete', $department);
        $department->delete();

        return redirect()->route('departments.index')->with('ok', 'Deleted');
    }
}
