<?php

namespace App\Http\Controllers;

use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RoleController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth']);
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $this->authorize('view', Role::class);
        
        $query = Role::with(['users', 'property']);
        
        if (! Auth::user()->isSuperAdmin() && ! Auth::user()->isRole('admin')) {
            $query->where('name', '!=', 'admin');
        }

        $roles = $query->latest()->paginate(15);

        return view('roles.index', ['roles' => $roles]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $this->authorize('create', Role::class);

        return view('roles.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->authorize('create', Role::class);
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'perm_assets' => 'nullable|string',
            'perm_users' => 'nullable|string',
            'perm_categories' => 'nullable|string',
            'perm_departments' => 'nullable|string',
            'perm_roles' => 'nullable|string',
        ]);

        // Ensure unfilled permissions become 'no access'
        $data = array_merge([
            'perm_assets' => 'no access',
            'perm_users' => 'no access',
            'perm_categories' => 'no access',
            'perm_departments' => 'no access',
            'perm_roles' => 'no access',
        ], array_filter($data));

        // Ensure role name is in lowercase
        $data['name'] = strtolower((string) $data['name']);

        Role::create($data);

        return redirect()->route('roles.index')->with('ok', 'Role Created');
    }

    /**
     * Display the specified resource.
     */
    public function show(Role $role)
    {
        $this->authorize('view', $role);

        if (! Auth::user()->isSuperAdmin() && ! Auth::user()->isRole('admin') && strtolower($role->name) === 'admin') {
            abort(403, 'You do not have permission to view the admin role.');
        }

        // Paginate related models separately
        $users = $role->users()->paginate(5, ['*'], 'users_page');

        return view('roles.show', ['role' => $role, 'users' => $users]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Role $role)
    {
        $this->authorize('update', $role);

        if (! Auth::user()->isSuperAdmin() && ! Auth::user()->isRole('admin') && strtolower($role->name) === 'admin') {
            abort(403, 'You do not have permission to edit the admin role.');
        }

        return view('roles.edit', [
            'role' => $role,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Role $role)
    {
        $this->authorize('update', $role);

        if (! Auth::user()->isSuperAdmin() && ! Auth::user()->isRole('admin') && strtolower($role->name) === 'admin') {
            abort(403, 'You do not have permission to update the admin role.');
        }

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'perm_assets' => 'nullable|string',
            'perm_users' => 'nullable|string',
            'perm_categories' => 'nullable|string',
            'perm_departments' => 'nullable|string',
            'perm_roles' => 'nullable|string',
        ]);

        // Ensure unfilled permissions become 'no access'
        $data = array_merge([
            'perm_assets' => 'no access',
            'perm_users' => 'no access',
            'perm_categories' => 'no access',
            'perm_departments' => 'no access',
            'perm_roles' => 'no access',
        ], array_filter($data));

        // Ensure role name is in lowercase
        $data['name'] = strtolower((string) $data['name']);
        $role->update($data);

        return redirect()->route('roles.show', $role)->with('ok', 'Updated');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Role $role)
    {
        $this->authorize('delete', $role);

        if (strtolower($role->name) === 'admin') {
            abort(403, 'The admin role cannot be deleted.');
        }

        $role->delete();

        return redirect()->route('roles.index')->with('ok', 'Deleted');
    }
}
