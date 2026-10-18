<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RegistrationAtomicityTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $over = []): array
    {
        return $over + ['company_name' => 'Acme', 'name' => 'Owner', 'email' => 'owner@acme.test', 'password' => 'SecurePass1', 'password_confirmation' => 'SecurePass1'];
    }

    public function test_when_the_verification_email_fails_nothing_is_created_and_nobody_is_logged_in(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('smtp down'));

        $res = $this->post('/register', $this->payload());

        $res->assertSessionHasErrors('email');
        $this->assertDatabaseCount('merchants', 0);
        $this->assertDatabaseCount('merchant_users', 0);
        $this->assertDatabaseCount('webhook_endpoints', 0);
        $this->assertGuest('merchant_web');
    }

    public function test_the_person_can_register_again_after_a_failure(): void
    {
        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('smtp down'));
        $this->post('/register', $this->payload())->assertSessionHasErrors('email');

        Mail::swap(new \Illuminate\Mail\MailManager($this->app));      // real (array) mailer again
        $this->post('/register', $this->payload())->assertRedirect(route('verification.notice'));

        $this->assertDatabaseCount('merchant_users', 1);
        $this->assertAuthenticated('merchant_web');
    }

    public function test_an_unverified_signup_is_replaced_not_blocked(): void
    {
        $this->post('/register', $this->payload(['name' => 'First']))->assertRedirect(route('verification.notice'));
        auth('merchant_web')->logout();

        $this->post('/register', $this->payload(['name' => 'Second']))->assertRedirect(route('verification.notice'));

        $this->assertDatabaseCount('merchants', 1);
        $this->assertDatabaseHas('merchant_users', ['email' => 'owner@acme.test', 'name' => 'Second']);
    }

    public function test_a_verified_email_cannot_be_registered_twice(): void
    {
        $this->post('/register', $this->payload());
        \App\Models\MerchantUser::query()->update(['email_verified_at' => now()]);
        auth('merchant_web')->logout();

        $this->post('/register', $this->payload(['company_name' => 'Other']))->assertSessionHasErrors('email');
        $this->assertDatabaseCount('merchants', 1);
    }

    public function test_password_is_never_flashed_back_to_the_form(): void
    {
        $res = $this->from('/register')->post('/register', $this->payload(['email' => 'someone@gmail.com']));
        $res->assertSessionHasErrors('email');
        $this->assertNull(session()->getOldInput('password'));
    }
}
