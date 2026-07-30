<?php

use App\Models\MoneybirdSetting;
use App\Services\Moneybird\MoneybirdClient;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Livewire\Component;

new class extends Component
{
    public bool $connected = false;

    public bool $autoSendInvoiceEmail = false;

    public ?string $administrationId = null;

    public ?string $workflowId = null;

    public ?string $documentStyleId = null;

    public ?string $ledgerAccountId = null;

    public ?string $mollieInvoiceStatus = null;

    public ?string $invoicePaymentStatus = null;

    public array $administrations = [];

    public array $workflows = [];

    public array $documentStyles = [];

    public array $ledgerAccounts = [];

    public ?string $connectionError = null;

    public function mount(MoneybirdClient $client): void
    {
        $configuration = MoneybirdSetting::resolved();
        $this->connected = (bool) $configuration['connected'];
        $this->autoSendInvoiceEmail = (bool) $configuration['auto_send_invoice_email'];
        $this->administrationId = $configuration['administration_id'];
        $this->workflowId = $configuration['workflow_id'];
        $this->documentStyleId = $configuration['document_style_id'];
        $this->ledgerAccountId = $configuration['ledger_account_id'];
        $this->mollieInvoiceStatus = $configuration['mollie_invoice_status'];
        $this->invoicePaymentStatus = $configuration['invoice_payment_status'];

        if ($this->connected) {
            $this->loadOptions($client);
        }
    }

    public function updatedAdministrationId(MoneybirdClient $client): void
    {
        $this->workflows = [];
        $this->documentStyles = [];
        $this->ledgerAccounts = [];

        if (filled($this->administrationId)) {
            $this->persistAdministration();
            $this->loadInvoiceOptions($client);
        }
    }

    public function save(): void
    {
        $validated = $this->validate([
            'administrationId' => [
                Rule::requiredIf($this->connected),
                'nullable',
                'string',
                Rule::in(collect($this->administrations)->pluck('id')->all()),
            ],
            'workflowId' => [
                'nullable',
                'string',
                'max:255',
                Rule::in(collect($this->workflows)->pluck('id')->all()),
            ],
            'documentStyleId' => [
                'nullable',
                'string',
                'max:255',
                Rule::in(collect($this->documentStyles)->pluck('id')->all()),
            ],
            'ledgerAccountId' => [
                'nullable',
                'string',
                'max:255',
                Rule::in(collect($this->ledgerAccounts)->pluck('id')->all()),
            ],
            'autoSendInvoiceEmail' => ['boolean'],
            'mollieInvoiceStatus' => ['nullable', Rule::in(['draft', 'open'])],
            'invoicePaymentStatus' => ['nullable', Rule::in(['draft', 'open'])],
        ]);
        $setting = MoneybirdSetting::current();
        $setting->configuration = array_replace(MoneybirdSetting::resolved(), [
            'administration_id' => $validated['administrationId'],
            'workflow_id' => $validated['workflowId'] ?: null,
            'document_style_id' => $validated['documentStyleId'] ?: null,
            'ledger_account_id' => $validated['ledgerAccountId'] ?: null,
            'auto_send_invoice_email' => $validated['autoSendInvoiceEmail'],
            'mollie_invoice_status' => $validated['mollieInvoiceStatus'] ?: null,
            'invoice_payment_status' => $validated['invoicePaymentStatus'] ?: null,
        ]);
        $setting->save();

        Flux::toast(__('Moneybird settings saved.'), variant: 'success');
    }

    public function disconnect(): void
    {
        $setting = MoneybirdSetting::current();
        $setting->configuration = array_replace(MoneybirdSetting::resolved(), [
            'connected' => false,
            'access_token' => null,
            'refresh_token' => null,
            'expires_at' => null,
            'administration_id' => null,
        ]);
        $setting->save();
        $this->connected = false;
        $this->administrationId = null;
        $this->administrations = [];
        $this->workflows = [];
        $this->documentStyles = [];
        $this->ledgerAccounts = [];

        Flux::toast(__('Moneybird disconnected.'), variant: 'success');
    }

    private function persistAdministration(): void
    {
        $setting = MoneybirdSetting::current();
        $setting->configuration = array_replace(MoneybirdSetting::resolved(), [
            'administration_id' => $this->administrationId,
        ]);
        $setting->save();
    }

    private function loadOptions(MoneybirdClient $client): void
    {
        try {
            $this->administrations = $client->administrations();

            if (filled($this->administrationId)) {
                $this->loadInvoiceOptions($client);
            }
        } catch (Throwable $exception) {
            report($exception);
            $this->connectionError = __('Could not load Moneybird settings.');
        }
    }

    private function loadInvoiceOptions(MoneybirdClient $client): void
    {
        try {
            $this->workflows = $client->workflows();
            $this->documentStyles = $client->documentStyles();
            $this->ledgerAccounts = $client->ledgerAccounts();
            $this->connectionError = null;
        } catch (Throwable $exception) {
            report($exception);
            $this->connectionError = __('Could not load Moneybird invoice settings.');
        }
    }
};
?>

