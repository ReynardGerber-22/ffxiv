import { useState } from "react";
import type { Material } from "./types/Material";
import { RecipeSearch } from "./components/RecipeSearch";
import {
  getMaterials,
  getExpandedMaterials,
} from "./services/materialService";

function App() {
  const [materials, setMaterials] = useState<Material[]>([]);
  const [expandedMaterials, setExpandedMaterials] = useState<Material[]>([]);

  const searchMaterials = async (
    job: string,
    minLevel: number,
    maxLevel: number
  ) => {
    const [materialsData, expandedMaterialsData] = await Promise.all([
      getMaterials(job, minLevel, maxLevel),
      getExpandedMaterials(job, minLevel, maxLevel),
    ]);

    setMaterials(materialsData);
    setExpandedMaterials(expandedMaterialsData);
  };

  const shards = expandedMaterials.filter((material) =>
    material.name.includes("Shard")
  );

  const rawMaterials = expandedMaterials.filter(
    (material) => !material.name.includes("Shard")
  );

  return (
    <main>
      <h1>FFXIV Crafting Planner</h1>

      <RecipeSearch onSearch={searchMaterials} />

      <h2>Materials to Prepare</h2>

      <ul>
        {materials.map((material) => (
          <li key={material.id}>
            {material.name} - {material.quantity}
          </li>
        ))}
      </ul>

      <h2>Raw Materials</h2>

      <ul>
        {rawMaterials.map((material) => (
          <li key={material.id}>
            {material.name} - {material.quantity}
          </li>
        ))}
      </ul>

      <h2>Shards</h2>

      <ul>
        {shards.map((material) => (
          <li key={material.id}>
            {material.name} - {material.quantity}
          </li>
        ))}
      </ul>
    </main>
  );
}

export default App;