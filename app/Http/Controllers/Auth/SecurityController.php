<?php

namespace App\Http\Controllers\Auth;

use App\Services\Security\Totp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

/** Self-service two-factor authentication. */
class SecurityController
{
    public function index(Request $request)
    {
        $user = Auth::guard('merchant_web')->user();
        $setup = null;
        if (! $user->hasMfa() && $user->mfa_secret) {                 // enrolment started, not confirmed
            $secret = $user->mfaSecret();
            $setup = ['secret' => $secret, 'uri' => Totp::uri($secret, $user->email)];
        }

        return view('settings.security', ['user' => $user, 'setup' => $setup, 'recovery' => session('recovery_codes')]);
    }

    public function start(Request $request)
    {
        $user = Auth::guard('merchant_web')->user();
        if ($user->hasMfa()) {
            return back()->withErrors(['code' => 'Two-factor authentication is already enabled.']);
        }
        $user->beginMfa();

        return redirect()->route('security.index');
    }

    public function confirm(Request $request)
    {
        $request->validate(['code' => 'required|string|max:12']);
        $codes = Auth::guard('merchant_web')->user()->confirmMfa($request->string('code')->toString());
        if ($codes === null) {
            return back()->withErrors(['code' => 'That code is not valid. Check the time on your phone and try again.']);
        }

        return redirect()->route('security.index')->with('status', 'Two-factor authentication is on.')->with('recovery_codes', $codes);
    }

    public function disable(Request $request)
    {
        $request->validate(['password' => 'required|string']);
        $user = Auth::guard('merchant_web')->user();
        if (! Hash::check($request->string('password')->toString(), $user->password)) {
            return back()->withErrors(['password' => 'Password is incorrect.']);
        }
        if (config('luvupay.security.require_mfa') && $user->mfaRequired()) {
            return back()->withErrors(['password' => 'Your role requires two-factor authentication; it cannot be turned off.']);
        }
        $user->disableMfa();

        return redirect()->route('security.index')->with('status', 'Two-factor authentication was turned off.');
    }
}
