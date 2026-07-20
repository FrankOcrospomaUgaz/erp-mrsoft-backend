<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AdminOnly
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->user()?->cliente_id) {
            return response()->json([
                'status' => 403,
                'message' => 'No autorizado',
            ], 403);
        }

        return $next($request);
    }
}
