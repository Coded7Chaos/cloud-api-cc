import { FormEvent, useCallback, useEffect, useMemo, useState } from 'react';
import { CheckCircle2, ClipboardCheck, Clock3, Plus, UsersRound } from 'lucide-react';
import { toast } from 'sonner';
import { api } from '../../lib/api';
import { useAuth } from '../../lib/auth';
import { Badge } from '../components/ui/badge';
import { Button } from '../components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '../components/ui/dialog';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '../components/ui/table';

type AssignableUser = {
    id: number;
    name: string;
    last_name: string;
    email: string;
};

type TaskAssignment = AssignableUser & {
    status: 'pending' | 'completed';
    assigned_at: string;
    completed_at: string | null;
};

type Task = {
    id: number;
    titulo: string;
    descripcion: string | null;
    created_at: string;
    usuarios: TaskAssignment[];
};

const formatDateTime = (value: string | null) =>
    value
        ? new Date(value).toLocaleString('es-BO', {
              dateStyle: 'short',
              timeStyle: 'short',
          })
        : '—';

export default function TareasPage() {
    const { user } = useAuth();
    const isAdmin = user?.role?.name === 'administrador';
    const [tasks, setTasks] = useState<Task[]>([]);
    const [assignableUsers, setAssignableUsers] = useState<AssignableUser[]>([]);
    const [loading, setLoading] = useState(true);
    const [dialogOpen, setDialogOpen] = useState(false);
    const [saving, setSaving] = useState(false);
    const [completingId, setCompletingId] = useState<number | null>(null);
    const [title, setTitle] = useState('');
    const [description, setDescription] = useState('');
    const [selectedUsers, setSelectedUsers] = useState<number[]>([]);

    const load = useCallback(() => {
        setLoading(true);
        api
            .get('/tareas')
            .then((response) => {
                setTasks(response.data.data);
                setAssignableUsers(response.data.assignable_users ?? []);
            })
            .catch(() => toast.error('No se pudieron cargar las tareas.'))
            .finally(() => setLoading(false));
    }, []);

    useEffect(() => load(), [load]);

    const assignments = useMemo(
        () =>
            tasks.flatMap((task) =>
                task.usuarios.map((assignment) => ({
                    task,
                    assignment,
                })),
            ),
        [tasks],
    );
    const completedCount = assignments.filter(({ assignment }) => assignment.status === 'completed').length;
    const pendingCount = assignments.length - completedCount;

    const openCreate = () => {
        setTitle('');
        setDescription('');
        setSelectedUsers([]);
        setDialogOpen(true);
    };

    const toggleUser = (userId: number) => {
        setSelectedUsers((current) =>
            current.includes(userId) ? current.filter((id) => id !== userId) : [...current, userId],
        );
    };

    const createTask = async (event: FormEvent) => {
        event.preventDefault();

        if (selectedUsers.length === 0) {
            toast.error('Selecciona al menos un agente.');
            return;
        }

        setSaving(true);
        try {
            await api.post('/tareas', {
                titulo: title,
                descripcion: description || null,
                usuarios: selectedUsers,
            });
            toast.success('Tarea creada y asignada.');
            setDialogOpen(false);
            load();
        } catch {
            toast.error('No se pudo crear la tarea. Revisa los datos e inténtalo otra vez.');
        } finally {
            setSaving(false);
        }
    };

    const completeTask = async (taskId: number) => {
        setCompletingId(taskId);
        try {
            await api.patch(`/tareas/${taskId}/completar`);
            setTasks((current) =>
                current.map((task) =>
                    task.id === taskId
                        ? {
                              ...task,
                              usuarios: task.usuarios.map((assignment) => ({
                                  ...assignment,
                                  status: 'completed',
                                  completed_at: new Date().toISOString(),
                              })),
                          }
                        : task,
                ),
            );
            toast.success('Tarea marcada como realizada.');
        } catch {
            toast.error('No se pudo actualizar la tarea.');
        } finally {
            setCompletingId(null);
        }
    };

    return (
        <div className="h-full bg-white rounded-2xl overflow-hidden flex flex-col">
            <div className="flex items-center justify-between gap-3 px-5 md:px-6 py-4 border-b border-black/5">
                <div>
                    <h2 className="text-lg font-medium text-[#004479]">Tareas</h2>
                    <p className="text-xs text-muted-foreground">
                        {isAdmin ? 'Asigna actividades y revisa su avance' : 'Actividades asignadas a tu cuenta'}
                    </p>
                </div>
                {isAdmin && (
                    <Button onClick={openCreate} className="bg-[#004479] hover:bg-[#00305a]">
                        <Plus size={16} /> Nueva tarea
                    </Button>
                )}
            </div>

            <div className="grid grid-cols-3 border-b border-black/5 bg-[#f8fafc]">
                <Summary label="Asignaciones" value={assignments.length} icon={UsersRound} />
                <Summary label="Pendientes" value={pendingCount} icon={Clock3} />
                <Summary label="Realizadas" value={completedCount} icon={CheckCircle2} />
            </div>

            <div className="flex-1 overflow-auto">
                {loading ? (
                    <p className="px-6 py-8 text-sm text-muted-foreground">Cargando tareas…</p>
                ) : tasks.length === 0 ? (
                    <div className="h-full min-h-64 flex flex-col items-center justify-center text-center p-6">
                        <ClipboardCheck size={36} className="text-muted-foreground/40" />
                        <p className="mt-3 text-sm font-medium text-[#004479]">No hay tareas todavía</p>
                        <p className="mt-1 text-xs text-muted-foreground">
                            {isAdmin ? 'Crea la primera tarea para comenzar.' : 'Cuando te asignen una aparecerá aquí.'}
                        </p>
                    </div>
                ) : isAdmin ? (
                    <AdminTaskTable assignments={assignments} />
                ) : (
                    <div className="grid gap-3 p-4 md:p-5 md:grid-cols-2 xl:grid-cols-3">
                        {tasks.map((task) => {
                            const assignment = task.usuarios[0];
                            const completed = assignment?.status === 'completed';

                            return (
                                <article key={task.id} className="rounded-xl border border-black/10 p-4 flex flex-col gap-3">
                                    <div className="flex items-start justify-between gap-3">
                                        <h3 className="font-medium text-[#004479]">{task.titulo}</h3>
                                        <StatusBadge completed={completed} />
                                    </div>
                                    {task.descripcion && (
                                        <p className="text-sm text-muted-foreground whitespace-pre-wrap">{task.descripcion}</p>
                                    )}
                                    <p className="text-xs text-muted-foreground mt-auto">
                                        Asignada {formatDateTime(assignment?.assigned_at ?? task.created_at)}
                                    </p>
                                    {completed ? (
                                        <p className="text-xs text-emerald-700">
                                            Realizada {formatDateTime(assignment.completed_at)}
                                        </p>
                                    ) : (
                                        <Button
                                            onClick={() => completeTask(task.id)}
                                            disabled={completingId === task.id}
                                            className="w-full bg-[#004479] hover:bg-[#00305a]"
                                        >
                                            <CheckCircle2 size={16} />
                                            {completingId === task.id ? 'Actualizando…' : 'Marcar como realizada'}
                                        </Button>
                                    )}
                                </article>
                            );
                        })}
                    </div>
                )}
            </div>

            <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
                <DialogContent className="sm:max-w-xl max-h-[90vh] overflow-y-auto">
                    <form onSubmit={createTask} className="space-y-5">
                        <DialogHeader>
                            <DialogTitle>Crear nueva tarea</DialogTitle>
                            <DialogDescription>Define la actividad y asígnala a uno o más agentes de soporte.</DialogDescription>
                        </DialogHeader>

                        <label className="block text-sm font-medium">
                            Título
                            <input
                                value={title}
                                onChange={(event) => setTitle(event.target.value)}
                                required
                                maxLength={255}
                                className="mt-1.5 w-full h-10 rounded-md border border-input bg-input-background px-3 outline-none focus:ring-2 focus:ring-[#004479]/20"
                            />
                        </label>

                        <label className="block text-sm font-medium">
                            Descripción
                            <textarea
                                value={description}
                                onChange={(event) => setDescription(event.target.value)}
                                rows={4}
                                className="mt-1.5 w-full rounded-md border border-input bg-input-background px-3 py-2 outline-none resize-y focus:ring-2 focus:ring-[#004479]/20"
                            />
                        </label>

                        <fieldset>
                            <legend className="mb-2 text-sm font-medium">Asignar agentes</legend>
                            <div className="max-h-60 overflow-y-auto rounded-lg border divide-y">
                                {assignableUsers.length === 0 ? (
                                    <p className="p-4 text-sm text-muted-foreground">No hay agentes de soporte disponibles.</p>
                                ) : (
                                    assignableUsers.map((assignable) => (
                                        <label
                                            key={assignable.id}
                                            className="flex cursor-pointer items-center gap-3 px-4 py-3 hover:bg-black/[0.03]"
                                        >
                                            <input
                                                type="checkbox"
                                                checked={selectedUsers.includes(assignable.id)}
                                                onChange={() => toggleUser(assignable.id)}
                                                className="size-4 accent-[#004479]"
                                            />
                                            <span className="min-w-0">
                                                <strong className="block text-sm text-[#004479]">
                                                    {assignable.name} {assignable.last_name}
                                                </strong>
                                                <small className="block truncate text-muted-foreground">{assignable.email}</small>
                                            </span>
                                        </label>
                                    ))
                                )}
                            </div>
                        </fieldset>

                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setDialogOpen(false)} disabled={saving}>
                                Cancelar
                            </Button>
                            <Button
                                type="submit"
                                disabled={saving || assignableUsers.length === 0}
                                className="bg-[#004479] hover:bg-[#00305a]"
                            >
                                {saving ? 'Creando…' : 'Crear y asignar'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </div>
    );
}

