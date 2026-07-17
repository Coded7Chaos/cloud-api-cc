import { useCallback, useEffect, useState } from 'react';
import { ClipboardList, Filter, RefreshCw } from 'lucide-react';
import { toast } from 'sonner';
import { api } from '../../lib/api';
import { Button } from '../components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '../components/ui/table';

type AuditLog = {
    id: number;
    category: 'usuarios' | 'horarios';
    action: string;
    description: string;
    actor: { id: number | null; name: string; email: string | null } | null;
    target: { id: number | null; name: string; email: string | null } | null;
    occurred_at: string;
};

const actionLabels: Record<string, string> = {
    usuario_creado: 'Usuario creado',
    usuario_editado: 'Usuario editado',
    usuario_eliminado: 'Usuario eliminado',
    contrasena_cambiada: 'Contraseña cambiada',
    recuperacion_solicitada: 'Recuperación solicitada',
    contrasena_recuperada: 'Contraseña recuperada',
    contrasena_inicial_creada: 'Contraseña inicial',
    horario_creado: 'Horario creado',
    horario_actualizado: 'Horario actualizado',
    horario_eliminado: 'Horario eliminado',
};

function formatDateTime(value: string) {
    return new Date(value).toLocaleString('es', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

export default function AuditoriaPage() {
    const [logs, setLogs] = useState<AuditLog[]>([]);
    const [loading, setLoading] = useState(true);
    const [category, setCategory] = useState('');
    const [dateFrom, setDateFrom] = useState('');
    const [dateTo, setDateTo] = useState('');

    const load = useCallback(async () => {
        setLoading(true);
        try {
            const res = await api.get('/audit-logs', {
                params: {
                    category: category || undefined,
                    date_from: dateFrom || undefined,
                    date_to: dateTo || undefined,
                },
            });
            setLogs(res.data.data);
        } catch {
            toast.error('No se pudo cargar la auditoría.');
        } finally {
            setLoading(false);
        }
    }, [category, dateFrom, dateTo]);

    useEffect(() => {
        load();
    }, [load]);

    const clearFilters = () => {
        setCategory('');
        setDateFrom('');
        setDateTo('');
    };

    return (
        <div className="h-full bg-white rounded-2xl overflow-hidden flex flex-col">
            <div className="px-5 md:px-6 py-4 border-b border-black/5">
                <div className="flex items-center gap-2">
                    <ClipboardList size={19} className="text-[#004479]" />
                    <h1 className="text-lg font-medium text-[#004479]">Auditoría</h1>
                </div>
                <p className="text-xs text-muted-foreground mt-1">
                    Movimientos administrativos de usuarios y horarios.
                </p>
            </div>

            <div className="px-5 md:px-6 py-3 border-b border-black/5 flex flex-wrap items-end gap-3">
                <div className="w-full sm:w-44">
                    <label className="text-xs text-muted-foreground">Sección</label>
                    <select
                        value={category}
                        onChange={(e) => setCategory(e.target.value)}
                        className="mt-1 w-full h-9 rounded-md border border-input bg-input-background px-3 text-sm outline-none focus:ring-2 focus:ring-[#004479]/20"
                    >
                        <option value="">Todas</option>
                        <option value="usuarios">Usuarios</option>
                        <option value="horarios">Horarios</option>
                    </select>
                </div>
                <div>
                    <label className="text-xs text-muted-foreground">Desde</label>
                    <input
                        type="date"
                        value={dateFrom}
                        onChange={(e) => setDateFrom(e.target.value)}
                        className="mt-1 block h-9 rounded-md border border-input bg-input-background px-3 text-sm outline-none focus:ring-2 focus:ring-[#004479]/20"
                    />
                </div>
                <div>
                    <label className="text-xs text-muted-foreground">Hasta</label>
                    <input
                        type="date"
                        value={dateTo}
                        onChange={(e) => setDateTo(e.target.value)}
                        className="mt-1 block h-9 rounded-md border border-input bg-input-background px-3 text-sm outline-none focus:ring-2 focus:ring-[#004479]/20"
                    />
                </div>
                <Button type="button" variant="outline" onClick={clearFilters}>
                    <Filter size={15} /> Limpiar
                </Button>
                <Button type="button" onClick={load} className="bg-[#004479] hover:bg-[#00305a]">
                    <RefreshCw size={15} /> Actualizar
                </Button>
            </div>

            <div className="flex-1 overflow-y-auto p-4 md:p-5">
                {loading ? (
                    <div className="h-full flex items-center justify-center text-sm text-muted-foreground">
                        Cargando auditoría…
                    </div>
                ) : logs.length === 0 ? (
                    <div className="h-full flex items-center justify-center text-sm text-muted-foreground">
                        No hay movimientos para los filtros seleccionados.
                    </div>
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Fecha</TableHead>
                                <TableHead>Sección</TableHead>
                                <TableHead>Evento</TableHead>
                                <TableHead>Quién</TableHead>
                                <TableHead>Afectado</TableHead>
                                <TableHead>Detalle</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {logs.map((log) => (
                                <TableRow key={log.id}>
                                    <TableCell className="text-muted-foreground">{formatDateTime(log.occurred_at)}</TableCell>
                                    <TableCell className="capitalize">{log.category}</TableCell>
                                    <TableCell>{actionLabels[log.action] ?? log.action}</TableCell>
                                    <TableCell>{log.actor?.name ?? 'Sistema'}</TableCell>
                                    <TableCell>{log.target?.name ?? '—'}</TableCell>
                                    <TableCell className="whitespace-normal min-w-72">{log.description}</TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}
            </div>
        </div>
    );
}
