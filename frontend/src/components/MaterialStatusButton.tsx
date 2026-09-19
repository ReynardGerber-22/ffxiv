export type MaterialStatus = "default" | "collecting" | "collected";

type MaterialStatusButtonProps = {
  itemName: string;
  isCrafting?: boolean;
  status: MaterialStatus;
  onClick: () => void;
};

export const MaterialStatusButton = ({ itemName, status, onClick, isCrafting = false }: MaterialStatusButtonProps) => {
  const labels = isCrafting
    ? { default: "To craft", collecting: "Crafting", collected: "Crafted" }
    : { default: "To collect", collecting: "Collecting", collected: "Collected" };
  const statusLabel = labels[status];
  const nextStatusLabel = labels[status === "default" ? "collecting" : status === "collecting" ? "collected" : "default"].toLowerCase();

  return (
          <button
            type="button"
            onClick={onClick}
            aria-label={`${itemName}: ${statusLabel}. Mark as ${nextStatusLabel}`}
            title={`Mark as ${nextStatusLabel}`}
            className={`min-h-11 w-24 shrink-0 rounded-lg border px-3 py-2 text-xs font-medium transition-colors focus-visible:outline-2 focus-visible:outline-blue-500 ${
              status === "collecting" ? "border-amber-800 text-amber-300 hover:bg-amber-950/60"
                : status === "collected" ? "border-emerald-800 text-emerald-300 hover:bg-emerald-950/60"
                  : "border-slate-700 text-slate-400 hover:bg-slate-800 hover:text-white"
            }`}
          >{statusLabel}</button>
  );
};
