import type { Material } from "../types/Material";
import {
  GatheringLocations,
  GatheringLocationsToggle,
} from "./GatheringLocations";
import { MobDropLocations, MobDropLocationsToggle } from "./MobDropLocations";
import { FishingLocations, FishingLocationsToggle } from "./FishingLocations";

export type MaterialStatus = "default" | "collecting" | "collected";

type MaterialRowProps = {
    material: Material;
    status: MaterialStatus;
    isGatheringExpanded: boolean;
    isFishingExpanded: boolean;
    isMobDropsExpanded: boolean;
    onCycleStatus: () => void;
    onToggleGathering: () => void;
    onToggleFishing: () => void;
    onToggleMobDrops: () => void;
};

export const MaterialRow = ({
  material,
  status,
  isGatheringExpanded,
  isFishingExpanded,
  isMobDropsExpanded,
  onCycleStatus,
  onToggleGathering,
  onToggleFishing,
  onToggleMobDrops,
}: MaterialRowProps) => {
  const gatheringNodes = material.gathering ?? [];
  const fishingSpots = (material.fishing ?? [])
    .filter((spot) =>
      spot.spot?.trim() &&
      spot.territory?.trim() &&
      typeof spot.x === "number" && Number.isFinite(spot.x) &&
      typeof spot.y === "number" && Number.isFinite(spot.y),
    )
    .map((spot) => ({
      ...spot,
      baits: spot.baits?.filter((bait) => bait.name?.trim()),
    }));
  const mobDrops = material.mobDrops ?? [];

  const shouldShowMobDrops = mobDrops.length > 0;

  return (
    <li>
      <div className="border-b border-slate-800 last:border-b-0">
        <button
          type="button"
          onClick={onCycleStatus}
          className={`grid w-full grid-cols-[minmax(0,1fr)_5rem_7ch] items-center gap-3 px-6 py-3 text-left transition-colors ${
            status === "collecting"
              ? "bg-amber-950/40 hover:bg-amber-950/60"
              : status === "collected"
                ? "bg-emerald-950/40 hover:bg-emerald-950/60"
                : "hover:bg-slate-800/50"
          }`}
        >
          <div className="min-w-0">
            <span
              className={`block break-words ${
                status === "collected"
                  ? "text-slate-500 line-through"
                  : "text-slate-200"
              }`}
            >
              {material.name}
            </span>
          </div>

          <span
            className={`text-xs font-medium ${
              status === "collecting" ? "text-amber-400" : "text-emerald-400"
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

        {(gatheringNodes.length > 0 || fishingSpots.length > 0 || shouldShowMobDrops) && (
          <div className="px-6 pb-3">
            <GatheringLocations
              materialId={material.id}
              nodes={gatheringNodes}
              isCollected={status === "collected"}
              isExpanded={isGatheringExpanded}
            />

            <GatheringLocationsToggle
              count={gatheringNodes.length}
              isExpanded={isGatheringExpanded}
              onToggle={onToggleGathering}
            />

            <FishingLocations
              materialId={material.id}
              spots={fishingSpots}
              isCollected={status === "collected"}
              isExpanded={isFishingExpanded}
            />

            <FishingLocationsToggle
              count={fishingSpots.length}
              isExpanded={isFishingExpanded}
              onToggle={onToggleFishing}
            />

            {shouldShowMobDrops && (
              <>
                <MobDropLocations
                  materialId={material.id}
                  drops={mobDrops}
                  isCollected={status === "collected"}
                  isExpanded={isMobDropsExpanded}
                />

                <MobDropLocationsToggle
                  count={mobDrops.length}
                  isExpanded={isMobDropsExpanded}
                  onToggle={onToggleMobDrops}
                />
              </>
            )}
          </div>
        )}
      </div>
    </li>
  );
};
