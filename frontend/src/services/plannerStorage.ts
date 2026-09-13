import type { Material } from "../types/Material";

export type SearchCriteria = {
    job: string;
    minLevel: number;
    maxLevel: number;
};

type SavedPlan = {
    criteria: SearchCriteria;
    craftingMaterials: Material[];
    expandedMaterials: Material[];
};

export const readSavedValue = (key: string): unknown => {
    try {
        return JSON.parse(localStorage.getItem(key) ?? "null");
    } catch {
        return null;
    }
};

export const saveValue = (key: string, value: unknown): boolean => {
    try {
        localStorage.setItem(key, JSON.stringify(value));
        return true;
    } catch {
        return false;
    }
};

const planKey = "ffxiv:plan:v1";
const jobs = ["Carpenter", "Blacksmith", "Armorer", "Goldsmith", "Leatherworker", "Weaver", "Alchemist", "Culinarian"];

const isMaterialList = (value: unknown): value is Material[] =>
    Array.isArray(value) && value.every((item) =>
        item && Number.isInteger(item.id) && typeof item.name === "string"
        && Number.isFinite(item.quantity) && item.quantity >= 0);

export const loadPlan = (): SavedPlan | null => {
    const value = readSavedValue(planKey);
    if (!value || typeof value !== "object") return null;
    const plan = value as Partial<SavedPlan>;
    const criteria = plan.criteria;
    if (!criteria || !jobs.includes(criteria.job)
        || !Number.isInteger(criteria.minLevel) || !Number.isInteger(criteria.maxLevel)
        || criteria.minLevel < 1 || criteria.maxLevel > 100
        || criteria.minLevel > criteria.maxLevel
        || !isMaterialList(plan.craftingMaterials)
        || !isMaterialList(plan.expandedMaterials)) return null;
    return { criteria, craftingMaterials: plan.craftingMaterials, expandedMaterials: plan.expandedMaterials };
};

export const savePlan = (plan: SavedPlan) => saveValue(planKey, plan);

export const progressKey = (criteria: SearchCriteria, section: string) =>
    `ffxiv:progress:v1:${criteria.job}:${criteria.minLevel}:${criteria.maxLevel}:${section}`;
