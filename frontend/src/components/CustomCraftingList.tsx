import { useState } from "react";
import {
  getCustomCraftingMaterials,
  getCustomExpandedMaterials,
} from "../services/customCraftingService";
import type { Material } from "../types/Material";
import { CustomItemSearch } from "./CustomItemSearch";
import type {
  CraftableItem,
  CustomCraftingItem,
} from "../types/CustomCraftingItem";
import { MaterialList } from "./MaterialList";

export const CustomCraftingList = () => {
  const [items, setItems] = useState<CustomCraftingItem[]>([]);
  const [craftingMaterials, setCraftingMaterials] = useState<Material[]>([]);
  const [expandedMaterials, setExpandedMaterials] = useState<Material[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [hasCalculated, setHasCalculated] = useState(false);
  const handleQuantityChange = (id: number, quantity: number) => {
    if (!Number.isInteger(quantity) || quantity < 1) {
      return;
    }

    setHasCalculated(false);
    setItems((currentItems) =>
      currentItems.map((item) =>
        item.id === id ? { ...item, quantity } : item,
      ),
    );
  };

  const handleRemove = (id: number) => {
    setHasCalculated(false);
    setItems((currentItems) => currentItems.filter((item) => item.id !== id));
  };
  const handleCalculate = async () => {
    if (items.length === 0 || loading) {
      return;
    }

    setLoading(true);
    setError(null);

    try {
      const [craftingData, expandedData] = await Promise.all([
        getCustomCraftingMaterials(items),
        getCustomExpandedMaterials(items),
      ]);

      setCraftingMaterials(craftingData);
      setExpandedMaterials(expandedData);
  setHasCalculated(true);
    } catch (error) {
      console.error(error);
      setError("Something went wrong while calculating materials.");
    } finally {
      setLoading(false);
    }
  };

  const handleItemSelect = (item: CraftableItem) => {
    setItems((currentItems) => {
      const existingItem = currentItems.find(
        (currentItem) => currentItem.id === item.id,
      );

      if (existingItem) {
        return currentItems;
      }

      setHasCalculated(false);
      return [
        ...currentItems,
        {
          id: item.id,
          name: item.name,
          quantity: 1,
          profession: item.profession,
          level: item.level,
        },
      ];
    });
  };

  const crystalTypes = ["Shard", "Crystal", "Cluster"];

  const isCrystal = (material: Material) =>
    crystalTypes.some((type) => material.name.includes(type));

  const crystals = expandedMaterials.filter(isCrystal);
  const rawMaterials = expandedMaterials.filter(
    (material) => !isCrystal(material),
  );

  return (
    <section aria-labelledby="custom-crafting-title">
      <div className="mb-8">
        <h2 id="custom-crafting-title" className="text-2xl font-semibold text-white">
          Custom Crafting List
        </h2>
        <p className="mt-2 text-slate-400">
          Build a list of specific items, then calculate everything needed to make them.
        </p>
      </div>

      <div className="space-y-6 rounded-xl border border-slate-800 bg-slate-900 p-6">
        <CustomItemSearch onSelect={handleItemSelect} />

        <section aria-labelledby="items-to-craft-title">
          <div className="flex items-center justify-between gap-4 border-b border-slate-800 pb-3">
            <h3 id="items-to-craft-title" className="text-lg font-semibold text-white">
              Items to Craft
            </h3>
            <span className="text-sm text-slate-400">
              {items.length} {items.length === 1 ? "item" : "items"}
            </span>
          </div>

          {items.length > 0 ? (
            <ul className="divide-y divide-slate-800">
              {items.map((item) => (
                <li key={item.id} className="flex flex-wrap items-center justify-between gap-4 py-4">
                  <div>
                    <p className="font-medium text-slate-100">{item.name}</p>
                    <p className="mt-1 text-sm text-slate-400">
                      {item.profession} • Level {item.level}
                    </p>
                  </div>

                  <div className="flex items-end gap-4">
                    <label className="grid gap-1 text-sm text-slate-400">
                      Quantity
                      <input
                        type="number"
                        min="1"
                        step="1"
                        value={item.quantity}
                        onChange={(event) =>
                          handleQuantityChange(item.id, Number(event.target.value))
                        }
                        aria-label={`Quantity for ${item.name}`}
                        className="w-20 rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-white tabular-nums focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20"
                      />
                    </label>
                    <button
                      type="button"
                      onClick={() => handleRemove(item.id)}
                      className="pb-2 text-sm font-medium text-red-400 transition-colors hover:text-red-300 focus-visible:outline-2 focus-visible:outline-red-400"
                    >
                      Remove
                    </button>
                  </div>
                </li>
              ))}
            </ul>
          ) : (
            <p className="py-4 text-sm text-slate-400">
              Search above to add the items you want to craft.
            </p>
          )}
        </section>

        <button
          type="button"
          onClick={handleCalculate}
          disabled={items.length === 0 || loading}
          className="rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-medium text-white transition-colors hover:bg-blue-500 disabled:cursor-not-allowed disabled:opacity-50 focus-visible:outline-2 focus-visible:outline-blue-500"
        >
          {loading ? "Calculating materials..." : "Calculate Materials"}
        </button>
      </div>

      {error && <p role="alert" className="mt-6 text-red-400">{error}</p>}

      {hasCalculated && (rawMaterials.length > 0 || crystals.length > 0 || craftingMaterials.length > 0) && (
        <div className="mt-10 space-y-8" aria-busy={loading}>
          {rawMaterials.length > 0 && (
            <MaterialList
              title="Raw Materials"
              storageKey="custom-raw"
              materials={rawMaterials}
            />
          )}

          {crystals.length > 0 && (
            <MaterialList
              title="Crystals"
              storageKey="custom-crystals"
              materials={crystals}
            />
          )}

          {craftingMaterials.length > 0 && (
            <MaterialList
              title="Craft These"
              isCrafting
              storageKey="custom-crafting"
              materials={craftingMaterials}
            />
          )}
        </div>
      )}
    </section>
  );
};
