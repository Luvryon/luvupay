<?php

namespace App\Http\Middleware;

use App\Models\Merchant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Least-privilege API keys: ->middleware('scope:resale:write'). Runs after merchant.auth. Null scopes = all. */
class RequireScope
{
    public function handle(Request $request, Closure $next, string ...$scopes): Response
    {
        /** @var Merchant|null $m */
        $m = $request->attributes->get('merchant');
        if (! $m || ! $m->hasScope(...$scopes)) {
            return response()->json([
                'error' => 'insufficient_scope',
                'message' => 'This API key is not allowed to call this endpoint.',
                'required' => $scopes,
            ], 403);
        }

        return $next($request);
    }
}
