<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MigrateFreshKeepUsers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:fresh-keep-users {--seed : Seed the database after migrations}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run fresh migrations while preserving the users table';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (! $this->confirm('This will drop all tables except users. Do you want to continue?')) {
            $this->info('Operation cancelled.');

            return self::SUCCESS;
        }

        $this->info('Backing up users table...');

        // Backup users table data if it exists
        $usersBackup = [];
        if (Schema::hasTable('users')) {
            $usersBackup = DB::table('users')->get()->toArray();
            $this->info('Backed up '.count($usersBackup).' users.');
        } else {
            $this->warn('Users table does not exist yet.');
        }

        // Run migrate:fresh
        $this->info('Running migrate:fresh...');
        $this->call('migrate:fresh', [
            '--force' => true,
            '--seed' => $this->option('seed'),
        ]);

        // Restore users if we had any
        if (! empty($usersBackup)) {
            $this->info('Restoring users...');

            foreach ($usersBackup as $user) {
                DB::table('users')->insert((array) $user);
            }

            $this->info('Restored '.count($usersBackup).' users.');
        }

        $this->newLine();
        $this->info('✓ Fresh migration completed with users preserved!');

        return self::SUCCESS;
    }
}
