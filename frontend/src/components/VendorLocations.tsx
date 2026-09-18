import { useId, useState } from "react";
import type { VendorInfo } from "../types/Material";

type VendorLocationsProps = {
    vendors: VendorInfo[];
    isCollected: boolean;
};

export const VendorLocations = ({ vendors, isCollected }: VendorLocationsProps) => {
    const [isExpanded, setIsExpanded] = useState(false);
    const listId = useId();
    const namedVendors = vendors.filter((vendor) => vendor.name.trim());
    if (namedVendors.length === 0) return null;

    const visibleVendors = isExpanded ? namedVendors : namedVendors.slice(0, 2);
    const commonPrice = namedVendors.every((vendor) => vendor.price === namedVendors[0].price);

    return (
        <div className={`min-w-0 text-sm leading-relaxed ${isCollected ? "text-slate-500" : "text-slate-400"}`}>
            <div className={isCollected ? "font-medium text-slate-500" : "font-medium text-slate-300"}>
                {commonPrice ? `${namedVendors[0].price.toLocaleString()} gil each` : "Prices vary by vendor"}
            </div>
            <div id={listId} className="mt-3 space-y-4">
                {visibleVendors.map((vendor) => (
                    <div key={`${vendor.id}-${vendor.price}-${vendor.mapId}-${vendor.x}-${vendor.y}`} className="min-w-0 break-words">
                        <span className={isCollected ? "text-slate-500" : "text-slate-300"}>{vendor.name}</span>
                        {vendor.title && <div className="text-xs text-slate-400">{vendor.title}</div>}
                        {!commonPrice && <span className="ml-2 inline-block tabular-nums">{vendor.price.toLocaleString()} gil each</span>}
                        {vendor.locationResolved && vendor.territory && vendor.x !== null && vendor.y !== null ? (
                            <div>
                                {vendor.territory}{vendor.area ? ` · ${vendor.area}` : ""}
                                <span className="block whitespace-nowrap tabular-nums">X: {vendor.x.toFixed(1)} Y: {vendor.y.toFixed(1)}</span>
                            </div>
                        ) : <div className="text-slate-500">Location unavailable</div>}
                    </div>
                ))}
            </div>
            {namedVendors.length > 2 && (
                <button
                    type="button"
                    aria-expanded={isExpanded}
                    aria-controls={listId}
                    onClick={() => setIsExpanded((current) => !current)}
                    className="mt-4 block min-h-11 w-full text-left text-sm text-slate-400 transition-colors hover:text-white focus-visible:outline-2 focus-visible:outline-blue-500"
                >
                    {isExpanded ? "Show fewer vendors" : `+${namedVendors.length - 2} more vendors`}
                </button>
            )}
        </div>
    );
};
