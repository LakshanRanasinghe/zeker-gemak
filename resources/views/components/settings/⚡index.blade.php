<?php

use App\Models\TeamMember;
use App\Models\Product;
use App\Models\PopularProduct;
use Flux\Flux;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

new class extends Component {
    use WithFileUploads;

    public string $tab = 'team';

    public array $teamMembers = [];

    public array $popularProducts = [];

    public string $search = '';

    public array $searchResults = [];

    public function mount(): void
    {
        $this->loadTeamMembers();
        $this->loadPopularProducts();
    }

    public function updatedSearch(): void
    {
        if (strlen($this->search) < 2) {
            $this->searchResults = [];

            return;
        }

        $this->searchResults = Product::query()
            ->where(function ($query) {
                $query->where('name', 'like', '%' . $this->search . '%')
                    ->orWhere('sku', 'like', '%' . $this->search . '%')
                    ->orWhere('article_number', 'like', '%' . $this->search . '%')
                    ->orWhere('title', 'like', '%' . $this->search . '%');
            })
            ->where(fn($q) => $q->where('product_template', '!=', 'warranty')->orWhereNull('product_template'))
            ->take(10)
            ->get()
            ->map(fn (Product $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'price' => $product->price,
                'image' => $product->getFirstMediaUrl('main'),
            ])
            ->toArray();
    }

    public function addProduct(int $productId): void
    {

        if (collect($this->popularProducts)->contains('product_id', $productId)) {
            Flux::toast(__('This product is already in the list.'), variant: 'warning');

            return;
        }

        $product = Product::find($productId);

        if (! $product) {
            return;
        }

        $this->popularProducts[] = [
            'product_id' => $product->id,
            'name' => $product->name,
            'sku' => $product->sku,
            'price' => $product->price,
            'image' => $product->getFirstMediaUrl('main'),
        ];

        $this->search = '';
        $this->searchResults = [];
        $this->savePopularProducts();
    }

    public function removeProduct(int $productId): void
    {
        $this->popularProducts = collect($this->popularProducts)
            ->reject(fn ($p) => (int) $p['product_id'] === $productId)
            ->values()
            ->toArray();
        $this->savePopularProducts();
    }

    public function handleSort($productId, $position): void
    {
        $productId = (int) $productId;
        $item = collect($this->popularProducts)->firstWhere('product_id', $productId);

        if (! $item) {
            return;
        }

        $list = collect($this->popularProducts)->reject(fn ($p) => (int) $p['product_id'] === $productId)->values();
        $list->splice($position, 0, [$item]);

        $this->popularProducts = $list->toArray();
        $this->savePopularProducts();
    }

    public function addTeamMember(): void
    {
        $this->teamMembers[] = $this->emptyTeamMember();
    }

    public function removeTeamMember(int $index): void
    {
        if (! isset($this->teamMembers[$index])) {
            return;
        }

        $this->deleteTemporaryUpload($this->teamMembers[$index]['profile_pic'] ?? null);

        unset($this->teamMembers[$index]);
        $this->teamMembers = array_values($this->teamMembers);
    }

    public function removeProfilePic(int $index): void
    {
        if (! isset($this->teamMembers[$index])) {
            return;
        }

        $this->deleteTemporaryUpload($this->teamMembers[$index]['profile_pic'] ?? null);

        $this->teamMembers[$index]['profile_pic'] = null;
        $this->teamMembers[$index]['existing_profile_pic_url'] = null;
        $this->teamMembers[$index]['clear_profile_pic'] = true;
    }

    public function save(): void
    {
        if ($this->tab === 'team') {
            $this->saveTeam();
        }
    }

    protected function saveTeam(): void
    {
        $this->validate([
            'teamMembers' => ['array'],
            'teamMembers.*.id' => ['nullable', 'integer', 'exists:team_members,id'],
            'teamMembers.*.first_name' => ['required', 'string', 'max:255'],
            'teamMembers.*.last_name' => ['required', 'string', 'max:255'],
            'teamMembers.*.email' => ['nullable', 'email', 'max:255'],
            'teamMembers.*.phone' => ['nullable', 'string', 'max:50'],
            'teamMembers.*.profile_pic' => ['nullable', 'image', 'max:10240'],
        ]);

        DB::transaction(function (): void {
            $keptIds = collect($this->teamMembers)
                ->pluck('id')
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->values();

            TeamMember::query()
                ->when($keptIds->isNotEmpty(), fn ($query) => $query->whereNotIn('id', $keptIds))
                ->when($keptIds->isEmpty(), fn ($query) => $query)
                ->get()
                ->each(fn (TeamMember $teamMember) => $teamMember->delete());

            foreach ($this->teamMembers as $index => $row) {
                $teamMember = TeamMember::query()->updateOrCreate(
                    ['id' => $row['id']],
                    [
                        'first_name' => $row['first_name'],
                        'last_name' => $row['last_name'],
                        'email' => $row['email'] ?: null,
                        'phone' => $row['phone'] ?: null,
                        'sort_order' => $index,
                    ],
                );

                if (($row['clear_profile_pic'] ?? false) && ! $row['profile_pic'] instanceof UploadedFile) {
                    $teamMember->clearMediaCollection('profile_pic');
                }

                if ($row['profile_pic'] instanceof UploadedFile) {
                    $teamMember
                        ->addMedia($row['profile_pic']->getRealPath())
                        ->usingName($row['profile_pic']->getClientOriginalName())
                        ->usingFileName($row['profile_pic']->getClientOriginalName())
                        ->toMediaCollection('profile_pic');
                }
            }
        });

        $this->loadTeamMembers();

        Flux::toast(__('Team members saved.'), variant: 'success');
    }

    protected function savePopularProducts(): void
    {
        DB::transaction(function (): void {
            PopularProduct::query()->delete();

            foreach ($this->popularProducts as $index => $item) {
                PopularProduct::create([
                    'product_id' => $item['product_id'],
                    'sort_order' => $index,
                ]);
            }
        });

        $this->loadPopularProducts();

        Flux::toast(__('Popular products updated.'), variant: 'success');
    }

    protected function loadTeamMembers(): void
    {
        $this->teamMembers = TeamMember::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (TeamMember $teamMember): array => [
                'row_key' => 'team-member-' . $teamMember->id,
                'id' => $teamMember->id,
                'first_name' => $teamMember->first_name,
                'last_name' => $teamMember->last_name,
                'email' => $teamMember->email,
                'phone' => $teamMember->phone,
                'profile_pic' => null,
                'existing_profile_pic_url' => $teamMember->profilePicUrl(),
                'clear_profile_pic' => false,
            ])
            ->values()
            ->all();

        if ($this->teamMembers === []) {
            $this->addTeamMember();
        }
    }

    protected function loadPopularProducts(): void
    {
        $this->popularProducts = PopularProduct::query()
            ->with('product')
            ->orderBy('sort_order')
            ->get()
            ->map(fn (PopularProduct $popularProduct): array => [
                'product_id' => $popularProduct->product_id,
                'name' => $popularProduct->product->name,
                'sku' => $popularProduct->product->sku,
                'price' => $popularProduct->product->price,
                'image' => $popularProduct->product->getFirstMediaUrl('main'),
            ])
            ->values()
            ->all();
    }

    protected function emptyTeamMember(): array
    {
        return [
            'row_key' => 'team-member-new-' . count($this->teamMembers) . '-' . str()->uuid(),
            'id' => null,
            'first_name' => '',
            'last_name' => '',
            'email' => '',
            'phone' => '',
            'profile_pic' => null,
            'existing_profile_pic_url' => null,
            'clear_profile_pic' => false,
        ];
    }

    protected function deleteTemporaryUpload(mixed $upload): void
    {
        if ($upload instanceof TemporaryUploadedFile) {
            $upload->delete();
        }
    }
};
?>

