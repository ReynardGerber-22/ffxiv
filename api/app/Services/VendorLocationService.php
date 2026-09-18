<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class VendorLocationService
{
    public const TEAMCRAFT_URL = 'https://raw.githubusercontent.com/ffxiv-teamcraft/ffxiv-teamcraft/staging/libs/data/src/lib/json/npcs.json';

    private const MAP_FIELDS = 'SizeFactor,OffsetX,OffsetY,PlaceNameSub.Name,TerritoryType.PlaceName.Name';

    public function __construct(private XivApiService $xiv) {}

    public static function mapCoordinate(float $world, float $offset, float $size): float
    {
        $scale = $size / 100;

        return 1 + (41 / $scale) * ((($world + $offset) * $scale + 1024) / 2048);
    }

    public function fetch(array $npcIds): array
    {
        $locations = [];
        foreach (array_chunk($npcIds, 40) as $ids) {
            $query = '+Type=8 +('.implode(' ', array_map(fn ($id) => 'Object='.$id, $ids)).')';
            $fields = 'Object@as(raw),Type,X,Y,Z,Map.SizeFactor,Map.OffsetX,Map.OffsetY,Map.PlaceNameSub.Name,Territory.PlaceName.Name';
            foreach ($this->xiv->searchAll('Level', $query, $fields) as $row) {
                $f = $row['fields'];
                $map = $f['Map']['fields'] ?? [];
                $territory = trim($f['Territory']['fields']['PlaceName']['fields']['Name'] ?? '');
                $size = $map['SizeFactor'] ?? 0;
                if ($territory === '' || $size <= 0 || empty($f['Map']['row_id'])
                    || empty($f['Territory']['row_id']) || ! $this->numeric($f['X'] ?? null) || ! $this->numeric($f['Z'] ?? null)) {
                    continue;
                }
                $x = self::mapCoordinate($f['X'], $map['OffsetX'] ?? 0, $size);
                $y = self::mapCoordinate($f['Z'], $map['OffsetY'] ?? 0, $size);
                if (! $this->usable($x, $y)) {
                    continue;
                }
                $locations[$f['Object@as(raw)']][] = [
                    'territory_id' => $f['Territory']['row_id'], 'territory' => $territory,
                    'map_id' => $f['Map']['row_id'], 'area' => ($map['PlaceNameSub']['fields']['Name'] ?? '') ?: null,
                    'raw_x' => $f['X'], 'raw_y' => $f['Y'] ?? null, 'raw_z' => $f['Z'],
                    'x' => $x, 'y' => $y, 'source' => 'xivapi', 'source_key' => (string) $row['row_id'],
                ];
            }
        }

        $missing = array_values(array_diff($npcIds, array_keys($locations)));
        if ($missing === []) {
            return $locations;
        }
        $data = Http::timeout(60)->retry(3, 1000)->get(self::TEAMCRAFT_URL)->throw()->json();
        if (! is_array($data) || $data === []) {
            throw new RuntimeException('Invalid Teamcraft NPC dataset; keeping existing vendor data.');
        }
        $positions = [];
        foreach ($missing as $id) {
            $p = $data[$id]['position'] ?? [];
            if (! empty($p['map']) && $this->usable($p['x'] ?? null, $p['y'] ?? null)) {
                $positions[$id] = $p;
            }
        }
        $maps = $this->xiv->getSheetRows('Map', array_column($positions, 'map'), self::MAP_FIELDS);
        foreach ($positions as $id => $p) {
            $map = $maps[$p['map']]['fields'] ?? [];
            $territory = trim($map['TerritoryType']['fields']['PlaceName']['fields']['Name'] ?? '');
            if ($territory === '' || empty($map['TerritoryType']['row_id'])) {
                continue;
            }
            $locations[$id][] = [
                'territory_id' => $map['TerritoryType']['row_id'], 'territory' => $territory,
                'map_id' => $p['map'], 'area' => ($map['PlaceNameSub']['fields']['Name'] ?? '') ?: null,
                'raw_x' => null, 'raw_y' => null, 'raw_z' => null,
                // Teamcraft coordinates are already map coordinates.
                'x' => $p['x'], 'y' => $p['y'], 'source' => 'teamcraft', 'source_key' => (string) $id,
            ];
        }

        return $locations;
    }

    private function numeric(mixed $value): bool
    {
        return is_numeric($value) && is_finite((float) $value);
    }

    private function usable(mixed $x, mixed $y): bool
    {
        return $this->numeric($x) && $this->numeric($y) && $x > 0 && $y > 0;
    }
}
