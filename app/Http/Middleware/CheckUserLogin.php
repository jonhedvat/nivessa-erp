<?php

namespace App\Http\Middleware;

use Closure;

class CheckUserLogin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $u = $request->user();
        // Deactivated staff are out even if a remember-me cookie signed them in.
        if ($u->user_type != 'user' || $u->allow_login != 1 || (!empty($u->status) && $u->status !== 'active')) {
            abort(403, 'Unauthorized action.');
        }

        return $next($request);
    }
}
