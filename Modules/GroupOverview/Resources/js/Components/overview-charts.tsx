type LineChartProps = {
    sales: Array<number | null>;
    collections: Array<number | null>;
    target: number[];
    labels: string[];
};

const crore = (value: number) => `₹${value.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} Cr`;

export function LineTrendChart({ sales, collections, target, labels }: LineChartProps) {
    const daily = labels.map((date, index) => ({
        date,
        sales: sales[index] ?? null,
        collections: collections[index] ?? null,
        target: target[index] ?? 0,
    }));
    const displayedDays = daily;
    const plottedValues = displayedDays.flatMap((day) => [day.sales, day.collections, day.target]).filter((value): value is number => value !== null);
    const highestValue = Math.max(...plottedValues, 1);
    const chartLeft = 48;
    const chartRight = 684;
    const chartTop = 18;
    const chartBottom = 176;
    const pointX = (index: number) => chartLeft + (index / Math.max(displayedDays.length - 1, 1)) * (chartRight - chartLeft);
    const pointY = (value: number) => chartBottom - (Math.max(0, value) / highestValue) * (chartBottom - chartTop);
    const lineSegments = (values: Array<number | null>) => {
        const segments: string[] = [];
        let points: string[] = [];

        values.forEach((value, index) => {
            if (value === null) {
                if (points.length > 1) segments.push(points.join(' '));
                points = [];
                return;
            }

            points.push(`${pointX(index)},${pointY(value)}`);
        });
        if (points.length > 1) segments.push(points.join(' '));

        return segments;
    };
    const salesSegments = lineSegments(displayedDays.map((day) => day.sales));
    const collectionSegments = lineSegments(displayedDays.map((day) => day.collections));
    const targetSegments = lineSegments(displayedDays.map((day) => day.target));
    const yTicks = Array.from({ length: 5 }, (_, index) => highestValue - (highestValue / 4) * index);
    const collectionTotal = collections.reduce<number>((total, value) => total + (value ?? 0), 0);
    const hasCollections = collectionTotal > 0;

    return (
        <div className="space-y-5 p-4 sm:p-5">
            <div className="border-border bg-muted/20 min-h-56 overflow-x-auto rounded-xl border px-3 py-4">
                <svg
                    viewBox="0 0 720 210"
                    className="h-56 w-full min-w-0"
                    role="img"
                    aria-label="Daily sales, collections, and target line chart"
                >
                    {yTicks.map((tick, index) => {
                        const y = chartTop + index * ((chartBottom - chartTop) / 4);
                        return (
                            <g key={index}>
                                <line
                                    x1={chartLeft}
                                    x2={chartRight}
                                    y1={y}
                                    y2={y}
                                    className="stroke-border"
                                    strokeDasharray={index === 4 ? undefined : '4 5'}
                                />
                                <text x={chartLeft - 8} y={y + 3} textAnchor="end" className="fill-muted-foreground text-[8px]">
                                    {tick.toLocaleString('en-IN', { maximumFractionDigits: 2 })}
                                </text>
                            </g>
                        );
                    })}
                    {salesSegments.map((points, index) => (
                        <polyline
                            key={`sales-${index}`}
                            points={points}
                            fill="none"
                            stroke="#219ebc"
                            strokeWidth="2.5"
                            strokeLinejoin="round"
                            strokeLinecap="round"
                            vectorEffect="non-scaling-stroke"
                        />
                    ))}
                    {collectionSegments.map((points, index) => (
                        <polyline
                            key={`collections-${index}`}
                            points={points}
                            fill="none"
                            stroke="#fb8500"
                            strokeWidth="2.5"
                            strokeLinejoin="round"
                            strokeLinecap="round"
                            vectorEffect="non-scaling-stroke"
                        />
                    ))}
                    {targetSegments.map((points, index) => (
                        <polyline
                            key={`target-${index}`}
                            points={points}
                            fill="none"
                            stroke="#7c3aed"
                            strokeWidth="2"
                            strokeDasharray="7 5"
                            strokeLinejoin="round"
                            strokeLinecap="round"
                            vectorEffect="non-scaling-stroke"
                        />
                    ))}
                    {displayedDays.map((day, index) => {
                        const x = pointX(index);
                        return (
                            <g key={day.date}>
                                {day.sales !== null && (
                                    <circle
                                        cx={x}
                                        cy={pointY(day.sales)}
                                        r="3"
                                        fill="#219ebc"
                                        stroke="white"
                                        strokeWidth="1.5"
                                        vectorEffect="non-scaling-stroke"
                                    />
                                )}
                                {day.collections !== null && (
                                    <circle
                                        cx={x}
                                        cy={pointY(day.collections)}
                                        r="3"
                                        fill="#fb8500"
                                        stroke="white"
                                        strokeWidth="1.5"
                                        vectorEffect="non-scaling-stroke"
                                    />
                                )}
                                <circle
                                    cx={x}
                                    cy={pointY(day.target)}
                                    r="3"
                                    fill="#7c3aed"
                                    stroke="white"
                                    strokeWidth="1.5"
                                    vectorEffect="non-scaling-stroke"
                                />
                                <rect x={x - 12} y={chartTop} width="24" height={chartBottom - chartTop} fill="transparent" className="cursor-help">
                                    <title>{`${day.date} · Sales: ${day.sales === null ? 'No data' : crore(day.sales)} · Collections: ${day.collections === null ? 'No data' : crore(day.collections)} · Target: ${crore(day.target)}`}</title>
                                </rect>
                                <text
                                    x={x}
                                    y="198"
                                    textAnchor="end"
                                    transform={`rotate(-50 ${x} 198)`}
                                    className="fill-muted-foreground text-[7px] font-semibold"
                                >
                                    {day.date}
                                </text>
                            </g>
                        );
                    })}
                </svg>
            </div>

            <div className="flex flex-wrap items-center justify-between gap-3 text-[10px] font-semibold">
                <div className="text-muted-foreground flex gap-5">
                    <span className="flex items-center gap-2">
                        <i className="bg-brand-teal h-0.5 w-5" />
                        Sales
                    </span>
                    <span className="flex items-center gap-2">
                        <i className="bg-brand-berry h-0.5 w-5" />
                        Collections
                    </span>
                    <span className="flex items-center gap-2">
                        <i className="border-brand-plum h-0 w-5 border-t-2 border-dashed" />
                        Target
                    </span>
                </div>
                {!hasCollections && (
                    <span className="text-brand-berry bg-brand-berry/10 rounded-md px-2.5 py-1">No collection ledger entries in this period</span>
                )}
            </div>
        </div>
    );
}

