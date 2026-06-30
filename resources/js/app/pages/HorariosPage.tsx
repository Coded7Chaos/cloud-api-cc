import { useEffect, useMemo, useState } from 'react';
import { Plus, Trash2, Save, CalendarClock } from 'lucide-react';
import axios from 'axios';
import { toast } from 'sonner';
import { api } from '../../lib/api';
import { Button } from '../components/ui/button';

type ApiShift = { id: number; weekday: number; start_time: string; end_time: string };
type ScheduleRow = {
    user: { id: number; name: string };
    version: { id: number; effective_from: string } | null;
    shifts: ApiShift[];
};

// Borrador editable en el cliente (key estable para React).
type DraftShift = { key: string; weekday: number; start_time: string; end_time: string };

const WEEKDAYS = [
    { n: 1, label: 'Lunes' },
    { n: 2, label: 'Martes' },
    { n: 3, label: 'Miércoles' },
    { n: 4, label: 'Jueves' },
    { n: 5, label: 'Viernes' },
    { n: 6, label: 'Sábado' },
    { n: 7, label: 'Domingo' },
];

let keyCounter = 0;
const newKey = () => `s${keyCounter++}`;

// Las fechas vienen como 'YYYY-MM-DD'. Parsearlas con new Date() las trata como
// UTC y corren un día según la zona horaria; las formateamos a mano.
const formatDate = (isoDate: string) => {
    const [y, m, d] = isoDate.split('-');
    return `${d}/${m}/${y}`;
};

