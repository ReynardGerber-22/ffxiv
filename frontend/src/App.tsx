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

  const [loading, setLoading] = useState<boolean>(false);
  const [error, setError] = useState<string | null>(null);

  const searchMaterials = async (
    job: string,
    minLevel: number,
    maxLevel: number
  ) => {
    setLoading(true);
    setError(null);

    try {
      const [craftingData, expandedData] = await Promise.all([
        getCraftingMaterials(job, minLevel, maxLevel),
        getExpandedMaterials(job, minLevel, maxLevel),
      ]);

      setCraftingMaterials(craftingData);
      setExpandedMaterials(expandedData);
    } catch (error) {
      console.error(error);

      setError("Something went wrong while calculating materials.");
    } finally {
      setLoading(false);
    }
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
      {loading && <p>Calculating materials...</p>}
      {error && <p>{error}</p>}

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