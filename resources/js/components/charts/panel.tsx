import type { ReactNode } from 'react';

/** Contenedor de gráfica con título. */
export function Panel({
    title,
    children,
    className = '',
}: {
    title: string;
    children: ReactNode;
    className?: string;
}) {
    return (
        <div
            className={`rounded-xl border border-sidebar-border/70 bg-card p-4 ${className}`}
        >
            <h2 className="mb-2 text-sm font-semibold">{title}</h2>
            {children}
        </div>
    );
}

const TONOS: Record<string, string> = {
    orange: 'border-l-orange-500',
    yellow: 'border-l-amber-500',
    blue: 'border-l-blue-500',
    green: 'border-l-emerald-500',
    pink: 'border-l-pink-500',
    purple: 'border-l-violet-500',
    gray: 'border-l-slate-400',
};

const nf = new Intl.NumberFormat('es-MX');

/** Tarjeta KPI de color (equivalente a la statsBar legacy). */
export function StatCard({
    label,
    value,
    color = 'gray',
    money,
}: {
    label: string;
    value: number;
    color?: keyof typeof TONOS | string;
    money?: boolean;
}) {
    const texto = money
        ? new Intl.NumberFormat('es-MX', {
              style: 'currency',
              currency: 'MXN',
              maximumFractionDigits: 0,
          }).format(value)
        : nf.format(value);

    return (
        <div
            className={`rounded-xl border border-l-4 border-sidebar-border/70 bg-card p-4 ${TONOS[color] ?? TONOS.gray}`}
        >
            <p className="text-2xl font-semibold tabular-nums">{texto}</p>
            <p className="text-xs text-muted-foreground">{label}</p>
        </div>
    );
}
