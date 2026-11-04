import { ArrowDownRight, ArrowUpRight, type LucideIcon } from "lucide-react";
import { type ReactNode } from "react";

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
  asOf,
  lastSync,
  icon: Icon,
}: {
  label: string;
  value?: string;
  note?: string;
  trend?: Trend;
  asOf?: string | null;
  lastSync?: string | null;
  icon: LucideIcon;
}) {
  const displayedValue = value ?? "—";
  const displayedNote = note ?? "";
  const displayedTrend = trend ?? "neutral";
  const moneyValue = displayedValue.match(/^₹\s*(.+?)\s*(Cr|Lk)$/);
  const [noteFirstLine, ...noteRemainingLines] = displayedNote.split("\n");
  const isYesterday = label === "Yesterday sales";
  const theme = label.includes("Collections")
    ? {
        top: "bg-emerald-500",
        ring: "border-emerald-100",
        icon: "border-emerald-200 bg-emerald-50 text-emerald-600 group-hover:bg-emerald-500",
      }
    : label.includes("Receivables") || label.includes("receivables")
      ? {
          top: "bg-amber-500",
          ring: "border-amber-100",
          icon: "border-amber-200 bg-amber-50 text-amber-600 group-hover:bg-amber-500",
        }
      : label.includes("Inventory")
        ? {
            top: "bg-violet-500",
            ring: "border-violet-100",
            icon: "border-violet-200 bg-violet-50 text-violet-600 group-hover:bg-violet-500",
          }
        : label.includes("priority")
          ? {
              top: "bg-rose-500",
              ring: "border-rose-100",
              icon: "border-rose-200 bg-rose-50 text-rose-600 group-hover:bg-rose-500",
            }
          : {
              top: "bg-cyan-500",
              ring: "border-cyan-100",
              icon: "border-cyan-200 bg-cyan-50 text-cyan-600 group-hover:bg-cyan-500",
            };

  return (
    <article className={`group relative min-h-44 overflow-hidden rounded-2xl border bg-card shadow-[0_12px_32px_-25px_rgba(2,48,71,0.6)] transition-all duration-300 hover:-translate-y-0.5 hover:shadow-[0_18px_38px_-24px_rgba(33,158,188,0.42)] ${isYesterday ? "border-cyan-300 bg-gradient-to-br from-cyan-50 via-white to-white ring-1 ring-cyan-100" : "border-border hover:border-brand-teal/70"}`}>
      <div className={`absolute inset-x-0 top-0 h-1 ${theme.top}`} />
      <div className={`absolute -right-12 -bottom-16 size-36 rounded-full border-[18px] transition-transform duration-500 group-hover:scale-110 ${theme.ring}`} />
      <div className="relative z-10 flex h-full flex-col justify-between p-4 xl:p-5">
        <div className="flex items-start justify-between gap-3">
          <div>
            <p className="text-foreground flex items-center gap-2 pt-1 text-lg leading-6 font-black">
              {label}
              {asOf && (
                <span className="rounded-full bg-cyan-100 px-2 py-0.5 text-[9px] font-extrabold tracking-wide text-cyan-700 uppercase">
                  {asOf}
                </span>
              )}
            </p>
          </div>
          <span className={`grid size-14 shrink-0 place-items-center rounded-2xl border shadow-sm transition-all duration-300 group-hover:scale-105 group-hover:text-white group-hover:shadow-lg ${theme.icon}`}>
            <Icon
              className="size-8 transition-transform duration-300 group-hover:scale-110"
              strokeWidth={1.7}
            />
          </span>
        </div>
        <div>
          <p className="mt-5 flex items-baseline text-3xl font-black tracking-[-0.035em] tabular-nums">
            {moneyValue ? (
              <span className="inline-flex items-baseline gap-1 whitespace-nowrap">
                <span className="text-[0.9em]">₹</span>
                <span>{moneyValue[1]}</span>
                <span className="ml-0.5 text-[0.68em] font-extrabold tracking-normal">{moneyValue[2]}</span>
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
          {lastSync && (
            <p className="text-muted-foreground mt-1 text-[9px] font-semibold">
              Last sync: {lastSync}
            </p>
          )}
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
  metaClassName = "",
  children,
  className = "",
}: {
  title: string;
  meta?: string;
  metaClassName?: string;
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
          <span className={`shrink-0 ${metaClassName || "text-muted-foreground text-[10px]"}`}>
            {meta}
          </span>
        )}
      </header>
      {children}
    </article>
  );
}
