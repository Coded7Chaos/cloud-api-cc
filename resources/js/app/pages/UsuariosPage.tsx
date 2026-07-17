import { useCallback, useEffect, useState } from 'react';
import { Plus, Pencil, Trash2, UserPlus } from 'lucide-react';
import { toast } from 'sonner';
import { api } from '../../lib/api';
import { Button } from '../components/ui/button';
import { Avatar, AvatarFallback } from '../components/ui/avatar';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '../components/ui/table';
import { UserFormDialog, type PanelUser } from './usuarios/UserFormDialog';
import { initials } from '../layout/nav-items';

export default function UsuariosPage() {
    const [users, setUsers] = useState<PanelUser[]>([]);
    const [loading, setLoading] = useState(true);
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editing, setEditing] = useState<PanelUser | null>(null);

    const load = useCallback(() => {
        setLoading(true);
        api
            .get('/users')
            .then((res) => setUsers(res.data.data))
            .catch(() => toast.error('No se pudieron cargar los usuarios.'))
            .finally(() => setLoading(false));
    }, []);

    useEffect(() => load(), [load]);

    const openCreate = () => {
        setEditing(null);
        setDialogOpen(true);
    };

    const openEdit = (user: PanelUser) => {
        setEditing(user);
        setDialogOpen(true);
    };

    const remove = async (user: PanelUser) => {
        if (!window.confirm(`¿Eliminar a ${user.name} ${user.last_name}?`)) return;
        try {
            await api.delete(`/users/${user.id}`);
            toast.success('Usuario eliminado.');
            setUsers((prev) => prev.filter((u) => u.id !== user.id));
        } catch {
            toast.error('No se pudo eliminar el usuario.');
        }
    };

    return (
        <div className="h-full bg-white rounded-2xl overflow-hidden flex flex-col">
            <div className="flex items-center justify-between gap-3 px-5 md:px-6 py-4 border-b border-black/5">
                <div>
                    <h2 className="text-lg font-medium text-[#004479]">Usuarios</h2>
                    <p className="text-xs text-muted-foreground">Agentes con acceso al panel</p>
                </div>
                <Button onClick={openCreate} className="bg-[#004479] hover:bg-[#00305a]">
                    <Plus size={16} /> Nuevo usuario
                </Button>
            </div>

            <div className="flex-1 overflow-auto">
                {loading ? (
                    <p className="px-6 py-6 text-sm text-muted-foreground">Cargando…</p>
                ) : users.length === 0 ? (
                    <div className="flex flex-col items-center justify-center py-16 text-center">
                        <UserPlus size={32} className="text-muted-foreground/40" />
                        <p className="text-sm text-muted-foreground mt-2">Aún no hay usuarios.</p>
                    </div>
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Usuario</TableHead>
                                <TableHead>Correo</TableHead>
                                <TableHead>Rol</TableHead>
                                <TableHead>Alta</TableHead>
                                <TableHead className="text-right">Acciones</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {users.map((u) => (
                                <TableRow key={u.id}>
                                    <TableCell>
                                        <div className="flex items-center gap-3">
                                            <Avatar className="w-9 h-9">
                                                <AvatarFallback className="bg-[#004479]/10 text-[#004479] text-xs">
                                                    {initials(u.name, u.last_name)}
                                                </AvatarFallback>
                                            </Avatar>
                                            <span className="font-medium text-[#004479]">
                                                {u.name} {u.last_name}
                                            </span>
                                        </div>
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">{u.email}</TableCell>
                                    <TableCell className="text-muted-foreground">{u.role?.name ?? '—'}</TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {new Date(u.created_at).toLocaleDateString('es')}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <div className="flex items-center justify-end gap-1">
                                            <Button variant="ghost" size="icon" onClick={() => openEdit(u)} title="Editar">
                                                <Pencil size={15} />
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                onClick={() => remove(u)}
                                                title="Eliminar"
                                                className="text-destructive hover:text-destructive"
                                            >
                                                <Trash2 size={15} />
                                            </Button>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}
            </div>

            <UserFormDialog open={dialogOpen} user={editing} onOpenChange={setDialogOpen} onSaved={load} />
        </div>
    );
}
