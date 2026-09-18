import type { ReactNode } from "react";

export const SourceSection = ({ title, children }: { title: string; children: ReactNode }) => (
  <section className="min-w-0 rounded-lg border border-slate-800 bg-slate-950/30 p-4 sm:p-5">
    <h3 className="mb-4 text-xs font-semibold uppercase tracking-wider text-slate-400">{title}</h3>
    {children}
  </section>
);

