import { useState, useRef } from "react";
import type { Material } from "./types/Material";
import { CraftingSearch } from "./components/CraftingSearch";

import {
  getCraftingMaterials,
  getExpandedMaterials,
} from "./services/materialService";
import { MaterialList } from "./components/MaterialList";

import { loadPlan, savePlan, progressKey, type SearchCriteria } from "./services/plannerStorage";

const crystalTypes = ["Shard", "Crystal", "Cluster"];

const isCrystal = (material: Material) =>
  crystalTypes.some((type) => material.name.includes(type));

function App() {
  const [savedPlan] = useState(loadPlan);
  const [storageError, setStorageError] = useState(false);
  const [includeSpecialSources, setIncludeSpecialSources] = useState(savedPlan?.criteria.includeSpecialSources ?? true);
  const [craftingMaterials, setCraftingMaterials] =
    useState<Material[]>(savedPlan?.craftingMaterials ?? []);

  const [expandedMaterials, setExpandedMaterials] =
    useState<Material[]>(savedPlan?.expandedMaterials ?? []);

  const [searchCriteria, setSearchCriteria] =
    useState<SearchCriteria | null>(savedPlan?.criteria ?? null);

  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const isSearching = useRef(false);


  const searchMaterials = async (
    job: string,
    minLevel: number,
    maxLevel: number,
    includeDrops = includeSpecialSources
  ) => {
    if (isSearching.current) {
      return;
    }
    isSearching.current = true;
    setLoading(true);
    setError(null);

    try {
      const [craftingData, expandedData] = await Promise.all([
        getCraftingMaterials(job, minLevel, maxLevel, includeDrops),
        getExpandedMaterials(job, minLevel, maxLevel, includeDrops),
      ]);

      setCraftingMaterials(craftingData);
      setExpandedMaterials(expandedData);
      const criteria = { job, minLevel, maxLevel, includeSpecialSources: includeDrops };
      setIncludeSpecialSources(includeDrops);
      setSearchCriteria(criteria);
      setStorageError(!savePlan({ criteria, craftingMaterials: craftingData, expandedMaterials: expandedData }));

    } catch (error) {
      console.error(error);
      setError("Something went wrong while calculating materials.");
    } finally {
      isSearching.current = false;
      setLoading(false);
    }
  };
  const crystals: Material[] = [];
  const rawMaterials: Material[] = [];

  expandedMaterials.forEach((material) => {
    if (isCrystal(material)) {
      crystals.push(material);
    } else {
      rawMaterials.push(material);
    }
  });

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

        <div className="mb-4 space-y-2">
          <label className="flex items-center gap-3 text-sm text-slate-200">
            <input
              type="checkbox"
              checked={includeSpecialSources}
              disabled={loading}
              onChange={(event) => {
                const include = event.target.checked;
                if (searchCriteria) {
                  void searchMaterials(searchCriteria.job, searchCriteria.minLevel, searchCriteria.maxLevel, include);
                } else {
                  setIncludeSpecialSources(include);
                }
              }}
              className="h-4 w-4 accent-blue-500"
              aria-describedby="special-sources-description"
            />
            Include recipes requiring special sources
          </label>
          <p id="special-sources-description" className="text-sm text-slate-400">
            Includes duty drops, special-currency purchases (such as tomestones), treasure maps, and voyages. Uncheck to exclude these recipes and recalculate all materials. Ordinary gathering, fishing, gil purchases, and open-world mob sources remain included.
          </p>
          {loading && <p role="status" className="text-sm text-slate-400">Recalculating materials…</p>}
        </div>

        <CraftingSearch loading={loading} onSearch={searchMaterials} initialCriteria={savedPlan?.criteria} />

        {storageError && <p className="mt-4 text-amber-400">Your browser could not save this plan. It may not be restored after a reload.</p>}

        {error && (
          <p className="mt-6 text-red-400">
            {error}
          </p>
        )}

        {searchCriteria && (
          <div className="mt-10 space-y-8" aria-busy={loading}>
            <div>
              <h2 className="text-xl font-semibold text-white">
                {searchCriteria.job}
              </h2>

              <p className="mt-1 text-sm text-slate-400">
                Levels {searchCriteria.minLevel}–{searchCriteria.maxLevel}
              </p>
            </div>

            <MaterialList
              title="Raw Materials"
              key={progressKey(searchCriteria, "raw")}
              storageKey={progressKey(searchCriteria, "raw")}
              materials={rawMaterials}
            />

            <MaterialList
              title="Crystals"
              key={progressKey(searchCriteria, "crystals")}
              storageKey={progressKey(searchCriteria, "crystals")}
              materials={crystals}
            />

            <MaterialList
              title="Craft These"
              isCrafting
              key={progressKey(searchCriteria, "crafting")}
              storageKey={progressKey(searchCriteria, "crafting")}
              materials={craftingMaterials}
            />
          </div>
        )}
      </div>
    </main>
  );
}

export default App;