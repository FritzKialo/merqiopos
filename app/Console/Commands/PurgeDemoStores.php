<?php

namespace App\Console\Commands;

use App\Support\DemoStore;
use Illuminate\Console\Command;

class PurgeDemoStores extends Command
{
    protected $signature   = 'demo:purge';
    protected $description = 'Delete demo sandboxes older than a day';

    public function handle(): int
    {
        $this->info('Removed ' . DemoStore::purge() . ' demo store(s).');
        return self::SUCCESS;
    }
}
