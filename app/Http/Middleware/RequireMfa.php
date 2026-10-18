<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** When luvupay.security.require_mfa is on, owners/admins/finance must enrol before using the console. */
class RequireMfa
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('merchant_web')->user();
        if ($user && config('luvupay.security.require_mfa') && $user->mfaRequired() && ! $user->hasMfa()
            && ! $request->routeIs('security.*', 'logout')) {
            return redirect()->route('security.index')->with('status', 'Your organisation requires two-factor authentication. Set it up to continue.');
        }

        return $next($request);
    }
}
