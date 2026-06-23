<?php

namespace App\Console\Commands;

use App\Imports\BBNLImport;
use Illuminate\Console\Command;
use Maatwebsite\Excel\Facades\Excel;

class ProductImport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:product-import';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import products from the live BBNL';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        Excel::import(new BBNLImport, storage_path('app/private/Producten-Export-2025-October-13-1037.csv'));
    }
}
