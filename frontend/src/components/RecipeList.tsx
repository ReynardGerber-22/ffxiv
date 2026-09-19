import type { Recipe } from "../types/Recipe";

export const RecipeList = ({ recipes }: { recipes: Recipe[] }) => (
    <ul className="divide-y divide-slate-800" aria-label="Planned crafting recipes">
        {[...recipes].sort((a, b) => a.level - b.level || a.name.localeCompare(b.name)).map((recipe) => (
            <li key={recipe.id} className="flex items-start justify-between gap-4 py-4">
                <div>
                    <p className="font-medium text-slate-100">{recipe.name}</p>
                    <p className="mt-1 text-sm text-slate-400">Level {recipe.level}</p>
                </div>
                <span className="shrink-0 rounded bg-slate-800 px-2 py-1 text-sm tabular-nums text-slate-200">
                    ×{recipe.amountResult}
                </span>
            </li>
        ))}
    </ul>
);
