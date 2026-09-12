import type { Recipe } from "../types/Recipe";

type RecipeListProps = {
    recipes: Recipe[];
};

export const RecipeList = ({ recipes }: RecipeListProps) => {
    return (
        <ul>
            {recipes.map((recipe) => (
                <li key={recipe.id}>
                    {recipe.name} - Level {recipe.level} {recipe.job}
                </li>
            ))}
        </ul>
    );
};