import type { FishingInfo } from "../types/Material";

type FishingLocationsProps = {
    materialId: number;
    spots: FishingInfo[];
    isCollected: boolean;
    isExpanded: boolean;
};

export const FishingLocations = ({
    materialId,
    spots,
    isCollected,
    isExpanded,
}: FishingLocationsProps) => {
    const visibleSpots = isExpanded ? spots : spots.slice(0, 2);

    return (
        <>
            {visibleSpots.map((spot, index) => (
                <div
                    key={`${materialId}-${index}`}
                    className={`mt-1 text-xs ${
                        isCollected
                            ? "text-slate-600"
                            : "text-slate-400"
                    }`}
                >
                    <span className="font-medium text-slate-300">
                        Fishing · Lv. {spot.level}
                    </span>

                    <span className="ml-2">
                        {spot.territory} — {spot.spot}
                    </span>

                    <span className="ml-2 tabular-nums">
                        X: {spot.x} Y: {spot.y}
                    </span>
                </div>
            ))}
        </>
    );
};

type FishingLocationsToggleProps = {
    count: number;
    isExpanded: boolean;
    onToggle: () => void;
};

export const FishingLocationsToggle = ({
    count,
    isExpanded,
    onToggle,
}: FishingLocationsToggleProps) => {
    if (count <= 2) return null;

    return (
        <button
            type="button"
            onClick={onToggle}
            className="block w-full px-6 pb-3 text-left text-xs text-slate-400 transition-colors hover:text-white"
        >
            {isExpanded
                ? "Show fewer fishing locations"
                : `+${count - 2} more fishing locations`}
        </button>
    );
};