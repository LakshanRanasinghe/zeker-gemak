<?php

use App\Models\BusinessAvailability;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

test('guests are redirected to the login page', function () {
    $response = $this->get(route('availability.index'));
    $response->assertRedirect(route('login'));
});

test('authenticated admins can visit the availability page', function () {
    $user = User::factory()->create();
    Role::findOrCreate('admin');
    $user->assignRole('admin');

    $this->actingAs($user);

    $response = $this->get(route('availability.index'));
    $response->assertOk();
    $response->assertSeeLivewire('availability.index');
});

test('can retrieve business availability dates via api', function () {
    BusinessAvailability::create([
        'date' => '2026-05-19',
    ]);
    BusinessAvailability::create([
        'date' => '2026-05-20',
    ]);

    $response = $this->getJson('/api/availabilities');

    $response->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0', '2026-05-19')
        ->assertJsonPath('data.1', '2026-05-20');
});

test('can filter availability by year and month via api', function () {
    BusinessAvailability::create([
        'date' => '2026-05-19',
    ]);

    BusinessAvailability::create([
        'date' => '2026-06-19',
    ]);

    BusinessAvailability::create([
        'date' => '2027-05-19',
    ]);

    // Filter for year 2026
    $response1 = $this->getJson('/api/availabilities?year=2026');
    $response1->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0', '2026-05-19')
        ->assertJsonPath('data.1', '2026-06-19');

    // Filter for month 05
    $response2 = $this->getJson('/api/availabilities?month=5');
    $response2->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0', '2026-05-19')
        ->assertJsonPath('data.1', '2027-05-19');

    // Filter for year 2026 and month 06
    $response3 = $this->getJson('/api/availabilities?year=2026&month=6');
    $response3->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0', '2026-06-19');
});
