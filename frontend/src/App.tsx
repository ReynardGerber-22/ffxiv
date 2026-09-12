import { useState, useRef } from "react";
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


  const [hasSearched, setHasSearched] = useState<boolean>(false);


  const [loading, setLoading] = useState<boolean>(false);
  const [error, setError] = useState<string | null>(null);


  const isSearching = useRef<boolean>(false);

  const searchMaterials = async (
    job: string,
    minLevel: number,
    maxLevel: number
  ) => {
    if (isSearching.current) {
      return;
    }

    isSearching.current = true;
    setLoading(true);
    setError(null);

    try {
      const [craftingData, expandedData] = await Promise.all([
        getCraftingMaterials(job, minLevel, maxLevel),
        getExpandedMaterials(job, minLevel, maxLevel),
      ]);

      setCraftingMaterials(craftingData);
      setExpandedMaterials(expandedData);
      setHasSearched(true);
    } catch (error) {
      console.error(error);
      setError("Something went wrong while calculating materials.");
    } finally {
      isSearching.current = false;
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
    <main className="min-h-screen bg-slate-950 px-6 py-10 text-white">
      <div className="mx-auto max-w-5xl">
        <header className="mb-10">
          <h1 className="text-4xl font-bold tracking-tight">
            FFXIV Crafting Planner
          </h1>

          <p className="mt-3 text-slate-400">
            Calculate the crafted materials, raw materials, and crystals
            needed for a selected crafting level range.
          </p>
        </header>

        <CraftingSearch loading={loading} onSearch={searchMaterials} />

        {error && (
          <p className="mt-6 text-red-400">
            {error}
          </p>
        )}

        {hasSearched && (
          <div className="mt-10 space-y-8">
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
          </div>
        )}
      </div>
    </main>
  );
}

export default App;