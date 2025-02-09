<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function getAllUsers()
{
    // Ensure the request is coming from an admin
    if (auth()->user()->is_admin) {
        $users = User::all();  // Retrieve all users
        return response()->json($users);
    }

    return response()->json(['message' => 'Unauthorized'], 401);
}

public function deleteUser($id)
{
    // Ensure the request is coming from an admin
    if (!auth()->user()->is_admin) {
        return response()->json(['message' => 'Unauthorized'], 401);
    }

    $user = User::find($id);

    // Check if user exists
    if (!$user) {
        return response()->json(['message' => 'User not found'], 404);
    }

    // Prevent an admin from deleting themselves
    if ($user->id === auth()->id()) {
        return response()->json(['message' => 'An admin cannot delete their own account'], 403);
    }

    // Delete the user
    $user->delete();

    return response()->json(['message' => 'User deleted successfully'], 200);
}


}
