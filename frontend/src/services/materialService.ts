import type { Material } from "../types/Material";

export const getCraftingMaterials = async (
    job: string,
    minLevel: number,
    maxLevel: number
): Promise<Material[]> => {
    const params = new URLSearchParams({
        job,
        minLevel: minLevel.toString(),
        maxLevel: maxLevel.toString(),
    });

    const response = await fetch(
        `http://localhost:8080/api/crafting-materials?${params.toString()}`
    );

    if (!response.ok) {
        throw new Error("Failed to fetch crafting materials.");
    }

    const materials: Material[] = await response.json();

    return materials;
};

export const getExpandedMaterials = async (
    job: string,
    minLevel: number,
    maxLevel: number
): Promise<Material[]> => {
    const params = new URLSearchParams({
        job,
        minLevel: minLevel.toString(),
        maxLevel: maxLevel.toString(),
    });

    const response = await fetch(
        `http://localhost:8080/api/expanded-materials?${params.toString()}`
    );

    if (!response.ok) {
        throw new Error("Failed to fetch expanded materials.");
    }

    const materials: Material[] = await response.json();

    return materials;
};