<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <flux:heading size="xl" level="1">{{ __('Settings') }}</flux:heading>
            <flux:subheading size="lg">
                {{ __('Manage team members and storefront settings.') }}
            </flux:subheading>
        </div>
    </div>

    <flux:tab.group>
        <flux:tabs wire:model.live="tab">
            <flux:tab name="team" icon="users">{{ __('Manage Team') }}</flux:tab>
            <flux:tab name="products" icon="star">{{ __('Popular Products') }}</flux:tab>
        </flux:tabs>

        <flux:tab.panel name="team" class="pt-6">
            <form wire:submit="save" class="space-y-6">
                <flux:card class="space-y-4">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <flux:heading size="lg">{{ __('Team members') }}</flux:heading>
                            <flux:text class="mt-2">
                                {{ __('Add the people shown on the storefront team section. Photos are optional and can be replaced at any time.') }}
                            </flux:text>
                        </div>

                        <flux:button type="button" variant="primary" icon="plus" wire:click="addTeamMember"
                            wire:loading.attr="disabled" wire:target="addTeamMember" data-test="add-team-member">
                            {{ __('Add team member') }}
                        </flux:button>
                    </div>
                </flux:card>

                <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
                    @forelse ($teamMembers as $index => $teamMember)
                        <flux:card class="space-y-5" wire:key="{{ $teamMember['row_key'] }}">
                            <div class="flex items-start gap-4">
                                <div class="shrink-0">
                                    <flux:field>
                                        <flux:label class="sr-only">{{ __('Profile picture') }}</flux:label>
                                        <flux:file-upload wire:model="teamMembers.{{ $index }}.profile_pic">
                                            <div
                                                class="relative flex items-center justify-center size-20 rounded-full transition-colors cursor-pointer border border-zinc-200 dark:border-white/10 hover:border-zinc-300 dark:hover:border-white/10 bg-zinc-100 hover:bg-zinc-200 dark:bg-white/10 hover:dark:bg-white/15 in-data-dragging:dark:bg-white/15 in-data-loading:opacity-70 overflow-hidden">
                                                @if ($teamMember['profile_pic'] instanceof TemporaryUploadedFile)
                                                    <img src="{{ $teamMember['profile_pic']->temporaryUrl() }}"
                                                        class="size-full object-cover rounded-full"
                                                        alt="{{ __('Profile picture preview') }}" />
                                                @elseif ($teamMember['existing_profile_pic_url'])
                                                    <img src="{{ $teamMember['existing_profile_pic_url'] }}"
                                                        class="size-full object-cover rounded-full"
                                                        alt="{{ trim($teamMember['first_name'] . ' ' . $teamMember['last_name']) ?: __('Team member') }}" />
                                                @else
                                                    <flux:icon name="user" variant="solid"
                                                        class="size-8 text-zinc-500 dark:text-zinc-400" />
                                                @endif
                                            </div>
                                        </flux:file-upload>
                                        <flux:error name="teamMembers.{{ $index }}.profile_pic" />
                                    </flux:field>
                                </div>

                                <div class="min-w-0 flex-1">
                                    <div class="min-w-0">
                                        <flux:heading>
                                            {{ trim($teamMember['first_name'] . ' ' . $teamMember['last_name']) ?: __('Team member :number', ['number' => $index + 1]) }}
                                        </flux:heading>
                                        <flux:text class="mt-1 text-zinc-500 dark:text-zinc-400">
                                            {{ $teamMember['email'] ?: __('Add contact details below.') }}
                                        </flux:text>

                                        @if ($teamMember['profile_pic'] || $teamMember['existing_profile_pic_url'])
                                            <div class="mt-2">
                                                <flux:button type="button" size="xs" variant="danger" icon="x-mark"
                                                    wire:click="removeProfilePic({{ $index }})">
                                                    {{ __('Remove photo') }}
                                                </flux:button>
                                            </div>
                                        @endif
                                    </div>
                                </div>

                                <flux:button type="button" size="sm" variant="ghost" icon="trash"
                                    wire:click="removeTeamMember({{ $index }})"
                                    aria-label="{{ __('Remove team member') }}" />
                            </div>

                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <flux:input wire:model="teamMembers.{{ $index }}.first_name"
                                    label="{{ __('First Name') }}" />
                                <flux:input wire:model="teamMembers.{{ $index }}.last_name"
                                    label="{{ __('Last Name') }}" />
                                <flux:input wire:model="teamMembers.{{ $index }}.phone"
                                    label="{{ __('Phone') }}" />
                            </div>
                        </flux:card>
                    @empty
                        <flux:card class="space-y-4 text-center xl:col-span-2">
                            <flux:heading size="lg">{{ __('No team members yet') }}</flux:heading>
                            <flux:text>{{ __('Add a team member to get started.') }}</flux:text>
                            <flux:button type="button" variant="primary" icon="plus" wire:click="addTeamMember"
                                data-test="add-team-member-empty">
                                {{ __('Add team member') }}
                            </flux:button>
                        </flux:card>
                    @endforelse
                </div>

                <div class="flex justify-end">
                    <flux:button type="submit" variant="primary" icon="check">
                        {{ __('Save team members') }}
                    </flux:button>
                </div>
            </form>
        </flux:tab.panel>

        <flux:tab.panel name="products" class="pt-6 space-y-6">
            <flux:card class="space-y-4">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <flux:heading size="lg">{{ __('Popular Products') }}</flux:heading>
                        <flux:text class="mt-2">
                            {{ __('Select products to feature on the storefront homepage.') }}
                        </flux:text>
                    </div>

                    <div class="flex items-center gap-2">
                        <flux:badge size="sm" color="zinc">
                            {{ count($popularProducts) }}
                        </flux:badge>
                    </div>
                </div>

                <div class="relative">
                    <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass"
                        placeholder="{{ __('Search products by name or SKU...') }}" />

                    @if ($searchResults)
                        <div
                            class="absolute z-10 w-full mt-1 bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-white/10 rounded-lg shadow-lg overflow-hidden max-h-60 overflow-y-auto">
                            @foreach ($searchResults as $result)
                                <button type="button" wire:click="addProduct({{ $result['id'] }})"
                                    class="w-full flex items-center gap-3 p-3 text-left hover:bg-zinc-100 dark:hover:bg-white/5 transition-colors">
                                    @if ($result['image'])
                                        <img src="{{ $result['image'] }}" class="size-10 object-cover rounded" />
                                    @else
                                        <div class="size-10 bg-zinc-100 dark:bg-white/10 rounded flex items-center justify-center">
                                            <flux:icon name="photo" class="size-5 text-zinc-400" />
                                        </div>
                                    @endif
                                    <div class="min-w-0 flex-1">
                                        <div class="font-medium truncate">{{ $result['name'] }}</div>
                                        <div class="text-sm text-zinc-500 truncate">
                                            {{ $result['sku'] ?: __('No SKU') }} • €{{ number_format($result['price'], 2, ',', '.') }}
                                        </div>
                                    </div>
                                    <flux:icon name="plus" class="size-4 text-zinc-400" />
                                </button>
                            @endforeach
                        </div>
                    @elseif($search && strlen($search) >= 2 && empty($searchResults))
                         <div class="absolute z-10 w-full mt-1 bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-white/10 rounded-lg shadow-lg p-4 text-center text-zinc-500">
                            {{ __('No products found.') }}
                        </div>
                    @endif
                </div>
            </flux:card>

            <div wire:sort="handleSort" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-3">
                @forelse ($popularProducts as $item)
                    <div wire:key="pop-{{ $item['product_id'] }}" wire:sort:item="{{ $item['product_id'] }}"
                         class="group relative bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-white/10 rounded-lg shadow-sm overflow-hidden flex flex-col h-full transition-shadow hover:shadow-md">
                        <div class="relative h-32 bg-zinc-100 dark:bg-white/5 overflow-hidden">
                            @if ($item['image'])
                                <img src="{{ $item['image'] }}" class="size-full object-cover" />
                            @else
                                <div class="size-full flex items-center justify-center">
                                    <flux:icon name="photo" class="size-8 text-zinc-300 dark:text-zinc-700" />
                                </div>
                            @endif

                            <div class="absolute top-1.5 right-1.5 flex gap-1">
                                <div wire:sort:handle class="p-1 bg-white/90 dark:bg-zinc-800/90 rounded border border-zinc-200 dark:border-white/10 cursor-move text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300 transition-colors shadow-sm">
                                    <flux:icon name="bars-3" class="size-3.5" />
                                </div>
                                <button type="button" wire:click="removeProduct({{ $item['product_id'] }})"
                                        class="p-1 bg-white/90 dark:bg-zinc-800/90 rounded border border-zinc-200 dark:border-white/10 text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors shadow-sm">
                                    <flux:icon name="trash" class="size-3.5" />
                                </button>
                            </div>
                        </div>

                        <div class="p-3 flex flex-col flex-1 gap-1">
                            <div class="text-[10px] font-semibold text-blue-500 uppercase tracking-wider truncate">
                                SKU: {{ $item['sku'] ?: 'N/A' }}
                            </div>
                            <h4 class="font-bold text-sm leading-tight text-zinc-900 dark:text-white line-clamp-2 min-h-[2.5rem]">
                                {{ $item['name'] }}
                            </h4>
                            <div class="mt-auto pt-2 border-t border-zinc-100 dark:border-white/5 flex items-center justify-between">
                                <div class="text-base font-bold text-zinc-900 dark:text-white">
                                    €{{ number_format($item['price'], 2, ',', '.') }}
                                </div>
                                <div class="text-[8px] text-zinc-500 uppercase font-medium">
                                    {{ __('ex. VAT') }}
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-span-full py-12 text-center border-2 border-dashed border-zinc-200 dark:border-white/10 rounded-xl">
                        <flux:icon name="star" class="size-12 mx-auto text-zinc-300 dark:text-zinc-700" />
                        <flux:heading level="3" size="lg" class="mt-4">{{ __('No popular products yet') }}</flux:heading>
                        <flux:text class="mt-2">{{ __('Search and add products above to feature them.') }}</flux:text>
                    </div>
                @endforelse
            </div>
        </flux:tab.panel>
    </flux:tab.group>
</div>
