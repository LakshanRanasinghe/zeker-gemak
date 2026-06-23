<?php

use App\Models\CustomerReview;
use App\Models\MasterProduct;
use App\Models\Product;
use App\Models\User;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public ?CustomerReview $customerReview = null;

    public $name = '';

    public $email = '';

    public $rating = 5;

    public $comment = '';

    public $source = 'manual';

    public $status = 'pending';

    public $product_id = null;

    public $product_type = null;

    public $user_id = null;

    public $avatar;

    public string $product_search = '';

    public array $product_results = [];

    public bool $product_dropdown_open = false;

    public ?string $selected_product_label = null;

    public string $customer_search = '';

    public array $customer_results = [];

    public bool $customer_dropdown_open = false;

    public ?string $selected_customer_label = null;

    public bool $link_to_customer = false;

    public bool $editMode = false;

    public function mount(?CustomerReview $customerReview = null)
    {
        if ($customerReview && $customerReview->exists) {
            $this->customerReview = $customerReview;
            $this->name = $customerReview->name;
            $this->email = $customerReview->email;
            $this->rating = $customerReview->rating;
            $this->comment = $customerReview->comment;
            $this->source = $customerReview->source;
            $this->status = $customerReview->status;
            $this->product_id = $customerReview->product_id;
            $this->product_type = $customerReview->product_type;
            $this->user_id = $customerReview->user_id;
            $this->link_to_customer = (bool) $customerReview->user_id;
            $this->editMode = true;

            if ($this->product_id) {
                $product = $this->product_type === 'variable'
                    ? MasterProduct::find($this->product_id)
                    : Product::find($this->product_id);

                if ($product) {
                    $this->selected_product_label = $this->labelFor($product, $this->product_type);
                }
            }

            if ($this->user_id && ($user = User::find($this->user_id))) {
                $this->selected_customer_label = $user->name.' · '.$user->email;
            }
        }
    }

    public function openProductDropdown(): void
    {
        $this->product_dropdown_open = true;
        $this->refreshProductResults();
    }

    public function closeProductDropdown(): void
    {
        $this->product_dropdown_open = false;
    }

    public function updatedProductSearch(): void
    {
        $this->product_dropdown_open = true;
        $this->refreshProductResults();
    }

    protected function refreshProductResults(): void
    {
        $term = trim($this->product_search);

        $simpleQuery = Product::query()->latest('id');
        $masterQuery = MasterProduct::query()->latest('id');

        if ($term !== '') {
            $simpleQuery->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('sku', 'like', "%{$term}%")
                    ->orWhere('article_number', 'like', "%{$term}%");
            });

            $masterQuery->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('article_number', 'like', "%{$term}%");
            });
        }

        $simple = $simpleQuery->take(8)->get()->map(fn ($p) => [
            'id' => $p->id,
            'type' => 'simple',
            'label' => $this->labelFor($p, 'simple'),
            'sku' => $p->sku,
        ]);

        $master = $masterQuery->take(8)->get()->map(fn ($p) => [
            'id' => $p->id,
            'type' => 'variable',
            'label' => $this->labelFor($p, 'variable'),
            'sku' => $p->article_number,
        ]);

        $this->product_results = $simple->concat($master)->take(12)->values()->all();
    }

    protected function labelFor($product, string $type): string
    {
        $title = method_exists($product, 'title') ? $product->title() : ($product->name ?? '');
        $title = $title !== '' ? $title : ('#'.$product->id);
        $sku = $type === 'variable' ? ($product->article_number ?? null) : ($product->sku ?? null);
        $badge = $type === 'variable' ? 'Variable' : 'Simple';

        return $title.($sku ? ' · '.$sku : '').' — '.$badge;
    }

    public function selectProduct(int $id, string $type): void
    {
        $exists = $type === 'variable'
            ? MasterProduct::whereKey($id)->exists()
            : Product::whereKey($id)->exists();

        if (! $exists) {
            $this->addError('product_id', __('Selected product was not found.'));

            return;
        }

        $picked = collect($this->product_results)->first(fn ($r) => $r['id'] === $id && $r['type'] === $type);

        $this->product_id = $id;
        $this->product_type = $type;
        $this->selected_product_label = $picked['label'] ?? null;
        $this->product_search = '';
        $this->product_dropdown_open = false;
    }

    public function clearProduct(): void
    {
        $this->product_id = null;
        $this->product_type = null;
        $this->selected_product_label = null;
        $this->product_search = '';
    }

    public function openCustomerDropdown(): void
    {
        $this->customer_dropdown_open = true;
        $this->refreshCustomerResults();
    }

    public function closeCustomerDropdown(): void
    {
        $this->customer_dropdown_open = false;
    }

    public function updatedCustomerSearch(): void
    {
        $this->customer_dropdown_open = true;
        $this->refreshCustomerResults();
    }

    protected function refreshCustomerResults(): void
    {
        $term = trim($this->customer_search);

        $query = User::query()->latest('id');

        if ($term !== '') {
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%");
            });
        }

        $this->customer_results = $query->take(8)->get()
            ->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
            ])->all();
    }

    public function selectCustomer(int $id): void
    {
        $user = User::find($id);

        if (! $user) {
            $this->addError('user_id', __('Selected customer was not found.'));

            return;
        }

        $this->user_id = $user->id;
        $this->selected_customer_label = $user->name.' · '.$user->email;

        if (blank($this->name)) {
            $this->name = $user->name;
        }
        if (blank($this->email)) {
            $this->email = $user->email;
        }

        $this->customer_search = '';
        $this->customer_dropdown_open = false;
    }

    public function clearCustomer(): void
    {
        $this->user_id = null;
        $this->selected_customer_label = null;
        $this->customer_search = '';
    }

    public function updatedLinkToCustomer($value): void
    {
        if (! $value) {
            $this->user_id = null;
            $this->selected_customer_label = null;
            $this->customer_search = '';
            $this->customer_dropdown_open = false;
        }
    }

    protected function messages(): array
    {
        return [
            'name.required' => __('Name is required.'),
            'rating.required' => __('Rating is required.'),
            'rating.between' => __('Rating must be between 1 and 5.'),
            'comment.required' => __('Comment is required.'),
            'avatar.image' => __('Avatar must be an image.'),
            'avatar.max' => __('Avatar max 5MB.'),
        ];
    }

    public function save()
    {
        $rules = [
            'rating' => 'required|integer|between:1,5',
            'comment' => 'required|string',
            'source' => 'required|string|max:50',
            'status' => 'required|in:pending,approved,rejected',
            'product_id' => 'nullable|integer',
            'product_type' => 'nullable|in:simple,variable',
        ];

        if ($this->link_to_customer) {
            $rules['user_id'] = 'required|integer|exists:users,id';
        } else {
            $rules['name'] = 'required|string|max:255';
            $rules['email'] = 'nullable|email|max:255';
            $rules['avatar'] = 'nullable|image|max:5120';
            $rules['user_id'] = 'nullable|integer|exists:users,id';
        }

        $validated = $this->validate($rules);

        if (empty($validated['product_id'])) {
            $validated['product_id'] = null;
            $validated['product_type'] = null;
        } else {
            $type = $validated['product_type'] ?: 'simple';
            $exists = $type === 'variable'
                ? MasterProduct::whereKey($validated['product_id'])->exists()
                : Product::whereKey($validated['product_id'])->exists();

            if (! $exists) {
                $this->addError('product_id', __('Selected product was not found.'));

                return;
            }

            $validated['product_type'] = $type;
        }

        if ($this->link_to_customer) {
            $user = User::find($validated['user_id']);
            $validated['name'] = $user->name;
            $validated['email'] = $user->email;
        } elseif (empty($validated['user_id']) && ! empty($validated['email'])) {
            $matched = User::where('email', $validated['email'])->first();
            if ($matched) {
                $validated['user_id'] = $matched->id;
                $this->user_id = $matched->id;
                $this->link_to_customer = true;
                $this->selected_customer_label = $matched->name.' · '.$matched->email;
            }
        }

        $reviewData = collect($validated)->except('avatar')->toArray();

        if (! $this->customerReview) {
            $review = CustomerReview::create(array_merge($reviewData, [
                'reviewed_at' => $reviewData['status'] !== 'pending' ? now() : null,
            ]));

            if (! $this->link_to_customer) {
                $this->uploadAvatar($review);
            }

            Flux::toast(__('Review created successfully.'), variant: 'success');

            $this->redirect(route('customer-reviews.index'), navigate: true);

            return;
        }

        $previousStatus = $this->customerReview->status;
        $reviewData['reviewed_at'] = $reviewData['status'] !== 'pending' && $previousStatus !== $reviewData['status']
            ? now()
            : $this->customerReview->reviewed_at;

        $this->customerReview->update($reviewData);

        if ($this->link_to_customer) {
            $this->customerReview->clearMediaCollection('avatar');
        } else {
            $this->uploadAvatar($this->customerReview);
        }

        Flux::toast(__('Review updated successfully.'), variant: 'success');
    }

    protected function uploadAvatar(CustomerReview $review): void
    {
        if (! $this->avatar) {
            return;
        }

        $review->clearMediaCollection('avatar');
        $review->addMedia($this->avatar->getRealPath())
            ->usingName($this->avatar->getClientOriginalName())
            ->usingFileName($this->avatar->getClientOriginalName())
            ->toMediaCollection('avatar');

        $this->avatar = null;
    }

    #[Computed]
    public function existingAvatarMedia()
    {
        if (! $this->editMode || ! $this->customerReview) {
            return collect();
        }

        return $this->customerReview->getMedia('avatar');
    }
};
?>

