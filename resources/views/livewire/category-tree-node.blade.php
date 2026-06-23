@props(['node', 'depth' => 0])

<div x-show="matchesSearch('{{ addslashes($node['name']) }}', {{ json_encode($node['children']) }})" class="space-y-1"
    style="margin-left: {{ $depth * 1.25 }}rem;">
    <div class="flex items-center gap-2 py-0.5 group">
        <flux:checkbox wire:model="selectedIds" value="{{ $node['id'] }}" label="{{ $node['name'] }}" size="sm"
            class="text-xs transition-colors hover:text-zinc-600 dark:hover:text-zinc-300" />
    </div>

    @if(!empty($node['children']))
        @foreach($node['children'] as $child)
            @include('livewire.category-tree-node', ['node' => $child, 'depth' => $depth + 1])
        @endforeach
    @endif
</div>