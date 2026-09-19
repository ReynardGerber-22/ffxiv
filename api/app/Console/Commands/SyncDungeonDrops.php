<?php

namespace App\Console\Commands;

use App\Models\DungeonDrop;
use App\Models\Item;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class SyncDungeonDrops extends Command
{
    public const SOURCES_URL = 'https://raw.githubusercontent.com/ffxiv-teamcraft/ffxiv-teamcraft/staging/libs/data/src/lib/json/instance-sources.json';

    public const INSTANCES_URL = 'https://raw.githubusercontent.com/ffxiv-teamcraft/ffxiv-teamcraft/staging/libs/data/src/lib/json/instances.json';

    protected $signature = 'dungeons:sync {--item=* : Limit to locally cached item IDs}';

    protected $description = 'Cache Teamcraft dungeon/duty sources for locally cached materials';

    public function handle(): int
    {
        $ids = $this->option('item');
        foreach ($ids as $id) {
            if (! ctype_digit((string) $id) || (int) $id < 1 || ! Item::whereKey($id)->exists()) {
                $this->error("Item {$id} must be a locally cached item ID.");

                return self::FAILURE;
            }
        }
        try {
            $this->info('Fetching Teamcraft duty sources...');
            $sources = Http::timeout(60)->retry(3, 1000)->get(self::SOURCES_URL)->throw()->json();
            $instances = Http::timeout(60)->retry(3, 1000)->get(self::INSTANCES_URL)->throw()->json();
            if (! is_array($sources) || $sources === [] || ! is_array($instances) || $instances === []) {
                throw new RuntimeException('Empty or invalid Teamcraft dataset.');
            }
            $unresolved = [];
            foreach ($sources as $itemId => $duties) {
                if (! ctype_digit((string) $itemId) || (int) $itemId < 1 || ! is_array($duties)) {
                    throw new RuntimeException('Invalid Teamcraft item/duty mapping.');
                }
                foreach ($duties as $dutyId) {
                    if (! is_int($dutyId)) {
                        throw new RuntimeException("Invalid duty ID for item {$itemId}.");
                    }
                    if ($dutyId < 1 || ! isset($instances[$dutyId])) {
                        $unresolved[$dutyId] = true;

                        continue;
                    }
                    if (! is_string($instances[$dutyId]['en'] ?? null) || trim($instances[$dutyId]['en']) === '') {
                        throw new RuntimeException("Missing or invalid duty metadata for item {$itemId}.");
                    }
                }
            }
            if ($unresolved !== []) {
                $this->warn('Skipping '.count($unresolved).' duty references without matching names.');
            }
            $count = 0;
            $query = Item::query()->select('id');
            if ($ids !== []) {
                $query->whereIn('id', $ids);
            }
            foreach ($query->lazyById(200) as $item) {
                foreach (array_unique($sources[$item->id] ?? []) as $dutyId) {
                    if (isset($unresolved[$dutyId])) {
                        continue;
                    }
                    DungeonDrop::updateOrCreate(
                        ['item_id' => $item->id, 'instance_id' => $dutyId],
                        ['name' => ucfirst(trim($instances[$dutyId]['en']))],
                    );
                    $count++;
                }
            }
            $this->info("Synced {$count} item/duty links. Existing links retained.");

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
