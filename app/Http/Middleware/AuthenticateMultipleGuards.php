<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class AuthenticateMultipleGuards
{
    /**
     * القائمة اللي بدنا نتحقق منها.
     */
    protected $guards = ['users', 'experts'];

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        foreach ($this->guards as $guard) {
            $user = Auth::guard($guard)->user(); // التحقق من المستخدم بدل check()

            if ($user) {
                Auth::shouldUse($guard); // تحديد الـ guard المستخدم فعليًا
                return $next($request);
            }
        }

        // إذا لم يكن هناك مستخدم مسجّل دخول عبر أي حارس
        return response()->json(['message' => 'MW:Unauthorized'], 401);
    }
}
