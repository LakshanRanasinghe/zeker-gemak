<?php

use Carbon\CarbonInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Vanilo\Order\Models\OrderItemProxy;
use Vanilo\Order\Models\OrderProxy;

new class extends Component {
    /**
     * @var array<string, int>
     */
    public array $orderCounts = [];

    /**
     * @var array<string, mixed>
     */
    public array $turnoverComparison = [];

    /**
     * @var array<int, array{month: string, current_year: float, previous_year: float}>
     */
    public array $yearToDateTurnover = [];

    public int $unexportedXmlOrderCount = 0;

    public string $currency = 'EUR';

    public string $currencySymbol = '€';

    public int $currentYear;

    public int $previousYear;

    public function mount(): void
    {
        $this->currency = config('app.currency', 'EUR');
        $this->currencySymbol = config('app.currency_symbol', '€');
        $this->currentYear = now()->year;
        $this->previousYear = now()->subYear()->year;

        $this->orderCounts = $this->loadOrderCounts();
        $this->turnoverComparison = $this->loadTurnoverComparison();
        $this->yearToDateTurnover = $this->loadYearToDateTurnover();
        $this->unexportedXmlOrderCount = $this->countUnexportedXmlOrders();
    }

    /**
     * @return array<string, int>
     */
    private function loadOrderCounts(): array
    {
        $now = now();

        return [
            'today' => $this->countOrdersBetween($now->copy()->startOfDay(), $now->copy()->endOfDay()),
            'week' => $this->countOrdersBetween($now->copy()->startOfWeek(), $now->copy()->endOfWeek()),
            'month' => $this->countOrdersBetween($now->copy()->startOfMonth(), $now->copy()->endOfMonth()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function loadTurnoverComparison(): array
    {
        $now = now();
        $current = $this->turnoverBetween($now->copy()->startOfMonth(), $now->copy()->endOfMonth());
        $previous = $this->turnoverBetween(
            $now->copy()->subYear()->startOfMonth(),
            $now->copy()->subYear()->endOfMonth(),
        );

        return [
            'current' => $current,
            'previous' => $previous,
            'difference' => $current - $previous,
            'percentage' => $previous > 0 ? (($current - $previous) / $previous) * 100 : null,
        ];
    }

    /**
     * @return array<int, array{month: string, current_year: float, previous_year: float}>
     */
    private function loadYearToDateTurnover(): array
    {
        $now = now();
        $months = [];

        for ($month = 1; $month <= $now->month; $month++) {
            $currentMonthEnd = Carbon::create($now->year, $month, 1)->endOfMonth();
            $previousMonthEnd = Carbon::create($now->year - 1, $month, 1)->endOfMonth();

            $months[] = [
                'month' => $currentMonthEnd->format('M'),
                'current_year' => $this->turnoverBetween($now->copy()->startOfYear(), $currentMonthEnd),
                'previous_year' => $this->turnoverBetween($now->copy()->subYear()->startOfYear(), $previousMonthEnd),
            ];
        }

        return $months;
    }

    private function countOrdersBetween(CarbonInterface $start, CarbonInterface $end): int
    {
        return DB::table('orders')
            ->whereNull('deleted_at')
            ->whereBetween(DB::raw('COALESCE(ordered_at, created_at)'), [$start, $end])
            ->count();
    }

    private function countUnexportedXmlOrders(): int
    {
        return DB::table('orders')
            ->whereNull('deleted_at')
            ->where('xml_exported', false)
            ->count();
    }

    private function turnoverBetween(CarbonInterface $start, CarbonInterface $end): float
    {
        return (float) DB::query()
            ->fromSub($this->orderTotalsQuery($start, $end), 'order_totals')
            ->sum('total');
    }

    private function orderTotalsQuery(?CarbonInterface $start = null, ?CarbonInterface $end = null): Builder
    {
        $orderClass = OrderProxy::modelClass();
        $orderItemClass = OrderItemProxy::modelClass();
        $orderMorphClass = (new $orderClass())->getMorphClass();
        $orderItemMorphClass = (new $orderItemClass())->getMorphClass();

        $itemAdjustments = DB::table('adjustments')
            ->select('adjustable_id', DB::raw('SUM(amount) as adjustment_total'))
            ->where('adjustable_type', $orderItemMorphClass)
            ->where('is_included', false)
            ->groupBy('adjustable_id');

        $itemTotals = DB::table('order_items')
            ->leftJoinSub($itemAdjustments, 'item_adjustments', 'item_adjustments.adjustable_id', '=', 'order_items.id')
            ->select('order_items.order_id', DB::raw('SUM((order_items.price * order_items.quantity) + COALESCE(item_adjustments.adjustment_total, 0)) as item_total'))
            ->groupBy('order_items.order_id');

        $orderAdjustments = DB::table('adjustments')
            ->select('adjustable_id', DB::raw('SUM(amount) as adjustment_total'))
            ->where('adjustable_type', $orderMorphClass)
            ->where('is_included', false)
            ->groupBy('adjustable_id');

        return DB::table('orders')
            ->leftJoinSub($itemTotals, 'item_totals', 'item_totals.order_id', '=', 'orders.id')
            ->leftJoinSub($orderAdjustments, 'order_adjustments', 'order_adjustments.adjustable_id', '=', 'orders.id')
            ->whereNull('orders.deleted_at')
            ->when($start && $end, fn (Builder $query) => $query->whereBetween(DB::raw('COALESCE(orders.ordered_at, orders.created_at)'), [$start, $end]))
            ->select([
                'orders.id',
                'orders.number',
                'orders.status',
                DB::raw('COALESCE(orders.ordered_at, orders.created_at) as ordered_on'),
                DB::raw('COALESCE(item_totals.item_total, 0) + COALESCE(order_adjustments.adjustment_total, 0) as total'),
            ]);
    }

    public function money(float $amount): string
    {
        return $this->currencySymbol.number_format($amount, 2);
    }

    public function signedMoney(float $amount): string
    {
        $prefix = $amount > 0 ? '+' : '';

        return $prefix.$this->money($amount);
    }

    public function signedPercentage(?float $percentage): string
    {
        if ($percentage === null) {
            return __('No last-year turnover');
        }

        return sprintf('%+0.1f%%', $percentage);
    }
};
?>

<div class="space-y-5">
    <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
        <div>
            <flux:heading size="xl" level="1">{{ __('Dashboard') }}</flux:heading>
            <flux:subheading size="lg">{{ __('Orders and turnover at a glance.') }}</flux:subheading>
        </div>

        <flux:button icon="plus" variant="primary" href="{{ route('orders.create') }}" wire:navigate>
            {{ __('New Order') }}
        </flux:button>
    </div>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        <flux:card class="space-y-5">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <flux:text>{{ __('Orders') }}</flux:text>
                    <flux:heading size="xl" class="mt-2 tabular-nums">{{ number_format($orderCounts['month'] ?? 0) }}</flux:heading>
                </div>
                <flux:badge color="blue" icon="shopping-bag">{{ __('This month') }}</flux:badge>
            </div>

            <div class="grid grid-cols-3 gap-3">
                <div class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                    <flux:text class="text-xs">{{ __('Today') }}</flux:text>
                    <div class="mt-1 text-xl font-semibold tabular-nums text-zinc-900 dark:text-white">{{ number_format($orderCounts['today'] ?? 0) }}</div>
                </div>
                <div class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                    <flux:text class="text-xs">{{ __('This week') }}</flux:text>
                    <div class="mt-1 text-xl font-semibold tabular-nums text-zinc-900 dark:text-white">{{ number_format($orderCounts['week'] ?? 0) }}</div>
                </div>
                <div class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                    <flux:text class="text-xs">{{ __('This month') }}</flux:text>
                    <div class="mt-1 text-xl font-semibold tabular-nums text-zinc-900 dark:text-white">{{ number_format($orderCounts['month'] ?? 0) }}</div>
                </div>
            </div>
        </flux:card>

        <flux:card class="space-y-5">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <flux:text>{{ __('Turnover this month') }}</flux:text>
                    <flux:heading size="xl" class="mt-2 tabular-nums">{{ $this->money($turnoverComparison['current'] ?? 0) }}</flux:heading>
                </div>
                <flux:badge :color="($turnoverComparison['difference'] ?? 0) >= 0 ? 'green' : 'red'" icon="chart-bar">
                    {{ $this->signedPercentage($turnoverComparison['percentage'] ?? null) }}
                </flux:badge>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                    <flux:text class="text-xs">{{ __('Same month last year') }}</flux:text>
                    <div class="mt-1 text-lg font-semibold tabular-nums text-zinc-900 dark:text-white">{{ $this->money($turnoverComparison['previous'] ?? 0) }}</div>
                </div>
                <div class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                    <flux:text class="text-xs">{{ __('Difference') }}</flux:text>
                    <div @class([
                        'mt-1 text-lg font-semibold tabular-nums',
                        'text-green-600 dark:text-green-400' => ($turnoverComparison['difference'] ?? 0) >= 0,
                        'text-red-600 dark:text-red-400' => ($turnoverComparison['difference'] ?? 0) < 0,
                    ])>
                        {{ $this->signedMoney($turnoverComparison['difference'] ?? 0) }}
                    </div>
                </div>
            </div>
        </flux:card>

        <flux:card class="overflow-hidden">
            <flux:text>{{ __('YTD turnover') }}</flux:text>
            <flux:heading size="xl" class="mt-2 tabular-nums">
                {{ $this->money((float) ($yearToDateTurnover[array_key_last($yearToDateTurnover)]['current_year'] ?? 0)) }}
            </flux:heading>

            <flux:chart class="-mx-8 -mb-8 mt-4 h-20" :value="$yearToDateTurnover">
                <flux:chart.svg gutter="0">
                    <flux:chart.line field="previous_year" class="text-zinc-300 dark:text-white/30" stroke-dasharray="4 4" curve="none" />
                    <flux:chart.line field="current_year" class="text-sky-500 dark:text-sky-400" curve="none" />
                    <flux:chart.area field="current_year" class="text-sky-100 dark:text-sky-400/20" curve="none" />
                </flux:chart.svg>
            </flux:chart>
        </flux:card>

        <flux:card class="space-y-5">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <flux:text>{{ __('Orders without XML') }}</flux:text>
                    <flux:heading size="xl" class="mt-2 tabular-nums">{{ number_format($unexportedXmlOrderCount) }}</flux:heading>
                </div>
                <flux:badge :color="$unexportedXmlOrderCount > 0 ? 'amber' : 'green'" :icon="$unexportedXmlOrderCount > 0 ? 'document-text' : 'check-circle'">
                    {{ $unexportedXmlOrderCount > 0 ? __('Pending') : __('Clear') }}
                </flux:badge>
            </div>

            <div class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                <flux:text class="text-xs">{{ __('XML export queue') }}</flux:text>
                <div class="mt-1 text-lg font-semibold tabular-nums text-zinc-900 dark:text-white">
                    {{ trans_choice('{0} No orders waiting|{1} :count order waiting|[2,*] :count orders waiting', $unexportedXmlOrderCount) }}
                </div>
            </div>
        </flux:card>
    </div>

    <flux:card>
        <div class="mb-4 flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
            <div>
                <flux:heading size="lg">{{ __('Turnover year to date') }}</flux:heading>
                <flux:subheading>{{ __('Cumulative monthly turnover compared with last year.') }}</flux:subheading>
            </div>
            <div class="flex gap-4">
                <flux:chart.legend :label="$currentYear">
                    <flux:chart.legend.indicator class="bg-sky-500" />
                </flux:chart.legend>
                <flux:chart.legend :label="$previousYear">
                    <flux:chart.legend.indicator class="bg-zinc-300 dark:bg-white/40" />
                </flux:chart.legend>
            </div>
        </div>

        <flux:chart :value="$yearToDateTurnover">
            <flux:chart.viewport class="h-56">
                <flux:chart.svg>
                    <flux:chart.line field="previous_year" class="text-zinc-300 dark:text-white/40" stroke-dasharray="4 4" curve="none" />
                    <flux:chart.line field="current_year" class="text-sky-500 dark:text-sky-400" curve="none" />
                    <flux:chart.point field="current_year" class="text-sky-500 dark:text-sky-400" />
                    <flux:chart.axis axis="x" field="month">
                        <flux:chart.axis.tick />
                        <flux:chart.axis.line />
                    </flux:chart.axis>
                    <flux:chart.axis axis="y" tick-prefix="{{ $currencySymbol }}" :format="['notation' => 'compact', 'maximumFractionDigits' => 1]">
                        <flux:chart.axis.grid />
                        <flux:chart.axis.tick />
                    </flux:chart.axis>
                    <flux:chart.cursor />
                </flux:chart.svg>
            </flux:chart.viewport>
            <flux:chart.tooltip>
                <flux:chart.tooltip.heading field="month" />
                <flux:chart.tooltip.value field="current_year" :label="$currentYear" :format="['style' => 'currency', 'currency' => $currency]" />
                <flux:chart.tooltip.value field="previous_year" :label="$previousYear" :format="['style' => 'currency', 'currency' => $currency]" />
            </flux:chart.tooltip>
        </flux:chart>
    </flux:card>
</div>
