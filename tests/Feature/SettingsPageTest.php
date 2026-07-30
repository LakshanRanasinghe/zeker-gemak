<?php

use App\Models\DhlSetting;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
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

test('authenticated admins can visit each separate settings page in Dutch', function () {
    $user = User::factory()->create();
    Role::findOrCreate('admin');
    $user->assignRole('admin');

    $this->actingAs($user)
        ->withSession(['locale' => 'nl']);

    $this->get(route('settings.show', 'team'))
        ->assertOk()
        ->assertSeeLivewire('settings.index')
        ->assertSee('Teamleden')
        ->assertDontSee('<ui-tabs', false);

    $this->get(route('settings.show', 'popular-products'))
        ->assertOk()
        ->assertSeeLivewire('settings.index')
        ->assertSee('Populaire producten')
        ->assertDontSee('<ui-tabs', false);

    $this->get(route('settings.show', 'dhl'))
        ->assertOk()
        ->assertSeeLivewire('settings.index')
        ->assertSee('DHL-account')
        ->assertDontSee('<ui-tabs', false);
});

test('the old settings URL redirects to the team page', function () {
    $user = User::factory()->create();
    Role::findOrCreate('admin');
    $user->assignRole('admin');

    $this->actingAs($user)
        ->get(route('settings.index'))
        ->assertRedirect(route('settings.show', 'team'));
});

test('DHL account and sender settings are saved encrypted', function () {
    Livewire::test('settings.index')
        ->set('tab', 'dhl')
        ->set('dhl.user_id', 'dhl-user')
        ->set('dhl.api_key', 'secret-dhl-key')
        ->set('dhl.account_id', '12345678')
        ->set('dhl.sender.company', 'Zeker Gemak')
        ->set('dhl.sender.first_name', 'Zeker')
        ->set('dhl.sender.last_name', 'Gemak')
        ->set('dhl.sender.street', 'Afzenderstraat')
        ->set('dhl.sender.house_number', '10')
        ->set('dhl.sender.postal_code', '1234 AB')
        ->set('dhl.sender.city', 'Amsterdam')
        ->set('dhl.sender.country_code', 'NL')
        ->set('dhl.sender.email', 'shipping@zeker-gemak.test')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('dhl.api_key', '')
        ->assertSet('hasDhlApiKey', true);

    $setting = DhlSetting::query()->firstOrFail();
    $stored = DB::table('dhl_settings')->value('configuration');

    expect(DhlSetting::resolved())
        ->key->toBe('secret-dhl-key')
        ->account_id->toBe('12345678')
        ->sender->company->toBe('Zeker Gemak')
        ->and($stored)->not->toContain('secret-dhl-key')
        ->and($setting->toArray())->not->toHaveKey('configuration');
});

test('leaving the DHL API key blank preserves the saved key', function () {
    DhlSetting::create([
        'configuration' => [
            'key' => 'existing-secret',
            'user_id' => 'old-user',
        ],
    ]);

    Livewire::test('settings.index')
        ->set('tab', 'dhl')
        ->set('dhl.user_id', 'new-user')
        ->set('dhl.account_id', '12345678')
        ->set('dhl.sender.company', 'Zeker Gemak')
        ->set('dhl.sender.street', 'Afzenderstraat')
        ->set('dhl.sender.house_number', '10')
        ->set('dhl.sender.postal_code', '1234 AB')
        ->set('dhl.sender.city', 'Amsterdam')
        ->set('dhl.sender.country_code', 'NL')
        ->set('dhl.sender.email', 'shipping@zeker-gemak.test')
        ->set('dhl.api_key', '')
        ->call('save')
        ->assertHasNoErrors();

    expect(DhlSetting::resolved())
        ->key->toBe('existing-secret')
        ->user_id->toBe('new-user');
});

test('invalid DHL settings are not saved', function () {
    Livewire::test('settings.index')
        ->set('tab', 'dhl')
        ->set('dhl.user_id', '')
        ->set('dhl.api_key', '')
        ->set('dhl.sender.email', 'not-an-email')
        ->call('save')
        ->assertHasErrors([
            'dhl.user_id' => 'required',
            'dhl.api_key' => 'required',
            'dhl.sender.email' => 'email',
        ]);

    expect(DhlSetting::query()->exists())->toBeFalse();
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
        ->assertSee('Teamlid 1')
        ->call('addTeamMember')
        ->assertCount('teamMembers', 2)
        ->assertSee('Teamlid 2');
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
