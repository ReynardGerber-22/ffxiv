import { useState } from "react";

type CraftingSearchProps = {
    onSearch: (
        job: string,
        minLevel: number,
        maxLevel: number
    ) => void;
};

export const CraftingSearch = ({ onSearch }: CraftingSearchProps) => {
    const [job, setJob] = useState<string>("Blacksmith");
    const [minLevel, setMinLevel] = useState<number>(1);
    const [maxLevel, setMaxLevel] = useState<number>(10);

    const submitSearch = () => {
        onSearch(job, minLevel, maxLevel);
    };

    return (
        <div>
            <label>
                Job
                <select
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

            <label>
                Min Level
                <input
                    type="number"
                    value={minLevel}
                    onChange={(event) => {
                        setMinLevel(Number(event.target.value));
                    }}
                />
            </label>

            <label>
                Max Level
                <input
                    type="number"
                    value={maxLevel}
                    onChange={(event) => {
                        setMaxLevel(Number(event.target.value));
                    }}
                />
            </label>

            <button onClick={submitSearch}>
                Calculate Materials
            </button>
        </div>
    );
};