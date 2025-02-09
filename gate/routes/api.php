<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\GatewayController;

/*
|------------------------------------------------------------------------------
| Web Routes
|------------------------------------------------------------------------------
|
| By default, these are NOT prefixed with "api".
| We'll add a custom prefix so the final path is "/gateway/api/..."
|
*/

Route::group(['prefix' => 'gateway/api', 'middleware' => ['api']], function () {
    Route::any('events/{path?}', [GatewayController::class, 'forwardEvents'])
         ->where('path', '.*');
});

Route::any('/gateway/api/chat/{path?}', [GatewayController::class, 'forwardChat'])
     ->where('path', '.*');

/**
 *  NOTIFICATIONS
 *  e.g. /gateway/api/notifications
 */
Route::any('/gateway/api/notifications/{path?}', [GatewayController::class, 'forwardNoti'])
     ->where('path', '.*');

Route::any('/gateway/api/user/{path?}', [GatewayController::class, 'forwardUserManagement'])
     ->where('path', '.*');