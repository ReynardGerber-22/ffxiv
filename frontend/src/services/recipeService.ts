import type { Recipe } from "../types/Recipe";

export const getRecipes = async (
    job: string,
    minLevel: number,
    maxLevel: number,
    includeSpecialSources = true,
    signal?: AbortSignal
): Promise<Recipe[]> => {
    const params = new URLSearchParams({
        job,
        minLevel: minLevel.toString(),
        maxLevel: maxLevel.toString(),
        includeSpecialSources: includeSpecialSources ? "1" : "0",
    });

    const response = await fetch(
        `${import.meta.env.VITE_API_URL ?? ""}/api/recipes?${params.toString()}`,
        { signal }
    );

    if (!response.ok) throw new Error("Could not load planned recipes.");

    const recipes: Recipe[] = await response.json();

    return recipes;
};