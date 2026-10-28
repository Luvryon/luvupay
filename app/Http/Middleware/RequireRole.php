<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Console RBAC: ->middleware('merchant.can:keys.rotate'). Permissions live in config('luvupay.rbac'). */
class RequireRole
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = Auth::guard('merchant_web')->user();
        if (! $user || ! $user->allows($permission)) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'forbidden', 'message' => 'Your role does not allow this action'], 403);
            }
            abort(403, 'Your role does not allow this action.');
        }

        return $next($request);
    }
}
