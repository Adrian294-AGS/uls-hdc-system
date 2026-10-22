<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * First-login password change for accounts created with a temporary password.
 */
class ForcedPasswordController extends Controller
{
    public function edit(Request $request): View|RedirectResponse
    {
        if (! $request->user()->must_change_password) {
            return redirect()->route($request->user()->dashboardRoute());
        }

        return view('auth.change-password');
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', 'different:current_password', Password::defaults()],
        ], [
            'current_password.current_password' => 'The temporary password is incorrect.',
            'password.different' => 'Choose a new password that is different from the temporary one.',
        ]);

        $user = $request->user();
        $user->password = $validated['password'];
        $user->must_change_password = false;
        $user->setRememberToken(Str::random(60));
        $user->save();

        $request->session()->regenerate();

        return redirect()
            ->route($user->dashboardRoute())
            ->with('status', 'Your password has been changed. You were signed out on your other devices.');
    }
}
