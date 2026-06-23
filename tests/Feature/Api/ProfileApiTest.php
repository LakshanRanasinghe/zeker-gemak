<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => Hash::make('old-password'),
    ]);
    \Laravel\Sanctum\Sanctum::actingAs($this->user);
});

it('returns the authenticated user profile', function () {
    $this->getJson('/api/user/profile')
        ->assertOk()
        ->assertJsonPath('data.id', $this->user->id)
        ->assertJsonPath('data.name', 'John Doe')
        ->assertJsonPath('data.email', 'john@example.com')
        ->assertJsonMissing(['password']);
});

it('updates name and email', function () {
    $this->putJson('/api/user/profile', [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
    ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Jane Doe')
        ->assertJsonPath('data.email', 'jane@example.com');

    $this->assertDatabaseHas('users', [
        'id' => $this->user->id,
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
    ]);
});

it('clears email_verified_at when email changes', function () {
    $this->user->update(['email_verified_at' => now()]);

    $this->putJson('/api/user/profile', [
        'name' => 'John Doe',
        'email' => 'newemail@example.com',
    ])->assertOk();

    expect($this->user->fresh()->email_verified_at)->toBeNull();
});

it('keeps email_verified_at when email stays the same', function () {
    $verifiedAt = now();
    $this->user->update(['email_verified_at' => $verifiedAt]);

    $this->putJson('/api/user/profile', [
        'name' => 'Updated Name',
        'email' => 'john@example.com',
    ])->assertOk();

    expect($this->user->fresh()->email_verified_at)->not->toBeNull();
});

it('validates required fields on profile update', function () {
    $this->putJson('/api/user/profile', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'email']);
});

it('validates unique email on profile update', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    $this->putJson('/api/user/profile', [
        'name' => 'John Doe',
        'email' => 'taken@example.com',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('allows keeping own email on profile update', function () {
    $this->putJson('/api/user/profile', [
        'name' => 'John Doe',
        'email' => 'john@example.com',
    ])->assertOk();
});

it('updates password with valid current password', function () {
    $this->putJson('/api/user/profile/password', [
        'current_password' => 'old-password',
        'password' => 'new-secure-password',
        'password_confirmation' => 'new-secure-password',
    ])
        ->assertOk()
        ->assertJsonPath('message', 'Password updated successfully.');

    expect(Hash::check('new-secure-password', $this->user->fresh()->password))->toBeTrue();
});

it('rejects wrong current password', function () {
    $this->putJson('/api/user/profile/password', [
        'current_password' => 'wrong-password',
        'password' => 'new-secure-password',
        'password_confirmation' => 'new-secure-password',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['current_password']);
});

it('rejects mismatched password confirmation', function () {
    $this->putJson('/api/user/profile/password', [
        'current_password' => 'old-password',
        'password' => 'new-secure-password',
        'password_confirmation' => 'different-password',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['password']);
});

it('requires all fields for password update', function () {
    $this->putJson('/api/user/profile/password', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['current_password', 'password']);
});

it('requires authentication for profile endpoints', function () {
    app('auth')->forgetGuards();

    $this->getJson('/api/user/profile')->assertUnauthorized();
    $this->putJson('/api/user/profile', [])->assertUnauthorized();
    $this->putJson('/api/user/profile/password', [])->assertUnauthorized();
});
