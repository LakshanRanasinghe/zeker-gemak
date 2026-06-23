<?php

namespace App\Livewire;

use App\Models\Post;
use Flux\Flux;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridComponent;
use PowerComponents\LivewirePowerGrid\PowerGridFields;

final class PageTable extends PowerGridComponent
{
    public string $tableName = 'pages-table';

    public function setUp(): array
    {
        $this->showCheckBox();

        return [
            PowerGrid::header()
                ->showSearchInput(),
            PowerGrid::footer()
                ->showPerPage()
                ->showRecordCount(),
        ];
    }

    public function datasource(): Builder
    {
        $locale = app()->getLocale();

        return DB::table('posts')->where('post_type', 'page')
            ->leftJoin('translations as t', function ($join) use ($locale) {
                $join->on('t.translatable_id', '=', 'posts.id')
                    ->where('t.translatable_type', '=', 'App\Models\Post')
                    ->where('t.language', '=', $locale);
            })
            ->selectRaw("
                posts.id,
                COALESCE(JSON_UNQUOTE(JSON_EXTRACT(t.fields, '$.title')), posts.title) as title,
                COALESCE(JSON_UNQUOTE(JSON_EXTRACT(t.fields, '$.slug')), posts.slug) as slug,
                posts.author,
                posts.post_type,
                posts.status,
                posts.created_at
            ");
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('title')
            ->add('slug')
            ->add('post_type', fn($row) => ucfirst($row->post_type))
            ->add('status', fn($row) => ucfirst($row->status))
            ->add('created_at', fn($row) => Carbon::parse($row->created_at)->format('d/m/Y H:i:s'));
    }

    public function columns(): array
    {
        return [
            Column::make(__('ID'), 'id')
                ->searchable()
                ->sortable(),

            Column::make(__('Title'), 'title')
                ->sortable()
                ->searchable(),

            Column::make(__('Slug'), 'slug')
                ->searchable(),

            Column::make(__('Type'), 'post_type')
                ->sortable(),

            Column::make(__('Status'), 'status')
                ->sortable(),

            Column::make(__('Created at'), 'created_at')
                ->sortable()->hidden(),

            Column::action(__('Action')),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::select('post_type', 'post_type')
                ->dataSource([
                    ['name' => __('Page'), 'value' => 'page'],
                    ['name' => __('Post'), 'value' => 'post'],
                ])
                ->optionValue('value')
                ->optionLabel('name'),

            Filter::select('status', 'status')
                ->dataSource([
                    ['name' => __('Published'), 'value' => 'published'],
                    ['name' => __('Draft'), 'value' => 'draft'],
                ])
                ->optionValue('value')
                ->optionLabel('name'),
        ];
    }

    public function header(): array
    {
        $buttons = [];

        if ($this->hasActiveFilters()) {
            $buttons[] = Button::add('clear-filters')
                ->slot(__('Clear Filters'))
                ->class('px-2 py-1 bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-md text-sm shadow-sm hover:bg-zinc-50 dark:hover:bg-zinc-700 text-zinc-700 dark:text-zinc-300 transition-colors')
                ->attributes([
                    'x-data' => '',
                    'x-on:click' => "\$wire.clearAllFilters(); \$wire.set('search', '');",
                ]);
        }

        if (!empty($this->checkboxValues)) {
            $buttons[] = Button::add('bulk-delete')
                ->slot(__('Bulk Delete'))
                ->class('px-2 py-1 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-md text-sm shadow-sm hover:bg-red-100 dark:hover:bg-red-900/40 text-red-600 dark:text-red-400 transition-colors')
                ->attributes([
                    'x-data' => '',
                    'x-on:click' => "if(confirm('" . __('Are you sure you want to delete the selected pages?') . "')) { \$wire.bulkDelete() }",
                ]);
        }

        return $buttons;
    }

    protected function hasActiveFilters(): bool
    {
        if (filled($this->search)) {
            return true;
        }

        foreach ($this->filters as $filter) {
            if (is_array($filter)) {
                foreach ($filter as $value) {
                    if (filled($value)) {
                        return true;
                    }
                }
            } elseif (filled($filter)) {
                return true;
            }
        }

        return false;
    }

    public function actions(object $row): array
    {
        return [
            Button::add('edit')
                ->slot(__('Edit'))
                ->class('px-2 py-1 bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-md text-sm shadow-sm hover:bg-zinc-50 dark:hover:bg-zinc-700 text-zinc-700 dark:text-zinc-300 transition-colors')
                ->route('pages.edit', ['page' => $row->id])
                ->attributes(['wire:navigate' => '']),

            Button::add('delete')
                ->slot(__('Delete'))
                ->class('px-2 py-1 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-md text-sm shadow-sm hover:bg-red-100 dark:hover:bg-red-900/40 text-red-600 dark:text-red-400 transition-colors')
                ->dispatch('deletePage', ['id' => $row->id])
                ->confirm(__('Are you sure you want to delete this entry? This action cannot be undone.')),
        ];
    }

    public function bulkDelete(): void
    {
        $ids = $this->checkboxValues;

        if (empty($ids)) {
            Flux::toast(__('Please select at least one page.'), variant: 'warning');

            return;
        }

        Post::whereIn('id', $ids)->get()->each->delete();

        $this->checkboxValues = [];
        $this->checkboxAll = false;
        $this->dispatch('pgBulkActions::clear', $this->tableName);

        Flux::toast(__('Selected pages deleted successfully.'), variant: 'success');
    }

    #[On('deletePage')]
    public function deletePage(int $id): void
    {
        $post = Post::findOrFail($id);
        $type = $post->post_type;

        $post->delete();

        $message = match ($type) {
            'post' => __('Post deleted successfully.'),
            'printer' => __('Printer deleted successfully.'),
            default => __('Page deleted successfully.'),
        };

        Flux::toast($message, variant: 'success');
    }
}
