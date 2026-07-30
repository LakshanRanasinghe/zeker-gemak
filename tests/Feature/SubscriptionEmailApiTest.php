<?php

use App\Models\SubscriptionEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('subscription email can be saved from the frontend', function () {
    $response = $this->postJson('/api/subscription-emails', [
        'email' => 'Subscriber@Example.com',
    ]);

    $response->assertCreated()
        ->assertJsonPath('message', 'Subscription email saved successfully.')
        ->assertJsonPath('data.email', 'subscriber@example.com');

    $this->assertDatabaseHas('subscription_emails', [
        'email' => 'subscriber@example.com',
    ]);
});

test('subscription email must be valid', function () {
    $this->postJson('/api/subscription-emails', [
        'email' => 'not-an-email',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('email');
});

test('subscription email must be unique', function () {
    SubscriptionEmail::create([
        'email' => 'subscriber@example.com',
    ]);

    $this->postJson('/api/subscription-emails', [
        'email' => 'Subscriber@Example.com',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('email');
});
