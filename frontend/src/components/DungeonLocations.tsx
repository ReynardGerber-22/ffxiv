import { useId, useState } from "react";

export const DungeonLocations = ({ dungeons }: { dungeons: { id: number; name: string }[] }) => {
    const [expanded, setExpanded] = useState(false);
    const listId = useId();

    return (
        <>
            <ul id={listId} className="space-y-3 text-sm leading-relaxed text-slate-300">
                {(expanded ? dungeons : dungeons.slice(0, 2)).map((duty) => (
                    <li key={duty.id}>{duty.name}</li>
                ))}
            </ul>
            {dungeons.length > 2 && (
                <button
                    type="button"
                    aria-expanded={expanded}
                    aria-controls={listId}
                    onClick={() => setExpanded((current) => !current)}
                    className="mt-4 block min-h-11 w-full text-left text-sm text-slate-400 hover:text-white focus-visible:outline-2 focus-visible:outline-blue-500"
                >
                    {expanded ? "Show fewer duties" : `Show all ${dungeons.length} duties`}
                </button>
            )}
        </>
    );
};
