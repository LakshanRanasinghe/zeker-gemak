<?php

namespace App\Livewire;

use App\Models\FaqPage;
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

final class FaqPageTable extends PowerGridComponent
{
    public string $tableName = 'faq-pages-table';

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

        return DB::table('faq_pages')
            ->whereNull('faq_pages.deleted_at')
            ->leftJoin('translations as t', function ($join) use ($locale) {
                $join->on('t.translatable_id', '=', 'faq_pages.id')
                    ->where('t.translatable_type', '=', 'App\Models\FaqPage')
                    ->where('t.language', '=', $locale);
            })
            ->selectRaw("
                faq_pages.id,
                COALESCE(JSON_UNQUOTE(JSON_EXTRACT(t.fields, '$.title')), faq_pages.title) as title,
                COALESCE(t.slug, faq_pages.slug) as slug,
                faq_pages.status,
                (select count(*) from faq_sections where faq_sections.faq_page_id = faq_pages.id) as sections_count,
                faq_pages.created_at
            ");
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('title')
            ->add('slug')
            ->add('status', fn ($row) => __(ucfirst($row->status)))
            ->add('sections_count', fn ($row) => (int) $row->sections_count)
            ->add('created_at', fn ($row) => Carbon::parse($row->created_at)->format('d/m/Y H:i'));
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

            Column::make(__('Sections'), 'sections_count')
                ->sortable(),

            Column::make(__('Status'), 'status')
                ->sortable(),

            Column::make(__('Created at'), 'created_at')
                ->sortable(),

            Column::action(__('Action')),
        ];
    }

    public function filters(): array
    {
        return [
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

        if (! empty($this->checkboxValues)) {
            $buttons[] = Button::add('bulk-delete')
                ->slot(__('Bulk Delete'))
                ->class('px-2 py-1 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-md text-sm shadow-sm hover:bg-red-100 dark:hover:bg-red-900/40 text-red-600 dark:text-red-400 transition-colors')
                ->attributes([
                    'x-data' => '',
                    'x-on:click' => "if(confirm('".__('Are you sure you want to delete the selected FAQ pages?')."')) { \$wire.bulkDelete() }",
                ]);
        }

        return $buttons;
    }

    public function actions(object $row): array
    {
        return [
            Button::add('edit')
                ->slot(__('Edit'))
                ->class('px-2 py-1 bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-md text-sm shadow-sm hover:bg-zinc-50 dark:hover:bg-zinc-700 text-zinc-700 dark:text-zinc-300 transition-colors')
                ->route('faq-pages.edit', ['faqPage' => $row->id])
                ->attributes(['wire:navigate' => '']),

            Button::add('delete')
                ->slot(__('Delete'))
                ->class('px-2 py-1 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-md text-sm shadow-sm hover:bg-red-100 dark:hover:bg-red-900/40 text-red-600 dark:text-red-400 transition-colors')
                ->dispatch('deleteFaqPage', ['id' => $row->id])
                ->confirm(__('Delete this FAQ page? Its sections will be removed, but the FAQ items themselves stay in the bank.')),
        ];
    }

    public function bulkDelete(): void
    {
        $ids = $this->checkboxValues;

        if (empty($ids)) {
            Flux::toast(__('Please select at least one FAQ page.'), variant: 'warning');

            return;
        }

        FaqPage::whereIn('id', $ids)->get()->each->delete();

        $this->checkboxValues = [];
        $this->checkboxAll = false;
        $this->dispatch('pgBulkActions::clear', $this->tableName);

        Flux::toast(__('Selected FAQ pages deleted successfully.'), variant: 'success');
    }

    #[On('deleteFaqPage')]
    public function deleteFaqPage(int $id): void
    {
        FaqPage::findOrFail($id)->delete();

        Flux::toast(__('FAQ page deleted successfully.'), variant: 'success');
    }
}