<div>
    <form wire:submit="save" class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="xl">
                    {{ $customerReview ? __('Edit Review') : __('Create Review') }}
                </flux:heading>
                <flux:subheading>
                    {{ $customerReview ? __('Update the review details and moderation status.') : __('Add a new review. Set the status to Approved to make it visible on your storefront.') }}
                </flux:subheading>
            </div>
            <div class="flex space-x-2">
                <flux:button href="{{ route('customer-reviews.index') }}" wire:navigate>
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 md:items-start">
            <div class="md:col-span-2 space-y-6">
                <flux:card class="space-y-6">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <flux:heading size="lg">{{ __('Reviewer') }}</flux:heading>
                            <flux:subheading>
                                {{ $link_to_customer
                                    ? __('Uses name, email and avatar from profile.')
                                    : __('Auto-links if email matches a customer.') }}
                            </flux:subheading>
                        </div>
                        <flux:switch wire:model.live="link_to_customer"
                            label="{{ __('Existing customer') }}" />
                    </div>

                    @if ($link_to_customer)
                        @if ($user_id && $selected_customer_label)
                            <div
                                class="flex items-center justify-between gap-3 px-3 py-2 rounded-md border border-emerald-200 dark:border-emerald-800 bg-emerald-50 dark:bg-emerald-900/20">
                                <div class="text-sm">
                                    <span class="text-emerald-700 dark:text-emerald-400 font-medium">
                                        {{ __('Linked customer:') }}
                                    </span>
                                    <span class="text-zinc-900 dark:text-white">{{ $selected_customer_label }}</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <flux:button size="sm" variant="subtle" icon="arrow-top-right-on-square"
                                        href="{{ route('customers.edit', ['id' => $user_id]) }}" target="_blank"
                                        type="button">
                                        {{ __('Open') }}
                                    </flux:button>
                                    <flux:button size="sm" variant="subtle" icon="x-mark" wire:click="clearCustomer"
                                        type="button">
                                        {{ __('Change') }}
                                    </flux:button>
                                </div>
                            </div>
                        @else
                            <div class="relative" x-data x-on:click.outside="$wire.closeCustomerDropdown()">
                                <flux:input wire:model.live.debounce.250ms="customer_search"
                                    x-on:focus="$wire.openCustomerDropdown()" label="{{ __('Customer') }}"
                                    placeholder="{{ __('Click to browse or search by name or email...') }}"
                                    icon="magnifying-glass" />

                                @if ($customer_dropdown_open && ! empty($customer_results))
                                    <div
                                        class="absolute z-20 w-full mt-1 bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-lg shadow-lg max-h-72 overflow-y-auto">
                                        @foreach ($customer_results as $customer)
                                            <button type="button" wire:click="selectCustomer({{ $customer['id'] }})"
                                                class="w-full text-left px-4 py-2 hover:bg-zinc-50 dark:hover:bg-zinc-700">
                                                <div
                                                    class="font-medium text-sm text-zinc-900 dark:text-white truncate">
                                                    {{ $customer['name'] }}
                                                </div>
                                                <div class="text-xs text-zinc-500">{{ $customer['email'] }}</div>
                                            </button>
                                        @endforeach
                                    </div>
                                @elseif ($customer_dropdown_open && empty($customer_results))
                                    <div
                                        class="absolute z-20 w-full mt-1 bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-lg shadow-lg px-4 py-3 text-sm text-zinc-500">
                                        {{ __('No customers found.') }}
                                    </div>
                                @endif
                            </div>
                            <flux:error name="user_id" />
                        @endif
                    @else
                        <div class="flex flex-col md:flex-row gap-6">
                            <div class="flex-1">
                                <flux:input wire:model="name" label="{{ __('Name') }}" placeholder="Jane Doe" />
                            </div>
                            <div class="flex-1">
                                <flux:input wire:model="email" type="email" label="{{ __('Email (optional)') }}"
                                    placeholder="jane@example.com" />
                            </div>
                        </div>

                        <flux:field>
                            <flux:label>{{ __('Avatar') }}</flux:label>
                            <x-file-pond wire:model="avatar" accept="image/*"
                                :uploads="$this->existingAvatarMedia" />
                            <flux:error name="avatar" />
                        </flux:field>
                    @endif
                </flux:card>

                <flux:card class="space-y-6">
                    <div>
                        <flux:heading size="lg">{{ __('Review') }}</flux:heading>
                        <flux:subheading>{{ __('Rating and comment.') }}</flux:subheading>
                    </div>

                    <flux:select wire:model="rating" label="{{ __('Rating') }}">
                        @for ($i = 5; $i >= 1; $i--)
                            <option value="{{ $i }}">{{ str_repeat('★', $i) . str_repeat('☆', 5 - $i) }} ({{ $i }})
                            </option>
                        @endfor
                    </flux:select>

                    <x-wysiwyg-editor wire:model="comment" :defer-delete="$editMode" label="{{ __('Comment') }}"
                        placeholder="{{ __('What did the customer say?') }}" />
                </flux:card>

                <flux:card class="space-y-6">
                    <div>
                        <flux:heading size="lg">{{ __('Linked Product (optional)') }}</flux:heading>
                        <flux:subheading>{{ __('Search and pick a product. Leave empty for site-wide reviews.') }}
                        </flux:subheading>
                    </div>

                    @if ($product_id && $selected_product_label)
                        <div
                            class="flex items-center justify-between gap-3 px-3 py-2 rounded-md border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/50">
                            <div class="text-sm">
                                <span class="text-zinc-500">{{ __('Selected:') }}</span>
                                <span class="font-medium text-zinc-900 dark:text-white">
                                    {{ $selected_product_label }}
                                </span>
                            </div>
                            <flux:button size="sm" variant="subtle" icon="x-mark" wire:click="clearProduct"
                                type="button">
                                {{ __('Clear') }}
                            </flux:button>
                        </div>
                    @else
                        <div class="relative" x-data x-on:click.outside="$wire.closeProductDropdown()">
                            <flux:input wire:model.live.debounce.250ms="product_search"
                                x-on:focus="$wire.openProductDropdown()"
                                placeholder="{{ __('Click to browse or type to search by name, SKU, or article number...') }}"
                                icon="magnifying-glass" />

                            @if ($product_dropdown_open && ! empty($product_results))
                                <div
                                    class="absolute z-20 w-full mt-1 bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-lg shadow-lg max-h-72 overflow-y-auto">
                                    @foreach ($product_results as $result)
                                        <button type="button"
                                            wire:click="selectProduct({{ $result['id'] }}, '{{ $result['type'] }}')"
                                            class="w-full text-left px-4 py-2 hover:bg-zinc-50 dark:hover:bg-zinc-700 flex items-center justify-between gap-3">
                                            <div class="min-w-0">
                                                <div class="font-medium text-sm text-zinc-900 dark:text-white truncate">
                                                    {{ $result['label'] }}
                                                </div>
                                                @if ($result['sku'])
                                                    <div class="text-xs text-zinc-500">{{ $result['sku'] }}</div>
                                                @endif
                                            </div>
                                            <span
                                                class="text-xs px-2 py-0.5 rounded-full border {{ $result['type'] === 'variable' ? 'border-indigo-200 text-indigo-700 bg-indigo-50 dark:bg-indigo-900/20 dark:text-indigo-400 dark:border-indigo-800' : 'border-zinc-200 text-zinc-600 bg-zinc-50 dark:bg-zinc-700 dark:text-zinc-300 dark:border-zinc-600' }} shrink-0">
                                                {{ $result['type'] === 'variable' ? __('Variable') : __('Simple') }}
                                            </span>
                                        </button>
                                    @endforeach
                                </div>
                            @elseif ($product_dropdown_open && empty($product_results))
                                <div
                                    class="absolute z-20 w-full mt-1 bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-lg shadow-lg px-4 py-3 text-sm text-zinc-500">
                                    {{ __('No products found.') }}
                                </div>
                            @endif
                        </div>
                        <flux:error name="product_id" />
                    @endif
                </flux:card>
            </div>

            <div class="space-y-6 sticky top-6">
                <flux:card class="space-y-6">
                    <div>
                        <flux:heading size="lg">{{ __('Moderation') }}</flux:heading>
                        <flux:subheading>{{ __('Only approved reviews are visible on your storefront.') }}</flux:subheading>
                    </div>

                    <flux:select wire:model="status" label="{{ __('Status') }}">
                        <option value="pending">{{ __('Pending') }}</option>
                        <option value="approved">{{ __('Approved') }}</option>
                        <option value="rejected">{{ __('Rejected') }}</option>
                    </flux:select>

                    <flux:select wire:model="source" label="{{ __('Source') }}">
                        <option value="manual">{{ __('Manual') }}</option>
                        <option value="google">{{ __('Google') }}</option>
                        <option value="api">{{ __('Storefront') }}</option>
                    </flux:select>

                    <div class="flex space-x-2 items-end">
                        <flux:button variant="primary" type="submit" class="w-full">{{ __('Save') }}</flux:button>
                    </div>
                </flux:card>
            </div>
        </div>
    </form>
</div>
