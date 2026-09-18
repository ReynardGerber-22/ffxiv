import { useId, useState, type ReactNode } from "react";

type MaterialLocationGroupProps = {
    territory: string;
    count: number;
    children: ReactNode;
};

export const MaterialLocationGroup = ({ territory, count, children }: MaterialLocationGroupProps) => {
    const [isExpanded, setIsExpanded] = useState(true);
    const contentId = useId();

    return (
        <li>
            <h3 className="bg-slate-950/60 text-sm font-semibold text-slate-300">
                <button
                    type="button"
                    aria-expanded={isExpanded}
                    aria-controls={contentId}
                    onClick={() => setIsExpanded((current) => !current)}
                    className="flex min-h-11 w-full items-center justify-between gap-3 px-6 py-3 text-left hover:bg-slate-800/50 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-blue-500"
                >
                    <span>
                        {territory}
                        <span className="ml-2 font-normal text-slate-400">({count})</span>
                    </span>
                    <svg
                        aria-hidden="true"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        strokeWidth={2}
                        className={`h-4 w-4 shrink-0 text-slate-400 transition-transform ${isExpanded ? "rotate-180" : ""}`}
                    >
                        <path d="m6 9 6 6 6-6" strokeLinecap="round" strokeLinejoin="round" />
                    </svg>
                </button>
            </h3>
            <ul id={contentId} hidden={!isExpanded} className="divide-y divide-slate-800 border-t border-slate-800">
                {children}
            </ul>
        </li>
    );
};
