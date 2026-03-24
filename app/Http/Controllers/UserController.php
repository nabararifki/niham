<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Property;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;

class UserController extends Controller
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
        $query = User::query();

        // Property scoping for non-super-admin
        if (! Auth::user()->isSuperAdmin()) {
            $query->where('property_id', Auth::user()->property_id);
            
            // Department scoping for non-admin without executive oversight
            if (! Auth::user()->isRole('admin') && ! Auth::user()->hasExecutiveOversight()) {
                $query->where('department_id', Auth::user()->department_id);
            }
        } else {
            // Super admin: scope to active property if set
            $activePropertyId = session('active_property_id');
            if ($activePropertyId) {
                $query->where('property_id', $activePropertyId);
            }
        }

        // Filter by department
        if ($request->filled('department')) {
            $query->where('department_id', $request->department);
        }

        // Filter by role
        if ($request->filled('role')) {
            $query->where('role_id', $request->role);
        }

        $users = $query->whereNot('name', 'Admin')
            ->where('is_super_admin', false) // don't show super admins in regular list
            ->with(['department', 'role', 'property'])
            ->paginate(15)->withQueryString();
            
        $departmentsQuery = Department::query();
        if (! Auth::user()->isSuperAdmin()) {
            $departmentsQuery->where('property_id', Auth::user()->property_id);
            if (! Auth::user()->isRole('admin') && ! Auth::user()->hasExecutiveOversight()) {
                $departmentsQuery->where('id', Auth::user()->department_id);
            }
        }
        $departments = $departmentsQuery->get();
        $roles = Role::with('property')->get();
        $properties = Auth::user()->isSuperAdmin() ? Property::all() : collect();

        return view('users.index', ['users' => $users, 'roles' => $roles, 'departments' => $departments, 'properties' => $properties]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $this->authorize('create', User::class);

        $departmentsQuery = Department::query()->with('property');
        if (! Auth::user()->isSuperAdmin()) {
            $departmentsQuery->where('property_id', Auth::user()->property_id);
            if (! Auth::user()->isRole('admin') && ! Auth::user()->hasExecutiveOversight()) {
                $departmentsQuery->where('id', Auth::user()->department_id);
            }
        }
        $departments = $departmentsQuery->get();

        $rolesQuery = Role::query()->with('property');
        if (! Auth::user()->isSuperAdmin() && ! Auth::user()->isRole('admin')) {
            $rolesQuery->where('name', '!=', 'admin');
        }

        return view('users.create', [
            'roles'       => $rolesQuery->get(),
            'departments' => $departments,
            'properties'  => Auth::user()->isSuperAdmin() ? Property::all() : collect(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->authorize('create', User::class);

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'lowercase', 'max:255', 'unique:'.User::class],
            'email' => ['string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'department_id' => ['required', 'exists:departments,id'],
            'role_id' => ['required', 'exists:roles,id'],
        ];

        // Super admin can assign to any property
        if (Auth::user()->isSuperAdmin()) {
            $rules['property_id'] = ['required', 'exists:properties,id'];
        }

        $request->validate($rules);

        // Enforce department selection restriction
        if (! Auth::user()->isSuperAdmin() && ! Auth::user()->isRole('admin') && !Auth::user()->hasExecutiveOversight() && $request->department_id != Auth::user()->department_id) {
            abort(403, 'You can only assign users to your own department.');
        }

        // Enforce Admin Role assignment restriction
        if (! Auth::user()->isSuperAdmin() && ! Auth::user()->isRole('admin')) {
            $assignedRole = Role::find($request->role_id);
            if ($assignedRole && strtolower((string) $assignedRole->name) === 'admin') {
                abort(403, 'You do not have permission to assign the admin role.');
            }
        }

        $userData = [
            'name' => $request->name,
            'username' => $request->username,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'department_id' => $request->department_id,
            'role_id' => $request->role_id,
        ];

        // Assign property
        if (Auth::user()->isSuperAdmin()) {
            $userData['property_id'] = $request->property_id;
        } else {
            $userData['property_id'] = Auth::user()->property_id;
        }

        $user = User::create($userData);

        event(new Registered($user));

        return redirect()->route('users.show', $user)->with('ok', 'Account Created');
    }

    /**
     * Display the specified resource.
     */
    public function show(User $user)
    {
        $this->authorize('view', $user);
        $user->load([
            'role',
            'department',
            'property',
        ]);

        return view('users.show', ['user' => $user]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(User $user)
    {
        $this->authorize('update', $user);

        $departmentsQuery = Department::query();
        if (! Auth::user()->isSuperAdmin()) {
            $departmentsQuery->where('property_id', Auth::user()->property_id);
            if (! Auth::user()->isRole('admin') && ! Auth::user()->hasExecutiveOversight()) {
                $departmentsQuery->where('id', Auth::user()->department_id);
            }
        }
        if (Auth::user()->isSuperAdmin()) {
            $departmentsQuery->with('property');
        }
        $departments = $departmentsQuery->get();

        $rolesQuery = Role::query();
        if (! Auth::user()->isSuperAdmin() && ! Auth::user()->isRole('admin')) {
            $rolesQuery->where('name', '!=', 'admin');
        }
        if (Auth::user()->isSuperAdmin()) {
            $rolesQuery->with('property');
        }

        return view('users.edit', [
            'user' => $user,
            'roles' => $rolesQuery->get(),
            'departments' => $departments,
            'properties' => Auth::user()->isSuperAdmin() ? Property::all() : collect(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, User $user)
    {
        $this->authorize('update', $user);

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'lowercase', 'max:255', Rule::unique('users')->ignore($user->id)],
            'email' => ['string', 'lowercase', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'department_id' => ['required', 'exists:departments,id'],
            'role_id' => ['required', 'exists:roles,id'],
        ];

        if (Auth::user()->isSuperAdmin()) {
            $rules['property_id'] = ['required', 'exists:properties,id'];
        }

        $data = $request->validate($rules);

        // Enforce department selection restriction
        if (! Auth::user()->isSuperAdmin() && ! Auth::user()->isRole('admin') && !Auth::user()->hasExecutiveOversight() && $request->department_id != Auth::user()->department_id) {
            abort(403, 'You can only assign users to your own department.');
        }

        // Enforce Admin Role assignment restriction
        if (! Auth::user()->isSuperAdmin() && ! Auth::user()->isRole('admin')) {
            $assignedRole = Role::find($request->role_id);
            if ($assignedRole && strtolower((string) $assignedRole->name) === 'admin') {
                abort(403, 'You do not have permission to assign or maintain the admin role for this user.');
            }
        }

        // Assign property
        if (Auth::user()->isSuperAdmin() && $request->filled('property_id')) {
            $data['property_id'] = $request->property_id;
        }

        $user->update($data);

        if ($request->filled('password')) {
            $user->update([
                'password' => Hash::make($request->password),
            ]);
        }

        return redirect()->route('users.show', $user)->with('ok', 'Account Updated');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user)
    {
        $this->authorize('delete', $user);
        $user->delete();

        return redirect()->route('users.index')->with('ok', 'Deleted');
    }
}
