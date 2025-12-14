<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class UpdateLastSeen
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        $user = $request->user();
        if ($user) {
            if (!$user->last_seen_at || $user->last_seen_at->diffInSeconds(now()) > 60) {
                $user->forceFill(['last_seen_at' => now()])->save();
            }
        }

        return $response;
    }
}

