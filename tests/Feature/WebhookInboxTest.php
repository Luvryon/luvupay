<?php

namespace Tests\Feature;

use App\Events\PayPal\PaymentCaptured;
use App\Jobs\ProcessWebhookEventJob;
use App\Models\WebhookEvent;
use App\Services\PayPal\PayPalClient;
use App\Services\PayPal\WebhookHandler;
use App\Services\PayPal\WebhookOutcome;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Mockery\MockInterface;
use Tests\TestCase;

class WebhookInboxTest extends TestCase
{
    use RefreshDatabase;

    private function body(string $id = 'WH-1', string $type = 'PAYMENT.CAPTURE.COMPLETED'): string
    {
        return json_encode(['id' => $id, 'event_type' => $type, 'resource' => ['id' => 'CAP-1', 'amount' => ['value' => '10.00', 'currency_code' => 'USD']]]);
    }

    private function signature(bool $valid): void
    {
        $this->mock(PayPalClient::class, fn (MockInterface $m) => $m->shouldReceive('verifyWebhookSignature')->andReturn($valid));
    }

    public function test_bad_signature_is_rejected_and_nothing_is_stored(): void
    {
        $this->signature(false);

        $this->postJson('/api/v1/webhooks/paypal', json_decode($this->body(), true))->assertStatus(400);
        $this->assertDatabaseCount('webhook_events', 0);
    }

    public function test_verified_event_is_stored_acknowledged_and_queued(): void
    {
        $this->signature(true);
        Bus::fake();

        $this->call('POST', '/api/v1/webhooks/paypal', [], [], [], ['CONTENT_TYPE' => 'application/json'], $this->body())
            ->assertOk()->assertJson(['status' => 'processed']);

        $this->assertDatabaseHas('webhook_events', ['event_id' => 'WH-1', 'status' => 'received']);
        Bus::assertDispatched(ProcessWebhookEventJob::class);
    }

    public function test_redelivery_does_not_create_a_second_row(): void
    {
        $this->signature(true);
        Bus::fake();
        $h = app(WebhookHandler::class);

        $this->assertSame(WebhookOutcome::Processed, $h->handle([], $this->body()));
        $this->assertSame(WebhookOutcome::Duplicate, $h->handle([], $this->body()));
        $this->assertDatabaseCount('webhook_events', 1);
    }

    public function test_unmapped_event_types_are_stored_as_ignored(): void
    {
        $this->signature(true);
        Bus::fake();

        $this->assertSame(WebhookOutcome::Ignored, app(WebhookHandler::class)->handle([], $this->body('WH-2', 'CATALOG.PRODUCT.CREATED')));
        $this->assertDatabaseHas('webhook_events', ['event_id' => 'WH-2', 'status' => 'ignored']);
        Bus::assertNothingDispatched();
    }

    public function test_job_dispatches_the_domain_event_once_and_marks_processed(): void
    {
        $this->signature(true);
        Event::fake([PaymentCaptured::class]);
        $id = $this->storeEvent();

        ProcessWebhookEventJob::dispatchSync($id);
        ProcessWebhookEventJob::dispatchSync($id);          // replay of a processed event is a no-op

        Event::assertDispatchedTimes(PaymentCaptured::class, 1);
        $this->assertDatabaseHas('webhook_events', ['id' => $id, 'status' => 'processed']);
    }

    public function test_failed_job_marks_event_failed_then_dead_letter(): void
    {
        $id = $this->storeEvent();
        Event::listen(PaymentCaptured::class, fn () => throw new \RuntimeException('boom'));

        try {
            ProcessWebhookEventJob::dispatchSync($id);
        } catch (\Throwable) {
        }
        $this->assertDatabaseHas('webhook_events', ['id' => $id, 'status' => 'failed', 'attempts' => 1]);

        (new ProcessWebhookEventJob($id))->failed(new \RuntimeException('boom'));
        $this->assertDatabaseHas('webhook_events', ['id' => $id, 'status' => 'dead']);
    }

    public function test_replay_command_requeues_dead_letters(): void
    {
        $id = $this->storeEvent();
        WebhookEvent::query()->whereKey($id)->update(['status' => 'dead']);
        Bus::fake();

        $this->artisan('luvi:webhooks:replay', ['--status' => 'dead'])->assertSuccessful();

        Bus::assertDispatched(ProcessWebhookEventJob::class);
        $this->assertDatabaseHas('webhook_events', ['id' => $id, 'status' => 'failed', 'attempts' => 0]);
    }

    private function storeEvent(): string
    {
        return WebhookEvent::create([
            'provider' => 'paypal', 'event_id' => 'WH-X', 'event_type' => 'PAYMENT.CAPTURE.COMPLETED', 'status' => 'received',
            'payload' => $this->body('WH-X'), 'received_at' => now(),
        ])->id;
    }
}
