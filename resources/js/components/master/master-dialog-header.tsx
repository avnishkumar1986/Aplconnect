import { DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Database } from 'lucide-react';

export const masterDialogContentClass =
    '!fixed !top-1/2 !left-1/2 !z-[100] flex max-h-[calc(100dvh-2rem)] w-[calc(100vw-2rem)] max-w-[1400px] !-translate-x-1/2 !-translate-y-1/2 flex-col gap-0 overflow-hidden rounded-xl border border-white/20 bg-slate-50 !p-0 opacity-100 shadow-[0_28px_80px_-20px_rgba(15,23,42,0.55)] pointer-events-auto [&>button]:top-4 [&>button]:right-5 [&>button]:rounded-full [&>button]:p-1.5 [&>button]:text-white/70 [&>button]:transition-colors [&>button]:hover:bg-white/10 [&>button]:hover:text-white';
export const masterDialogFormClass = 'flex max-h-[calc(100dvh-2rem)] min-h-0 flex-col overflow-hidden';
export const masterDialogBodyClass = 'flex-1 overflow-y-auto p-4 sm:p-5';
export const masterDialogFooterClass = 'shrink-0 border-t border-slate-200 bg-white px-4 py-3 sm:px-6';

export function MasterDialogHeader({ title, description, compact = false }: { title: string; description?: string; compact?: boolean }) {
    return (
        <DialogHeader
            className={`border-brand-gold bg-brand-plum relative w-full shrink-0 overflow-hidden border-b-2 px-4 text-left sm:px-5 ${compact ? 'py-2' : 'py-2.5 sm:py-3'}`}
        >
            <div className="flex items-start gap-2.5">
                <span
                    className={`text-brand-aqua grid shrink-0 place-items-center rounded-lg border border-white/15 bg-white/10 ${compact ? 'size-8' : 'size-9'}`}
                >
                    <Database className="size-5" />
                </span>
                <div className="min-w-0 space-y-1">
                    <p className="text-brand-aqua text-[10px] font-bold tracking-[0.18em] uppercase">APL Connect · Master data</p>
                    <DialogTitle className="text-base leading-tight font-bold text-white sm:text-lg">{title}</DialogTitle>
                    {description && <DialogDescription className="max-w-md text-xs leading-4 text-white/70">{description}</DialogDescription>}
                </div>
            </div>
        </DialogHeader>
    );
}
