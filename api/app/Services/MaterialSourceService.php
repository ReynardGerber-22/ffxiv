<?php

namespace App\Services;

class MaterialSourceService
{
    public function __construct(
        private GatheringService $gatheringService,
        private MobDropService $mobDropService,
        private FishingService $fishingService,
    ) {}

    public function enrichMaterials(array $materials): array
    {
        $materials = $this->gatheringService->enrichMaterials($materials);
        $materials = $this->fishingService->enrichMaterials($materials);
        return $this->mobDropService->enrichMaterials($materials);
    }
}
