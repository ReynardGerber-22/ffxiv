<?php

namespace App\Console\Commands;

use App\Services\TeamcraftFishingService;
use Illuminate\Console\Command;
use Throwable;

class ImportFishingBaits extends Command
{
    protected $signature = 'fishing:import-baits {--item=* : Import only these fish Item IDs}';

    protected $description = 'Import Teamcraft bait recommendations and missing XIVAPI item/spot metadata';

    public function handle(TeamcraftFishingService $service): int
    {
        $data = $service->fetch();
        $selected = $this->option('item');
        foreach ($selected as $id) {
            if (! ctype_digit((string) $id) || ! isset($data[$id])) {
                $this->error("No Teamcraft fishing data for Item {$id}.");

                return self::FAILURE;
            }
        }
        if ($selected !== []) {
            $data = array_intersect_key($data, array_flip($selected));
        }
        $this->info('Syncing missing item metadata...');
        $service->prepareItems($data);
        $failed = 0;
        $count = 0;
        foreach ($data as $fishId => $records) {
            try {
                $count += $service->importFish((int) $fishId, $records);
                $this->line("Imported fish {$fishId}.");
            } catch (Throwable $exception) {
                $failed++;
                $this->error("Fish {$fishId}: {$exception->getMessage()}");
            }
        }
        $this->info("Imported {$count} recommendations; {$failed} fish failed.");

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
