import type { GatheringInfo } from "../types/Material";

type GatheringLocationsProps = {
    materialId: number;
    nodes: GatheringInfo[];
    isCollected: boolean;
    isExpanded: boolean;
};

export const GatheringLocations = ({
    materialId,
    nodes,
    isCollected,
    isExpanded,
}: GatheringLocationsProps) => {
    const visibleNodes = isExpanded ? nodes : nodes.slice(0, 2);

    return (
        <>
            {visibleNodes.map((node, index) => (
                <div
                    key={`${materialId}-${index}`}
                    className={`mt-1 text-xs ${isCollected
                        ? "text-slate-600"
                        : "text-slate-400"
                        }`}
                >
                    <span className="font-medium text-slate-300">
                        {node.type} · Lv. {node.level}
                    </span>

                    <span className="ml-2">
                        {node.territory} — {node.area}
                    </span>

                    <span className="ml-2 tabular-nums">
                        X: {node.x} Y: {node.y}
                    </span>
                </div>
            ))}
        </>
    );
};

type GatheringLocationsToggleProps = {
    count: number;
    isExpanded: boolean;
    onToggle: () => void;
};

export const GatheringLocationsToggle = ({
    count,
    isExpanded,
    onToggle,
}: GatheringLocationsToggleProps) => {
    if (count <= 2) return null;

    return (
        <button
            type="button"
            onClick={onToggle}
            className="block w-full px-6 pb-3 text-left text-xs text-slate-400 transition-colors hover:text-white"
        >
            {isExpanded
                ? "Show fewer gathering locations"
                : `+${count - 2} more gathering locations`}
        </button>
    );
};
