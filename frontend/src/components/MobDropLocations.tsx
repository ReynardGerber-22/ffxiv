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

                        {territories.map((territory, territoryIndex) => (
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
                    </div>
                );
            })}
        </>
    );
};

type MobDropLocationsToggleProps = {
    count: number;
    isExpanded: boolean;
    onToggle: () => void;
};

export const MobDropLocationsToggle = ({
    count,
    isExpanded,
    onToggle,
}: MobDropLocationsToggleProps) => {
    if (count <= 2) return null;

    return (
        <button
            type="button"
            onClick={onToggle}
            className="mt-4 block min-h-11 w-full text-left text-sm text-slate-400 transition-colors hover:text-white"
        >
            {isExpanded
                ? "Show fewer mob sources"
                : `+${count - 2} more mob source${count - 2 === 1 ? "" : "s"}`}
        </button>
    );
};
