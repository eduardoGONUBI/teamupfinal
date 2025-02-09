<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use App\Models\User;

class ForgotPasswordController extends Controller
{
    /**
     * Send a reset link to the given user.
     */
    public function sendResetLinkEmail(Request $request)
    {
        // Validate the email
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
        ]);

        // Handle validation failures
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Invalid email address.',
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

        // Send the password reset link
        $status = Password::sendResetLink(
            $request->only('email')
        );

        // Handle the response
        if ($status === Password::RESET_LINK_SENT) {
            return response()->json([
                'message' => 'Password reset link sent to your email.',
            ], 200);
        } else {
            return response()->json([
                'message' => 'Unable to send password reset link.',
            ], 500);
        }
    }
}
