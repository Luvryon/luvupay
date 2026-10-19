<?php

namespace App\Http\Controllers\Auth;

use App\Mail\PlainHtmlMail;
use App\Models\Merchant;
use App\Models\MerchantUser;
use App\Models\WebhookEndpoint;
use App\Support\AuditContext;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Throwable;

class MerchantAuthController
{
    private const FREE_MAIL = ['gmail.com', 'googlemail.com', 'yahoo.com', 'yahoo.co.id', 'hotmail.com', 'outlook.com', 'live.com', 'icloud.com', 'mail.com', 'proton.me', 'protonmail.com', 'aol.com', 'yandex.com', 'zoho.com'];

    public function showRegister()
    {
        return view('auth.register');
    }

    /**
     * Registration is ALL-OR-NOTHING. Merchant, owner, webhook endpoint and the verification email are one unit:
     * if anything fails (database, SMTP, ...) the transaction rolls back, no account exists, nobody is logged in,
     * and the person simply submits the form again. (The verification email is sent synchronously on purpose:
     * a queued mail would "succeed" here and fail later, leaving an account nobody can verify.)
     */
    public function register(Request $request, AuditContext $ctx)
    {
        $data = $request->validate([
            'company_name' => 'required|string|max:120',
            'name' => 'required|string|max:120',
            'email' => 'required|email|max:191',
            'password' => ['required', 'confirmed', Password::min(10)->mixedCase()->numbers()->symbols()->uncompromised(3)],
        ]);

        $email = strtolower($data['email']);
        $domain = substr(strrchr($email, '@') ?: '', 1);
        if ($domain === '' || in_array($domain, self::FREE_MAIL, true)) {
            return back()->withErrors(['email' => 'Please use a valid work email (company domain). Free webmail is not allowed.'])->withInput($request->except('password', 'password_confirmation'));
        }

        $existing = MerchantUser::query()->where('email', $email)->first();
        if ($existing && $existing->hasVerifiedEmail()) {
            return back()->withErrors(['email' => 'This email is already registered. Log in instead.'])->withInput($request->except('password', 'password_confirmation'));
        }

        $keyData = Merchant::issueApiKey('live');

        try {
            $user = DB::transaction(function () use ($data, $email, $existing, $keyData) {
                // A previous sign-up for this address that was never verified holds no key and no data: replace it,
                // so a typo or a lost email never locks the real owner out.
                if ($existing) {
                    Merchant::query()->whereKey($existing->merchant_id)->delete();
                }

                $merchant = Merchant::create([
                    'id' => (string) Str::uuid(),
                    'name' => $data['company_name'],
                    'email' => $email,
                    'api_key_prefix' => $keyData['prefix'],
                    'api_key_hash' => $keyData['hash'],
                    'publishable_key' => $keyData['publishable'],
                    'allowed_origins' => [],
                    'is_active' => 'true',
                ]);

                $user = MerchantUser::registerOwner($merchant, $data['name'], $email, $data['password']);
                $code = $user->issueEmailVerificationCode();

                WebhookEndpoint::create([
                    'id' => (string) Str::uuid(),
                    'merchant_id' => $merchant->id,
                    'url' => 'https://example.com/webhooks/luvupay',
                    'secret' => 'whsec_'.Str::random(32),
                    'events' => ['entitlement.transfer'],
                    'is_active' => 'false',
                ]);

                $this->sendVerificationEmail($user, $code);   // throws -> everything above is rolled back

                return $user;
            });
        } catch (UniqueConstraintViolationException) {
            return back()->withErrors(['email' => 'This email is already registered. Log in instead.'])->withInput($request->except('password', 'password_confirmation'));
        } catch (Throwable $e) {
            report($e);
            Log::warning('auth.register_failed', ['email_domain' => $domain, 'error' => $e->getMessage()]);

            return back()->withErrors(['email' => 'We could not complete your registration, so no account was created. Please check your details and try again.'])
                ->withInput($request->except('password', 'password_confirmation'));
        }

        // Committed: only now does the person get a session.
        $ctx->actor('merchant_register', $user->id);
        Auth::guard('merchant_web')->login($user);
        $request->session()->regenerate();
        // API key revealed ONLY after email verification succeeds (not on verify form).
        $request->session()->put('pending_api_key', $keyData['key']);

        return redirect()->route('verification.notice');
    }

    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request, AuditContext $ctx)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $key = 'login:'.Str::lower($credentials['email']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withErrors(['email' => 'Too many attempts. Try again later.']);
        }

        $guard = Auth::guard('merchant_web');
        // once(): checks the credentials without opening a session, so a 2FA account is not logged in half-way.
        if (! $guard->once([
            'email' => strtolower($credentials['email']),
            'password' => $credentials['password'],
            'is_active' => true,
        ])) {
            RateLimiter::hit($key, 300);

            return back()->withErrors(['email' => 'Invalid credentials.'])->withInput($request->only('email'));
        }

        /** @var MerchantUser $user */
        $user = $guard->user();
        RateLimiter::clear($key);

        if ($user->hasMfa()) {
            $request->session()->regenerate();
            $request->session()->put('mfa_pending', ['id' => $user->id, 'remember' => $request->boolean('remember'), 'until' => now()->addMinutes(10)->timestamp]);

            return redirect()->route('mfa.challenge');
        }

        return $this->finishLogin($request, $user, $request->boolean('remember'), $ctx);
    }

    public function showMfa(Request $request)
    {
        abort_unless($this->pendingMfaUser($request), 302, '', ['Location' => route('login')]);

        return view('auth.mfa');
    }

    public function verifyMfa(Request $request, AuditContext $ctx)
    {
        $request->validate(['code' => 'required|string|max:32']);
        $user = $this->pendingMfaUser($request);
        if (! $user) {
            return redirect()->route('login')->withErrors(['email' => 'Your sign-in expired. Please log in again.']);
        }

        $key = 'mfa:'.$user->id.'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withErrors(['code' => 'Too many attempts. Try again later.']);
        }
        if (! $user->verifyMfa($request->string('code')->toString())) {
            RateLimiter::hit($key, 300);

            return back()->withErrors(['code' => 'Invalid code. Use your authenticator app or a recovery code.']);
        }
        RateLimiter::clear($key);
        $remember = (bool) ($request->session()->get('mfa_pending.remember'));
        $request->session()->forget('mfa_pending');

        return $this->finishLogin($request, $user, $remember, $ctx);
    }

    public function logout(Request $request)
    {
        Auth::guard('merchant_web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function showVerify()
    {
        return view('auth.verify');
    }

    public function verify(Request $request)
    {
        $data = $request->validate(['code' => 'required|string|size:6']);
        /** @var MerchantUser $user */
        $user = Auth::guard('merchant_web')->user();
        abort_unless($user, 403);

        $key = 'verify:'.$user->id;
        if (RateLimiter::tooManyAttempts($key, 8)) {
            return back()->withErrors(['code' => 'Too many attempts. Request a new code in a few minutes.']);
        }
        if (! $user->verifyEmailCode($data['code'])) {
            RateLimiter::hit($key, 600);

            return back()->withErrors(['code' => 'Invalid or expired code.']);
        }
        RateLimiter::clear($key);

        $pendingKey = $request->session()->pull('pending_api_key');

        // Land on dashboard (onboarding removed).
        $target = 'dashboard';
        try {
            if (\Illuminate\Support\Facades\Route::has('dashboard')) {
                $target = 'dashboard'; // complete PayPal onboarding before dashboard
            }
        } catch (\Throwable $e) {
            $target = 'dashboard';
        }

        $redirect = redirect()->route($target)->with('status', 'Email verified. Welcome to LuvuPay — connect PayPal to open the console.');
        if ($pendingKey) {
            $redirect->with('new_api_key', $pendingKey);
        }

        return $redirect;
    }

    public function resend(Request $request)
    {
        /** @var MerchantUser $user */
        $user = Auth::guard('merchant_web')->user();
        abort_unless($user, 403);
        if ($user->hasVerifiedEmail()) {
            return redirect()->route('dashboard');
        }
        $code = $user->issueEmailVerificationCode();
        try {
            $this->sendVerificationEmail($user, $code);
        } catch (Throwable $e) {
            report($e);

            return back()->withErrors(['code' => 'We could not send the email right now. Please try again in a minute.']);
        }

        return back()->with('status', 'A new verification code was sent.');
    }

    private function finishLogin(Request $request, MerchantUser $user, bool $remember, AuditContext $ctx)
    {
        Auth::guard('merchant_web')->login($user, $remember);
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();
        $ctx->actor('merchant_login', $user->id);

        return redirect()->intended(route('dashboard'));
    }

    private function pendingMfaUser(Request $request): ?MerchantUser
    {
        $p = $request->session()->get('mfa_pending');
        if (! is_array($p) || ($p['until'] ?? 0) < time()) {
            return null;
        }

        return MerchantUser::query()->where('is_active', true)->find($p['id']);
    }

    private function sendVerificationEmail(MerchantUser $user, string $code): void
    {
        $fromHost = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'domainku.com';
        $name = e($user->name);
        $safeCode = e($code);
        $host = e($fromHost);

        $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#0f172a;font-family:Inter,Segoe UI,system-ui,sans-serif">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#0f172a;padding:32px 16px">
    <tr><td align="center">
      <table role="presentation" width="100%" style="max-width:520px;background:#111827;border:1px solid #1e293b;border-radius:16px;overflow:hidden">
        <tr><td style="padding:28px 28px 12px;background:linear-gradient(135deg,#5b3df5,#7c3aed)">
          <p style="margin:0;font-size:13px;font-weight:600;letter-spacing:0.08em;text-transform:uppercase;color:rgba(255,255,255,0.85)">LuvuPay Security</p>
          <h1 style="margin:8px 0 0;font-size:22px;line-height:1.25;color:#fff">Verify your merchant account</h1>
        </td></tr>
        <tr><td style="padding:28px;color:#e2e8f0">
          <p style="margin:0 0 16px;font-size:15px;line-height:1.6">Hi {$name},</p>
          <p style="margin:0 0 20px;font-size:15px;line-height:1.6;color:#94a3b8">Enter this code in the console to finish email verification. It expires in <strong style="color:#e2e8f0">30 minutes</strong>.</p>
          <div style="text-align:center;margin:24px 0;padding:20px;background:#0f172a;border:1px solid #334155;border-radius:12px">
            <p style="margin:0;font-size:32px;font-weight:700;letter-spacing:0.35em;color:#a78bfa;font-family:ui-monospace,SFMono-Regular,Menlo,monospace">{$safeCode}</p>
          </div>
          <p style="margin:0;font-size:13px;line-height:1.5;color:#64748b">If you did not create a LuvuPay merchant account, ignore this email.</p>
        </td></tr>
        <tr><td style="padding:16px 28px 24px;border-top:1px solid #1e293b">
          <p style="margin:0;font-size:12px;color:#64748b">© LuvuPay · contact@{$host}</p>
        </td></tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;

        // sendNow(): synchronous so delivery failure rolls back registration transaction.
        Mail::to($user->email)->sendNow(new PlainHtmlMail('Verify your LuvuPay merchant account', $html));
    }
}
