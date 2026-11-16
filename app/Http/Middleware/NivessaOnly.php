<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

/**
 * Only Nivessa's own staff (business 1) can use the ERP (Sarah, 2026-11-16).
 *
 * The public sign-up page let outsiders create their own "business". A bot
 * account made that way (pHqghUme, business 10) could still reach pages that
 * aren't split by business (website orders, Discogs shipping) and changed real
 * orders. Any login from another business is signed out on its next request.
 */
class NivessaOnly
{
    const BUSINESS_ID = 1;

    public function handle($request, Closure $next)
    {
        $user = Auth::user();
        if ($user && (int) $user->business_id !== self::BUSINESS_ID) {
            \Log::warning('NivessaOnly: signed out outside login', [
                'user_id' => $user->id,
                'username' => $user->username,
                'business_id' => $user->business_id,
                'ip' => $request->ip(),
                'ua' => substr((string) $request->userAgent(), 0, 200),
                'path' => $request->path(),
            ]);
            Auth::logout();
            if ($request->hasSession()) {
                $request->session()->invalidate();
            }
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'This account cannot use this system.'], 403);
            }
            return redirect('/login')->with('status', 'This account cannot use this system.');
        }
        return $next($request);
    }
}
