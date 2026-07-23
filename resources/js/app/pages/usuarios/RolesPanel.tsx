import { useCallback, useEffect, useState } from 'react';
import { Plus, Pencil, Trash2, ShieldCheck, ShieldAlert } from 'lucide-react';
import { toast } from 'sonner';
import axios from 'axios';
import { api } from '../../../lib/api';
import { useAuth } from '../../../lib/auth';
import { Button } from '../../components/ui/button';
import { Badge } from '../../components/ui/badge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '../../components/ui/table';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '../../components/ui/dialog';
import { RoleFormDialog, type Permission, type RoleRow } from './RoleFormDialog';

/** Pestaña "Roles": gestión de roles y de los permisos que otorga cada uno. */
export default function RolesPanel() {
    const { user } = useAuth();
    const [roles, setRoles] = useState<RoleRow[]>([]);
    const [permissions, setPermissions] = useState<Permission[]>([]);
    const [loading, setLoading] = useState(true);
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editing, setEditing] = useState<RoleRow | null>(null);
    const [deleting, setDeleting] = useState<RoleRow | null>(null);
    const [deleteLoading, setDeleteLoading] = useState(false);

    const load = useCallback(() => {
        setLoading(true);
        Promise.all([api.get('/roles'), api.get('/permissions')])
            .then(([rolesRes, permsRes]) => {
                setRoles(rolesRes.data.data);
                setPermissions(permsRes.data.data);
            })
            .catch(() => toast.error('No se pudieron cargar los roles.'))
            .finally(() => setLoading(false));
    }, []);

    useEffect(() => load(), [load]);

    const openCreate = () => {
        setEditing(null);
        setDialogOpen(true);
    };

    const openEdit = (role: RoleRow) => {
        setEditing(role);
        setDialogOpen(true);
    };

    // El servidor es la autoridad, pero deshabilitar acá evita el intento y el
    // toast de error: ni roles del sistema ni el rol propio se pueden borrar.
    const canDelete = (role: RoleRow) => !role.is_protected && role.id !== user?.role?.id;

    const confirmDelete = async () => {
        if (!deleting) return;
        setDeleteLoading(true);
        try {
            const res = await api.delete(`/roles/${deleting.id}`);
            const left = res.data?.data?.users_left_without_role ?? 0;
            toast.success(
                left > 0
                    ? `Rol eliminado. ${left} usuario${left === 1 ? '' : 's'} quedó sin rol.`
                    : 'Rol eliminado.',
            );
            setDeleting(null);
            load();
        } catch (err) {
            const message =
                axios.isAxiosError(err) && err.response?.data?.message
                    ? err.response.data.message
                    : 'No se pudo eliminar el rol.';
            toast.error(message);
        } finally {
            setDeleteLoading(false);
        }
    };

    return (
        <div className="h-full bg-white rounded-2xl overflow-hidden flex flex-col">
            <div className="flex items-center justify-between gap-3 px-5 md:px-6 py-4 border-b border-black/5">
                <div>
                    <h2 className="text-lg font-medium text-[#004479]">Roles</h2>
                    <p className="text-xs text-muted-foreground">Define qué puede hacer cada tipo de agente</p>
                </div>
                <Button onClick={openCreate} className="bg-[#004479] hover:bg-[#00305a]">
                    <Plus size={16} /> Nuevo rol
                </Button>
            </div>

            <div className="flex-1 overflow-auto">
                {loading ? (
                    <p className="px-6 py-6 text-sm text-muted-foreground">Cargando…</p>
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Rol</TableHead>
                                <TableHead>Permisos</TableHead>
                                <TableHead>Usuarios</TableHead>
                                <TableHead className="text-right">Acciones</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {roles.map((role) => (
                                <TableRow key={role.id}>
                                    <TableCell>
                                        <div className="flex items-center gap-2">
                                            <span className="font-medium text-[#004479] capitalize">{role.name}</span>
                                            {role.is_protected && (
                                                <Badge variant="secondary" className="gap-1 text-[10px]">
                                                    <ShieldCheck size={11} /> Sistema
                                                </Badge>
                                            )}
                                        </div>
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {role.permissions.length} de {permissions.length}
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">{role.users_count}</TableCell>
                                    <TableCell className="text-right">
                                        <div className="flex items-center justify-end gap-1">
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                onClick={() => openEdit(role)}
                                                title="Editar"
                                            >
                                                <Pencil size={15} />
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                onClick={() => setDeleting(role)}
                                                disabled={!canDelete(role)}
                                                title={
                                                    canDelete(role)
                                                        ? 'Eliminar'
                                                        : role.is_protected
                                                          ? 'Rol del sistema: no se puede eliminar'
                                                          : 'Es tu rol actual: no puedes eliminarlo'
                                                }
                                                className="text-destructive hover:text-destructive disabled:text-muted-foreground/40"
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

            <RoleFormDialog
                open={dialogOpen}
                role={editing}
                permissions={permissions}
                onOpenChange={setDialogOpen}
                onSaved={load}
            />

            <Dialog open={deleting !== null} onOpenChange={(open) => !open && setDeleting(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Eliminar rol</DialogTitle>
                        <DialogDescription asChild>
                            <div className="space-y-3">
                                <p>
                                    Vas a eliminar el rol{' '}
                                    <span className="font-medium text-foreground capitalize">{deleting?.name}</span>.
                                    Esta acción no se puede deshacer.
                                </p>
                                {(deleting?.users_count ?? 0) > 0 && (
                                    <div className="flex gap-2 rounded-lg bg-destructive/5 border border-destructive/20 px-3 py-2.5 text-sm text-foreground">
                                        <ShieldAlert size={18} className="text-destructive shrink-0 mt-0.5" />
                                        <span>
                                            Hay{' '}
                                            <span className="font-semibold">
                                                {deleting?.users_count} usuario
                                                {deleting?.users_count === 1 ? '' : 's'}
                                            </span>{' '}
                                            con este rol. Al eliminarlo quedará
                                            {deleting?.users_count === 1 ? '' : 'n'} sin rol y perderá
                                            {deleting?.users_count === 1 ? '' : 'n'} el acceso hasta que le
                                            {deleting?.users_count === 1 ? '' : 's'} asignes uno nuevo.
                                        </span>
                                    </div>
                                )}
                            </div>
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setDeleting(null)} disabled={deleteLoading}>
                            Cancelar
                        </Button>
                        <Button variant="destructive" onClick={confirmDelete} disabled={deleteLoading}>
                            {deleteLoading ? 'Eliminando…' : 'Eliminar rol'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}
