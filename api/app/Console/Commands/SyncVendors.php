<?php

namespace App\Console\Commands;

use App\Models\Item;
use App\Services\VendorSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class SyncVendors extends Command
{
    protected $signature = 'vendors:sync {--item=* : Limit to these Item IDs; defaults to all cached items}';

    protected $description = 'Cache NQ gil-shop offers, NPCs and locations for crafting materials';

    public function handle(VendorSyncService $service): int
    {
        $ids = $this->option('item');
        foreach ($ids as $id) {
            if (! ctype_digit((string) $id) || (int) $id < 1) {
                $this->error('Item IDs must be positive integers.');

                return self::FAILURE;
            }
        }
        $ids = $ids === [] ? Item::pluck('id')->all() : array_map('intval', $ids);
        $lock = Cache::lock('vendors:sync', 7200);
        if (! $lock->get()) {
            $this->error('A vendor sync is already running.');

            return self::FAILURE;
        }
        try {
            // The full Teamcraft NPC JSON exceeds PHP's default 128 MB when decoded.
            // This allowance applies only to this offline CLI import, not API requests.
            $memoryLimit = ini_parse_quantity(ini_get('memory_limit'));
            if ($memoryLimit > 0 && $memoryLimit < 512 * 1024 * 1024) {
                ini_set('memory_limit', '512M');
            }
            $this->info('Discovering gil vendors and locations for '.count($ids).' items...');
            $stats = $service->sync($ids);
            $this->table(array_keys($stats), [array_values($stats)]);

            return self::SUCCESS;
        } finally {
            $lock->release();
        }
    }
}
