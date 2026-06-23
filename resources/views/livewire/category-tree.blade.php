<?php

use Vanilo\Category\Models\Taxon;
use Vanilo\Category\Models\Taxonomy;
use Illuminate\Support\Str;
use Livewire\Attributes\Modelable;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public string $taxonomyName = 'Category';

    #[Modelable]
    public array $selectedIds = [];

    public string $newCategoryName = '';
    public ?int $parentId = null;
    public bool $showAddForm = false;

    public function mount(string $taxonomyName = 'Category')
    {
        $this->taxonomyName = $taxonomyName;
        \Log::info("CategoryTree mounted for taxonomy: " . $this->taxonomyName);
    }

    #[Computed]
    public function taxons()
    {
        return Taxon::whereHas('taxonomy', fn($q) => $q->where('name', $this->taxonomyName))
            ->orderBy('priority')
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function tree()
    {
        $grouped = $this->taxons->groupBy('parent_id');

        $buildTree = function ($parentId) use (&$buildTree, $grouped) {
            return $grouped->get($parentId, collect())->map(function ($taxon) use ($buildTree) {
                return [
                    'id' => $taxon->id,
                    'name' => $taxon->name,
                    'children' => $buildTree($taxon->id),
                ];
            })->values();
        };

        return $buildTree(null);
    }

    public function addCategory()
    {
        $this->validate([
            'newCategoryName' => 'required|string|max:255',
        ]);

        $taxonomy = Taxonomy::firstOrCreate(['name' => $this->taxonomyName]);

        $taxon = Taxon::create([
            'name' => $this->newCategoryName,
            'slug' => Str::slug($this->newCategoryName),
            'taxonomy_id' => $taxonomy->id,
            'parent_id' => $this->parentId,
        ]);

        $this->selectedIds[] = (int) $taxon->id;
        $this->newCategoryName = '';
        $this->parentId = null;
        $this->showAddForm = false;

        $this->dispatch('category-added');
    }
}; ?>

<div class="space-y-4" x-data="{
    search: '',
    matchesSearch(name, children) {
        if (!this.search) return true;
        const normalizedSearch = this.search.toLowerCase();
        if (name.toLowerCase().includes(normalizedSearch)) return true;
        // Also show if any children match
        return children.some(child => this.matchesSearch(child.name, child.children));
    }
}">
    <div class="px-1">
        <flux:input x-model="search" placeholder="{{ __('Search categories...') }}" icon="magnifying-glass" size="sm"
            clearable />
    </div>

    <div
        class="border border-zinc-200 dark:border-zinc-700 rounded-lg p-2 max-h-[300px] overflow-y-auto bg-zinc-50/50 dark:bg-zinc-900/50">
        @if($this->tree->isEmpty())
            <div class="py-4 text-center">
                <flux:subheading>{{ __('No categories found.') }}</flux:subheading>
            </div>
        @else
            <div class="space-y-1">
                @foreach($this->tree as $node)
                    @include('livewire.category-tree-node', ['node' => $node, 'depth' => 0])
                @endforeach
            </div>
        @endif
    </div>

    <div>
        @if(!$showAddForm)
            <flux:button variant="subtle" size="sm" icon="plus" wire:click="$set('showAddForm', true)" class="text-xs">
                {{ __('Add New Category') }}
            </flux:button>
        @else
            <div
                class="space-y-3 bg-white dark:bg-zinc-800 p-3 rounded-lg border border-zinc-200 dark:border-zinc-700 shadow-sm">
                <flux:input wire:model="newCategoryName" placeholder="{{ __('Category name...') }}" size="sm" />

                <flux:select wire:model="parentId" placeholder="{{ __('Parent Category (optional)') }}" size="sm">
                    <option value="">{{ __('None (Top Level)') }}</option>
                    @foreach($this->taxons as $taxon)
                        <option value="{{ $taxon->id }}">{{ $taxon->name }}</option>
                    @endforeach
                </flux:select>

                <div class="flex gap-2">
                    <flux:button size="sm" variant="ghost" wire:click="$set('showAddForm', false)" class="flex-1">
                        {{ __('Cancel') }}
                    </flux:button>
                    <flux:button size="sm" variant="primary" wire:click="addCategory" class="flex-1">
                        {{ __('Add') }}
                    </flux:button>
                </div>
            </div>
        @endif
    </div>
</div>