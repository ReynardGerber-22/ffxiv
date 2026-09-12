import { useState, type SubmitEventHandler } from "react";

type CraftingSearchProps = {
    onSearch: (
        job: string,
        minLevel: number,
        maxLevel: number
    ) => void;
    loading: boolean;
};;

export const CraftingSearch = ({ onSearch, loading }: CraftingSearchProps) => {
    const [job, setJob] = useState<string>("Blacksmith");
    const [minLevel, setMinLevel] = useState<string>("1");
    const [maxLevel, setMaxLevel] = useState<string>("10");
    const [error, setError] = useState<string | null>(null);

    const submitSearch: SubmitEventHandler<HTMLFormElement> = (event) => {
        event.preventDefault();
        
        if (minLevel === "" || maxLevel === "") {
            setError("Please enter both a minimum and maximum level.");
            return;
        }

        const min = Number(minLevel);
        const max = Number(maxLevel);

        if (min < 1 || max < 1) {
            setError("Levels must be at least 1.");
            return;
        }

        if (min > 100 || max > 100) {
            setError("Levels cannot be higher than 100.");
            return;
        }

        if (min > max) {
            setError("Minimum level cannot be higher than maximum level.");
            return;
        }

        setError(null);

        onSearch(job, min, max);
    };

    return (
        <form
            onSubmit={submitSearch}
            className="rounded-xl border border-slate-800 bg-slate-900 p-6"
        >            <div className="grid gap-4 md:grid-cols-4">
                <label className="flex flex-col gap-2">
                    <span className="text-sm font-medium text-slate-300">
                        Job
                    </span>

                    <select
                        className="rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-white focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20"
                        value={job}
                        onChange={(event) => {
                            setJob(event.target.value);
                        }}
                    >
                        <option value="Carpenter">Carpenter</option>
                        <option value="Blacksmith">Blacksmith</option>
                        <option value="Armorer">Armorer</option>
                        <option value="Goldsmith">Goldsmith</option>
                        <option value="Leatherworker">Leatherworker</option>
                        <option value="Weaver">Weaver</option>
                        <option value="Alchemist">Alchemist</option>
                        <option value="Culinarian">Culinarian</option>
                    </select>
                </label>

                <label className="flex flex-col gap-2">
                    <span className="text-sm font-medium text-slate-300">
                        Min Level
                    </span>

                    <input
                        className="rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-white focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20"
                        min={1}
                        max={100}
                        type="number"
                        value={minLevel}
                        onChange={(event) => {
                            setMinLevel(event.target.value);
                        }}
                    />
                </label>

                <label className="flex flex-col gap-2">
                    <span className="text-sm font-medium text-slate-300">
                        Max Level
                    </span>

                    <input
                        className="rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-white focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20"
                        min={1}
                        max={100}
                        type="number"
                        value={maxLevel}
                        onChange={(event) => {
                            setMaxLevel(event.target.value);
                        }}
                    />
                </label>
                <button
                    disabled={loading}
                    className="self-end rounded-lg bg-blue-600 px-4 py-2 font-medium text-white transition hover:bg-blue-500 disabled:cursor-not-allowed disabled:opacity-50"
                    type="submit"
                >
                    {loading ? "Calculating..." : "Calculate Materials"}
                </button>

            </div>

            {error && (
                <p className="mt-4 text-sm text-red-400">
                    {error}
                </p>
            )}
        </form>
    );
};