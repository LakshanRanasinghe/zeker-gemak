<?php

namespace App\Console\Commands;

use App\Models\DiscountGroup;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Log;

class SyncWoocommerceDiscountGroups extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:sync-woocommerce-discount-groups';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync Woocommerce Discount Groups with the System';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $groups = Http::get('https://businesslabels.nl/wp-json/wp/v2/discount_group?per_page=50');

        $groups = json_decode($groups->getBody(), true);

        foreach ($groups as $group) {
            $discounts = [];
            foreach ($group['acf']['group_discount'] as $d) {
                $discounts[] = array(
                    'discount' => $d['discount'],
                    'quantity' => $d['quantity_min'],
                );
            }

            DiscountGroup::updateOrCreate([
                'name' => $group['title']['rendered'],
            ], [
                'name' => $group['title']['rendered'],
                'discounts' => json_encode($discounts)
            ]);
        }
    }
}
