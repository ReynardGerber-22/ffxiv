import { useId, useMemo, useState } from "react";
import type { Material } from "../types/Material";
import { MaterialRow, type MaterialStatus } from "./MaterialRow";
import { MaterialLocationGroup } from "./MaterialLocationGroup";
import { readSavedValue, saveValue } from "../services/plannerStorage";

type MaterialListProps = {
    title: string;
    materials: Material[];
    storageKey: string;
    isCrafting?: boolean;
};

type SortOrder = "name-asc" | "name-desc" | "quantity-desc" | "quantity-asc" | "location" | "profession";

type LocationGroup = {
    territory: string;
    materials: Material[];
};

const UNKNOWN_LOCATION = "Other / Unknown Location";

const materialTerritories = (material: Material): string[] => {
    const territories = new Set<string>();
    const add = (territory: string | null | undefined) => {
        const normalized = territory?.trim();
        if (normalized) territories.add(normalized);
    };

    material.gathering?.forEach((node) => add(node.territory));
    material.fishing?.forEach((spot) => add(spot.territory));
    material.mobDrops?.forEach((drop) => drop.territories?.forEach((territory) => add(territory.territory)));
    material.vendors?.forEach((vendor) => {
        if (vendor.locationResolved) add(vendor.territory);
    });

    return [...territories];
};

const locationGroups = (materials: Material[]): LocationGroup[] => {
    const groups = new Map<string, Material[]>();

    materials.forEach((material) => {
        const territories = materialTerritories(material);
        (territories.length > 0 ? territories : [UNKNOWN_LOCATION]).forEach((territory) => {
            const group = groups.get(territory) ?? [];
            group.push(material);
            groups.set(territory, group);
        });
    });

    return [...groups.entries()]
        .sort(([territoryA], [territoryB]) => {
            if (territoryA === UNKNOWN_LOCATION) return 1;
            if (territoryB === UNKNOWN_LOCATION) return -1;
            return territoryA.localeCompare(territoryB);
        })
        .map(([territory, groupMaterials]) => ({
            territory,
            materials: groupMaterials.sort((a, b) => a.name.localeCompare(b.name)),
        }));
};

export const MaterialList = ({
    title,
    materials,
    storageKey,
    isCrafting = false,
}: MaterialListProps) => {
    const [isExpanded, setIsExpanded] = useState(true);
    const [sortOrder, setSortOrder] = useState<SortOrder>("name-asc");
    const [expandedGathering, setExpandedGathering] = useState<Record<number, boolean>>({});
    const [expandedMobDrops, setExpandedMobDrops] = useState<Record<number, boolean>>({});
    const [expandedFishing, setExpandedFishing] = useState<Record<number, boolean>>({});

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
                    case "profession": {
                        const professionA = a.profession?.trim() ?? "";
                        const professionB = b.profession?.trim() ?? "";
                        if (!professionA && professionB) return 1;
                        if (professionA && !professionB) return -1;
                        return professionA.localeCompare(professionB) || alphabetical;
                    }
                    case "name-desc": return -alphabetical;
                    case "quantity-desc": return b.quantity - a.quantity || alphabetical;
                    case "quantity-asc": return a.quantity - b.quantity || alphabetical;
                    default: return alphabetical;
                }
            }),
        [materials, sortOrder]
    );
    const groupedMaterials = useMemo(
        () => sortOrder === "location" ? locationGroups(materials) : [],
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

    const renderMaterial = (material: Material) => (
        <MaterialRow
            key={material.id}
            material={material}
            isCrafting={isCrafting}
            status={materialStatuses[material.id] ?? "default"}
            isGatheringExpanded={expandedGathering[material.id] ?? false}
            isFishingExpanded={expandedFishing[material.id] ?? false}
            isMobDropsExpanded={expandedMobDrops[material.id] ?? false}
            onCycleStatus={() => cycleStatus(material.id)}
            onToggleGathering={() => setExpandedGathering((current) => ({
                ...current,
                [material.id]: !current[material.id],
            }))}
            onToggleFishing={() => setExpandedFishing((current) => ({
                ...current,
                [material.id]: !current[material.id],
            }))}
            onToggleMobDrops={() => setExpandedMobDrops((current) => ({
                ...current,
                [material.id]: !current[material.id],
            }))}
        />
    );

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
                        {isCrafting && <option value="profession">Profession: A–Z</option>}
                        <option value="location">Location</option>
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

            <ul
                id={listId}
                hidden={!isExpanded}
                aria-label={`${title} items`}
                className="divide-y divide-slate-800 border-t border-slate-800 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-blue-500"
            >
                {sortOrder === "location" ? groupedMaterials.map((group) => (
                    <MaterialLocationGroup key={group.territory} territory={group.territory} count={group.materials.length}>
                        {group.materials.map(renderMaterial)}
                    </MaterialLocationGroup>
                )) : sortedMaterials.map(renderMaterial)}
            </ul>
        </section>
    );
};
