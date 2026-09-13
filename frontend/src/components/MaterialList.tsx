import { useId, useMemo, useState } from "react";
import type { Material } from "../types/Material";
import { readSavedValue, saveValue } from "../services/plannerStorage";

type MaterialListProps = {
    title: string;
    materials: Material[];
    storageKey: string;
};

type MaterialStatus = "default" | "collecting" | "collected";
type SortOrder = "name-asc" | "name-desc" | "quantity-desc" | "quantity-asc";

export const MaterialList = ({
    title,
    materials,
    storageKey,
}: MaterialListProps) => {
    const [isExpanded, setIsExpanded] = useState(true);
    const [sortOrder, setSortOrder] = useState<SortOrder>("name-asc");
    const listId = useId();
    const [storageError, setStorageError] = useState(false);

    const [materialStatuses, setMaterialStatuses] = useState<
        Record<number, MaterialStatus>
    >(() => {
        const saved = readSavedValue(storageKey);
        if (!saved || typeof saved !== "object" || Array.isArray(saved)) return {};
        return Object.fromEntries(Object.entries(saved).filter(([id, status]) =>
            /^\d+$/.test(id) && (status === "collecting" || status === "collected")
        )) as Record<number, MaterialStatus>;
    });

    const sortedMaterials = useMemo(
        () =>
            [...materials].sort((a, b) => {
                const alphabetical = a.name.localeCompare(b.name);
                switch (sortOrder) {
                    case "name-desc": return -alphabetical;
                    case "quantity-desc": return b.quantity - a.quantity || alphabetical;
                    case "quantity-asc": return a.quantity - b.quantity || alphabetical;
                    default: return alphabetical;
                }
            }),
        [materials, sortOrder]
    );

    const cycleStatus = (materialId: number) => {
            const status = materialStatuses[materialId] ?? "default";

            const nextStatus: MaterialStatus =
                status === "default"
                    ? "collecting"
                    : status === "collecting"
                        ? "collected"
                        : "default";

            const next = {
                ...materialStatuses,
                [materialId]: nextStatus,
            };
            if (nextStatus === "default") delete next[materialId];
            setMaterialStatuses(next);
            setStorageError(!saveValue(storageKey, next));
    };

    return (
        <section className="overflow-hidden rounded-xl border border-slate-800 bg-slate-900">
            <h2 className="text-lg font-semibold text-white">
                <button
                    type="button"
                    aria-expanded={isExpanded}
                    aria-controls={listId}
                    onClick={() => setIsExpanded((current) => !current)}
                    className="flex w-full items-center justify-between gap-3 px-6 py-4 text-left transition-colors hover:bg-slate-800/50 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-blue-500"
                >
                    <span>
                        {title}
                        <span className="ml-2 text-sm font-normal text-slate-400">
                            ({sortedMaterials.length})
                        </span>
                    </span>
                    <svg
                        aria-hidden="true"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        strokeWidth={2}
                        className={`h-5 w-5 shrink-0 text-slate-400 transition-transform ${isExpanded ? "rotate-180" : ""}`}
                    >
                        <path d="m6 9 6 6 6-6" strokeLinecap="round" strokeLinejoin="round" />
                    </svg>
                </button>
            </h2>

            <div className="flex flex-wrap items-center justify-between gap-3 border-t border-slate-800 px-6 py-2">
                <label className="flex flex-wrap items-center gap-2 text-sm text-slate-400">
                    Sort by
                    <select
                        aria-label={`Sort ${title}`}
                        value={sortOrder}
                        onChange={(event) => setSortOrder(event.target.value as SortOrder)}
                        className="max-w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-white focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20"
                    >
                        <option value="name-asc">Name: A–Z</option>
                        <option value="name-desc">Name: Z–A</option>
                        <option value="quantity-desc">Quantity: highest first</option>
                        <option value="quantity-asc">Quantity: lowest first</option>
                    </select>
                </label>
                <button
                    type="button"
                    aria-label={`Reset progress for ${title}`}
                    disabled={Object.keys(materialStatuses).length === 0}
                    onClick={() => {
                        setMaterialStatuses({});
                        setStorageError(!saveValue(storageKey, {}));
                    }}
                    className="text-sm text-slate-400 hover:text-white disabled:opacity-40 disabled:cursor-not-allowed"
                >
                    Reset progress
                </button>
            </div>
            {storageError && (
                <p className="px-6 pb-3 text-sm text-amber-400">
                    Your browser could not save progress. Changes may be lost after a reload.
                </p>
            )}

            <ul id={listId} hidden={!isExpanded} className="divide-y divide-slate-800 border-t border-slate-800">
                {sortedMaterials.map((material) => {

                    const status =
                        materialStatuses[material.id] ?? "default";

                    return (
                        <li key={material.id}>
                            <button
                                type="button"
                                onClick={() => cycleStatus(material.id)}
                                className={`grid w-full grid-cols-[minmax(0,1fr)_5rem_7ch] items-center gap-3 px-6 py-3 text-left transition-colors ${status === "collecting"
                                    ? "bg-amber-950/40 hover:bg-amber-950/60"
                                    : status === "collected"
                                        ? "bg-emerald-950/40 hover:bg-emerald-950/60"
                                        : "hover:bg-slate-800/50"
                                    }`}
                            >
                                    <span
                                        className={`min-w-0 break-words ${
                                            status === "collected"
                                                ? "text-slate-500 line-through"
                                                : "text-slate-200"
                                        }`}
                                    >
                                        {material.name}
                                    </span>

                                <span
                                    className={`text-xs font-medium ${status === "collecting"
                                        ? "text-amber-400"
                                        : "text-emerald-400"
                                        }`}
                                >
                                    {status === "collecting"
                                        ? "Collecting"
                                        : status === "collected"
                                            ? "Collected"
                                            : ""}
                                </span>

                                <span className="justify-self-end rounded-md bg-slate-800 px-2 py-1 text-sm font-semibold tabular-nums text-slate-200">
                                    ×{material.quantity}
                                </span>
                            </button>
                        </li>
                    );
                })}
            </ul>
        </section>
    );
};
