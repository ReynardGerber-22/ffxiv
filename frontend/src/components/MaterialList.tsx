import { useMemo } from "react";
import type { Material } from "../types/Material";

type MaterialListProps = {
    title: string;
    materials: Material[];
};

export const MaterialList = ({
    title,
    materials,
}: MaterialListProps) => {

    const sortedMaterials = useMemo(
        () =>
            [...materials].sort((a, b) =>
                a.name.localeCompare(b.name)
            ),
        [materials]
    );

    return (
        <section className="overflow-hidden rounded-xl border border-slate-800 bg-slate-900">
            <div className="border-b border-slate-800 px-6 py-4">
                <h2 className="text-lg font-semibold text-white">
                    {title}
                    <span className="ml-2 text-sm font-normal text-slate-400">
                        ({sortedMaterials.length})
                    </span>
                </h2>
            </div>

            <ul className="divide-y divide-slate-800">
                {sortedMaterials.map((material) => (
                    <li
                        key={material.id}
                        className="flex items-center justify-between px-6 py-3 transition-colors hover:bg-slate-800/50"
                    >
                        <span className="text-slate-200">
                            {material.name}
                        </span>

                        <span className="rounded-md bg-slate-800 px-2 py-1 text-sm font-semibold text-slate-200">
                            ×{material.quantity}
                        </span>
                    </li>
                ))}
            </ul>
        </section>
    );
};