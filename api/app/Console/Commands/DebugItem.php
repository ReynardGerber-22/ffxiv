<?php

namespace App\Console\Commands;

use App\Services\XivApiService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('xivapi:debug-item {itemId : The item ID to debug}')]
#[Description('Debug a specific item from the XIV API')]
class DebugItem extends Command
{
    public function handle(XivApiService $xivApiService): int
    {
        $itemId = (int) $this->argument('itemId');

        $item = $xivApiService->getItem($itemId);

        dump($item);

        return self::SUCCESS;
    }
}