<?php

namespace App\Http\Controllers\Auth;

use App\Mail\PlainHtmlMail;
use App\Models\MerchantUser;
use App\Models\TeamInvitation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Throwable;

/** Team members, roles and invitations. Only roles with `team.manage`; the owner role is never assignable here. */
class TeamController
{
    private const ASSIGNABLE = ['admin', 'finance', 'developer', 'viewer'];

    public function index()
    {
        return view('team.index', ['roles' => self::ASSIGNABLE]);
    }

    /** GET /team/data: members and pending invitations for the AJAX tables */
    public function data(Request $request): JsonResponse
    {
        $m = $request->attributes->get('merchant');
        $me = Auth::guard('merchant_web')->id();

        $members = MerchantUser::query()->where('merchant_id', $m->id)->orderByDesc('is_owner')->orderBy('name')->get()
            ->map(fn (MerchantUser $u) => [
                'id' => $u->id, 'name' => $u->name, 'email' => $u->email,
                'initial' => strtoupper(substr($u->name ?: $u->email, 0, 1)),
                'role' => $u->role, 'is_owner' => (bool) $u->is_owner, 'is_active' => (bool) $u->is_active,
                'mfa' => $u->hasMfa(), 'editable' => ! $u->is_owner && $u->id !== $me,
            ]);

        $invitations = TeamInvitation::query()->where('merchant_id', $m->id)->whereNull('accepted_at')->where('expires_at', '>', now())->latest()->get()
            ->map(fn (TeamInvitation $i) => ['id' => $i->id, 'email' => $i->email, 'role' => $i->role, 'expires' => $i->expires_at->diffForHumans()]);

        return response()->json(['members' => $members, 'invitations' => $invitations, 'roles' => self::ASSIGNABLE]);
    }

    public function invite(Request $request)
    {
        $data = $request->validate(['email' => 'required|email|max:191', 'role' => ['required', Rule::in(self::ASSIGNABLE)]]);
        $m = $request->attributes->get('merchant');
        $inviter = Auth::guard('merchant_web')->user();
        $email = strtolower($data['email']);

        if (MerchantUser::query()->where('email', $email)->exists()) {
            return $this->fail($request, 'email', 'That email already has a LuvuPay account');
        }

        $token = Str::random(48);
        try {
            DB::transaction(function () use ($m, $inviter, $email, $data, $token) {
                TeamInvitation::query()->where('merchant_id', $m->id)->where('email', $email)->whereNull('accepted_at')->delete();
                TeamInvitation::create([
                    'merchant_id' => $m->id, 'email' => $email, 'role' => $data['role'], 'invited_by' => $inviter->id,
                    'token_hash' => hash('sha256', $token), 'expires_at' => now()->addHours((int) config('luvupay.security.invite_ttl_hours', 72)),
                ]);
                $url = route('team.accept', $token);
                $html = '<p>'.e($inviter->name).' invited you to join <strong>'.e($m->name).'</strong> on LuvuPay as <strong>'.e($data['role']).'</strong></p>'
                    .'<p><a href="'.e($url).'">Accept the invitation</a> (valid for '.(int) config('luvupay.security.invite_ttl_hours', 72).' hours)</p>';
                Mail::to($email)->sendNow(new PlainHtmlMail('You were invited to '.$m->name.' on LuvuPay', $html));
            });
        } catch (Throwable $e) {
            report($e);

            return $this->fail($request, 'email', 'The invitation could not be sent, so it was not created. Please try again');
        }

        return $this->done($request, "Invitation sent to {$email}");
    }

    public function revoke(Request $request, string $invitation)
    {
        TeamInvitation::query()->where('merchant_id', $request->attributes->get('merchant_id'))->whereKey($invitation)->delete();

        return $this->done($request, 'Invitation revoked');
    }

    public function updateRole(Request $request, string $member)
    {
        $data = $request->validate(['role' => ['required', Rule::in(self::ASSIGNABLE)]]);
        $user = $this->member($request, $member);
        $user->update(['role' => $data['role']]);

        return $this->done($request, "{$user->name} is now {$data['role']}");
    }

    public function remove(Request $request, string $member)
    {
        $user = $this->member($request, $member);
        $user->update(['is_active' => false]);                 // keeps audit history intact; blocks login immediately

        return $this->done($request, "{$user->name} was removed");
    }

    /** JSON for AJAX callers, a redirect with a flash message for plain form posts */
    private function done(Request $request, string $message)
    {
        return $request->expectsJson() ? response()->json(['ok' => true, 'message' => $message]) : back()->with('status', $message);
    }

    private function fail(Request $request, string $field, string $message)
    {
        return $request->expectsJson()
            ? response()->json(['ok' => false, 'message' => $message, 'errors' => [$field => [$message]]], 422)
            : back()->withErrors([$field => $message]);
    }

    public function showAccept(string $token)
    {
        $inv = TeamInvitation::findByToken($token);
        abort_unless($inv && $inv->isUsable(), 404);

        return view('team.accept', ['invitation' => $inv->load('merchant'), 'token' => $token]);
    }

    public function accept(Request $request, string $token)
    {
        $inv = TeamInvitation::findByToken($token);
        abort_unless($inv && $inv->isUsable(), 404);
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'password' => ['required', 'confirmed', Password::min(10)->mixedCase()->numbers()],
        ]);

        $user = DB::transaction(function () use ($inv, $data) {
            $locked = TeamInvitation::query()->lockForUpdate()->findOrFail($inv->id);
            abort_unless($locked->isUsable(), 404);
            $u = MerchantUser::create([
                'id' => (string) Str::uuid(), 'merchant_id' => $locked->merchant_id, 'name' => $data['name'], 'email' => $locked->email,
                'password' => $data['password'], 'role' => $locked->role, 'is_owner' => false, 'is_active' => true,
                'email_verified_at' => now(),                   // the invitation link proved control of the mailbox
            ]);
            $locked->update(['accepted_at' => now()]);

            return $u;
        });

        Auth::guard('merchant_web')->login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('status', 'Welcome to the team');
    }

    private function member(Request $request, string $id): MerchantUser
    {
        $user = MerchantUser::query()->where('merchant_id', $request->attributes->get('merchant_id'))->findOrFail($id);
        abort_if($user->is_owner || $user->id === Auth::guard('merchant_web')->id(), 422, 'The owner and your own account cannot be changed here');

        return $user;
    }
}
