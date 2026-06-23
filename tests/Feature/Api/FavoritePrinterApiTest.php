<?php

use App\Models\FavoritePrinter;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    \Laravel\Sanctum\Sanctum::actingAs($this->user);
});

function createPrinter(array $overrides = []): Post
{
    return Post::create(array_merge([
        'title' => 'Test Printer',
        'slug' => 'test-printer',
        'status' => 'published',
        'post_type' => 'printer',
    ], $overrides));
}

it('adds a printer to favorites', function () {
    $printer = createPrinter(['title' => 'Zebra ZD421', 'slug' => 'zebra-zd421']);

    $this->postJson("/api/user/favorite-printers/{$printer->id}")
        ->assertStatus(201)
        ->assertJsonPath('message', 'Printer added to favorites.');

    $this->assertDatabaseHas('favorite_printers', [
        'user_id' => $this->user->id,
        'printer_id' => $printer->id,
    ]);
});

it('does not duplicate a favorite printer', function () {
    $printer = createPrinter(['title' => 'Dup Printer', 'slug' => 'dup-printer']);

    $this->postJson("/api/user/favorite-printers/{$printer->id}")
        ->assertStatus(201);
    $this->postJson("/api/user/favorite-printers/{$printer->id}")
        ->assertStatus(201);

    expect(FavoritePrinter::where('user_id', $this->user->id)
        ->where('printer_id', $printer->id)
        ->count()
    )->toBe(1);
});

it('removes a printer from favorites', function () {
    $printer = createPrinter(['title' => 'Remove Printer', 'slug' => 'remove-printer']);

    FavoritePrinter::create([
        'user_id' => $this->user->id,
        'printer_id' => $printer->id,
    ]);

    $this->deleteJson("/api/user/favorite-printers/{$printer->id}")
        ->assertOk()
        ->assertJsonPath('message', 'Printer removed from favorites.');

    $this->assertDatabaseMissing('favorite_printers', [
        'user_id' => $this->user->id,
        'printer_id' => $printer->id,
    ]);
});

it('checks if a printer is favorited', function () {
    $printer = createPrinter(['title' => 'Check Printer', 'slug' => 'check-printer']);

    $this->getJson("/api/user/favorite-printers/{$printer->id}/check")
        ->assertOk()
        ->assertJsonPath('is_favorite', false);

    FavoritePrinter::create([
        'user_id' => $this->user->id,
        'printer_id' => $printer->id,
    ]);

    $this->getJson("/api/user/favorite-printers/{$printer->id}/check")
        ->assertOk()
        ->assertJsonPath('is_favorite', true);
});

it('lists all favorite printers for the authenticated user', function () {
    $printer1 = createPrinter(['title' => 'Printer A', 'slug' => 'printer-a']);
    $printer2 = createPrinter(['title' => 'Printer B', 'slug' => 'printer-b']);

    FavoritePrinter::create(['user_id' => $this->user->id, 'printer_id' => $printer1->id]);
    FavoritePrinter::create(['user_id' => $this->user->id, 'printer_id' => $printer2->id]);

    $response = $this->getJson('/api/user/favorite-printers')
        ->assertOk()
        ->assertJsonCount(2, 'data');

    $slugs = collect($response->json('data'))->pluck('slug')->sort()->values()->all();
    expect($slugs)->toBe(['printer-a', 'printer-b']);
});

it('returns 404 when favoriting a non-existent printer', function () {
    $this->postJson('/api/user/favorite-printers/99999')
        ->assertNotFound();
});

it('returns 404 when favoriting a non-printer post', function () {
    $page = Post::create([
        'title' => 'About Us',
        'slug' => 'about-us',
        'status' => 'published',
        'post_type' => 'page',
    ]);

    $this->postJson("/api/user/favorite-printers/{$page->id}")
        ->assertNotFound();
});

it('requires authentication to access favorite printers', function () {
    app('auth')->forgetGuards();

    $this->getJson('/api/user/favorite-printers')
        ->assertUnauthorized();
});

it('does not show favorite printers from other users', function () {
    $otherUser = User::factory()->create();
    $printer = createPrinter(['title' => 'Other Printer', 'slug' => 'other-printer']);

    FavoritePrinter::create([
        'user_id' => $otherUser->id,
        'printer_id' => $printer->id,
    ]);

    $this->getJson('/api/user/favorite-printers')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});
