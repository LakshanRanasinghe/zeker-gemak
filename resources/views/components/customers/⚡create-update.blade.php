<?php

use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Konekt\Address\Models\Address;
use Livewire\Component;

new class extends Component {
    public ?int $customerId = null;

    public $name = '';
    public $email = '';
    public $password = '';
    public $password_confirmation = '';
    public $phone = '';
    public $is_active = true;
    public $type = 'client';
    public $debitor_no = '';

    // Read-only display fields
    public $last_login_at = null;
    public $login_count = 0;
    public $email_verified_at = null;
    public $created_at = null;
    public $updated_at = null;

    // Addresses
    public $addresses = [];
    public $showAddressForm = false;
    public $editingAddressId = null;

    // Address form fields
    public $addr_type = 'shipping';
    public $addr_name = '';
    public $addr_firstname = '';
    public $addr_lastname = '';
    public $addr_company_name = '';
    public $addr_address = '';
    public $addr_address2 = '';
    public $addr_city = '';
    public $addr_postalcode = '';
    public $addr_country_id = 'NL';
    public $addr_phone = '';
    public $addr_email = '';
    public $addr_tax_nr = '';
    public $addr_registration_nr = '';

    public function mount($id = null)
    {
        if ($id) {
            $user = User::findOrFail($id);
            $this->customerId = $user->id;
            $this->name = $user->name;
            $this->email = $user->email;
            $this->phone = $user->phone ?? '';
            $this->is_active = (bool) $user->is_active;
            $this->type = $user->type ?? 'client';
            $this->debitor_no = $user->debitor_no ?? '';

            $this->last_login_at = $user->last_login_at?->diffForHumans();
            $this->login_count = $user->login_count ?? 0;
            $this->email_verified_at = $user->email_verified_at?->diffForHumans();
            $this->created_at = $user->created_at?->diffForHumans();
            $this->updated_at = $user->updated_at?->diffForHumans();

            $this->loadAddresses();
        }
    }

    protected function loadAddresses()
    {
        if (!$this->customerId) {
            return;
        }

        $this->addresses = User::findOrFail($this->customerId)
            ->addresses()
            ->latest()
            ->get()
            ->map(
                fn($a) => [
                    'id' => $a->id,
                    'type' => (string) $a->type->value(),
                    'name' => $a->name,
                    'firstname' => $a->firstname,
                    'lastname' => $a->lastname,
                    'company_name' => $a->company_name,
                    'address' => $a->address,
                    'address2' => $a->address2,
                    'city' => $a->city,
                    'postalcode' => $a->postalcode,
                    'country_id' => $a->country_id,
                    'country_name' => $a->country_id ? \Illuminate\Support\Facades\DB::table('countries')->where('id', $a->country_id)->value('name') : null,
                    'phone' => $a->phone,
                    'email' => $a->email,
                    'tax_nr' => $a->tax_nr,
                    'registration_nr' => $a->registration_nr,
                ],
            )
            ->toArray();
    }

    public function openAddressForm()
    {
        if (!$this->customerId) {
            Flux::toast(__('Please save the customer first before adding addresses.'), variant: 'warning');
            return;
        }

        $this->resetAddressForm();
        $this->showAddressForm = true;
        $this->editingAddressId = null;
    }

    public function editAddress($addressId)
    {
        $address = collect($this->addresses)->firstWhere('id', $addressId);
        if (!$address) {
            return;
        }

        $this->editingAddressId = $addressId;
        $this->showAddressForm = true;
        $this->addr_type = $address['type'] ?? 'shipping';
        $this->addr_name = $address['name'] ?? '';
        $this->addr_firstname = $address['firstname'] ?? '';
        $this->addr_lastname = $address['lastname'] ?? '';
        $this->addr_company_name = $address['company_name'] ?? '';
        $this->addr_address = $address['address'] ?? '';
        $this->addr_address2 = $address['address2'] ?? '';
        $this->addr_city = $address['city'] ?? '';
        $this->addr_postalcode = $address['postalcode'] ?? '';
        $this->addr_country_id = $address['country_id'] ?? 'NL';
        $this->addr_phone = $address['phone'] ?? '';
        $this->addr_email = $address['email'] ?? '';
        $this->addr_tax_nr = $address['tax_nr'] ?? '';
        $this->addr_registration_nr = $address['registration_nr'] ?? '';
    }

    public function cancelAddressForm()
    {
        $this->showAddressForm = false;
        $this->editingAddressId = null;
        $this->resetAddressForm();
    }

    protected function resetAddressForm()
    {
        $this->addr_type = 'shipping';
        $this->addr_name = '';
        $this->addr_firstname = '';
        $this->addr_lastname = '';
        $this->addr_company_name = '';
        $this->addr_address = '';
        $this->addr_address2 = '';
        $this->addr_city = '';
        $this->addr_postalcode = '';
        $this->addr_country_id = 'NL';
        $this->addr_phone = '';
        $this->addr_email = '';
        $this->addr_tax_nr = '';
        $this->addr_registration_nr = '';
    }

    protected function addressRules(): array
    {
        return [
            'addr_type' => 'required|in:shipping,billing',
            'addr_name' => 'nullable|string|max:255',
            'addr_firstname' => 'nullable|string|max:255',
            'addr_lastname' => 'nullable|string|max:255',
            'addr_company_name' => 'nullable|string|max:255',
            'addr_address' => 'required|string|max:255',
            'addr_address2' => 'nullable|string|max:255',
            'addr_city' => 'required|string|max:255',
            'addr_postalcode' => 'nullable|string|max:12',
            'addr_country_id' => 'required|exists:countries,id',
            'addr_phone' => 'nullable|string|max:50',
            'addr_email' => 'nullable|email|max:255',
            'addr_tax_nr' => 'nullable|string|max:17',
            'addr_registration_nr' => 'nullable|string|max:50',
        ];
    }

    protected function addressMessages(): array
    {
        return [
            'addr_type.required' => __('Address type is required.'),
            'addr_type.in' => __('Address type must be shipping or billing.'),
            'addr_name.max' => __('Name cannot exceed 255 characters.'),
            'addr_firstname.max' => __('First name cannot exceed 255 characters.'),
            'addr_lastname.max' => __('Last name cannot exceed 255 characters.'),
            'addr_company_name.max' => __('Company name cannot exceed 255 characters.'),
            'addr_address.required' => __('Street address is required.'),
            'addr_address.max' => __('Street address cannot exceed 255 characters.'),
            'addr_address2.max' => __('Address line 2 cannot exceed 255 characters.'),
            'addr_city.required' => __('City is required.'),
            'addr_city.max' => __('City cannot exceed 255 characters.'),
            'addr_postalcode.max' => __('Postal code cannot exceed 12 characters.'),
            'addr_country_id.required' => __('Country is required.'),
            'addr_country_id.exists' => __('Please select a valid country.'),
            'addr_phone.max' => __('Phone number cannot exceed 50 characters.'),
            'addr_email.email' => __('Please enter a valid email address.'),
            'addr_email.max' => __('Email cannot exceed 255 characters.'),
            'addr_tax_nr.max' => __('VAT number cannot exceed 17 characters.'),
            'addr_registration_nr.max' => __('KVK number cannot exceed 50 characters.'),
        ];
    }

    public function saveAddress()
    {
        $this->resetErrorBag();

        $validated = $this->validate($this->addressRules(), $this->addressMessages());

        $addressData = [
            'type' => $validated['addr_type'],
            'name' => $validated['addr_name'],
            'firstname' => $validated['addr_firstname'],
            'lastname' => $validated['addr_lastname'],
            'company_name' => $validated['addr_company_name'],
            'address' => $validated['addr_address'],
            'address2' => $validated['addr_address2'],
            'city' => $validated['addr_city'],
            'postalcode' => $validated['addr_postalcode'],
            'country_id' => $validated['addr_country_id'],
            'phone' => $validated['addr_phone'],
            'email' => $validated['addr_email'],
            'tax_nr' => $validated['addr_tax_nr'],
            'registration_nr' => $validated['addr_registration_nr'],
        ];

        $customer = User::findOrFail($this->customerId);

        if ($this->editingAddressId) {
            $address = $customer->addresses()->findOrFail($this->editingAddressId);
            $address->update($addressData);
            Flux::toast(__('Address updated successfully.'), variant: 'success');
        } else {
            if ($addressData['type'] === 'billing' && $customer->addresses()->where('type', 'billing')->exists()) {
                $this->addError('addr_type', __('Customer already has a billing address. Please edit the existing one.'));
                return;
            }
            $customer->addresses()->create($addressData);
            Flux::toast(__('Address added successfully.'), variant: 'success');
        }

        $this->showAddressForm = false;
        $this->editingAddressId = null;
        $this->resetAddressForm();
        $this->loadAddresses();
    }

    public function deleteAddress($addressId)
    {
        $customer = User::findOrFail($this->customerId);
        $customer->addresses()->findOrFail($addressId)->delete();

        Flux::toast(__('Address deleted successfully.'), variant: 'success');
        $this->loadAddresses();
    }

    protected function messages(): array
    {
        return [
            'name.required' => __('Name is required.'),
            'name.max' => __('Name cannot exceed 255 characters.'),
            'email.required' => __('Email is required.'),
            'email.email' => __('Please enter a valid email address.'),
            'email.unique' => __('This email is already taken.'),
            'password.required' => __('Password is required.'),
            'password.min' => __('Password must be at least 8 characters.'),
            'password.confirmed' => __('Password confirmation does not match.'),
            'phone.max' => __('Phone number cannot exceed 50 characters.'),
            'type.in' => __('Type must be client or admin.'),
            'debitor_no.max' => __('Debitor number cannot exceed 255 characters.'),
        ];
    }

    public function save()
    {
        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email' . ($this->customerId ? ',' . $this->customerId : ''),
            'phone' => 'nullable|string|max:50',
            'is_active' => 'boolean',
            'type' => 'nullable|string|in:client,admin',
            'debitor_no' => 'nullable|string|max:255',
        ];

        if (!$this->customerId) {
            $rules['password'] = 'required|string|min:8|confirmed';
        } else {
            $rules['password'] = 'nullable|string|min:8|confirmed';
        }

        $this->resetErrorBag();

        $validator = Validator::make($this->all(), $rules, $this->messages());

        if ($validator->fails()) {
            foreach ($validator->errors()->messages() as $field => $msgs) {
                $this->addError($field, $msgs[0]);
            }
        }

        if ($this->getErrorBag()->isNotEmpty()) {
            $this->dispatch('scroll-to-error');
            return;
        }

        $validated = $this->validate($rules);

        $data = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'is_active' => $validated['is_active'],
            'type' => $validated['type'] ?? 'client',
            'debitor_no' => $validated['debitor_no'] ?? null,
        ];

        if (!empty($validated['password'])) {
            $data['password'] = Hash::make($validated['password']);
        }

        if (!$this->customerId) {
            User::create($data);

            Flux::toast(__('Customer created successfully.'), variant: 'success');

            $this->redirect(route('customers.index'), navigate: true);
        } else {
            User::findOrFail($this->customerId)->update($data);

            Flux::toast(__('Customer updated successfully.'), variant: 'success');
        }
    }
};
?>

