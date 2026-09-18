<?php

namespace App\Services;

class MaterialSourceService
{
    public function __construct(
        private GatheringService $gatheringService,
        private MobDropService $mobDropService,
        private FishingService $fishingService,
        private VendorService $vendorService,
    ) {}

    public function enrichMaterials(array $materials): array
    {
        $materials = $this->gatheringService->enrichMaterials($materials);
        $materials = $this->fishingService->enrichMaterials($materials);
        $materials = $this->mobDropService->enrichMaterials($materials);

        return $this->vendorService->enrichMaterials($materials);
    }
}
