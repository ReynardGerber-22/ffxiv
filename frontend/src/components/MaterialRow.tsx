import { useId, useState } from "react";
import type { Material } from "../types/Material";
import {
  GatheringLocations,
  GatheringLocationsToggle,
} from "./GatheringLocations";
import { MobDropLocations, MobDropLocationsToggle } from "./MobDropLocations";
import { FishingLocations, FishingLocationsToggle } from "./FishingLocations";
import { VendorLocations } from "./VendorLocations";

import { SourceSection } from "./SourceSection";
import { MaterialStatusButton, type MaterialStatus } from "./MaterialStatusButton";

export type { MaterialStatus } from "./MaterialStatusButton";

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
  const [detailsExpanded, setDetailsExpanded] = useState(false);
  const detailsId = useId();
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
  const vendors = (material.vendors ?? []).filter((vendor) => vendor.name.trim());

  const shouldShowMobDrops = mobDrops.length > 0;

  const sourceSummary = [
    vendors.length > 0
      ? `Buy · ${vendors.some((vendor) => vendor.price !== vendors[0].price) ? "from " : ""}${Math.min(...vendors.map((vendor) => vendor.price)).toLocaleString()} gil each`
      : null,
    gatheringNodes.length > 0 ? "Gather" : null,
    fishingSpots.length > 0 ? "Fish" : null,
    shouldShowMobDrops ? "Mob drops" : null,
  ].filter(Boolean).join("  •  ");
  const hasDetails = sourceSummary.length > 0;
  const isCollected = status === "collected";


  return (
    <li>
      <div className={`grid grid-cols-[minmax(0,1fr)_auto] items-center gap-3 px-4 py-4 sm:gap-6 sm:px-6 ${
        status === "collecting" ? "bg-amber-950/20" : isCollected ? "bg-emerald-950/20" : ""
      }`}>
        <button
          type="button"
          disabled={!hasDetails}
          aria-expanded={hasDetails ? detailsExpanded : undefined}
          aria-controls={hasDetails ? detailsId : undefined}
          onClick={() => setDetailsExpanded((current) => !current)}
          className="flex min-h-11 min-w-0 items-start gap-3 rounded text-left focus-visible:outline-2 focus-visible:outline-blue-500 enabled:hover:text-white"
        >
          {hasDetails && (
            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={2}
              className={`mt-1 h-4 w-4 shrink-0 text-slate-400 transition-transform ${detailsExpanded ? "rotate-90" : ""}`}>
              <path d="m9 5 7 7-7 7" strokeLinecap="round" strokeLinejoin="round" />
            </svg>
          )}
          <span className="min-w-0">
            <span className={`block break-words font-medium ${isCollected ? "text-slate-500 line-through" : "text-slate-200"}`}>
              {material.name}
            </span>
            {hasDetails && <span className="mt-1 block text-xs leading-relaxed text-slate-400">{sourceSummary}</span>}
          </span>
        </button>
        <div className="flex flex-col-reverse items-end gap-2 sm:flex-row sm:items-center sm:gap-4">
          <MaterialStatusButton itemName={material.name} status={status} onClick={onCycleStatus} />
          <span className="w-20 rounded-md bg-slate-800 px-2.5 py-1 text-center text-sm font-semibold tabular-nums text-slate-200">×{material.quantity}</span>
        </div>
      </div>
      {hasDetails && (
        <div id={detailsId} hidden={!detailsExpanded} className="px-4 pb-5 sm:px-6 sm:pb-6">
          <div className="grid items-start gap-4 md:grid-cols-2">
            {vendors.length > 0 && (
              <SourceSection title="Buy from NPC">
                <VendorLocations vendors={vendors} isCollected={isCollected} />
              </SourceSection>
            )}
            {gatheringNodes.length > 0 && (
              <SourceSection title="Gather">
                <GatheringLocations materialId={material.id} nodes={gatheringNodes} isCollected={isCollected} isExpanded={isGatheringExpanded} />
                <GatheringLocationsToggle count={gatheringNodes.length} isExpanded={isGatheringExpanded} onToggle={onToggleGathering} />
              </SourceSection>
            )}
            {fishingSpots.length > 0 && (
              <SourceSection title="Fish">
                <FishingLocations materialId={material.id} spots={fishingSpots} isCollected={isCollected} isExpanded={isFishingExpanded} />
                <FishingLocationsToggle count={fishingSpots.length} isExpanded={isFishingExpanded} onToggle={onToggleFishing} />
              </SourceSection>
            )}
            {shouldShowMobDrops && (
              <SourceSection title="Mob drops">
                <MobDropLocations materialId={material.id} drops={mobDrops} isCollected={isCollected} isExpanded={isMobDropsExpanded} />
                <MobDropLocationsToggle drops={mobDrops} isExpanded={isMobDropsExpanded} onToggle={onToggleMobDrops} />
              </SourceSection>
            )}
          </div>
        </div>
      )}
    </li>
  );
};
