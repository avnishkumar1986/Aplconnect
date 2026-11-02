import { ArrowDownRight, ArrowUpRight, type LucideIcon } from "lucide-react";
import { type ReactNode, useState } from "react";

type Trend = "up" | "down" | "neutral";

export function SectionHeading({ children }: { children: ReactNode }) {
  return (
    <div className="flex items-center gap-3">
      <span className="bg-brand-teal h-5 w-1 rounded-full" />
      <h2 className="text-foreground shrink-0 text-[11px] font-extrabold tracking-[0.08em] uppercase">
        {children}
      </h2>
      <div className="from-border h-px flex-1 bg-gradient-to-r to-transparent" />
    </div>
  );
}

export function MetricCard({
  label,
  value,
  note,
  trend,
  icon: Icon,
  periodValues,
}: {
  label: string;
  value?: string;
  note?: string;
  trend?: Trend;
  icon: LucideIcon;
  periodValues?: readonly {
    period: string;
    value: string;
    note: string;
    trend: Trend;
  }[];
}) {
  const [selectedPeriod, setSelectedPeriod] = useState<string>(
    periodValues?.[0]?.period ?? "MTD",
  );
  const selectedMetric = periodValues?.find(
    ({ period }) => period === selectedPeriod,
  );
  const displayedValue = selectedMetric?.value ?? value ?? "—";
  const displayedNote = selectedMetric?.note ?? note ?? "";
  const displayedTrend = selectedMetric?.trend ?? trend ?? "neutral";
  const croreValue = displayedValue.match(/^₹\s*(.+?)\s*Cr$/);
  const [noteFirstLine, ...noteRemainingLines] = displayedNote.split("\n");

  return (
    <article className="border-border bg-card group relative min-h-40 overflow-hidden rounded-2xl border shadow-[0_12px_32px_-25px_rgba(2,48,71,0.6)] transition-all duration-300 hover:-translate-y-1 hover:border-brand-teal/70 hover:shadow-[0_20px_42px_-24px_rgba(33,158,188,0.5)]">
      <div className="bg-brand-teal absolute inset-x-0 top-0 h-[3px] opacity-70 transition-opacity group-hover:opacity-100" />
      <div className="border-brand-aqua/20 absolute -right-12 -bottom-16 size-36 rounded-full border-[18px] transition-transform duration-500 group-hover:scale-110" />
      <div className="relative z-10 flex h-full flex-col justify-between p-5">
        <div className="flex items-start justify-between gap-3">
          <div>
            <p className="text-foreground pt-1 text-[11px] leading-4 font-bold">
              {label}
            </p>
            {periodValues && (
              <div
                className="bg-muted mt-2 flex w-fit rounded-lg p-0.5"
                role="group"
                aria-label={`${label} reporting period`}
              >
                {periodValues.map(({ period }) => (
                  <button
                    key={period}
                    type="button"
                    onClick={() => setSelectedPeriod(period)}
                    aria-pressed={selectedPeriod === period}
                    className={`rounded-md px-2 py-1 text-[9px] font-extrabold transition ${
                      selectedPeriod === period
                        ? "bg-card text-brand-teal shadow-sm"
                        : "text-muted-foreground hover:bg-card/60 hover:text-foreground"
                    }`}
                  >
                    {period}
                  </button>
                ))}
              </div>
            )}
          </div>
          <span className="bg-brand-aqua/10 text-brand-teal group-hover:bg-brand-teal group-hover:shadow-brand-teal/20 grid size-14 shrink-0 place-items-center rounded-2xl border border-brand-aqua/35 shadow-sm transition-all duration-300 group-hover:scale-105 group-hover:text-white group-hover:shadow-lg">
            <Icon
              className="size-8 transition-transform duration-300 group-hover:scale-110"
              strokeWidth={1.7}
            />
          </span>
        </div>
        <div>
          <p className="mt-5 flex items-baseline text-2xl font-black tracking-tight sm:text-[1.65rem]">
            {croreValue ? (
              <span className="inline-flex items-baseline gap-2 whitespace-nowrap">
                <span>₹</span>
                <span>{croreValue[1]}</span>
                <span className="text-[0.8em]">Cr</span>
              </span>
            ) : displayedValue}
          </p>
          <p
            className={`mt-1.5 flex items-start gap-1 whitespace-pre-line text-[10px] leading-4 font-semibold ${displayedTrend === "down" ? "text-brand-berry" : displayedTrend === "up" ? "text-brand-teal" : "text-muted-foreground"}`}
          >
            {displayedTrend === "up" && <ArrowUpRight className="size-3" />}
            {displayedTrend === "down" && <ArrowDownRight className="size-3" />}
            <span>
              {noteFirstLine}
              {noteRemainingLines.length > 0 && (
                <span className="block text-orange-600">
                  {noteRemainingLines.join(" ")}
                </span>
              )}
            </span>
          </p>
        </div>
      </div>
    </article>
  );
}

export function DepartmentCard({
  name,
  metric,
  detail,
  icon: Icon,
}: {
  name: string;
  metric: string;
  detail: string;
  icon: LucideIcon;
}) {
  return (
    <article className="border-border bg-card hover:border-brand-teal/60 group relative flex min-h-36 items-start justify-between overflow-hidden rounded-2xl border p-5 shadow-[0_10px_30px_-24px_rgba(2,48,71,0.45)] transition-all duration-300 hover:-translate-y-1 hover:shadow-[0_18px_38px_-22px_rgba(33,158,188,0.5)]">
      <div className="bg-brand-gold absolute inset-x-0 top-0 h-0.5" />
      <div className="bg-brand-gold/5 absolute -right-12 -bottom-14 size-32 rounded-full transition-transform duration-500 group-hover:scale-125" />
      <div className="relative z-10 pr-4">
        <p className="text-muted-foreground text-[10px] font-bold tracking-wide uppercase">
          {name}
        </p>
        <p className="mt-2 text-sm leading-5 font-black tracking-tight">
          {metric}
        </p>
        <p className="text-muted-foreground mt-2 text-[10px] leading-4">
          {detail}
        </p>
      </div>
      <span className="bg-brand-aqua/15 text-brand-teal group-hover:bg-brand-teal group-hover:shadow-brand-teal/25 relative z-10 grid size-16 shrink-0 place-items-center rounded-2xl border border-brand-aqua/30 shadow-sm transition-all duration-300 group-hover:scale-110 group-hover:rotate-3 group-hover:text-white group-hover:shadow-lg">
        <Icon
          className="size-9 transition-transform duration-300 group-hover:scale-105"
          strokeWidth={1.7}
        />
      </span>
    </article>
  );
}

export function DashboardPanel({
  title,
  meta,
  children,
  className = "",
}: {
  title: string;
  meta?: string;
  children: ReactNode;
  className?: string;
}) {
  return (
    <article
      className={`border-border bg-card overflow-hidden rounded-2xl border shadow-[0_10px_30px_-24px_rgba(2,48,71,0.45)] ${className}`}
    >
      <header className="border-border bg-muted/35 flex items-center justify-between gap-4 border-b px-4 py-3.5">
        <h3 className="text-xs font-bold">{title}</h3>
        {meta && (
          <span className="text-muted-foreground shrink-0 text-[10px]">
            {meta}
          </span>
        )}
      </header>
      {children}
    </article>
  );
}
