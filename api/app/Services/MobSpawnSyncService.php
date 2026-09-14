<?php

namespace App\Services;

use App\Models\MobSpawn;
use Illuminate\Support\Facades\Http;

class MobSpawnSyncService
{
    private const MOB_SPAWN_URL =
    'https://raw.githubusercontent.com/Critical-Impact/LuminaSupplemental/main/src/LuminaSupplemental.Excel/Generated/MobSpawn.csv';

    public function sync(): int
    {
        $response = Http::get(self::MOB_SPAWN_URL)->throw();

        $lines = preg_split('/\r\n|\r|\n/', trim($response->body()));
        $header = str_getcsv(array_shift($lines));

        MobSpawn::truncate();

        $count = 0;

        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }

            $values = str_getcsv($line);
            $row = array_combine($header, $values);

            [$x, $y, $z] = array_map(
                'floatval',
                explode(';', $row['Position'])
            );

            if ($z === -1.0) {
                continue;
            }

            if ($x === 21.48 && $y === 21.48 && $z === -1.0) {
                continue;
            }

            MobSpawn::create([
                'bnpc_name_id' => (int) $row['BNpcNameId'],
                'bnpc_base_id' => (int) $row['BNpcBaseId'],
                'territory_type_id' => (int) $row['TerritoryTypeId'],
                'x' => $x,
                'y' => $y,
                'z' => $z,
                'subtype' => (int) $row['Subtype'],
            ]);

            $count++;
        }

        return $count;
    }
}
