<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class isExpertMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        // التحقق من أن المستخدم مسجل دخول عبر الـ "experts" guard
        if (!Auth::guard('experts')->check()) {
            return response()->json([
                'status' => 0,
                'message' => 'You are not authorized'
            ], 403);
        }

        return $next($request);
    }
}
