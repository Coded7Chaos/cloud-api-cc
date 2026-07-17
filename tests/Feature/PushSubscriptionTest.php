<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PushSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_register_push_subscription(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->postJson('/api/push/subscriptions', [
            'endpoint' => 'https://push.example.test/subscription/123',
            'keys' => [
                'p256dh' => 'public-key',
                'auth' => 'auth-token',
            ],
            'contentEncoding' => 'aes128gcm',
        ])->assertCreated();

        $this->assertDatabaseHas('push_subscriptions', [
            'user_id' => $user->id,
            'endpoint' => 'https://push.example.test/subscription/123',
            'endpoint_hash' => hash('sha256', 'https://push.example.test/subscription/123'),
            'public_key' => 'public-key',
            'auth_token' => 'auth-token',
        ]);
    }

    public function test_authenticated_user_can_remove_push_subscription(): void
    {
        $user = User::factory()->create();
        $subscription = $user->pushSubscriptions()->create([
            'endpoint' => 'https://push.example.test/subscription/123',
            'endpoint_hash' => hash('sha256', 'https://push.example.test/subscription/123'),
            'public_key' => 'public-key',
            'auth_token' => 'auth-token',
            'content_encoding' => 'aes128gcm',
        ]);
        $this->actingAs($user);

        $this->deleteJson('/api/push/subscriptions', [
            'endpoint' => $subscription->endpoint,
        ])->assertNoContent();

        $this->assertDatabaseMissing('push_subscriptions', [
            'id' => $subscription->id,
        ]);
    }
}
