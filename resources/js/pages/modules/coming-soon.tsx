import { BrandLockup } from '@/components/brand-lockup';
import { Button } from '@/components/ui/button';
import { Head, Link } from '@inertiajs/react';
import { ArrowRight, CheckCircle2, Clock3, Construction, ShieldCheck, Sparkles } from 'lucide-react';

type ModuleSummary = {
    name: string;
    slug: string;
    description: string | null;
};

export default function ModuleComingSoon({ module }: { module: ModuleSummary }) {
    return (
        <>
            <Head title={`${module.name} · Coming Soon`} />
            <main className="relative min-h-screen overflow-hidden bg-slate-950 text-white">
                <div className="absolute inset-0 bg-[radial-gradient(circle_at_15%_20%,rgba(8,145,178,0.24),transparent_34%),radial-gradient(circle_at_85%_75%,rgba(249,115,22,0.16),transparent_30%)]" />
                <div className="absolute inset-0 [background-image:linear-gradient(rgba(255,255,255,.8)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,.8)_1px,transparent_1px)] [background-size:42px_42px] opacity-[0.035]" />

                <header className="relative z-10 flex items-center justify-between border-b border-white/10 px-5 py-4 sm:px-8 lg:px-12">
                    <BrandLockup tone="dark" compact orientation="horizontal" />
                    <span className="hidden items-center gap-2 rounded-full border border-emerald-400/20 bg-emerald-400/10 px-3 py-1.5 text-xs font-semibold text-emerald-300 sm:flex">
                        <span className="size-2 rounded-full bg-emerald-400" /> Portal online
                    </span>
                </header>

                <div className="relative z-10 mx-auto grid min-h-[calc(100vh-77px)] max-w-7xl items-center gap-12 px-5 py-12 sm:px-8 lg:grid-cols-[1.05fr_.95fr] lg:px-12 lg:py-16">
                    <section>
                        <div className="inline-flex items-center gap-2 rounded-full border border-cyan-300/20 bg-cyan-300/10 px-3 py-1.5 text-xs font-bold tracking-wide text-cyan-200 uppercase">
                            <Clock3 className="size-3.5" /> Module coming soon
                        </div>
                        <h1 className="mt-6 max-w-3xl text-4xl leading-tight font-black tracking-tight sm:text-5xl lg:text-6xl">
                            {module.name} is not currently active.
                        </h1>
                        <p className="mt-5 max-w-2xl text-base leading-7 text-slate-300 sm:text-lg">
                            {module.description ||
                                'This business module is being prepared for your workspace and will become available after activation.'}
                        </p>
                        <div className="mt-8 flex flex-col gap-3 sm:flex-row">
                            <Button asChild size="lg" className="gap-2 bg-cyan-500 text-white hover:bg-cyan-400">
                                <Link href="/">
                                    Return to dashboard <ArrowRight className="size-4" />
                                </Link>
                            </Button>
                            <div className="flex items-center gap-2 px-2 text-sm text-slate-400">
                                <ShieldCheck className="size-4 text-emerald-400" /> Your existing access settings are preserved
                            </div>
                        </div>
                    </section>

                    <section className="rounded-3xl border border-white/10 bg-white/[0.07] p-3 shadow-2xl shadow-black/20 backdrop-blur-xl">
                        <div className="rounded-[1.25rem] border border-white/10 bg-white/[0.06] p-6 sm:p-8">
                            <div className="flex items-center justify-between">
                                <div className="flex size-14 items-center justify-center rounded-2xl bg-cyan-400/15 text-cyan-300 ring-1 ring-cyan-300/20">
                                    <Construction className="size-7" />
                                </div>
                                <span className="rounded-full border border-amber-300/20 bg-amber-300/10 px-3 py-1 text-[10px] font-bold tracking-wider text-amber-200 uppercase">
                                    Awaiting activation
                                </span>
                            </div>
                            <h2 className="mt-7 text-xl font-bold">What happens next?</h2>
                            <div className="mt-5 space-y-4">
                                {[
                                    ['Administrator activation', 'The module is enabled from Master Control when it is ready for use.'],
                                    ['Automatic navigation', 'Its menu, pages, and role permissions appear without additional setup.'],
                                    ['Secure access', 'Users see only the functions assigned through their active roles.'],
                                ].map(([title, description]) => (
                                    <div key={title} className="flex gap-3 rounded-xl border border-white/10 bg-black/10 p-3.5">
                                        <CheckCircle2 className="mt-0.5 size-4 shrink-0 text-cyan-300" />
                                        <div>
                                            <p className="text-sm font-semibold text-white">{title}</p>
                                            <p className="mt-1 text-xs leading-5 text-slate-400">{description}</p>
                                        </div>
                                    </div>
                                ))}
                            </div>
                            <div className="mt-6 flex items-center gap-2 border-t border-white/10 pt-5 text-xs text-slate-400">
                                <Sparkles className="size-4 text-amber-300" /> Module reference:{' '}
                                <span className="font-mono text-slate-300">{module.slug}</span>
                            </div>
                        </div>
                    </section>
                </div>
            </main>
        </>
    );
}