<div class="space-y-6">
    <div>
        <flux:heading size="xl" level="1">{{ __('Moneybird') }}</flux:heading>
        <flux:subheading>{{ __('Manage the Zeker-Gemak Moneybird connection and invoice defaults.') }}</flux:subheading>
    </div>

    @if (session('success'))
        <flux:callout variant="success" icon="check-circle" heading="{{ session('success') }}" />
    @endif

    @if (session('error') || $connectionError)
        <flux:callout variant="danger" icon="exclamation-triangle"
            heading="{{ session('error') ?: $connectionError }}" />
    @endif

    <flux:card class="space-y-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <flux:heading size="lg">{{ __('Moneybird account') }}</flux:heading>
                <flux:text class="mt-1">
                    {{ $connected ? __('Connected to Moneybird for Zeker-Gemak.') : __('Connect a dedicated Moneybird account for Zeker-Gemak.') }}
                </flux:text>
            </div>

            <flux:badge :color="$connected ? 'green' : 'zinc'">
                {{ $connected ? __('Connected') : __('Disconnected') }}
            </flux:badge>
        </div>

        <div class="flex flex-wrap gap-3">
            <flux:button variant="primary" icon="link" :href="route('moneybird.connect')">
                {{ $connected ? __('Reconnect') : __('Connect Moneybird') }}
            </flux:button>

            @if ($connected)
                <flux:button variant="danger" icon="x-mark" wire:click="disconnect"
                    wire:confirm="{{ __('Are you sure you want to disconnect Moneybird?') }}">
                    {{ __('Disconnect') }}
                </flux:button>
            @endif
        </div>
    </flux:card>

    <flux:card class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('Invoice configuration') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Defaults applied to Zeker-Gemak invoices created from orders.') }}</flux:text>
        </div>

        <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
            <flux:field>
                <flux:label>{{ __('Administration') }}</flux:label>
                <flux:select wire:model.live="administrationId" :disabled="!$connected">
                    <flux:select.option value="">{{ __('Select an administration') }}</flux:select.option>
                    @foreach ($administrations as $administration)
                        <flux:select.option :value="$administration['id']" wire:key="administration-{{ $administration['id'] }}">
                            {{ $administration['name'] }}
                        </flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="administrationId" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Workflow') }}</flux:label>
                <flux:select wire:model="workflowId" :disabled="!$administrationId">
                    <flux:select.option value="">{{ __('Default workflow') }}</flux:select.option>
                    @foreach ($workflows as $workflow)
                        <flux:select.option :value="$workflow['id']" wire:key="workflow-{{ $workflow['id'] }}">
                            {{ $workflow['name'] }}
                        </flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="workflowId" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Invoice style') }}</flux:label>
                <flux:select wire:model="documentStyleId" :disabled="!$administrationId">
                    <flux:select.option value="">{{ __('Default invoice style') }}</flux:select.option>
                    @foreach ($documentStyles as $documentStyle)
                        <flux:select.option :value="$documentStyle['id']" wire:key="document-style-{{ $documentStyle['id'] }}">
                            {{ $documentStyle['name'] }}
                        </flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="documentStyleId" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Ledger account') }}</flux:label>
                <flux:select wire:model="ledgerAccountId" :disabled="!$administrationId">
                    <flux:select.option value="">{{ __('Default ledger account') }}</flux:select.option>
                    @foreach ($ledgerAccounts as $ledgerAccount)
                        <flux:select.option :value="$ledgerAccount['id']" wire:key="ledger-account-{{ $ledgerAccount['id'] }}">
                            {{ $ledgerAccount['name'] }}
                        </flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="ledgerAccountId" />
            </flux:field>
        </div>

        <flux:switch wire:model="autoSendInvoiceEmail"
            label="{{ __('Email invoices automatically after creation') }}" />

        <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
            <flux:field>
                <flux:label>{{ __('Mollie payment invoice status') }}</flux:label>
                <flux:select wire:model="mollieInvoiceStatus" :disabled="!$connected">
                    <flux:select.option value="">{{ __('Use automatic email setting') }}</flux:select.option>
                    <flux:select.option value="draft">{{ __('Draft') }}</flux:select.option>
                    <flux:select.option value="open">{{ __('Open and send') }}</flux:select.option>
                </flux:select>
                <flux:error name="mollieInvoiceStatus" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Invoice payment invoice status') }}</flux:label>
                <flux:select wire:model="invoicePaymentStatus" :disabled="!$connected">
                    <flux:select.option value="">{{ __('Use automatic email setting') }}</flux:select.option>
                    <flux:select.option value="draft">{{ __('Draft') }}</flux:select.option>
                    <flux:select.option value="open">{{ __('Open and send') }}</flux:select.option>
                </flux:select>
                <flux:error name="invoicePaymentStatus" />
            </flux:field>
        </div>

        <div class="flex justify-end">
            <flux:button variant="primary" icon="check" wire:click="save" wire:loading.attr="disabled"
                :disabled="!$connected">
                {{ __('Save Moneybird settings') }}
            </flux:button>
        </div>
    </flux:card>
</div>
