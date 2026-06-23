<?php

use App\Models\TeamMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

test('team members can be fetched by the frontend', function () {
    $second = TeamMember::create([
        'first_name' => 'Grace',
        'last_name' => 'Hopper',
        'email' => 'grace@example.com',
        'phone' => '+31111111111',
        'sort_order' => 2,
    ]);

    $first = TeamMember::create([
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'email' => 'ada@example.com',
        'phone' => null,
        'sort_order' => 1,
    ]);

    $file = UploadedFile::fake()->image('ada.jpg');
    $first->addMedia($file->getRealPath())
        ->usingFileName('ada.jpg')
        ->toMediaCollection('profile_pic');

    $response = $this->getJson('/api/team-members');

    $response->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $first->id)
        ->assertJsonPath('data.0.first_name', 'Ada')
        ->assertJsonPath('data.0.last_name', 'Lovelace')
        ->assertJsonPath('data.0.name', 'Ada Lovelace')
        ->assertJsonPath('data.0.email', 'ada@example.com')
        ->assertJsonPath('data.0.phone', null)
        ->assertJsonPath('data.0.profile_pic_url', fn (?string $url) => $url !== null && str_contains($url, 'ada.jpg'))
        ->assertJsonPath('data.0.sort_order', 1)
        ->assertJsonPath('data.1.id', $second->id);
});

test('team members endpoint returns an empty collection', function () {
    $this->getJson('/api/team-members')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});
