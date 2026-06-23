<?php

use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Config::set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
});

test('guests are redirected from the settings page to the login page', function () {
    $response = $this->get(route('settings.index'));

    $response->assertRedirect(route('login'));
});

test('authenticated admins can visit the settings page', function () {
    $user = User::factory()->create();
    Role::findOrCreate('admin');
    $user->assignRole('admin');

    $this->actingAs($user);

    $response = $this->get(route('settings.index'));

    $response->assertOk();
    $response->assertSeeLivewire('settings.index');
    $response->assertSee('Manage Team');
    $response->assertSee('Popular Products');
});

test('team members can be saved with profile pictures', function () {
    $file = UploadedFile::fake()->image('avatar.jpg');

    Livewire::test('settings.index')
        ->set('teamMembers.0.first_name', 'Ada')
        ->set('teamMembers.0.last_name', 'Lovelace')
        ->set('teamMembers.0.email', 'ada@example.com')
        ->set('teamMembers.0.phone', '+31123456789')
        ->set('teamMembers.0.profile_pic', $file)
        ->call('save')
        ->assertHasNoErrors();

    $teamMember = TeamMember::query()->where('email', 'ada@example.com')->firstOrFail();

    expect($teamMember->first_name)->toBe('Ada')
        ->and($teamMember->phone)->toBe('+31123456789')
        ->and($teamMember->getMedia('profile_pic'))->toHaveCount(1);
});

test('team member blocks can be added', function () {
    Livewire::test('settings.index')
        ->assertCount('teamMembers', 1)
        ->assertSee('Team member 1')
        ->call('addTeamMember')
        ->assertCount('teamMembers', 2)
        ->assertSee('Team member 2');
});

test('removed persisted team members are deleted when saving', function () {
    $removed = TeamMember::create([
        'first_name' => 'Grace',
        'last_name' => 'Hopper',
        'email' => 'grace@example.com',
    ]);

    $kept = TeamMember::create([
        'first_name' => 'Katherine',
        'last_name' => 'Johnson',
        'email' => 'katherine@example.com',
    ]);

    Livewire::test('settings.index')
        ->set('teamMembers', [
            [
                'row_key' => 'team-member-'.$kept->id,
                'id' => $kept->id,
                'first_name' => 'Katherine',
                'last_name' => 'Johnson',
                'email' => 'katherine@example.com',
                'phone' => null,
                'profile_pic' => null,
                'existing_profile_pic_url' => null,
                'clear_profile_pic' => false,
            ],
        ])
        ->call('save')
        ->assertHasNoErrors();

    expect(TeamMember::query()->find($removed->id))->toBeNull()
        ->and(TeamMember::query()->find($kept->id))->not->toBeNull();
});

test('profile pictures are replaced in a single file media collection', function () {
    $teamMember = TeamMember::create([
        'first_name' => 'Existing',
        'last_name' => 'Member',
        'email' => 'existing@example.com',
    ]);
    $oldFile = UploadedFile::fake()->image('old-avatar.jpg');

    $teamMember->addMedia($oldFile->getRealPath())
        ->usingFileName('old-avatar.jpg')
        ->toMediaCollection('profile_pic');

    Livewire::test('settings.index')
        ->set('teamMembers.0.id', $teamMember->id)
        ->set('teamMembers.0.first_name', 'Existing')
        ->set('teamMembers.0.last_name', 'Member')
        ->set('teamMembers.0.email', 'existing@example.com')
        ->set('teamMembers.0.profile_pic', UploadedFile::fake()->image('new-avatar.jpg'))
        ->call('save')
        ->assertHasNoErrors();

    $teamMember->refresh();

    expect($teamMember->getMedia('profile_pic'))->toHaveCount(1)
        ->and($teamMember->getFirstMedia('profile_pic')->file_name)->toBe('new-avatar.jpg');
});

test('team member fields are validated', function () {
    Livewire::test('settings.index')
        ->set('teamMembers.0.first_name', '')
        ->set('teamMembers.0.last_name', '')
        ->set('teamMembers.0.email', 'not-an-email')
        ->call('save')
        ->assertHasErrors([
            'teamMembers.0.first_name' => 'required',
            'teamMembers.0.last_name' => 'required',
            'teamMembers.0.email' => 'email',
        ]);
});

test('team members can be saved without email', function () {
    Livewire::test('settings.index')
        ->set('teamMembers.0.first_name', 'NoEmail')
        ->set('teamMembers.0.last_name', 'Member')
        ->set('teamMembers.0.email', '')
        ->set('teamMembers.0.phone', '+31123456789')
        ->call('save')
        ->assertHasNoErrors();

    $teamMember = TeamMember::query()->where('first_name', 'NoEmail')->firstOrFail();

    expect($teamMember->email)->toBeNull();
});
