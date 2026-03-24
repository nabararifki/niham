<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        $departmentsQuery = \App\Models\Department::query()->with('property');
        $rolesQuery = \App\Models\Role::query()->with('property');
        if (! Auth::user()->isSuperAdmin()) {
            $departmentsQuery->where('property_id', Auth::user()->property_id);
            $rolesQuery->where('property_id', Auth::user()->property_id);
        }

        return view('profile.edit', [
            'user'        => $request->user(),
            'departments' => $departmentsQuery->get(),
            'roles'       => $rolesQuery->get(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Update the user's notification preferences.
     */
    public function updateNotifications(Request $request): RedirectResponse
    {
        $request->user()->update([
            'notify_department' => $request->has('notify_department') && $request->boolean('notify_department'),
            'notify_all_properties' => $request->has('notify_all_properties') && $request->boolean('notify_all_properties'),
            'notify_email' => $request->has('notify_email') && $request->boolean('notify_email'),
            'email_frequency' => $request->input('email_frequency', 'immediate')
        ]);

        return Redirect::back()->with('status', 'notifications-updated');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
