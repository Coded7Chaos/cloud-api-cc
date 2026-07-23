import { useEffect, useMemo, useState, type FormEvent } from 'react';
import axios from 'axios';
import { toast } from 'sonner';
import { ShieldCheck } from 'lucide-react';
import { api } from '../../../lib/api';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '../../components/ui/dialog';
import { Button } from '../../components/ui/button';
import { Input } from '../../components/ui/input';
import { Label } from '../../components/ui/label';

export type Permission = { id: number; name: string; description: string };

export type RoleRow = {
    id: number;
    name: string;
    is_protected: boolean;
    users_count: number;
    permissions: number[];
};

type Props = {
    open: boolean;
    role: RoleRow | null; // null = crear, objeto = editar
    permissions: Permission[];
    onOpenChange: (open: boolean) => void;
    onSaved: () => void;
};

// Etiqueta legible para cada categoría (el prefijo antes del punto del permiso).
const categoryLabels: Record<string, string> = {
    usuarios: 'Usuarios',
    roles: 'Roles',
    conversaciones: 'Conversaciones',
    horarios: 'Horarios',
    auditoria: 'Auditoría',
    tareas: 'Tareas',
};

function categoryOf(permissionName: string): string {
    return permissionName.split('.')[0] ?? 'otros';
}

export function RoleFormDialog({ open, role, permissions, onOpenChange, onSaved }: Props) {
    const isEdit = !!role;
    const [name, setName] = useState('');
    const [selected, setSelected] = useState<Set<number>>(new Set());
    const [errors, setErrors] = useState<Record<string, string[]>>({});
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        if (open) {
            setName(role?.name ?? '');
            setSelected(new Set(role?.permissions ?? []));
            setErrors({});
        }
    }, [open, role]);

    // Permisos agrupados por categoría, en el orden en que llega el catálogo.
    const groups = useMemo(() => {
        const map = new Map<string, Permission[]>();
        for (const permission of permissions) {
            const key = categoryOf(permission.name);
            (map.get(key) ?? map.set(key, []).get(key)!).push(permission);
        }
        return [...map.entries()];
    }, [permissions]);

    const toggle = (id: number) =>
        setSelected((prev) => {
            const next = new Set(prev);
            next.has(id) ? next.delete(id) : next.add(id);
            return next;
        });

    const submit = async (e: FormEvent) => {
        e.preventDefault();
        setSaving(true);
        setErrors({});
        const payload = { name, permissions: [...selected] };
        try {
            if (isEdit) {
                await api.put(`/roles/${role!.id}`, payload);
                toast.success('Rol actualizado.');
            } else {
                await api.post('/roles', payload);
                toast.success('Rol creado.');
            }
            onOpenChange(false);
            onSaved();
        } catch (err) {
            if (axios.isAxiosError(err) && err.response?.status === 422) {
                // Errores de validación por campo, o un mensaje suelto (rol del
                // sistema que no se puede renombrar).
                setErrors(err.response.data?.errors ?? {});
                if (!err.response.data?.errors && err.response.data?.message) {
                    toast.error(err.response.data.message);
                }
            } else {
                toast.error('No se pudo guardar el rol.');
            }
        } finally {
            setSaving(false);
        }
    };

    const nameError = errors.name?.[0];

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[85vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>{isEdit ? 'Editar rol' : 'Nuevo rol'}</DialogTitle>
                    <DialogDescription>
                        {isEdit
                            ? 'Cambia el nombre y marca los permisos que tendrá este rol.'
                            : 'Ponle un nombre y elige qué podrá hacer quien tenga este rol.'}
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={submit} className="space-y-4">
                    <div className="space-y-1.5">
                        <Label htmlFor="role-name">Nombre del rol</Label>
                        <Input
                            id="role-name"
                            value={name}
                            onChange={(e) => setName(e.target.value)}
                            disabled={role?.is_protected}
                            placeholder="Ej. Supervisor"
                        />
                        {role?.is_protected && (
                            <p className="flex items-center gap-1.5 text-xs text-muted-foreground">
                                <ShieldCheck size={13} /> Rol del sistema: puedes ajustar sus permisos, pero no
                                renombrarlo.
                            </p>
                        )}
                        {nameError && <p className="text-xs text-destructive">{nameError}</p>}
                    </div>

                    <div className="space-y-3">
                        <Label>Permisos</Label>
                        {groups.map(([category, perms]) => (
                            <div key={category} className="rounded-lg border border-black/5 overflow-hidden">
                                <div className="bg-muted/60 px-3 py-1.5 text-xs font-medium text-[#004479]">
                                    {categoryLabels[category] ?? category}
                                </div>
                                <div className="divide-y divide-black/5">
                                    {perms.map((permission) => (
                                        <label
                                            key={permission.id}
                                            className="flex items-start gap-2.5 px-3 py-2 cursor-pointer hover:bg-muted/40"
                                        >
                                            <input
                                                type="checkbox"
                                                checked={selected.has(permission.id)}
                                                onChange={() => toggle(permission.id)}
                                                className="mt-0.5 h-4 w-4 accent-[#004479]"
                                            />
                                            <span className="text-sm text-foreground leading-tight">
                                                {permission.description}
                                                <span className="block text-[11px] text-muted-foreground font-mono">
                                                    {permission.name}
                                                </span>
                                            </span>
                                        </label>
                                    ))}
                                </div>
                            </div>
                        ))}
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                            Cancelar
                        </Button>
                        <Button type="submit" disabled={saving} className="bg-[#004479] hover:bg-[#00305a]">
                            {saving ? 'Guardando…' : isEdit ? 'Guardar cambios' : 'Crear rol'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
