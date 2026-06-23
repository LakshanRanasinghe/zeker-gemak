<?php

use App\Models\User;
use Database\Seeders\EuCountriesSeeder;
use Database\Seeders\EuProvincesSeeder;
use Database\Seeders\UserRoles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Konekt\Address\Models\Address;
use Konekt\Address\Models\Province;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(EuCountriesSeeder::class);
    $this->seed(EuProvincesSeeder::class);
    $this->seed(UserRoles::class);
});

test('a user can register via API', function () {
    $province = Province::where('country_id', 'NL')->where('code', 'NH')->firstOrFail();

    $data = [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'first_name' => 'John',
        'last_name' => 'Doe',
        'company' => 'Business Labels',
        'phone' => '0612345678',
        'billing_email' => 'accounts@example.com',
        'country_id' => 'NL',
        'country' => 'Netherlands',
        'street_address' => 'Main Street 1',
        'postcode' => '1234 AB',
        'city' => 'Amsterdam',
        'state_id' => $province->id,
        'province_id' => $province->id,
        'state' => $province->name,
        'vat_number' => 'NL123456789B01',
        'kvk_number' => '12345678',
    ];

    $response = $this->postJson('/api/register', $data);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'message',
            'user' => [
                'id',
                'name',
                'email',
                'first_name',
                'last_name',
            ],
            'access_token',
        ]);

    $this->assertDatabaseHas('users', [
        'email' => 'john@example.com',
    ]);

    $user = User::where('email', 'john@example.com')->first();
    expect($user->hasRole('customer'))->toBeTrue();

    $this->assertDatabaseHas('addresses', [
        'model_type' => User::class,
        'model_id' => $user->id,
        'registration_nr' => '12345678',
    ]);
});

test('registration succeeds if optional company and kvk fields are missing', function () {
    $province = Province::where('country_id', 'NL')->where('code', 'NH')->firstOrFail();

    $data = [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'first_name' => 'John',
        'last_name' => 'Doe',
        'phone' => '0612345678',
        'billing_email' => 'accounts@example.com',
        'country_id' => 'NL',
        'country' => 'Netherlands',
        'street_address' => 'Main Street 1',
        'postcode' => '1234 AB',
        'city' => 'Amsterdam',
        'state_id' => $province->id,
        'province_id' => $province->id,
        'state' => $province->name,
        'vat_number' => 'NL123456789B01',
    ];

    $response = $this->postJson('/api/register', $data);

    $response->assertStatus(201);

    $user = User::where('email', 'john@example.com')->first();
    $address = Address::where('model_id', $user->id)->first();

    expect($address->registration_nr)->toBeNull();
});

test('registration data endpoint returns countries with provinces', function () {
    $response = $this->getJson('/api/register/data');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'countries' => [
                '*' => [
                    'id',
                    'name',
                    'provinces' => [
                        '*' => [
                            'id',
                            'name',
                            'country_id',
                        ],
                    ],
                ],
            ],
        ]);

    $nl = collect($response->json('countries'))->firstWhere('id', 'NL');
    expect($nl['provinces'])->not->toBeEmpty();
});

test('registration requires frontend registration fields', function () {
    $response = $this->postJson('/api/register', [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'first_name' => 'John',
        'last_name' => 'Doe',
        'country_id' => 'NL',
        'country' => 'Netherlands',
        'street_address' => 'Main Street 1',
        'postcode' => '1234 AB',
        'city' => 'Amsterdam',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors([
            'phone',
            'billing_email',
            'vat_number',
        ])
        ->assertJsonMissingValidationErrors([
            'state_id',
            'province_id',
            'state',
        ]);
});

test('registration province must match selected country and state', function () {
    $belgianProvince = Province::where('country_id', 'BE')->firstOrFail();

    $response = $this->postJson('/api/register', [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'first_name' => 'John',
        'last_name' => 'Doe',
        'phone' => '0612345678',
        'billing_email' => 'accounts@example.com',
        'country_id' => 'NL',
        'country' => 'Netherlands',
        'street_address' => 'Main Street 1',
        'postcode' => '1234 AB',
        'city' => 'Amsterdam',
        'state_id' => $belgianProvince->id,
        'province_id' => $belgianProvince->id + 1,
        'state' => 'Not Antwerp',
        'vat_number' => 'NL123456789B01',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors([
            'state_id',
            'province_id',
            'state',
        ]);
});
