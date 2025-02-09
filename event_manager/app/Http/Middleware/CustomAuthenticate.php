<?php
namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;

class CustomAuthenticate extends Middleware
{
    protected function authenticate($request, array $guards)
    {
        if (empty($guards)) {
            $guards = ['api']; // Default to API guard
        }

        parent::authenticate($request, $guards);
    }

    protected function redirectTo($request)
    {
        return null; // Ensure no redirection
    }
}
