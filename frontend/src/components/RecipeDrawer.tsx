import { useEffect, useRef, useState } from "react";
import type { SearchCriteria } from "../services/plannerStorage";
import { getRecipes } from "../services/recipeService";
import type { Recipe } from "../types/Recipe";
import { RecipeList } from "./RecipeList";

const Drawer = ({ criteria, onClose }: { criteria: SearchCriteria; onClose: () => void }) => {
    const dialog = useRef<HTMLDialogElement>(null);
    const [recipes, setRecipes] = useState<Recipe[]>([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(false);
    const [attempt, setAttempt] = useState(0);

    useEffect(() => {
        const element = dialog.current!;
        const previousOverflow = document.body.style.overflow;
        if (!element.open) element.showModal();
        document.body.style.overflow = "hidden";
        return () => {
            document.body.style.overflow = previousOverflow;
        };
    }, []);

    useEffect(() => {
        const controller = new AbortController();
        getRecipes(criteria.job, criteria.minLevel, criteria.maxLevel, criteria.includeSpecialSources ?? true, controller.signal)
            .then((data) => {
                if (!controller.signal.aborted) setRecipes(data);
            })
            .catch(() => {
                if (!controller.signal.aborted) setError(true);
            })
            .finally(() => {
                if (!controller.signal.aborted) setLoading(false);
            });
        return () => controller.abort();
    }, [criteria, attempt]);

    return (
        <dialog
            ref={dialog}
            aria-labelledby="planned-recipes-title"
            onClose={onClose}
            onClick={(event) => {
                if (event.target !== event.currentTarget) return;
                const bounds = event.currentTarget.getBoundingClientRect();
                if (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom) {
                    event.currentTarget.close();
                }
            }}
            className="fixed inset-y-0 left-auto right-0 m-0 h-dvh max-h-none w-full max-w-md border-l border-slate-700 bg-slate-900 p-0 text-white shadow-2xl backdrop:bg-black/60"
        >
            <div className="flex h-full flex-col">
                <header className="flex items-start justify-between gap-4 border-b border-slate-800 p-6">
                    <div>
                        <h2 id="planned-recipes-title" className="text-xl font-semibold">Items to craft</h2>
                        <p className="mt-2 text-sm text-slate-400">{criteria.job} · Levels {criteria.minLevel}–{criteria.maxLevel}</p>
                        <p className="mt-1 text-xs text-slate-400">Special sources {criteria.includeSpecialSources === false ? "excluded" : "included"}</p>
                    </div>
                    <button type="button" onClick={() => dialog.current?.close()} aria-label="Close items to craft"
                        className="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg p-0 text-slate-400 transition-colors hover:bg-slate-800 hover:text-white focus-visible:outline-2 focus-visible:outline-blue-500">
                        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" className="block h-5 w-5">
                            <path d="m6 6 12 12M18 6 6 18" />
                        </svg>
                    </button>
                </header>
                <div className="min-h-0 flex-1 overflow-y-auto px-6 py-4" aria-busy={loading}>
                    {loading ? <p role="status" className="text-slate-400">Loading recipes…</p> : error ? (
                        <div role="alert">
                            <p className="text-red-400">Could not load the recipes. Please try again.</p>
                            <button type="button" onClick={() => { setError(false); setLoading(true); setAttempt((value) => value + 1); }}
                                className="mt-3 rounded-lg bg-blue-600 px-4 py-2 hover:bg-blue-500">Retry</button>
                        </div>
                    ) : recipes.length === 0 ? (
                        <p className="text-slate-400">No recipes match this level range and source setting.</p>
                    ) : (
                        <>
                            <p className="mb-2 text-sm text-slate-400">{recipes.length} recipes · One craft per recipe. Quantities show the items produced.</p>
                            <RecipeList recipes={recipes} />
                        </>
                    )}
                </div>
            </div>
        </dialog>
    );
};

export const RecipeDrawer = ({ criteria, disabled }: { criteria: SearchCriteria; disabled: boolean }) => {
    const [open, setOpen] = useState(false);
    const trigger = useRef<HTMLButtonElement>(null);
    return (
        <>
            <button ref={trigger} type="button" disabled={disabled} aria-haspopup="dialog" onClick={() => setOpen(true)}
                className="rounded-lg border border-slate-700 bg-slate-900 px-4 py-2 text-sm font-medium text-slate-200 hover:bg-slate-800 disabled:opacity-50 focus-visible:outline-2 focus-visible:outline-blue-500">
                View items to craft
            </button>
            {open && <Drawer criteria={criteria} onClose={() => { setOpen(false); trigger.current?.focus(); }} />}
        </>
    );
};
