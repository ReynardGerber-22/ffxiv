<?php

namespace App\Services;

class MaterialSourceService
{
    public function __construct(
        private GatheringService $gatheringService,
        private MobDropService $mobDropService,
    ) {}

    public function enrichMaterials(array $materials): array
    {
        $materials = $this->gatheringService->enrichMaterials($materials);

        return $this->mobDropService->enrichMaterials($materials);
    }
}
