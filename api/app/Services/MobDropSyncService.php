<?php

namespace App\Services;

use App\Models\MobDrop;
use Illuminate\Support\Facades\Http;
use App\Models\Item;

class MobDropSyncService
{
    private const MOB_DROP_URL =
    'https://raw.githubusercontent.com/Critical-Impact/LuminaSupplemental/main/src/LuminaSupplemental.Excel/Generated/MobDrop.csv';

    public function sync(): int
    {
        $response = Http::get(self::MOB_DROP_URL)->throw();

        $lines = preg_split('/\r\n|\r|\n/', trim($response->body()));

        $header = str_getcsv(array_shift($lines));

        $count = 0;

        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }

            $values = str_getcsv($line);
            $row = array_combine($header, $values);

            $itemId = (int) $row['ItemId'];

            if (!Item::whereKey($itemId)->exists()) {
                continue;
            }

            MobDrop::updateOrCreate([
                'item_id' => $itemId,
                'bnpc_name_id' => (int) $row['BNpcNameId'],
            ]);

            $count++;
        }

        return $count;
    }
}
