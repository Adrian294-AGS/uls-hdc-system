<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class PasswordController extends Controller
{
    /**
     * Update the user's password.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validateWithBag('updatePassword', [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', 'different:current_password', Password::defaults()],
        ], [
            'current_password.current_password' => 'Your current password is incorrect.',
            'password.different' => 'Choose a new password that is different from your current one.',
        ]);

        $user = $request->user();
        $user->password = Hash::make($validated['password']);
        // Remember-me cookies on other devices stop working; AuthenticateSession ends their sessions.
        $user->setRememberToken(Str::random(60));
        $user->save();

        return back()->with('status', 'password-updated');
    }
}
