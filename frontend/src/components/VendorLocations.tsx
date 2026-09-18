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
        <div className={`mt-2 min-w-0 text-xs leading-relaxed ${isCollected ? "text-slate-500" : "text-slate-400"}`}>
            <div className={isCollected ? "font-medium text-slate-500" : "font-medium text-slate-300"}>
                Purchasable{commonPrice ? ` · ${namedVendors[0].price.toLocaleString()} gil each` : " · Gil vendors"}
            </div>
            <div id={listId} className="space-y-1">
                {visibleVendors.map((vendor) => (
                    <div key={`${vendor.id}-${vendor.price}-${vendor.mapId}-${vendor.x}-${vendor.y}`} className="min-w-0 break-words">
                        <span className={isCollected ? "text-slate-500" : "text-slate-300"}>{vendor.name}</span>
                        {vendor.title && <span> · {vendor.title}</span>}
                        {!commonPrice && <span className="ml-2 inline-block tabular-nums">{vendor.price.toLocaleString()} gil each</span>}
                        {vendor.locationResolved && vendor.territory && vendor.x !== null && vendor.y !== null ? (
                            <div>
                                {vendor.territory}{vendor.area ? ` · ${vendor.area}` : ""}
                                <span className="ml-2 inline-block whitespace-nowrap tabular-nums">X: {vendor.x.toFixed(1)} Y: {vendor.y.toFixed(1)}</span>
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
                    className="mt-2 block w-full text-left text-xs text-slate-400 transition-colors hover:text-white focus-visible:outline-2 focus-visible:outline-blue-500"
                >
                    {isExpanded ? "Show fewer vendors" : `+${namedVendors.length - 2} more vendors`}
                </button>
            )}
        </div>
    );
};
