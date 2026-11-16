<?php

namespace App\Http\Middleware;

use Closure;

/**
 * Bulk and destructive admin tools (imports, stock/cost rewrites, sync
 * setup) are for business admins only, not every logged-in staff member
 * (Sarah, 2026-11-16).
 */
class AdminOnly
{
    public function handle($request, Closure $next)
    {
        $u = $request->user();
        $ok = $u && ($u->can('superadmin') || $u->hasRole('Admin#' . $u->business_id));
        if (!$ok) {
            abort(403, 'Only admins can use this tool.');
        }
        return $next($request);
    }
}
