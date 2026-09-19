import { useEffect, useState } from "react";
import { searchCraftableItems } from "../services/customCraftingService";
import type { CraftableItem } from "../types/CustomCraftingItem";

interface CustomItemSearchProps {
  onSelect: (item: CraftableItem) => void;
}

export const CustomItemSearch = ({ onSelect }: CustomItemSearchProps) => {
  const [search, setSearch] = useState("");
  const [results, setResults] = useState<CraftableItem[]>([]);
  const [loading, setLoading] = useState(false);
  const [hasSearched, setHasSearched] = useState(false);
  const searchId = "custom-item-search-results";

  useEffect(() => {
    const query = search.trim();
    if (query.length < 2) return;

    let cancelled = false;
    const timeout = setTimeout(async () => {
      try {
        setLoading(true);
        setHasSearched(false);

        const items = await searchCraftableItems(query);

        if (!cancelled) {
          setResults(items);
          setHasSearched(true);
        }
      } catch {
        if (!cancelled) {
          setResults([]);
          setHasSearched(true);
        }
      } finally {
        if (!cancelled) {
          setLoading(false);
        }
      }
    }, 300);

    return () => {
      cancelled = true;
      clearTimeout(timeout);
    };
  }, [search]);

  return (
    <div className="relative">
      <label
        htmlFor="custom-item-search"
        className="mb-2 block text-sm font-medium text-slate-200"
      >
        Search for an item to craft
      </label>
      <input
        id="custom-item-search"
        type="text"
        value={search}
        onChange={(event) => {
          setSearch(event.target.value);
          setResults([]);
          setHasSearched(false);
          setLoading(false);
        }}
        placeholder="Search for an item..."
        role="combobox"
        aria-autocomplete="list"
        aria-controls={searchId}
        aria-expanded={loading || results.length > 0 || hasSearched}
        aria-describedby="custom-item-search-help"
        className="w-full rounded-lg border border-slate-700 bg-slate-950 px-4 py-3 text-white placeholder:text-slate-500 transition-colors focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20"
      />
      <p id="custom-item-search-help" className="mt-2 text-sm text-slate-400">
        Enter at least two characters to find craftable recipes.
      </p>

      {(loading || results.length > 0 || hasSearched) && (
        <div
          id={searchId}
          role="listbox"
          aria-label="Craftable item search results"
          className="absolute z-10 mt-2 max-h-80 w-full overflow-y-auto rounded-xl border border-slate-700 bg-slate-900 shadow-xl shadow-slate-950/40"
        >
          {loading ? (
            <p role="status" className="px-4 py-3 text-sm text-slate-400">
              Searching craftable items...
            </p>
          ) : results.length > 0 ? (
            results.map((item) => (
              <button
                key={item.id}
                type="button"
                role="option"
                aria-label={`${item.name}, ${item.profession}, level ${item.level}`}
                onClick={() => {
                  onSelect(item);
                  setSearch("");
                  setResults([]);
                  setHasSearched(false);
                }}
                className="block w-full border-b border-slate-800 px-4 py-3 text-left last:border-b-0 transition-colors hover:bg-slate-800 focus:bg-slate-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-blue-500"
              >
                <span className="block font-medium text-slate-100">{item.name}</span>
                <span className="mt-1 block text-sm text-slate-400">
                  {item.profession} • Level {item.level}
                </span>
              </button>
            ))
          ) : (
            <p className="px-4 py-3 text-sm text-slate-400">
              No craftable items match this search.
            </p>
          )}
        </div>
      )}
    </div>
  );
};
