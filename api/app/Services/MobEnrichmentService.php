<?php

namespace App\Services;

class MobEnrichmentService
{
    public function __construct(
        private XivApiService $xivApiService
    ) {
    }

    public function getMobName(int $bnpcNameId): ?string
    {
        $response = $this->xivApiService->getSheetRow(
            'BNpcName',
            $bnpcNameId,
            'Singular'
        );

        return $response['fields']['Singular'] ?? null;
    }

    public function getTerritoryName(int $territoryTypeId): ?string
    {
        $response = $this->xivApiService->getSheetRow(
            'TerritoryType',
            $territoryTypeId,
            'PlaceName.Name'
        );

        return $response['fields']['PlaceName']['fields']['Name'] ?? null;
    }
}