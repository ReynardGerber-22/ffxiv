import type { Material } from "../types/Material";
const API_URL = import.meta.env.VITE_API_URL;

const getMaterials = async (
    endpoint: string,
    job: string,
    minLevel: number,
    maxLevel: number,
    includeSpecialSources = true
): Promise<Material[]> => {
    const params = new URLSearchParams({
        job,
        minLevel: minLevel.toString(),
        maxLevel: maxLevel.toString(),
        includeSpecialSources: includeSpecialSources ? "1" : "0",
    });

    const response = await fetch(
        `${API_URL}${endpoint}?${params.toString()}`
    );

    if (!response.ok) {
        throw new Error(`Failed to fetch materials from ${endpoint}.`);
    }

    const materials: Material[] = await response.json();

    return materials;
};

export const getCraftingMaterials = (
    job: string,
    minLevel: number,
    maxLevel: number,
    includeSpecialSources = true
): Promise<Material[]> => {
    return getMaterials(
        "/api/crafting-materials",
        job,
        minLevel,
        maxLevel,
        includeSpecialSources
    );
};

export const getExpandedMaterials = (
    job: string,
    minLevel: number,
    maxLevel: number,
    includeSpecialSources = true
): Promise<Material[]> => {
    return getMaterials(
        "/api/expanded-materials",
        job,
        minLevel,
        maxLevel,
        includeSpecialSources
    );
};