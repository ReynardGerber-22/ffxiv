import type {
  CraftableItem,
  CustomCraftingItem,
} from "../types/CustomCraftingItem";
import type { Material } from "../types/Material";

const API_URL = import.meta.env.VITE_API_URL;

export const searchCraftableItems = async (
  search: string,
): Promise<CraftableItem[]> => {
  const params = new URLSearchParams({
    search,
  });

  const response = await fetch(
    `${API_URL}/api/craftable-items?${params.toString()}`,
  );

  if (!response.ok) {
    throw new Error("Failed to search craftable items.");
  }

  return response.json();
};

const getCustomMaterials = async (
  endpoint: string,
  items: CustomCraftingItem[],
): Promise<Material[]> => {
  const response = await fetch(`${API_URL}${endpoint}`, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
    },
    body: JSON.stringify({
      items: items.map((item) => ({
        id: item.id,
        quantity: item.quantity,
      })),
    }),
  });

  if (!response.ok) {
    throw new Error(`Failed to fetch materials from ${endpoint}.`);
  }

  return response.json();
};

export const getCustomExpandedMaterials = (
  items: CustomCraftingItem[],
): Promise<Material[]> => {
  return getCustomMaterials("/api/custom-materials", items);
};

export const getCustomCraftingMaterials = (
  items: CustomCraftingItem[],
): Promise<Material[]> => {
  return getCustomMaterials("/api/custom-crafting-materials", items);
};
