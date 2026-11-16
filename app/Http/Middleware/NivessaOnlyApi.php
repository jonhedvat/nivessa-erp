<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

/**
 * API-token twin of NivessaOnly (Sarah, 2026-11-16). The connector API
 * (/connector/api, Passport tokens) skips the web middleware group, so an
 * outside-business account with a token could still read and change data.
 * Only business 1 tokens get through.
 */
class NivessaOnlyApi
{
    public function handle($request, Closure $next)
    {
        $user = Auth::guard('api')->user();
        if ($user && (int) $user->business_id !== NivessaOnly::BUSINESS_ID) {
            \Log::warning('NivessaOnlyApi: blocked outside API token', [
                'user_id' => $user->id,
                'username' => $user->username,
                'business_id' => $user->business_id,
                'ip' => $request->ip(),
                'path' => $request->path(),
            ]);
            return response()->json(['success' => false, 'message' => 'This account cannot use this system.'], 403);
        }
        return $next($request);
    }
}
