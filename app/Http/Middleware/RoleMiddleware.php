<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, $role)
    {
        if (!$request->user() || strtolower($request->user()->role) !== strtolower($role)) {
            return response()->json(['message' => 'Accès refusé - rôle non autorisé'], 403);
        }

        return $next($request);
    }
}