export default function HorariosPage() {
    const [schedules, setSchedules] = useState<ScheduleRow[]>([]);
    const [loading, setLoading] = useState(true);
    const [selectedUserId, setSelectedUserId] = useState<number | null>(null);
    const [draft, setDraft] = useState<DraftShift[]>([]);
    const [saving, setSaving] = useState(false);

    const applyData = (rows: ScheduleRow[], keepSelection = true) => {
        setSchedules(rows);
        const id = keepSelection && selectedUserId != null ? selectedUserId : rows[0]?.user.id ?? null;
        setSelectedUserId(id);
        loadDraft(rows, id);
    };

    const loadDraft = (rows: ScheduleRow[], userId: number | null) => {
        const row = rows.find((r) => r.user.id === userId);
        setDraft(
            (row?.shifts ?? []).map((s) => ({
                key: newKey(),
                weekday: s.weekday,
                start_time: s.start_time,
                end_time: s.end_time,
            })),
        );
    };

    useEffect(() => {
        api
            .get('/schedules')
            .then((res) => applyData(res.data.data, false))
            .catch(() => toast.error('No se pudieron cargar los horarios.'))
            .finally(() => setLoading(false));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const selectUser = (userId: number) => {
        setSelectedUserId(userId);
        loadDraft(schedules, userId);
    };

    const selectedRow = useMemo(
        () => schedules.find((r) => r.user.id === selectedUserId) ?? null,
        [schedules, selectedUserId],
    );

    const shiftsByDay = (weekday: number) => draft.filter((s) => s.weekday === weekday);

    const addShift = (weekday: number) =>
        setDraft((d) => [...d, { key: newKey(), weekday, start_time: '09:00', end_time: '18:00' }]);

    const removeShift = (key: string) => setDraft((d) => d.filter((s) => s.key !== key));

    const updateShift = (key: string, field: 'start_time' | 'end_time', value: string) =>
        setDraft((d) => d.map((s) => (s.key === key ? { ...s, [field]: value } : s)));

    const save = async () => {
        if (selectedUserId == null) return;
        setSaving(true);
        try {
            const payload = {
                shifts: draft.map(({ weekday, start_time, end_time }) => ({ weekday, start_time, end_time })),
            };
            const res = await api.put(`/users/${selectedUserId}/schedule`, payload);
            toast.success('Horario guardado.');
            applyData(res.data.data, true);
        } catch (err) {
            if (axios.isAxiosError(err) && err.response?.status === 422) {
                toast.error('Revisa los turnos: la hora de fin debe ser posterior a la de inicio.');
            } else {
                toast.error('No se pudo guardar el horario.');
            }
        } finally {
            setSaving(false);
        }
    };

    if (loading) {
        return (
            <div className="h-full bg-white rounded-2xl flex items-center justify-center">
                <p className="text-sm text-muted-foreground">Cargando horarios…</p>
            </div>
        );
    }

    return (
        <div className="h-full grid gap-3 md:gap-4 grid-cols-1 md:grid-cols-[260px_1fr]">
            {/* Lista de usuarios */}
            <div className="bg-white rounded-2xl overflow-hidden flex flex-col">
                <div className="px-5 pt-5 pb-3 border-b border-black/5">
                    <h3 className="text-lg font-medium text-[#004479]">Horarios</h3>
                    <p className="text-xs text-muted-foreground">Selecciona un agente</p>
                </div>
                <div className="flex-1 overflow-y-auto p-2">
                    {schedules.map((row) => {
                        const selected = row.user.id === selectedUserId;
                        const days = new Set(row.shifts.map((s) => s.weekday)).size;
                        return (
                            <button
                                key={row.user.id}
                                onClick={() => selectUser(row.user.id)}
                                className={`w-full flex items-center justify-between gap-2 px-3 py-2.5 rounded-xl text-left mb-1 transition-colors ${
                                    selected ? 'bg-[#004479]/8' : 'hover:bg-black/5'
                                }`}
                            >
                                <span className="text-sm truncate text-[#004479]">{row.user.name}</span>
                                <span className="text-[11px] text-muted-foreground shrink-0">{days} días</span>
                            </button>
                        );
                    })}
                </div>
            </div>

            {/* Editor semanal */}
            <div className="bg-white rounded-2xl overflow-hidden flex flex-col">
                <div className="flex items-center justify-between gap-3 px-5 md:px-6 py-4 border-b border-black/5">
                    <div className="flex items-center gap-2 min-w-0">
                        <CalendarClock size={18} className="text-[#004479] shrink-0" />
                        <div className="min-w-0">
                            <h2 className="text-base font-medium text-[#004479] truncate">
                                {selectedRow?.user.name ?? 'Sin selección'}
                            </h2>
                            <p className="text-xs text-muted-foreground">
                                {selectedRow?.version
                                    ? `Vigente desde ${formatDate(selectedRow.version.effective_from)}`
                                    : 'Sin horario vigente'}
                            </p>
                        </div>
                    </div>
                    <Button onClick={save} disabled={saving || selectedUserId == null} className="bg-[#004479] hover:bg-[#00305a]">
                        <Save size={16} /> {saving ? 'Guardando…' : 'Guardar'}
                    </Button>
                </div>

                <div className="flex-1 overflow-y-auto p-4 md:p-5 space-y-2">
                    {WEEKDAYS.map(({ n, label }) => {
                        const dayShifts = shiftsByDay(n);
                        return (
                            <div key={n} className="flex items-start gap-3 py-2 border-b border-black/5 last:border-0">
                                <div className="w-24 shrink-0 pt-2 text-sm font-medium text-[#004479]">{label}</div>
                                <div className="flex-1 space-y-2">
                                    {dayShifts.length === 0 && (
                                        <p className="text-xs text-muted-foreground pt-2">Descanso</p>
                                    )}
                                    {dayShifts.map((s) => (
                                        <div key={s.key} className="flex items-center gap-2">
                                            <input
                                                type="time"
                                                value={s.start_time}
                                                onChange={(e) => updateShift(s.key, 'start_time', e.target.value)}
                                                className="bg-[#f4f6f9] rounded-lg px-3 py-1.5 text-sm outline-none focus:ring-2 focus:ring-[#004479]/20"
                                            />
                                            <span className="text-muted-foreground text-sm">—</span>
                                            <input
                                                type="time"
                                                value={s.end_time}
                                                onChange={(e) => updateShift(s.key, 'end_time', e.target.value)}
                                                className="bg-[#f4f6f9] rounded-lg px-3 py-1.5 text-sm outline-none focus:ring-2 focus:ring-[#004479]/20"
                                            />
                                            <button
                                                onClick={() => removeShift(s.key)}
                                                className="w-8 h-8 rounded-lg text-destructive hover:bg-destructive/10 flex items-center justify-center"
                                                title="Quitar turno"
                                            >
                                                <Trash2 size={15} />
                                            </button>
                                        </div>
                                    ))}
                                </div>
                                <button
                                    onClick={() => addShift(n)}
                                    disabled={selectedUserId == null}
                                    className="shrink-0 mt-1 flex items-center gap-1 text-xs text-[#004479] hover:bg-[#004479]/8 rounded-lg px-2 py-1.5 disabled:opacity-40"
                                >
                                    <Plus size={14} /> Turno
                                </button>
                            </div>
                        );
                    })}
                </div>
            </div>
        </div>
    );
}
