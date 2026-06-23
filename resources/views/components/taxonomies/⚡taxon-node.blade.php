<li data-id="{{ $node['id'] }}" class="my-2 group">
    <div
        class="flex items-center gap-2 bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-lg p-2 shadow-sm relative">
        <div class="cursor-grab active:cursor-grabbing text-zinc-400 hover:text-zinc-600 handle p-1">
            <flux:icon.bars-3 size="sm" />
        </div>

        <div class="flex-1" wire:key="taxon-name-{{ $node['id'] }}-{{ $selectedLocale }}">
            <flux:input
                wire:model.blur="taxonsFlat.{{ $node['flat_index'] }}.data.{{ $selectedLocale }}.name"
                plain
                class="!bg-transparent !border-none !p-0 !h-auto focus:!ring-0" />
        </div>

        <div class="flex items-center gap-3 pr-2">
            @if($node['items_count'] > 0)
                <flux:badge size="sm" variant="pill" color="zinc" class="font-mono text-xs">
                    {{ $node['items_count'] }} {{ $node['items_count'] === 1 ? __('item') : __('items') }}
                </flux:badge>
            @else
                <flux:badge size="sm" variant="pill" color="zinc" class="font-mono text-xs opacity-50">
                    {{ __('0 items') }}
                </flux:badge>
            @endif

            <flux:button variant="subtle" icon="pencil-square" size="sm" wire:click="editTaxon('{{ $node['id'] }}')" />

            <flux:button variant="subtle" icon="trash" size="sm" wire:click="removeTaxon('{{ $node['id'] }}')" />
        </div>
    </div>

    <ul
        class="nested-sortable pl-6 mt-2 space-y-2 border-l-2 border-dashed border-zinc-200 dark:border-zinc-700 min-h-[10px]">
        @foreach($node['children'] as $childNode)
            @include('components.taxonomies.⚡taxon-node', ['node' => $childNode, 'selectedLocale' => $selectedLocale])
        @endforeach
    </ul>
</li>