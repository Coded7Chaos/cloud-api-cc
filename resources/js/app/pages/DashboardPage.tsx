import { useCallback, useEffect, useState, type ReactNode } from 'react';
import { Link } from 'react-router';
import {
    CalendarDays,
    CheckCircle2,
    CircleDot,
    Clock3,
    Headphones,
    MessageCircle,
    MessageSquareReply,
    RefreshCw,
    UserRoundCheck,
    UsersRound,
    type LucideIcon,
} from 'lucide-react';
import { toast } from 'sonner';
import { api } from '../../lib/api';
import { useAuth } from '../../lib/auth';
import { Badge } from '../components/ui/badge';
import { Button } from '../components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '../components/ui/table';

type SupportDashboard = {
    role: 'soporte';
    summary: {
        assigned_chats: number;
        active_chats: number;
        pending_tasks: number;
        completed_tasks: number;
    };
    schedule: {
        effective_from: string;
        shifts: Array<{ weekday: number; start_time: string; end_time: string }>;
    } | null;
    tasks: Array<{
        id: number;
        titulo: string;
        descripcion: string | null;
        status: 'pending' | 'completed';
        assigned_at: string;
        completed_at: string | null;
    }>;
    recent_conversations: Array<{
        id: number;
        contact: string;
        status: string;
        preview: string | null;
        last_message_at: string | null;
    }>;
};

type AdminDashboard = {
    role: 'administrador';
    summary: {
        agents: number;
        total_chats: number;
        active_chats: number;
        unassigned_chats: number;
        pending_tasks: number;
        completed_tasks: number;
    };
    agent_activity: Array<{
        id: number;
        name: string;
        email: string;
        chats_count: number;
        active_chats_count: number;
        responses_count: number;
        pending_tasks_count: number;
        completed_tasks_count: number;
        last_response_at: string | null;
    }>;
    recent_task_assignments: Array<{
        task_id: number;
        titulo: string;
        user: { id: number; name: string; email: string };
        status: 'pending' | 'completed';
        assigned_at: string;
        completed_at: string | null;
    }>;
};

type DashboardData = SupportDashboard | AdminDashboard;

const WEEKDAYS: Record<number, string> = {
    1: 'Lunes',
    2: 'Martes',
    3: 'Miércoles',
    4: 'Jueves',
    5: 'Viernes',
    6: 'Sábado',
    7: 'Domingo',
};

const formatDateTime = (value: string | null) =>
    value
        ? new Date(value).toLocaleString('es-BO', {
              dateStyle: 'short',
              timeStyle: 'short',
          })
        : 'Sin actividad';

export default function DashboardPage() {
    const { user } = useAuth();
    const [dashboard, setDashboard] = useState<DashboardData | null>(null);
    const [loading, setLoading] = useState(true);
    const [completingId, setCompletingId] = useState<number | null>(null);

    const load = useCallback(() => {
        setLoading(true);
        api
            .get('/dashboard')
            .then((response) => setDashboard(response.data.data))
            .catch(() => toast.error('No se pudo cargar el dashboard.'))
            .finally(() => setLoading(false));
    }, []);

    useEffect(() => load(), [load]);

    const completeTask = async (taskId: number) => {
        setCompletingId(taskId);
        try {
            await api.patch(`/tareas/${taskId}/completar`);
            toast.success('Tarea marcada como realizada.');
            load();
        } catch {
            toast.error('No se pudo actualizar la tarea.');
        } finally {
            setCompletingId(null);
        }
    };

    if (loading) {
        return (
            <div className="h-full bg-white rounded-2xl flex items-center justify-center">
                <p className="text-sm text-muted-foreground">Cargando dashboard…</p>
            </div>
        );
    }

    if (!dashboard) {
        return (
            <div className="h-full bg-white rounded-2xl flex flex-col items-center justify-center gap-3 text-center p-6">
                <p className="text-sm text-muted-foreground">No fue posible cargar el dashboard.</p>
                <Button variant="outline" onClick={load}>
                    <RefreshCw size={15} /> Reintentar
                </Button>
            </div>
        );
    }

    const fullName = [user?.name, user?.last_name].filter(Boolean).join(' ');

    return (
        <div className="h-full overflow-y-auto space-y-4 pb-2">
            <header className="rounded-2xl bg-[#004479] text-white px-5 md:px-7 py-5 md:py-6">
                <p className="text-xs uppercase tracking-[0.18em] text-white/60">
                    {dashboard.role === 'administrador' ? 'Panel administrativo' : 'Mi jornada'}
                </p>
                <h1 className="mt-1 text-2xl font-semibold">Hola, {fullName || 'usuario'}</h1>
                <p className="mt-1 text-sm text-white/70">
                    {dashboard.role === 'administrador'
                        ? 'Resumen general del equipo, las conversaciones y las tareas.'
                        : 'Tu horario, tus tareas y tus conversaciones en un solo lugar.'}
                </p>
            </header>

            {dashboard.role === 'administrador' ? (
                <AdminView data={dashboard} />
            ) : (
                <SupportView data={dashboard} completingId={completingId} onComplete={completeTask} />
            )}
        </div>
    );
}

