<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AdminController;
use App\Models\User;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\ResetPasswordController;

Route::group(['prefix' => 'auth'], function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login',    [AuthController::class, 'login']);
    Route::middleware('auth:api', 'verified')->group(function () {
        Route::post('logout',   [AuthController::class, 'logout']);
        Route::get('me',        [AuthController::class, 'me']);
        Route::delete('delete', [AuthController::class, 'delete']);
        Route::put('update', [AuthController::class, 'update']);
    });
});

Route::middleware(['auth:api', 'admin'])->get('/users', [AdminController::class, 'getAllUsers']);
Route::delete('/admin/users/{id}', [AdminController::class, 'deleteUser'])->middleware(['auth:api', 'admin']);

// Email Verification Routes
Route::get('/email/verify/{id}/{hash}', function (Request $request, $id, $hash) {
    $user = User::findOrFail($id);

    // Verify the hash matches
    if (! hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
        return response()->json(['message' => 'Invalid or expired verification link.'], 400);
    }

    // Check if the email is already verified
    if ($user->hasVerifiedEmail()) {
        return response()->json(['message' => 'Email already verified.'], 200);
    }

    // Mark the email as verified
    $user->markEmailAsVerified();

    return response()->json(['message' => 'Email verified successfully.'], 200);
})->middleware(['signed'])->name('verification.verify');

// Password Reset Routes
Route::post('/password/email', [ForgotPasswordController::class, 'sendResetLinkEmail']);
Route::post('/password/reset', [ResetPasswordController::class, 'reset']);
