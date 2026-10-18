<?php

namespace Tests\Feature;

use App\Models\Merchant;
use App\Models\MerchantUser;
use App\Support\MerchantProject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class ConsoleProjectContextTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['luvupay.demo_simulator' => true]);
        Mail::fake();
    }

    private function owner(string $name = 'Acme'): MerchantUser
    {
        $k = Merchant::issueApiKey('live');
        $m = Merchant::create([
            'id' => (string) Str::uuid(), 'name' => $name, 'email' => Str::slug($name).'@test.dev', 'api_key_prefix' => $k['prefix'],
            'api_key_hash' => $k['hash'], 'publishable_key' => $k['publishable'], 'allowed_origins' => [], 'is_active' => true,
        ]);
        $u = MerchantUser::registerOwner($m, 'Owner', Str::slug($name).'-owner@test.dev', 'SecurePass1');
        $u->forceFill(['email_verified_at' => now()])->save();

        return $u;
    }

    public function test_project_value_object_formats_the_plan_for_display(): void
    {
        config(['luvupay.demo_project' => ['name' => 'StreamNetFly', 'plan' => 'Premium', 'price_minor' => 2000, 'currency' => 'USD', 'interval' => 'month', 'active_subscribers' => 1240]]);

        $p = MerchantProject::current();

        $this->assertSame('StreamNetFly Premium', $p->product());
        $this->assertSame('$20.00', $p->price());
        $this->assertSame(1240, $p->toArray()['active_subscribers']);
    }

    public function test_dashboard_shows_the_project_context_and_loads_only_self_hosted_assets(): void
    {
        $res = $this->actingAs($this->owner(), 'merchant_web')->get('/dashboard')->assertOk();

        $res->assertSee('Managing project', false)->assertSee('StreamNetFly')
            ->assertSee('Premium Subscription', false)->assertSee('$20.00/mo', false)->assertSee('1,240');
        $res->assertSee('Trigger Test: New Subscription Checkout')->assertSee('Confirm &amp; Pay', false);
        $res->assertSee('Simulate User: Retries StreamNetFly Payment ($20)', false);
        $res->assertSee('Simulate User: Lets StreamNetFly Expire (Churn)', false);
        $res->assertSee('Chooses to RESALE StreamNetFly Access (Marketplace)', false);

        $html = $res->getContent();
        $this->assertStringContainsString('Content-Security-Policy', $html);
        foreach (['cdn.tailwindcss.com', 'jsdelivr', 'cdnjs', 'unpkg', 'fonts.googleapis', 'font-awesome'] as $host) {
            $this->assertStringNotContainsString($host, $html, "no third-party asset host: {$host}");
        }
        $this->assertStringContainsString('href="/css/ui.css', $html);   // relative URLs: correct behind ngrok / proxies
        $this->assertDoesNotMatchRegularExpression('/\son(click|submit|change|load)=/i', $html, 'no inline event handlers');
    }

    public function test_checkout_charges_the_projects_plan_and_ignores_client_supplied_product_and_price(): void
    {
        $user = $this->owner();

        $res = $this->actingAs($user, 'merchant_web')->postJson('/api/v1/dashboard/demo/checkout', [
            'display_name' => 'John Doe', 'email' => 'john@example.com',
            'product' => 'Free Forever Plan', 'amount_minor' => 100,
        ])->assertOk();

        $this->assertSame('StreamNetFly Premium', $res->json('row.product'));
        $this->assertSame(2000, $res->json('row.amount_minor'));
        $this->assertDatabaseHas('transactions', ['merchant_id' => $user->merchant_id, 'amount_minor' => 2000]);
    }

    public function test_webhook_logs_are_isolated_per_merchant(): void
    {
        $alice = $this->owner('Alice Co');
        $bob = $this->owner('Bob Co');

        $this->actingAs($alice, 'merchant_web')->postJson('/api/v1/dashboard/demo/checkout', ['email' => 'a@example.com'])->assertOk();
        // A real inbound provider event that belongs to nobody in particular must not be listed either.
        DB::table('webhook_events')->insert([
            'id' => (string) Str::uuid(), 'provider' => 'paypal', 'merchant_id' => null, 'event_id' => 'WH-REAL-1', 'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'status' => 'processed', 'payload' => '{"secret":"other-tenant"}', 'attempts' => 1, 'received_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $own = $this->actingAs($alice, 'merchant_web')->getJson('/api/v1/dashboard/demo/webhook-logs')->assertOk();
        $this->assertGreaterThan(0, count($own->json('data')));
        $this->assertStringNotContainsString('other-tenant', $own->getContent());

        $this->actingAs($bob, 'merchant_web')->getJson('/api/v1/dashboard/demo/webhook-logs')->assertOk()->assertJson(['data' => []]);
    }

    public function test_team_member_names_cannot_inject_script_through_the_confirm_prompt(): void
    {
        $owner = $this->owner();
        $evil = "x');alert(1);//";
        MerchantUser::registerOwner(Merchant::find($owner->merchant_id), $evil, 'member@test.dev', 'SecurePass1')
            ->forceFill(['is_owner' => false, 'role' => 'viewer'])->save();

        $html = $this->actingAs($owner, 'merchant_web')->get('/team')->assertOk()->getContent();

        $this->assertStringNotContainsString("onsubmit=", $html);
        $this->assertStringNotContainsString("alert(1);//')", $html);
        $this->assertStringContainsString('data-confirm="Remove x&#039;);alert(1);// from the team?"', $html);
    }
}
