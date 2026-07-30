<?php

use App\Models\MoneybirdSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Config::set('app.key', 'base64:'.base64_encode(str_repeat('m', 32)));
    Config::set('services.zeker_gemak_moneybird', [
        'client_id' => 'zeker-client',
        'client_secret' => 'zeker-secret',
        'redirect_uri' => 'https://zeker-gemak.test/moneybird/callback',
        'api_url' => 'https://moneybird.test/api/v2',
        'authorize_url' => 'https://moneybird.test/oauth/authorize',
        'token_url' => 'https://moneybird.test/oauth/token',
        'dashboard_url' => 'https://moneybird.test',
        'connect_timeout' => 1,
        'timeout' => 2,
        'scopes' => ['sales_invoices', 'settings'],
    ]);
});

test('admins can connect a dedicated Zeker-Gemak Moneybird account', function () {
    $user = User::factory()->create();
    Role::findOrCreate('admin');
    $user->assignRole('admin');
    Http::preventStrayRequests();
    Http::fake([
        'https://moneybird.test/oauth/token' => Http::response([
            'access_token' => 'zeker-access-token',
            'refresh_token' => 'zeker-refresh-token',
            'expires_in' => 1200,
        ]),
        'https://moneybird.test/api/v2/administrations.json' => Http::response([
            ['id' => 'administration-1', 'name' => 'Zeker-Gemak BV'],
        ]),
    ]);

    $this->actingAs($user)
        ->withSession(['zeker_gemak_moneybird.oauth_state' => 'expected-state'])
        ->get(route('moneybird.callback', ['state' => 'expected-state', 'code' => 'oauth-code']))
        ->assertRedirect(route('moneybird.settings'));

    expect(MoneybirdSetting::resolved())
        ->connected->toBeTrue()
        ->access_token->toBe('zeker-access-token')
        ->refresh_token->toBe('zeker-refresh-token')
        ->administration_id->toBe('administration-1')
        ->and(DB::table('moneybird_settings')->value('configuration'))
        ->not->toContain('zeker-access-token');
});

test('admins can select the administration and required invoice defaults', function () {
    MoneybirdSetting::create([
        'configuration' => [
            ...MoneybirdSetting::resolved(),
            'connected' => true,
            'access_token' => 'access-token',
            'refresh_token' => 'refresh-token',
            'administration_id' => 'administration-1',
        ],
    ]);
    Http::preventStrayRequests();
    Http::fake([
        'https://moneybird.test/api/v2/administrations.json' => Http::response([
            ['id' => 'administration-1', 'name' => 'Zeker-Gemak BV'],
        ]),
        'https://moneybird.test/api/v2/administration-1/workflows.json' => Http::response([
            ['id' => 'workflow-1', 'name' => 'Standard'],
        ]),
        'https://moneybird.test/api/v2/administration-1/document_styles.json' => Http::response([
            ['id' => 'style-1', 'name' => 'Zeker-Gemak'],
        ]),
        'https://moneybird.test/api/v2/administration-1/ledger_accounts.json' => Http::response([
            ['id' => 'ledger-1', 'name' => 'Webshop'],
        ]),
    ]);

    Livewire::test('moneybird-settings.index')
        ->set('workflowId', 'workflow-1')
        ->set('documentStyleId', 'style-1')
        ->set('ledgerAccountId', 'ledger-1')
        ->set('autoSendInvoiceEmail', true)
        ->set('mollieInvoiceStatus', 'open')
        ->set('invoicePaymentStatus', 'draft')
        ->call('save')
        ->assertHasNoErrors()
        ->call('disconnect')
        ->assertSet('connected', false);

    expect(MoneybirdSetting::resolved())
        ->connected->toBeFalse()
        ->access_token->toBeNull()
        ->refresh_token->toBeNull()
        ->workflow_id->toBe('workflow-1')
        ->document_style_id->toBe('style-1')
        ->ledger_account_id->toBe('ledger-1')
        ->mollie_invoice_status->toBe('open')
        ->invoice_payment_status->toBe('draft');
});

test('non admins cannot open Moneybird settings', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('moneybird.settings'))
        ->assertForbidden();
});
