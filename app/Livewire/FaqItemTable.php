<?php

namespace App\Livewire;

use App\Models\FaqItem;
use Flux\Flux;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridComponent;
use PowerComponents\LivewirePowerGrid\PowerGridFields;

final class FaqItemTable extends PowerGridComponent
{
    public string $tableName = 'faq-items-table';

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

        return DB::table('faq_items')
            ->whereNull('faq_items.deleted_at')
            ->leftJoin('translations as t', function ($join) use ($locale) {
                $join->on('t.translatable_id', '=', 'faq_items.id')
                    ->where('t.translatable_type', '=', 'App\Models\FaqItem')
                    ->where('t.language', '=', $locale);
            })
            ->selectRaw("
                faq_items.id,
                COALESCE(JSON_UNQUOTE(JSON_EXTRACT(t.fields, '$.question')), faq_items.question) as question,
                COALESCE(JSON_UNQUOTE(JSON_EXTRACT(t.fields, '$.answer')), faq_items.answer) as answer,
                (select count(*) from faq_section_item where faq_section_item.faq_item_id = faq_items.id) as usage_count,
                faq_items.created_at
            ");
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('question')
            ->add('answer_preview', fn ($row) => Str::limit(trim(strip_tags((string) $row->answer)), 90))
            ->add('usage_count', fn ($row) => (int) $row->usage_count)
            ->add('created_at', fn ($row) => Carbon::parse($row->created_at)->format('d/m/Y H:i'));
    }

    public function columns(): array
    {
        return [
            Column::make(__('ID'), 'id')
                ->searchable()
                ->sortable(),

            Column::make(__('Question'), 'question')
                ->sortable()
                ->searchable(),

            Column::make(__('Answer'), 'answer_preview', 'answer')
                ->searchable(),

            Column::make(__('Used in'), 'usage_count')
                ->sortable(),

            Column::make(__('Created at'), 'created_at')
                ->sortable(),

            Column::action(__('Action')),
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
                    'x-on:click' => "if(confirm('".__('Are you sure you want to delete the selected FAQ items?')."')) { \$wire.bulkDelete() }",
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
                ->route('faq-items.edit', ['faqItem' => $row->id])
                ->attributes(['wire:navigate' => '']),

            Button::add('delete')
                ->slot(__('Delete'))
                ->class('px-2 py-1 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-md text-sm shadow-sm hover:bg-red-100 dark:hover:bg-red-900/40 text-red-600 dark:text-red-400 transition-colors')
                ->dispatch('deleteFaqItem', ['id' => $row->id])
                ->confirm(__('Delete this FAQ item? It will also be removed from any pages that use it.')),
        ];
    }

    public function bulkDelete(): void
    {
        $ids = $this->checkboxValues;

        if (empty($ids)) {
            Flux::toast(__('Please select at least one FAQ item.'), variant: 'warning');

            return;
        }

        FaqItem::whereIn('id', $ids)->get()->each->delete();

        $this->checkboxValues = [];
        $this->checkboxAll = false;
        $this->dispatch('pgBulkActions::clear', $this->tableName);

        Flux::toast(__('Selected FAQ items deleted successfully.'), variant: 'success');
    }

    #[On('deleteFaqItem')]
    public function deleteFaqItem(int $id): void
    {
        FaqItem::findOrFail($id)->delete();

        Flux::toast(__('FAQ item deleted successfully.'), variant: 'success');
    }
}
