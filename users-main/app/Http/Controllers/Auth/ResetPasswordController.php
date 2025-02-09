<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Password;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use App\Models\User;

class ResetPasswordController extends Controller
{
    /**
     * Reset the given user's password.
     */
    public function reset(Request $request)
    {
        // Validate the request
        $validator = Validator::make($request->all(), [
            'token'                 => 'required',
            'email'                 => 'required|email|exists:users,email',
            'password'              => 'required|string|min:6|confirmed',
        ]);

        // Handle validation failures
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Invalid data provided.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        // Retrieve the user
        $user = User::where('email', $request->email)->first();

        // Check if the user is an admin
        if ($user->is_admin) {
            return response()->json([
                'message' => 'Admin users cannot reset their passwords via this method.',
            ], 403);
        }

        // Attempt to reset the password
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                // Update the user's password
                $user->password = Hash::make($password);
                $user->setRememberToken(Str::random(60));
                $user->save();

                // Fire the password reset event
                event(new PasswordReset($user));
            }
        );

        // Handle the response
        if ($status === Password::PASSWORD_RESET) {
            return response()->json([
                'message' => 'Password has been reset successfully.',
            ], 200);
        } else {
            return response()->json([
                'message' => 'Failed to reset password.',
                'errors'  => ['email' => __($status)],
            ], 500);
        }
    }
}