<div>
    <x-scroll-to-error />
    <form wire:submit="save" class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="xl">{{ $customerId ? __('Edit Customer') : __('Create Customer') }}
                </flux:heading>
                <flux:subheading>{{ $customerId ? __('Update customer details.') : __('Add a new customer.') }}
                </flux:subheading>
            </div>
            <div class="flex space-x-2">
                <flux:button href="{{ route('customers.index') }}" wire:navigate>{{ __('Cancel') }}</flux:button>
                <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 md:items-start">
            <div class="md:col-span-2 space-y-6">
                <!-- Basic Information -->
                <flux:card class="space-y-6">
                    <div>
                        <flux:heading size="lg">{{ __('Basic Information') }}</flux:heading>
                        <flux:subheading>{{ __('Customer name, email, and contact details.') }}</flux:subheading>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 items-start">
                        <flux:input wire:model="name" label="{{ __('Name') }}"
                            placeholder="{{ __('Enter customer name') }}" />
                        <flux:input wire:model="email" type="email" label="{{ __('Email') }}"
                            placeholder="{{ __('Enter email address') }}" />
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 items-start">
                        <flux:input wire:model="phone" label="{{ __('Phone') }}"
                            placeholder="{{ __('Enter phone number') }}" />
                        <flux:input wire:model="debitor_no" label="{{ __('Debitor No') }}"
                            placeholder="{{ __('Enter debitor number') }}" />
                    </div>
                </flux:card>

                <!-- Password -->
                <flux:card class="space-y-6">
                    <div>
                        <flux:heading size="lg">{{ __('Password') }}</flux:heading>
                        <flux:subheading>
                            {{ $customerId ? __('Leave blank to keep current password.') : __('Set the customer password.') }}
                        </flux:subheading>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 items-start">
                        <div>
                            <flux:input wire:model="password" type="password" label="{{ __('Password') }}"
                                placeholder="{{ __('Enter password') }}" />
                        </div>
                        <div>
                            <flux:input wire:model="password_confirmation" type="password"
                                label="{{ __('Confirm Password') }}" placeholder="{{ __('Confirm password') }}" />
                        </div>
                    </div>
                </flux:card>

                <!-- Addresses -->
                <flux:card class="space-y-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <flux:heading size="lg">{{ __('Addresses') }}</flux:heading>
                            <flux:subheading>{{ __('Manage shipping and billing addresses.') }}</flux:subheading>
                        </div>
                        @if (!$showAddressForm)
                            <flux:button size="sm" icon="plus" wire:click="openAddressForm">
                                {{ __('Add Address') }}
                            </flux:button>
                        @endif
                    </div>

                    <!-- Address Form (Add / Edit) -->
                    @if ($showAddressForm)
                        <div
                            class="border border-zinc-200 dark:border-zinc-700 rounded-lg p-4 space-y-4 bg-zinc-50 dark:bg-zinc-800/50">
                            <div class="flex items-center justify-between">
                                <flux:heading size="base">
                                    {{ $editingAddressId ? __('Edit Address') : __('New Address') }}
                                </flux:heading>
                                <flux:button size="sm" variant="ghost" icon="x-mark"
                                    wire:click="cancelAddressForm" />
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 items-start">
                                <div>
                                    <flux:select wire:model.live="addr_type" label="{{ __('Type') }}">
                                        <option value="shipping">{{ __('Shipping') }}</option>
                                        <option value="billing">{{ __('Billing') }}</option>
                                    </flux:select>
                                </div>
                                <div>
                                    <flux:input wire:model="addr_company_name" label="{{ __('Company') }}"
                                        placeholder="{{ __('Company name') }}" />
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-start">
                                <div>
                                    <flux:input wire:model="addr_name" label="{{ __('Name') }}"
                                        placeholder="{{ __('Full name') }}" />
                                </div>
                                <div>
                                    <flux:input wire:model="addr_firstname" label="{{ __('First Name') }}"
                                        placeholder="{{ __('First name') }}" />
                                </div>
                                <div>
                                    <flux:input wire:model="addr_lastname" label="{{ __('Last Name') }}"
                                        placeholder="{{ __('Last name') }}" />
                                </div>
                            </div>

                            <flux:input wire:model="addr_address" label="{{ __('Address') }}"
                                placeholder="{{ __('Street address') }}" />

                            <flux:input wire:model="addr_address2" label="{{ __('Address Line 2') }}"
                                placeholder="{{ __('Apartment, suite, etc.') }}" />

                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-start">
                                <div>
                                    <flux:input wire:model="addr_city" label="{{ __('City') }}"
                                        placeholder="{{ __('City') }}" />
                                </div>
                                <div>
                                    <flux:input wire:model="addr_postalcode" label="{{ __('Postal Code') }}"
                                        placeholder="{{ __('Postal code') }}" maxlength="12" />
                                </div>
                                <div>
                                    <flux:select wire:model="addr_country_id" label="{{ __('Country') }}" searchable
                                        placeholder="{{ __('Select country') }}">
                                        @foreach (\Illuminate\Support\Facades\DB::table('countries')->orderBy('name')->get() as $country)
                                            <flux:select.option value="{{ $country->id }}">{{ $country->name }}
                                            </flux:select.option>
                                        @endforeach
                                    </flux:select>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 items-start">
                                <div>
                                    <flux:input wire:model="addr_phone" label="{{ __('Phone') }}"
                                        placeholder="{{ __('Phone number') }}" />
                                </div>
                                <div>
                                    <flux:input wire:model="addr_email" type="email" label="{{ __('Email') }}"
                                        placeholder="{{ __('Email address') }}" />
                                </div>
                            </div>

                            @if ($addr_type === 'billing')
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 items-start">
                                    <div>
                                        <flux:input wire:model="addr_tax_nr" label="{{ __('VAT Number') }}"
                                            placeholder="{{ __('e.g. NL123456789B01') }}" maxlength="17" />
                                    </div>
                                    <div>
                                        <flux:input wire:model="addr_registration_nr" label="{{ __('KVK Number') }}"
                                            placeholder="{{ __('e.g. 12345678') }}" maxlength="50" />
                                    </div>
                                </div>
                            @endif

                            <div class="flex justify-end gap-2 pt-2">
                                <flux:button size="sm" wire:click="cancelAddressForm">{{ __('Cancel') }}
                                </flux:button>
                                <flux:button size="sm" variant="primary" wire:click="saveAddress">
                                    {{ $editingAddressId ? __('Update Address') : __('Add Address') }}
                                </flux:button>
                            </div>
                        </div>
                    @endif

                    <!-- Address List -->
                    @if (count($addresses) > 0)
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            @foreach ($addresses as $addr)
                                <div class="relative group border border-zinc-200 dark:border-zinc-700 rounded-xl bg-white dark:bg-zinc-800/60 overflow-hidden transition-shadow hover:shadow-md"
                                    wire:key="address-{{ $addr['id'] }}">
                                    <!-- Type ribbon -->
                                    <div
                                        class="px-4 py-2.5 border-b border-zinc-100 dark:border-zinc-700/60 flex items-center justify-between {{ $addr['type'] === 'billing' ? 'bg-blue-50 dark:bg-blue-900/10' : 'bg-green-50 dark:bg-green-900/10' }}">
                                        <div class="flex items-center gap-2">
                                            <span
                                                class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-xs font-semibold {{ $addr['type'] === 'billing' ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300' : 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300' }}">
                                                @if ($addr['type'] === 'billing')
                                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24"
                                                        stroke-width="2" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z" />
                                                    </svg>
                                                @else
                                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24"
                                                        stroke-width="2" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12" />
                                                    </svg>
                                                @endif
                                                {{ ucfirst($addr['type']) }}
                                            </span>
                                            @if ($addr['company_name'])
                                                <span
                                                    class="text-xs font-medium text-zinc-600 dark:text-zinc-300 truncate">{{ $addr['company_name'] }}</span>
                                            @endif
                                        </div>
                                        <div class="flex gap-0.5">
                                            <flux:button size="xs" variant="ghost" icon="pencil-square"
                                                wire:click="editAddress({{ $addr['id'] }})"
                                                class="opacity-60 hover:opacity-100" />
                                            <flux:button size="xs" variant="ghost" icon="trash"
                                                wire:click="deleteAddress({{ $addr['id'] }})"
                                                wire:confirm="{{ __('Are you sure you want to delete this address?') }}"
                                                class="opacity-60 hover:opacity-100 text-red-500 hover:text-red-600 dark:text-red-400 dark:hover:text-red-300" />
                                        </div>
                                    </div>

                                    <!-- Address body -->
                                    <div class="px-4 py-3 space-y-2">
                                        @if ($addr['firstname'] || $addr['lastname'] || $addr['name'])
                                            <p class="text-sm font-medium text-zinc-800 dark:text-zinc-100">
                                                @if ($addr['firstname'] || $addr['lastname'])
                                                    {{ trim(($addr['firstname'] ?? '') . ' ' . ($addr['lastname'] ?? '')) }}
                                                @else
                                                    {{ $addr['name'] }}
                                                @endif
                                            </p>
                                        @endif

                                        <div class="text-sm text-zinc-500 dark:text-zinc-400 leading-relaxed">
                                            <p>{{ $addr['address'] }}</p>
                                            @if ($addr['address2'])
                                                <p>{{ $addr['address2'] }}</p>
                                            @endif
                                            <p>{{ collect([$addr['postalcode'], $addr['city']])->filter()->join(' ') }}{{ $addr['country_name'] ? ', ' . $addr['country_name'] : '' }}
                                            </p>
                                        </div>

                                        @if ($addr['type'] === 'billing' && ($addr['tax_nr'] || $addr['registration_nr']))
                                            <div
                                                class="flex flex-wrap items-center gap-x-4 gap-y-1 pt-1 border-t border-zinc-100 dark:border-zinc-700/50 mt-2">
                                                @if ($addr['tax_nr'])
                                                    <span class="text-xs text-zinc-500 dark:text-zinc-400">
                                                        <span class="font-medium">{{ __('VAT') }}:</span> {{ $addr['tax_nr'] }}
                                                    </span>
                                                @endif
                                                @if ($addr['registration_nr'])
                                                    <span class="text-xs text-zinc-500 dark:text-zinc-400">
                                                        <span class="font-medium">{{ __('KVK') }}:</span> {{ $addr['registration_nr'] }}
                                                    </span>
                                                @endif
                                            </div>
                                        @endif

                                        @if ($addr['phone'] || $addr['email'])
                                            <div
                                                class="flex flex-wrap items-center gap-x-3 gap-y-1 pt-1 border-t border-zinc-100 dark:border-zinc-700/50 mt-2">
                                                @if ($addr['phone'])
                                                    <span
                                                        class="inline-flex items-center gap-1 text-xs text-zinc-400 dark:text-zinc-500">
                                                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24"
                                                            stroke-width="2" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 01-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z" />
                                                        </svg>
                                                        {{ $addr['phone'] }}
                                                    </span>
                                                @endif
                                                @if ($addr['email'])
                                                    <span
                                                        class="inline-flex items-center gap-1 text-xs text-zinc-400 dark:text-zinc-500">
                                                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24"
                                                            stroke-width="2" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
                                                        </svg>
                                                        {{ $addr['email'] }}
                                                    </span>
                                                @endif
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @elseif(!$showAddressForm)
                        <div class="text-center py-8">
                            <div
                                class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-zinc-100 dark:bg-zinc-800 mb-3">
                                <svg class="w-6 h-6 text-zinc-400 dark:text-zinc-500" fill="none"
                                    viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" />
                                </svg>
                            </div>
                            <flux:text class="text-zinc-400 dark:text-zinc-500">{{ __('No addresses yet.') }}
                            </flux:text>
                            <p class="text-xs text-zinc-400 dark:text-zinc-600 mt-1">
                                {{ __('Add a shipping or billing address for this customer.') }}</p>
                        </div>
                    @endif
                </flux:card>
            </div>

            <div class="space-y-6 sticky top-6">
                <!-- Status & Type -->
                <flux:card class="space-y-6">
                    <div>
                        <flux:heading size="lg">{{ __('Status & Type') }}</flux:heading>
                    </div>

                    <flux:switch wire:model="is_active" label="{{ __('Active') }}"
                        description="{{ __('Enable or disable this customer account.') }}" />

                    <flux:select wire:model="type" label="{{ __('Type') }}">
                        <option value="client">{{ __('Client') }}</option>
                        <option value="admin">{{ __('Admin') }}</option>
                    </flux:select>
                </flux:card>

                @if ($customerId)
                    <flux:card class="space-y-4">
                        <div>
                            <flux:heading size="lg">{{ __('Account Info') }}</flux:heading>
                        </div>

                        <div class="space-y-3 text-sm">
                            <div class="flex justify-between">
                                <flux:text class="text-zinc-500 dark:text-zinc-400">{{ __('Last Login') }}</flux:text>
                                <flux:text>{{ $last_login_at ?? __('Never') }}</flux:text>
                            </div>
                            <flux:separator />
                            <div class="flex justify-between">
                                <flux:text class="text-zinc-500 dark:text-zinc-400">{{ __('Login Count') }}
                                </flux:text>
                                <flux:text>{{ $login_count }}</flux:text>
                            </div>
                            <flux:separator />
                            <div class="flex justify-between">
                                <flux:text class="text-zinc-500 dark:text-zinc-400">{{ __('Email Verified') }}
                                </flux:text>
                                <flux:text>{{ $email_verified_at ?? __('Not verified') }}</flux:text>
                            </div>
                            <flux:separator />
                            <div class="flex justify-between">
                                <flux:text class="text-zinc-500 dark:text-zinc-400">{{ __('Created') }}</flux:text>
                                <flux:text>{{ $created_at }}</flux:text>
                            </div>
                            <flux:separator />
                            <div class="flex justify-between">
                                <flux:text class="text-zinc-500 dark:text-zinc-400">{{ __('Updated') }}</flux:text>
                                <flux:text>{{ $updated_at }}</flux:text>
                            </div>
                        </div>
                    </flux:card>
                @endif
            </div>
        </div>
    </form>
</div>