export function PlanComparisonChart({ rows, unit }: { rows: Array<{ name: string; actual: number; plan: number }>; unit: 'Lk' | 'Cr' }) {
    const maximum = Math.max(...rows.flatMap((row) => [row.actual, row.plan]), 1);
    const formatValue = (valueInLakhs: number) => {
        const value = unit === 'Cr' ? valueInLakhs / 100 : valueInLakhs;

        return `${value.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${unit}`;
    };

    return (
        <div className="p-4 sm:p-5">
            <div className="space-y-7 py-3">
                {rows.map((row) => {
                    const achievement = row.plan > 0 ? `${Math.round((row.actual / row.plan) * 100)}% achieved` : 'Target unavailable';
                    return (
                        <div key={row.name} className="grid gap-2 sm:grid-cols-[120px_1fr] sm:items-center sm:gap-4">
                            <div>
                                <p className="text-[11px] font-bold">{row.name}</p>
                                <p className="text-muted-foreground mt-0.5 text-[9px]">{achievement}</p>
                            </div>
                            <div className="space-y-2">
                                <div className="flex items-center gap-3">
                                    <span className="text-muted-foreground w-9 text-[8px] font-bold uppercase">Actual</span>
                                    <div className="bg-muted relative h-3 flex-1 overflow-hidden rounded-full">
                                        <div
                                            className="from-brand-teal to-brand-aqua h-full rounded-full bg-gradient-to-r shadow-sm transition-all duration-500"
                                            style={{ width: `${(row.actual / maximum) * 100}%` }}
                                        />
                                    </div>
                                    <span className="w-20 text-right text-[9px] font-bold whitespace-nowrap">{formatValue(row.actual)}</span>
                                </div>
                                <div className="flex items-center gap-3">
                                    <span className="text-muted-foreground w-9 text-[8px] font-bold uppercase">Target</span>
                                    <div className="bg-muted relative h-2.5 flex-1 overflow-hidden rounded-full">
                                        <div
                                            className="from-brand-gold to-brand-berry h-full rounded-full bg-gradient-to-r transition-all duration-500"
                                            style={{ width: `${(row.plan / maximum) * 100}%` }}
                                        />
                                    </div>
                                    <span className="w-20 text-right text-[9px] font-bold whitespace-nowrap">{formatValue(row.plan)}</span>
                                </div>
                            </div>
                        </div>
                    );
                })}
            </div>
            <div className="border-border text-muted-foreground mt-5 flex justify-center gap-6 border-t pt-4 text-[10px] font-semibold">
                <span className="flex items-center gap-2">
                    <i className="bg-brand-teal size-2.5 rounded-full" />
                    Actual
                </span>
                <span className="flex items-center gap-2">
                    <i className="bg-brand-gold size-2.5 rounded-full" />
                    Target
                </span>
            </div>
        </div>
    );
}
