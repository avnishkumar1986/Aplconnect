import { ChevronDown, ChevronLeft, ChevronRight, ChevronsUpDown, Search } from 'lucide-react';
import { type ReactNode, useEffect, useMemo, useState } from 'react';

export type DataTableColumn<T> = {
    key: string;
    label: string;
    value: (row: T) => string | number;
    render?: (row: T) => ReactNode;
    sortable?: boolean;
    className?: string;
};

export function DataTable<T extends object>({
    rows,
    columns,
    searchPlaceholder = 'Search records',
    initialPageSize = 5,
    emptyMessage = 'No matching records found.',
}: {
    rows: T[];
    columns: DataTableColumn<T>[];
    searchPlaceholder?: string;
    initialPageSize?: number;
    emptyMessage?: string;
}) {
    const [query, setQuery] = useState('');
    const [sortKey, setSortKey] = useState(columns.find((column) => column.sortable !== false)?.key ?? '');
    const [direction, setDirection] = useState<'asc' | 'desc'>('asc');
    const [page, setPage] = useState(1);
    const [pageSize, setPageSize] = useState(initialPageSize);

    const filteredRows = useMemo(() => {
        const normalizedQuery = query.trim().toLowerCase();
        if (!normalizedQuery) return rows;
        return rows.filter((row) => columns.some((column) => String(column.value(row)).toLowerCase().includes(normalizedQuery)));
    }, [columns, query, rows]);

    const sortedRows = useMemo(() => {
        const column = columns.find((item) => item.key === sortKey);
        if (!column) return filteredRows;
        return [...filteredRows].sort((left, right) => {
            const leftValue = column.value(left);
            const rightValue = column.value(right);
            const result = typeof leftValue === 'number' && typeof rightValue === 'number'
                ? leftValue - rightValue
                : String(leftValue).localeCompare(String(rightValue), undefined, { numeric: true });
            return direction === 'asc' ? result : -result;
        });
    }, [columns, direction, filteredRows, sortKey]);

    const pageCount = Math.max(1, Math.ceil(sortedRows.length / pageSize));
    const visibleRows = sortedRows.slice((page - 1) * pageSize, page * pageSize);
    const firstRecord = sortedRows.length === 0 ? 0 : (page - 1) * pageSize + 1;
    const lastRecord = Math.min(page * pageSize, sortedRows.length);

    useEffect(() => setPage(1), [query, pageSize, rows]);
    useEffect(() => setPage((current) => Math.min(current, pageCount)), [pageCount]);

    const sortBy = (column: DataTableColumn<T>) => {
        if (column.sortable === false) return;
        if (sortKey === column.key) setDirection((current) => current === 'asc' ? 'desc' : 'asc');
        else {
            setSortKey(column.key);
            setDirection('asc');
        }
    };

    return (
        <div>
            <div className="border-border bg-card flex flex-col gap-3 border-b px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                <label className="relative block w-full sm:max-w-xs">
                    <Search className="text-muted-foreground absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                    <input value={query} onChange={(event) => setQuery(event.target.value)} placeholder={searchPlaceholder} className="border-input bg-background focus:border-brand-teal focus:ring-brand-aqua/30 h-9 w-full rounded-lg border pr-3 pl-9 text-xs shadow-sm outline-none transition focus:ring-2" />
                </label>
                <label className="text-muted-foreground flex items-center gap-2 text-[10px] font-semibold">
                    Show
                    <span className="relative">
                        <select value={pageSize} onChange={(event) => setPageSize(Number(event.target.value))} className="border-input bg-background h-9 appearance-none rounded-lg border py-0 pr-8 pl-3 text-xs font-semibold outline-none">
                            {[5, 10, 25].map((size) => <option key={size} value={size}>{size}</option>)}
                        </select>
                        <ChevronDown className="text-muted-foreground pointer-events-none absolute top-1/2 right-2 size-3.5 -translate-y-1/2" />
                    </span>
                    entries
                </label>
            </div>

            <div className="overflow-x-auto">
                <table className="w-full min-w-[760px] text-left text-[11px]">
                    <thead className="bg-muted text-muted-foreground sticky top-0 z-10 text-[9px] tracking-wider uppercase">
                        <tr><th className="w-16 px-4 py-3 text-center font-bold">S.No.</th>{columns.map((column) => <th key={column.key} className={`px-4 py-3 font-bold ${column.className ?? ''}`}><button type="button" onClick={() => sortBy(column)} disabled={column.sortable === false} className="inline-flex items-center gap-1.5 disabled:cursor-default"><span>{column.label}</span>{column.sortable !== false && <ChevronsUpDown className={`size-3 ${sortKey === column.key ? 'text-brand-teal' : 'opacity-40'}`} />}</button></th>)}</tr>
                    </thead>
                    <tbody>
                        {visibleRows.map((row, rowIndex) => <tr key={rowIndex} className="border-border even:bg-muted/20 hover:bg-brand-aqua/10 border-t transition-colors"><td className="text-muted-foreground px-4 py-3.5 text-center font-semibold">{(page - 1) * pageSize + rowIndex + 1}</td>{columns.map((column) => <td key={column.key} className={`px-4 py-3.5 ${column.className ?? ''}`}>{column.render ? column.render(row) : column.value(row)}</td>)}</tr>)}
                        {visibleRows.length === 0 && <tr><td colSpan={columns.length + 1} className="text-muted-foreground px-4 py-10 text-center text-xs">{emptyMessage}</td></tr>}
                    </tbody>
                </table>
            </div>

            <div className="border-border bg-muted/20 text-muted-foreground flex flex-col gap-3 border-t px-4 py-3 text-[10px] sm:flex-row sm:items-center sm:justify-between">
                <span>Showing {firstRecord} to {lastRecord} of {sortedRows.length} entries</span>
                <div className="flex items-center gap-1.5">
                    <button type="button" disabled={page === 1} onClick={() => setPage((current) => current - 1)} className="border-input hover:bg-muted grid size-8 place-items-center rounded-lg border disabled:cursor-not-allowed disabled:opacity-40" aria-label="Previous page"><ChevronLeft className="size-4" /></button>
                    <span className="bg-brand-teal grid min-w-8 place-items-center rounded-lg px-2 py-2 font-bold text-white">{page}</span>
                    <span className="px-1">of {pageCount}</span>
                    <button type="button" disabled={page === pageCount} onClick={() => setPage((current) => current + 1)} className="border-input hover:bg-muted grid size-8 place-items-center rounded-lg border disabled:cursor-not-allowed disabled:opacity-40" aria-label="Next page"><ChevronRight className="size-4" /></button>
                </div>
            </div>
        </div>
    );
}
