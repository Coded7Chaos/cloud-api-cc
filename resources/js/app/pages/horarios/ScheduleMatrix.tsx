import { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';
import { api } from '../../../lib/api';

type ApiShift = { id: number; weekday: number; start_time: string; end_time: string };
type ScheduleRow = {
    user: { id: number; name: string };
    version: { id: number; effective_from: string } | null;
    shifts: ApiShift[];
};

// Cada agente (fila del legend) con su número estable y sus horas semanales.
type Agent = { number: number; id: number; name: string; weeklyHours: string; shifts: ApiShift[] };
type Band = { start: string; end: string };

const DAY_LABELS: Record<number, string> = {
    1: 'Lunes',
    2: 'Martes',
    3: 'Miércoles',
    4: 'Jueves',
    5: 'Viernes',
    6: 'Sábado',
    7: 'Domingo',
};

const toMinutes = (hhmm: string) => {
    const [h, m] = hhmm.split(':').map(Number);
    return h * 60 + m;
};

const formatHours = (minutes: number) => {
    const hours = minutes / 60;
    return Number.isInteger(hours) ? String(hours) : hours.toFixed(1);
};

/**
 * Pestaña "Distribución": vista de solo lectura tipo planilla, armada
 * automáticamente desde los turnos ya cargados. Las filas son franjas horarias
 * (los cortes que surgen de los turnos), las columnas los días, y cada celda
 * lista el número de los agentes presentes en esa franja. Abajo, la leyenda
 * número → nombre, como en la planilla de referencia.
 */
export default function ScheduleMatrix() {
    const [rows, setRows] = useState<ScheduleRow[]>([]);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        api
            .get('/schedules')
            .then((res) => setRows(res.data.data))
            .catch(() => toast.error('No se pudieron cargar los horarios.'))
            .finally(() => setLoading(false));
    }, []);

    // Numeramos a los agentes por orden de llegada (ya vienen ordenados por
    // nombre); ese número es el que aparece en las celdas.
    const agents = useMemo<Agent[]>(
        () =>
            rows.map((row, i) => {
                const minutes = row.shifts.reduce(
                    (sum, s) => sum + (toMinutes(s.end_time) - toMinutes(s.start_time)),
                    0,
                );
                return {
                    number: i + 1,
                    id: row.user.id,
                    name: row.user.name,
                    weeklyHours: formatHours(minutes),
                    shifts: row.shifts,
                };
            }),
        [rows],
    );

    // Días con al menos un turno; siempre mostramos Lun–Vie.
    const days = useMemo(() => {
        const present = new Set<number>([1, 2, 3, 4, 5]);
        for (const row of rows) for (const s of row.shifts) present.add(s.weekday);
        return [...present].sort((a, b) => a - b);
    }, [rows]);

    // Franjas: los instantes de inicio/fin de todos los turnos, ordenados y
    // tomados de a pares consecutivos. Cada franja tiene un roster estable.
    const bands = useMemo<Band[]>(() => {
        const times = new Set<string>();
        for (const row of rows) {
            for (const s of row.shifts) {
                times.add(s.start_time);
                times.add(s.end_time);
            }
        }
        const sorted = [...times].sort();
        const result: Band[] = [];
        for (let i = 0; i < sorted.length - 1; i++) {
            result.push({ start: sorted[i], end: sorted[i + 1] });
        }
        return result;
    }, [rows]);

    // Números de los agentes presentes en (día, franja).
    const presentNumbers = (weekday: number, band: Band): number[] =>
        agents
            .filter((a) =>
                a.shifts.some(
                    (s) => s.weekday === weekday && s.start_time <= band.start && s.end_time >= band.end,
                ),
            )
            .map((a) => a.number);

    if (loading) {
        return (
            <div className="h-full bg-white rounded-2xl flex items-center justify-center">
                <p className="text-sm text-muted-foreground">Cargando distribución…</p>
            </div>
        );
    }

    if (bands.length === 0) {
        return (
            <div className="h-full bg-white rounded-2xl flex items-center justify-center">
                <p className="text-sm text-muted-foreground">Aún no hay turnos cargados para distribuir.</p>
            </div>
        );
    }

    return (
        <div className="h-full bg-white rounded-2xl overflow-auto p-4 md:p-5 space-y-6">
            {/* Matriz horario × día */}
            <div className="overflow-x-auto">
                <table className="border-collapse text-sm">
                    <thead>
                        <tr>
                            <th className="sticky left-0 z-10 bg-[#7a2e4a] text-white font-semibold px-3 py-2 border border-white/20 text-left whitespace-nowrap">
                                HORARIO
                            </th>
                            {days.map((d) => (
                                <th
                                    key={d}
                                    className="bg-[#7a2e4a] text-white font-semibold px-4 py-2 border border-white/20 uppercase whitespace-nowrap"
                                >
                                    {DAY_LABELS[d]}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {bands.map((band) => (
                            <tr key={`${band.start}-${band.end}`}>
                                <td className="sticky left-0 z-10 bg-[#f4f6f9] text-[#004479] font-medium px-3 py-1.5 border border-black/10 whitespace-nowrap">
                                    {band.start} - {band.end}
                                </td>
                                {days.map((d) => {
                                    const numbers = presentNumbers(d, band);
                                    return (
                                        <td
                                            key={d}
                                            className="px-2 py-1.5 border border-black/10 align-top min-w-[96px]"
                                        >
                                            <div className="flex flex-wrap gap-1">
                                                {numbers.map((n) => (
                                                    <span
                                                        key={n}
                                                        className="inline-flex items-center justify-center min-w-[22px] h-[22px] px-1 rounded bg-[#004479]/10 text-[#004479] text-xs font-medium"
                                                    >
                                                        {n}
                                                    </span>
                                                ))}
                                            </div>
                                        </td>
                                    );
                                })}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {/* Leyenda número → agente */}
            <div className="overflow-x-auto">
                <table className="border-collapse text-sm">
                    <thead>
                        <tr>
                            <th className="bg-[#7a2e4a] text-white font-semibold px-3 py-2 border border-white/20 w-12">
                                N°
                            </th>
                            <th className="bg-[#7a2e4a] text-white font-semibold px-4 py-2 border border-white/20 text-left">
                                Apellidos y nombres
                            </th>
                            <th className="bg-[#7a2e4a] text-white font-semibold px-3 py-2 border border-white/20 w-16">
                                H/S
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {agents.map((a) => (
                            <tr key={a.id}>
                                <td className="px-3 py-1.5 border border-black/10 text-center font-medium text-[#004479]">
                                    {a.number}
                                </td>
                                <td className="px-4 py-1.5 border border-black/10 whitespace-nowrap">{a.name}</td>
                                <td className="px-3 py-1.5 border border-black/10 text-center text-muted-foreground">
                                    {a.weeklyHours}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
