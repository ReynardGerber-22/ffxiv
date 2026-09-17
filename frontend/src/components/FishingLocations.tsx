import type { FishingBait, FishingInfo } from "../types/Material";

const BaitNames = ({ baits, isCollected }: { baits: FishingBait[]; isCollected: boolean }) => (
    <div className={`mt-1 min-w-0 text-xs leading-relaxed ${isCollected ? "text-slate-500" : "text-slate-300"}`}>
        <span className="text-slate-500">Recommended bait · </span>
        <span className="break-words">{baits.map((bait) => bait.name).join(", ")}</span>
    </div>
);

const baitKey = (baits: FishingBait[]) =>
    [...new Set(baits.map((bait) => bait.id))].sort((a, b) => a - b).join(",");

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

    const firstBaits = spots[0]?.baits ?? [];
    const sharedBaits = firstBaits.length > 0 && spots.every(
        (spot) => baitKey(spot.baits ?? []) === baitKey(firstBaits),
    );

    return (
        <>
            {sharedBaits && <BaitNames baits={firstBaits} isCollected={isCollected} />}
            {visibleSpots.map((spot, index) => (
                <div
                    key={`${materialId}-${index}`}
                    className={`mt-1 min-w-0 break-words text-xs ${
                        isCollected
                            ? "text-slate-600"
                            : "text-slate-400"
                    }`}
                >
                    <span className={`font-medium ${isCollected ? "text-slate-500" : "text-slate-300"}`}>
                        Fishing · Lv. {spot.level}
                    </span>

                    <span className="ml-2">
                        {spot.territory} — {spot.spot}
                    </span>

                    <span className="ml-2 inline-block whitespace-nowrap tabular-nums">
                        X: {spot.x} Y: {spot.y}
                    </span>

                    {!sharedBaits && (spot.baits?.length ?? 0) > 0 && (
                        <BaitNames baits={spot.baits ?? []} isCollected={isCollected} />
                    )}
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
            className="mt-2 block w-full text-left text-xs text-slate-400 transition-colors hover:text-white"
        >
            {isExpanded
                ? "Show fewer fishing locations"
                : `+${count - 2} more fishing locations`}
        </button>
    );
};