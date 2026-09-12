import { useState } from "react";
import type { Material } from "./types/Material";
import { CraftingSearch } from "./components/CraftingSearch";

import {
  getCraftingMaterials,
  getExpandedMaterials,
} from "./services/materialService";
import { MaterialList } from "./components/MaterialList";

function App() {
  const [craftingMaterials, setCraftingMaterials] =
    useState<Material[]>([]);

  const [expandedMaterials, setExpandedMaterials] =
    useState<Material[]>([]);

  const searchMaterials = async (
    job: string,
    minLevel: number,
    maxLevel: number
  ) => {
    const [craftingData, expandedData] = await Promise.all([
      getCraftingMaterials(job, minLevel, maxLevel),
      getExpandedMaterials(job, minLevel, maxLevel),
    ]);

    setCraftingMaterials(craftingData);
    setExpandedMaterials(expandedData);
  };

  const crystals = expandedMaterials.filter((material) =>
    ["Shard", "Crystal", "Cluster"].some((type) =>
      material.name.includes(type)
    )
  );

  const rawMaterials = expandedMaterials.filter(
    (material) =>
      !["Shard", "Crystal", "Cluster"].some((type) =>
        material.name.includes(type)
      )
  );

  return (
    <main>
      <CraftingSearch onSearch={searchMaterials} />
      <MaterialList
        title="Craft These"
        materials={craftingMaterials}
      />

      <MaterialList
        title="Raw Materials"
        materials={rawMaterials}
      />

      <MaterialList
        title="Crystals"
        materials={crystals}
      />
    </main>
  );
}

export default App;