export type MaterialStatus = "default" | "collecting" | "collected";

type MaterialStatusButtonProps = {
  itemName: string;
  status: MaterialStatus;
  onClick: () => void;
};

export const MaterialStatusButton = ({ itemName, status, onClick }: MaterialStatusButtonProps) => {
  const statusLabel = status === "default" ? "To collect" : status === "collecting" ? "Collecting" : "Collected";
  const nextStatusLabel = status === "default" ? "collecting" : status === "collecting" ? "collected" : "to collect";

  return (
          <button
            type="button"
            onClick={onClick}
            aria-label={`${itemName}: ${statusLabel}. Mark as ${nextStatusLabel}`}
            title={`Mark as ${nextStatusLabel}`}
            className={`min-h-11 rounded-lg border px-3 py-2 text-xs font-medium transition-colors focus-visible:outline-2 focus-visible:outline-blue-500 ${
              status === "collecting" ? "border-amber-800 text-amber-300 hover:bg-amber-950/60"
                : status === "collected" ? "border-emerald-800 text-emerald-300 hover:bg-emerald-950/60"
                  : "border-slate-700 text-slate-400 hover:bg-slate-800 hover:text-white"
            }`}
          >{statusLabel}</button>
  );
};
