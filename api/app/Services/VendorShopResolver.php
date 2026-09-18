<?php

namespace App\Services;

class VendorShopResolver
{
    public function __construct(private XivApiService $xiv) {}

    public function resolve(array $shopIds): array
    {
        $pending = $shopIds;
        $seen = [];
        $parents = [];
        $npcs = [];
        $handlers = [];

        // Traverse reverse links, so only handlers leading to a verified GilShop qualify.
        while ($pending !== []) {
            $batch = array_values(array_diff(array_unique($pending), array_keys($seen)));
            $pending = [];
            foreach (array_chunk($batch, 40) as $ids) {
                foreach ($ids as $id) {
                    $seen[$id] = true;
                }
                $query = implode(' ', array_map(fn ($id) => 'ENpcData[]='.$id, $ids));
                foreach ($this->xiv->searchAll('ENpcBase', $query, 'ENpcData@as(raw)') as $row) {
                    foreach (array_intersect($row['fields']['ENpcData@as(raw)'] ?? [], $ids) as $id) {
                        $npcs[$id][$row['row_id']] = true;
                    }
                }
                foreach (['PreHandler' => 'Target', 'TopicSelect' => 'Shop[]'] as $sheet => $field) {
                    $query = implode(' ', array_map(fn ($id) => $field.'='.$id, $ids));
                    $fields = $sheet === 'PreHandler'
                        ? 'Target@as(raw),UnlockQuest@as(raw)'
                        : 'Shop@as(raw),Name';
                    foreach ($this->xiv->searchAll($sheet, $query, $fields) as $row) {
                        $id = $row['row_id'];
                        $handlers[$id] = ['sheet' => $sheet, 'fields' => $row['fields']];
                        $targets = $sheet === 'PreHandler'
                            ? [$row['fields']['Target@as(raw)']]
                            : $row['fields']['Shop@as(raw)'];
                        foreach (array_intersect($targets, $ids) as $target) {
                            $parents[$target][$id] = true;
                        }
                        if (! isset($seen[$id])) {
                            $pending[] = $id;
                        }
                    }
                }
            }
        }

        $links = [];
        foreach ($shopIds as $shopId) {
            $queue = [[$shopId]];
            $visited = [];
            while ($queue !== []) {
                $path = array_shift($queue);
                $id = $path[count($path) - 1];
                if (isset($visited[$id])) {
                    continue;
                }
                $visited[$id] = true;
                foreach (array_keys($npcs[$id] ?? []) as $npcId) {
                    // Multiple menu paths must not duplicate the vendor/shop relationship.
                    $links[$npcId][$shopId]['paths'][] = array_reverse($path);
                    $links[$npcId][$shopId]['handlers'] = array_replace(
                        $links[$npcId][$shopId]['handlers'] ?? [],
                        array_intersect_key($handlers, array_flip($path)),
                    );
                }
                foreach (array_keys($parents[$id] ?? []) as $parent) {
                    $queue[] = [...$path, $parent];
                }
            }
        }

        return $links;
    }
}
