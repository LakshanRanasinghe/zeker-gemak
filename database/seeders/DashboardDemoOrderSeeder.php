<?php

namespace Database\Seeders;

use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Vanilo\Order\Models\OrderItemProxy;
use Vanilo\Order\Models\OrderProxy;

class DashboardDemoOrderSeeder extends Seeder
{
    private const PREFIX = 'BL-DEMO-DASH-';

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::transaction(function () {
            $this->clearExistingDemoOrders();

            $now = now();

            foreach ($this->monthlyTurnoverTargets($now->year) as $month => $target) {
                $this->createMonthlyOrders($now->year, $month, $target);
            }

            foreach ($this->monthlyTurnoverTargets($now->year - 1) as $month => $target) {
                $this->createMonthlyOrders($now->year - 1, $month, $target);
            }

            $this->command?->info('Dashboard demo orders seeded for '.$now->year.' and '.($now->year - 1).'.');
        });
    }

    private function clearExistingDemoOrders(): void
    {
        $orderIds = DB::table('orders')
            ->where('number', 'like', self::PREFIX.'%')
            ->pluck('id');

        if ($orderIds->isEmpty()) {
            return;
        }

        $itemIds = DB::table('order_items')
            ->whereIn('order_id', $orderIds)
            ->pluck('id');

        $orderClass = OrderProxy::modelClass();
        $orderItemClass = OrderItemProxy::modelClass();

        DB::table('adjustments')
            ->where(function ($query) use ($orderClass, $orderIds) {
                $query->where('adjustable_type', (new $orderClass)->getMorphClass())
                    ->whereIn('adjustable_id', $orderIds);
            })
            ->orWhere(function ($query) use ($orderItemClass, $itemIds) {
                $query->where('adjustable_type', (new $orderItemClass)->getMorphClass())
                    ->whereIn('adjustable_id', $itemIds);
            })
            ->delete();

        DB::table('orders')
            ->whereIn('id', $orderIds)
            ->delete();
    }

    /**
     * @return array<int, float>
     */
    private function monthlyTurnoverTargets(int $year): array
    {
        $currentYear = now()->year;

        if ($year === $currentYear) {
            return [
                1 => 12840,
                2 => 14675,
                3 => 17120,
                4 => 18990,
                5 => 23150,
            ];
        }

        return [
            1 => 10250,
            2 => 11890,
            3 => 13240,
            4 => 15110,
            5 => 16480,
        ];
    }

    private function createMonthlyOrders(int $year, int $month, float $target): void
    {
        $orderCount = $year === now()->year && $month === now()->month ? 9 : 6;
        $baseAmount = round($target / $orderCount, 2);

        for ($index = 1; $index <= $orderCount; $index++) {
            $orderedAt = $this->orderedAt($year, $month, $index);
            $amount = $index === $orderCount
                ? $target - ($baseAmount * ($orderCount - 1))
                : $baseAmount;

            $this->createOrder(
                number: sprintf('%s%d-%02d-%02d', self::PREFIX, $year, $month, $index),
                orderedAt: $orderedAt,
                itemAmount: max(25, $amount - 18),
                adjustmentAmount: $amount >= 18 ? 18 : 0,
                xmlExported: ! $this->shouldRemainInXmlQueue($year, $month, $index),
            );
        }
    }

    private function shouldRemainInXmlQueue(int $year, int $month, int $index): bool
    {
        $now = now();

        return $year === $now->year
            && $month >= max(1, $now->month - 1)
            && $index % 3 !== 0;
    }

    private function orderedAt(int $year, int $month, int $index): CarbonInterface
    {
        $now = now();

        if ($year === $now->year && $month === $now->month) {
            $dates = [
                $now->copy()->startOfDay()->addHours(9),
                $now->copy()->startOfDay()->addHours(11),
                $now->copy()->startOfWeek()->addDay()->addHours(14),
                $now->copy()->startOfWeek()->addDays(2)->addHours(10),
                $now->copy()->startOfMonth()->addDays(1)->addHours(9),
                $now->copy()->startOfMonth()->addDays(5)->addHours(15),
                $now->copy()->startOfMonth()->addDays(7)->addHours(12),
                $now->copy()->startOfMonth()->addDays(9)->addHours(16),
                $now->copy()->startOfMonth()->addDays(11)->addHours(13),
            ];

            return $dates[$index - 1] ?? $now->copy();
        }

        return Carbon::create($year, $month, 1)
            ->addDays(($index - 1) * 4)
            ->setTime(9 + ($index % 6), 0);
    }

    private function createOrder(
        string $number,
        CarbonInterface $orderedAt,
        float $itemAmount,
        float $adjustmentAmount,
        bool $xmlExported,
    ): void {
        $xmlExportedAt = $xmlExported ? $orderedAt->copy()->addHours(2) : null;

        $orderId = DB::table('orders')->insertGetId([
            'number' => $number,
            'status' => 'completed',
            'xml_exported' => $xmlExported,
            'xml_exported_at' => $xmlExportedAt,
            'fulfillment_status' => 'fulfilled',
            'currency' => config('app.currency', 'EUR'),
            'language' => 'en',
            'notes' => 'Demo order for the admin dashboard.',
            'ordered_at' => $orderedAt,
            'created_at' => $orderedAt,
            'updated_at' => $orderedAt,
        ]);

        DB::table('order_items')->insert([
            'order_id' => $orderId,
            'product_type' => 'product',
            'product_id' => 1,
            'name' => 'Demo dashboard order item',
            'fulfillment_status' => 'fulfilled',
            'quantity' => 1,
            'price' => $itemAmount,
            'created_at' => $orderedAt,
            'updated_at' => $orderedAt,
        ]);

        if ($adjustmentAmount <= 0) {
            return;
        }

        $orderClass = OrderProxy::modelClass();

        DB::table('adjustments')->insert([
            'type' => 'shipping',
            'adjustable_type' => (new $orderClass)->getMorphClass(),
            'adjustable_id' => $orderId,
            'adjuster' => 'dashboard_demo',
            'title' => 'Demo shipping',
            'amount' => $adjustmentAmount,
            'is_locked' => true,
            'is_included' => false,
            'created_at' => $orderedAt,
            'updated_at' => $orderedAt,
        ]);
    }
}
