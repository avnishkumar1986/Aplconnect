import { Link } from '@inertiajs/react';
import { ArrowLeft, Network } from 'lucide-react';
import { type ReactNode } from 'react';

export default function ProjectPageHeader({
    eyebrow,
    title,
    subtitle,
    icon,
    meta,
    actions,
    backHref,
    embedded = false,
    compact = false,
}: {
    eyebrow?: string;
    title: string;
    subtitle?: string;
    icon?: ReactNode;
    meta?: ReactNode;
    actions?: ReactNode;
    backHref?: string;
    embedded?: boolean;
    compact?: boolean;
}) {
    return (
        <section
            className={`relative text-white ${embedded ? 'overflow-visible bg-transparent' : `overflow-hidden rounded-xl border border-t-2 border-white/10 border-t-orange-500 bg-gradient-to-r from-[#07374b] via-[#075b70] to-[#0b8da3] shadow-md shadow-slate-900/15 ${compact ? 'p-2' : 'p-2.5 sm:p-3'}`}`}
        >
            <Network className="pointer-events-none absolute -right-8 -bottom-12 size-44 text-white/5" aria-hidden="true" />
            <div
                className={`relative flex gap-2.5 ${embedded ? 'h-full flex-col justify-center sm:flex-row sm:items-center sm:justify-between' : 'flex-col xl:flex-row xl:items-center xl:justify-between'}`}
            >
                <div className="flex min-w-0 items-center gap-2.5">
                    {icon && (
                        <span className="grid size-8 shrink-0 place-items-center rounded-lg bg-white/10 text-orange-300 shadow-inner ring-1 ring-white/15 sm:size-9">
                            {icon}
                        </span>
                    )}
                    <div className="min-w-0">
                        {eyebrow && <p className="text-[10px] font-bold tracking-[0.18em] text-orange-300 uppercase">{eyebrow}</p>}
                        <h1 className="mt-0.5 text-base leading-tight font-bold break-words text-white sm:text-lg">{title}</h1>
                        {subtitle && <p className="mt-0.5 max-w-xl text-[11px] leading-4 text-white/65">{subtitle}</p>}
                        {(meta || backHref) && (
                            <div className="mt-1.5 flex flex-wrap items-center gap-2">
                                {backHref && (
                                    <Link
                                        href={backHref}
                                        prefetch
                                        aria-label="Back to dashboard"
                                        className="inline-flex h-7 items-center gap-1.5 rounded-md border border-white/15 bg-white/8 px-2.5 text-[10px] font-semibold text-white/80 transition hover:border-white/30 hover:bg-white/15 hover:text-white focus-visible:ring-2 focus-visible:ring-orange-300/60 focus-visible:outline-none active:scale-[0.97]"
                                    >
                                        <ArrowLeft className="size-3.5" />
                                        <span>Back to dashboard</span>
                                    </Link>
                                )}
                                {meta && (
                                    <span className="inline-flex rounded-full border border-white/10 bg-white/8 px-2 py-0.5 text-[10px] font-medium text-white/75">
                                        {meta}
                                    </span>
                                )}
                            </div>
                        )}
                    </div>
                </div>
                {actions && (
                    <div
                        className={`relative z-10 flex min-w-0 flex-wrap items-center gap-2 ${embedded ? 'w-full sm:w-auto sm:shrink-0 sm:justify-end' : `w-full rounded-lg border border-white/15 bg-white/10 shadow-inner shadow-black/5 backdrop-blur-md xl:w-auto xl:max-w-[72%] xl:justify-end ${compact ? 'p-1' : 'p-1.5 sm:p-2'}`}`}
                    >
                        {actions}
                    </div>
                )}
            </div>
        </section>
    );
}
