export interface CraftableItem {
  id: number;
  name: string;
  profession: string;
  level: number;
  amountResult: number;
}

export interface CustomCraftingItem {
  id: number;
  name: string;
  quantity: number;
  profession: string;
  level: number;
}