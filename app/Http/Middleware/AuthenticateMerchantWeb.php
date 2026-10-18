<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateMerchantWeb
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('merchant_web')->user();

        if (! $user || ! $user->is_active) {
            return redirect()->guest(route('login'));
        }

        if (! $user->hasVerifiedEmail()) {
            return redirect()->route('verification.notice');
        }

        $request->attributes->set('merchant', $user->merchant);
        $request->attributes->set('merchant_id', $user->merchant_id);
        $request->attributes->set('merchant_user', $user);

        return $next($request);
    }
}
