import type { Recipe } from "../types/Recipe";

export const getRecipes = async (
    job: string,
    minLevel: number,
    maxLevel: number
): Promise<Recipe[]> => {
    const params = new URLSearchParams({
        job,
        minLevel: minLevel.toString(),
        maxLevel: maxLevel.toString(),
    });

    const response = await fetch(
        `http://localhost:8080/api/recipes?${params.toString()}`
    );

    const recipes: Recipe[] = await response.json();

    return recipes;
};