function Summary({ label, value, icon: Icon }: { label: string; value: number; icon: typeof UsersRound }) {
    return (
        <div className="flex items-center justify-center gap-2 px-2 py-3 border-r last:border-r-0 border-black/5">
            <Icon size={16} className="hidden sm:block text-[#004479]" />
            <div>
                <span className="block text-base font-semibold leading-none text-[#004479]">{value}</span>
                <span className="text-[10px] sm:text-xs text-muted-foreground">{label}</span>
            </div>
        </div>
    );
}

function StatusBadge({ completed }: { completed: boolean }) {
    return completed ? (
        <Badge className="border-transparent bg-emerald-100 text-emerald-800">Realizada</Badge>
    ) : (
        <Badge variant="secondary" className="text-amber-800 bg-amber-100">
            Pendiente
        </Badge>
    );
}

function AdminTaskTable({ assignments }: { assignments: Array<{ task: Task; assignment: TaskAssignment }> }) {
    return (
        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead className="pl-5 md:pl-6">Tarea</TableHead>
                    <TableHead>Agente</TableHead>
                    <TableHead>Asignada</TableHead>
                    <TableHead>Estado</TableHead>
                    <TableHead>Realizada</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {assignments.map(({ task, assignment }) => (
                    <TableRow key={`${task.id}-${assignment.id}`}>
                        <TableCell className="pl-5 md:pl-6 whitespace-normal min-w-60">
                            <span className="font-medium text-[#004479]">{task.titulo}</span>
                            {task.descripcion && (
                                <span className="mt-0.5 block text-xs text-muted-foreground line-clamp-2">
                                    {task.descripcion}
                                </span>
                            )}
                        </TableCell>
                        <TableCell>
                            <span className="font-medium">
                                {assignment.name} {assignment.last_name}
                            </span>
                            <span className="block text-xs text-muted-foreground">{assignment.email}</span>
                        </TableCell>
                        <TableCell className="text-muted-foreground">{formatDateTime(assignment.assigned_at)}</TableCell>
                        <TableCell>
                            <StatusBadge completed={assignment.status === 'completed'} />
                        </TableCell>
                        <TableCell className="text-muted-foreground">{formatDateTime(assignment.completed_at)}</TableCell>
                    </TableRow>
                ))}
            </TableBody>
        </Table>
    );
}
