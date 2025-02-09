<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class GatewayController extends Controller
{
    /**
     * Catch-all forwarder for Events routes
     * Example: POST /gateway/api/events => forwarded to POST http://event_manager_webserver/api/events
     *          GET  /gateway/api/events/123 => forwarded to GET  http://event_manager_webserver/api/events/123
     */
    public function forwardEvents(Request $request)
    {
        // Grab the Authorization header (JWT token, etc.) if present
        $token = $request->header('Authorization');

        // The {path?} parameter from our route definition:
        // e.g. "123", "123/participants", etc.
        $subPath = $request->route('path') ?? '';

        // Build the internal URL to the Event microservice
        // e.g. http://event_manager_webserver/api/events
        $internalUrl = 'http://event_manager_webserver/api/events';

        // If there's any subPath, append it
        if (!empty($subPath)) {
            $internalUrl .= '/' . $subPath;
        }

        // Forward the request with:
        // - The original method (GET, POST, PUT, etc.)
        // - The query params
        // - The JSON body
        $response = Http::withHeaders([
            'Authorization' => $token
        ])->send($request->method(), $internalUrl, [
            'query' => $request->query(),
            'json'  => $request->all(),
        ]);

        // Return the service’s response (status, body, headers) back to the client
        return response($response->body(), $response->status())
            ->header('Content-Type', $response->header('Content-Type'));
    }
    public function forwardChat(Request $request)
    {
        $token   = $request->header('Authorization');
        $subPath = $request->route('path') ?? '';  // e.g. "sendMessage/5"

        // Point to your *Chat* service inside Docker
        $CHAT_SERVICE_URL = 'http://chat_microservice_webserver/api';

        // If there's a sub-path, append it
        $targetUrl = $CHAT_SERVICE_URL . (empty($subPath) ? '' : '/'.$subPath);

        // Forward the request
        $response = Http::withHeaders([
            'Authorization' => $token
        ])->send($request->method(), $targetUrl, [
            'query' => $request->query(),
            'json'  => $request->all(),
        ]);

        return response($response->body(), $response->status())
            ->header('Content-Type', $response->header('Content-Type'));
    }

    public function forwardNoti(Request $request)
    {
        $token   = $request->header('Authorization');
        $subPath = $request->route('path') ?? '';  // e.g. "sendMessage/5"

        // Point to your *Chat* service inside Docker
        $NOTI_SERVICE_URL = 'http://noti_microservice_webserver/api';

        // If there's a sub-path, append it
        $targetUrl = $NOTI_SERVICE_URL . (empty($subPath) ? '' : '/'.$subPath);

        // Forward the request
        $response = Http::withHeaders([
            'Authorization' => $token
        ])->send($request->method(), $targetUrl, [
            'query' => $request->query(),
            'json'  => $request->all(),
        ]);

        return response($response->body(), $response->status())
            ->header('Content-Type', $response->header('Content-Type'));
    }

    public function forwardUserManagement(Request $request)
    {
        // Grab the Authorization header if present (JWT, etc.)
        $token   = $request->header('Authorization');
        $subPath = $request->route('path') ?? '';

        // Point to your *User Management* service inside Docker
        // (Adjust to your actual Docker service name & path)
        $USER_MANAGEMENT_SERVICE_URL = 'http://laravel-nginx/api';

        // Build the target URL, e.g. /api/auth/register =>  /gateway/api/user/auth/register
        // So subPath might be "auth/register", "auth/login", etc.
        $targetUrl = $USER_MANAGEMENT_SERVICE_URL . (empty($subPath) ? '' : '/'.$subPath);

        // Forward the request with method, query params, and JSON body
        $response = Http::withHeaders([
            'Authorization' => $token
        ])->send($request->method(), $targetUrl, [
            'query' => $request->query(),
            'json'  => $request->all(),
        ]);

        // Return the response from the service
        return response($response->body(), $response->status())
            ->header('Content-Type', $response->header('Content-Type'));
    }
}


