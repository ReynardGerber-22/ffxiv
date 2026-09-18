import type { MobDrop } from "../types/Material";

type MobDropLocationsProps = {
    materialId: number;
    drops: MobDrop[];
    isCollected: boolean;
    isExpanded: boolean;
};

export const MobDropLocations = ({
    materialId,
    drops,
    isCollected,
    isExpanded,
}: MobDropLocationsProps) => {
    if (drops.length === 0) {
        return null;
    }

    const visibleMobDrops = isExpanded ? drops : drops.slice(0, 2);

    const toTitleCase = (value: string) =>
        value.replace(/\b\w/g, (character) => character.toUpperCase());

    return (
        <>
            {visibleMobDrops.map((drop, index) => {
                const territories = drop.territories ?? [];
                const visibleTerritories = isExpanded ? territories : territories.slice(0, 2);

                return (
                    <div
                        key={`${materialId}-mob-${index}`}
                        className={`mt-4 first:mt-0 text-sm leading-relaxed ${isCollected
                                ? "text-slate-500"
                                : "text-slate-400"
                            }`}
                    >
                        <div className="font-medium text-slate-300">
                            {toTitleCase(drop.mob)}
                        </div>

                        {visibleTerritories.map((territory, territoryIndex) => (
                            <div
                                key={`${materialId}-mob-${index}-territory-${territoryIndex}`}
                                className="mt-2"
                            >
                                {!isExpanded ? (
                                    <div>
                                        <span>{territory.territory}</span>

                                        {territory.locations.length > 0 && (
                                            <>
                                                <span className="block tabular-nums">
                                                    X: {territory.locations[0].x} Y: {territory.locations[0].y}
                                                </span>

                                                {territory.locations.length > 1 && (
                                                    <span className="ml-1 text-slate-500">
                                                        (+{territory.locations.length - 1})
                                                    </span>
                                                )}
                                            </>
                                        )}
                                    </div>
                                ) : territory.locations.length === 1 ? (
                                    <div>
                                        <span>{territory.territory}</span>

                                        <span className="block tabular-nums text-slate-400">
                                            X: {territory.locations[0].x} Y: {territory.locations[0].y}
                                        </span>
                                    </div>
                                ) : (
                                    <div>
                                        <div>{territory.territory}</div>

                                        <div className="ml-3 text-slate-500">
                                            {territory.locations.map((location, locationIndex) => (
                                                <div
                                                    key={`${materialId}-mob-${index}-territory-${territoryIndex}-location-${locationIndex}`}
                                                    className="tabular-nums"
                                                >
                                                    X: {location.x} Y: {location.y}
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                )}
                            </div>
                        ))}
                        {!isExpanded && territories.length > 2 && (
                            <p className="mt-2 text-slate-500">+{territories.length - 2} more territories</p>
                        )}
                    </div>
                );
            })}
        </>
    );
};

type MobDropLocationsToggleProps = {
    drops: MobDrop[];
    isExpanded: boolean;
    onToggle: () => void;
};

export const MobDropLocationsToggle = ({
    drops,
    isExpanded,
    onToggle,
}: MobDropLocationsToggleProps) => {
    const hasHiddenDetails = drops.length > 2 || drops.some((drop) =>
        (drop.territories?.length ?? 0) > 2 ||
        drop.territories?.some((territory) => territory.locations.length > 1),
    );
    if (!hasHiddenDetails) return null;

    return (
        <button
            type="button"
            onClick={onToggle}
            aria-expanded={isExpanded}
            className="mt-4 block min-h-11 w-full text-left text-sm text-slate-400 transition-colors hover:text-white"
        >
            {isExpanded
                ? "Show fewer mob sources"
                : "Show all mob sources and locations"}
        </button>
    );
};