function SupportView({
    data,
    completingId,
    onComplete,
}: {
    data: SupportDashboard;
    completingId: number | null;
    onComplete: (taskId: number) => void;
}) {
    return (
        <>
            <div className="grid grid-cols-2 lg:grid-cols-4 gap-3">
                <SummaryCard label="Chats asignados" value={data.summary.assigned_chats} icon={MessageCircle} />
                <SummaryCard label="Chats activos" value={data.summary.active_chats} icon={CircleDot} />
                <SummaryCard label="Tareas pendientes" value={data.summary.pending_tasks} icon={Clock3} />
                <SummaryCard label="Tareas realizadas" value={data.summary.completed_tasks} icon={CheckCircle2} />
            </div>

            <div className="grid gap-4 lg:grid-cols-3">
                <DashboardSection title="Mi horario" icon={CalendarDays}>
                    {data.schedule?.shifts.length ? (
                        <div className="space-y-2">
                            {data.schedule.shifts.map((shift, index) => (
                                <div
                                    key={`${shift.weekday}-${shift.start_time}-${index}`}
                                    className="flex items-center justify-between rounded-xl bg-[#f4f6f9] px-3 py-2.5 text-sm"
                                >
                                    <span className="font-medium text-[#004479]">{WEEKDAYS[shift.weekday]}</span>
                                    <span className="text-muted-foreground">
                                        {shift.start_time} – {shift.end_time}
                                    </span>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <EmptyState text="No tienes un horario asignado." />
                    )}
                </DashboardSection>

                <DashboardSection title="Mis tareas recientes" icon={CheckCircle2} action={{ label: 'Ver todas', to: '/tareas' }}>
                    {data.tasks.length ? (
                        <div className="space-y-2">
                            {data.tasks.map((task) => (
                                <article key={task.id} className="rounded-xl border border-black/10 p-3">
                                    <div className="flex items-start justify-between gap-2">
                                        <h3 className="text-sm font-medium text-[#004479]">{task.titulo}</h3>
                                        <TaskStatus status={task.status} />
                                    </div>
                                    {task.descripcion && (
                                        <p className="mt-1.5 text-xs text-muted-foreground line-clamp-2">{task.descripcion}</p>
                                    )}
                                    {task.status === 'pending' && (
                                        <Button
                                            size="sm"
                                            onClick={() => onComplete(task.id)}
                                            disabled={completingId === task.id}
                                            className="mt-3 w-full bg-[#004479] hover:bg-[#00305a]"
                                        >
                                            {completingId === task.id ? 'Actualizando…' : 'Marcar realizada'}
                                        </Button>
                                    )}
                                </article>
                            ))}
                        </div>
                    ) : (
                        <EmptyState text="No tienes tareas asignadas." />
                    )}
                </DashboardSection>

                <DashboardSection title="Conversaciones recientes" icon={MessageCircle} action={{ label: 'Ir a chats', to: '/chats' }}>
                    {data.recent_conversations.length ? (
                        <div className="space-y-2">
                            {data.recent_conversations.map((conversation) => (
                                <Link
                                    key={conversation.id}
                                    to="/chats"
                                    className="block rounded-xl border border-black/10 p-3 hover:bg-black/[0.025] transition-colors"
                                >
                                    <div className="flex items-start justify-between gap-2">
                                        <span className="text-sm font-medium text-[#004479] truncate">{conversation.contact}</span>
                                        <Badge variant="outline" className="text-[10px]">
                                            {conversation.status === 'closed' ? 'Cerrado' : 'Activo'}
                                        </Badge>
                                    </div>
                                    <p className="mt-1 truncate text-xs text-muted-foreground">
                                        {conversation.preview || 'Sin mensajes todavía'}
                                    </p>
                                    <p className="mt-1 text-[10px] text-muted-foreground">
                                        {formatDateTime(conversation.last_message_at)}
                                    </p>
                                </Link>
                            ))}
                        </div>
                    ) : (
                        <EmptyState text="No tienes conversaciones asignadas." />
                    )}
                </DashboardSection>
            </div>
        </>
    );
}

function AdminView({ data }: { data: AdminDashboard }) {
    return (
        <>
            <div className="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-3">
                <SummaryCard label="Agentes" value={data.summary.agents} icon={UsersRound} />
                <SummaryCard label="Chats totales" value={data.summary.total_chats} icon={MessageCircle} />
                <SummaryCard label="Chats activos" value={data.summary.active_chats} icon={CircleDot} />
                <SummaryCard label="Sin asignar" value={data.summary.unassigned_chats} icon={UserRoundCheck} />
                <SummaryCard label="Tareas pendientes" value={data.summary.pending_tasks} icon={Clock3} />
                <SummaryCard label="Tareas realizadas" value={data.summary.completed_tasks} icon={CheckCircle2} />
            </div>

            <div className="grid gap-4 xl:grid-cols-[minmax(0,1.5fr)_minmax(320px,0.7fr)]">
                <DashboardSection title="Actividad del equipo" icon={Headphones}>
                    {data.agent_activity.length ? (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Agente</TableHead>
                                    <TableHead className="text-center">Chats</TableHead>
                                    <TableHead className="text-center">Respuestas</TableHead>
                                    <TableHead className="text-center">Tareas</TableHead>
                                    <TableHead>Última respuesta</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {data.agent_activity.map((agent) => (
                                    <TableRow key={agent.id}>
                                        <TableCell>
                                            <span className="font-medium text-[#004479]">{agent.name}</span>
                                            <span className="block text-xs text-muted-foreground">{agent.email}</span>
                                        </TableCell>
                                        <TableCell className="text-center">
                                            <span className="font-medium">{agent.active_chats_count}</span>
                                            <span className="text-xs text-muted-foreground">/{agent.chats_count}</span>
                                        </TableCell>
                                        <TableCell className="text-center">{agent.responses_count}</TableCell>
                                        <TableCell className="text-center">
                                            <span className="text-emerald-700">{agent.completed_tasks_count}</span>
                                            <span className="text-muted-foreground"> / </span>
                                            <span className="text-amber-700">{agent.pending_tasks_count}</span>
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {formatDateTime(agent.last_response_at)}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    ) : (
                        <EmptyState text="No hay agentes de soporte registrados." />
                    )}
                </DashboardSection>

                <DashboardSection title="Asignaciones recientes" icon={CheckCircle2} action={{ label: 'Ver tareas', to: '/tareas' }}>
                    {data.recent_task_assignments.length ? (
                        <div className="space-y-2">
                            {data.recent_task_assignments.map((assignment) => (
                                <article
                                    key={`${assignment.task_id}-${assignment.user.id}`}
                                    className="rounded-xl border border-black/10 p-3"
                                >
                                    <div className="flex items-start justify-between gap-2">
                                        <h3 className="text-sm font-medium text-[#004479]">{assignment.titulo}</h3>
                                        <TaskStatus status={assignment.status} />
                                    </div>
                                    <p className="mt-1 text-xs text-muted-foreground">{assignment.user.name}</p>
                                    <p className="mt-1 text-[10px] text-muted-foreground">
                                        Asignada {formatDateTime(assignment.assigned_at)}
                                    </p>
                                </article>
                            ))}
                        </div>
                    ) : (
                        <EmptyState text="Todavía no hay tareas asignadas." />
                    )}
                </DashboardSection>
            </div>

            <div className="rounded-2xl bg-white px-5 py-4 text-xs text-muted-foreground flex flex-wrap gap-x-5 gap-y-1">
                <span className="flex items-center gap-1.5">
                    <MessageSquareReply size={14} className="text-[#004479]" /> Respuestas enviadas por cada agente
                </span>
                <span>Chats: activos / asignados</span>
                <span>Tareas: realizadas / pendientes</span>
            </div>
        </>
    );
}

function SummaryCard({ label, value, icon: Icon }: { label: string; value: number; icon: LucideIcon }) {
    return (
        <div className="rounded-2xl bg-white p-4 flex items-center gap-3 min-w-0">
            <div className="size-10 rounded-xl bg-[#004479]/8 text-[#004479] flex items-center justify-center shrink-0">
                <Icon size={19} />
            </div>
            <div className="min-w-0">
                <strong className="block text-xl leading-none text-[#004479]">{value}</strong>
                <span className="mt-1 block text-xs text-muted-foreground truncate">{label}</span>
            </div>
        </div>
    );
}

function DashboardSection({
    title,
    icon: Icon,
    action,
    children,
}: {
    title: string;
    icon: LucideIcon;
    action?: { label: string; to: string };
    children: ReactNode;
}) {
    return (
        <section className="rounded-2xl bg-white overflow-hidden min-w-0">
            <header className="min-h-14 border-b border-black/5 px-4 md:px-5 py-3 flex items-center gap-2">
                <Icon size={17} className="text-[#004479]" />
                <h2 className="font-medium text-[#004479]">{title}</h2>
                {action && (
                    <Link to={action.to} className="ml-auto text-xs text-[#004479] hover:underline">
                        {action.label}
                    </Link>
                )}
            </header>
            <div className="p-3 md:p-4 max-h-[32rem] overflow-auto">{children}</div>
        </section>
    );
}

function TaskStatus({ status }: { status: 'pending' | 'completed' }) {
    return status === 'completed' ? (
        <Badge className="border-transparent bg-emerald-100 text-emerald-800">Realizada</Badge>
    ) : (
        <Badge variant="secondary" className="bg-amber-100 text-amber-800">
            Pendiente
        </Badge>
    );
}

function EmptyState({ text }: { text: string }) {
    return <p className="py-10 text-center text-sm text-muted-foreground">{text}</p>;
}
