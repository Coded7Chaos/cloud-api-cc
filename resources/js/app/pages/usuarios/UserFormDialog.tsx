import { useEffect, useState, type FormEvent } from 'react';
import axios from 'axios';
import { toast } from 'sonner';
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
import { PasswordInput } from '../../components/ui/password-input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '../../components/ui/select';

export type PanelUser = {
    id: number;
    name: string;
    last_name: string;
    email: string;
    email_verified_at: string | null;
    created_at: string;
    role_id: number | null;
    role?: { id: number; name: string } | null;
};

type Props = {
    open: boolean;
    user: PanelUser | null; // null = crear, objeto = editar
    onOpenChange: (open: boolean) => void;
    onSaved: () => void;
};

type FieldErrors = Record<string, string[]>;

const empty = { name: '', last_name: '', email: '', password: '', password_confirmation: '', role_id: '' };

export function UserFormDialog({ open, user, onOpenChange, onSaved }: Props) {
    const isEdit = !!user;
    const [form, setForm] = useState(empty);
    const [errors, setErrors] = useState<FieldErrors>({});
    const [saving, setSaving] = useState(false);
    const [roles, setRoles] = useState<{ id: number; name: string }[]>([]);

    useEffect(() => {
        api.get('/roles').then((res) => setRoles(res.data.data)).catch(() => {});
    }, []);

    useEffect(() => {
        if (open) {
            setForm(
                user
                    ? {
                          name: user.name,
                          last_name: user.last_name,
                          email: user.email,
                          password: '',
                          password_confirmation: '',
                          role_id: user.role_id ? String(user.role_id) : '',
                      }
                    : empty,
            );
            setErrors({});
        }
    }, [open, user]);

    const set = (key: keyof typeof empty, value: string) => setForm((f) => ({ ...f, [key]: value }));

    const submit = async (e: FormEvent) => {
        e.preventDefault();
        setSaving(true);
        setErrors({});
        try {
            if (isEdit) {
                await api.put(`/users/${user!.id}`, form);
                toast.success('Usuario actualizado.');
            } else {
                await api.post('/users', form);
                toast.success('Usuario creado.');
            }
            onOpenChange(false);
            onSaved();
        } catch (err) {
            if (axios.isAxiosError(err) && err.response?.status === 422) {
                setErrors(err.response.data?.errors ?? {});
            } else {
                toast.error('No se pudo guardar el usuario.');
            }
        } finally {
            setSaving(false);
        }
    };

    const fieldError = (key: string) => errors[key]?.[0];

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{isEdit ? 'Editar usuario' : 'Nuevo usuario'}</DialogTitle>
                    <DialogDescription>
                        {isEdit
                            ? 'Modifica los datos del agente. Deja la contraseña vacía para no cambiarla.'
                            : 'Completa los datos del agente. Le enviaremos una invitación por correo para que cree su contraseña.'}
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={submit} className="space-y-4">
                    <div className="grid grid-cols-2 gap-3">
                        <div className="space-y-1.5">
                            <Label htmlFor="name">Nombre</Label>
                            <Input id="name" value={form.name} onChange={(e) => set('name', e.target.value)} />
                            {fieldError('name') && <p className="text-xs text-destructive">{fieldError('name')}</p>}
                        </div>
                        <div className="space-y-1.5">
                            <Label htmlFor="last_name">Apellido</Label>
                            <Input id="last_name" value={form.last_name} onChange={(e) => set('last_name', e.target.value)} />
                            {fieldError('last_name') && <p className="text-xs text-destructive">{fieldError('last_name')}</p>}
                        </div>
                    </div>

                    <div className="space-y-1.5">
                        <Label htmlFor="email">Correo electrónico</Label>
                        <Input id="email" type="email" value={form.email} onChange={(e) => set('email', e.target.value)} />
                        {fieldError('email') && <p className="text-xs text-destructive">{fieldError('email')}</p>}
                    </div>

                    <div className="space-y-1.5">
                        <Label htmlFor="role_id">Rol</Label>
                        <Select value={form.role_id} onValueChange={(value) => set('role_id', value)}>
                            <SelectTrigger id="role_id">
                                <SelectValue placeholder="Selecciona un rol" />
                            </SelectTrigger>
                            <SelectContent>
                                {roles.map((r) => (
                                    <SelectItem key={r.id} value={String(r.id)}>
                                        {r.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {fieldError('role_id') && <p className="text-xs text-destructive">{fieldError('role_id')}</p>}
                    </div>

                    {isEdit && (
                        <>
                            <div className="grid grid-cols-2 gap-3">
                                <div className="space-y-1.5">
                                    <Label htmlFor="password">Contraseña</Label>
                                    <PasswordInput
                                        id="password"
                                        value={form.password}
                                        onChange={(e) => set('password', e.target.value)}
                                        autoComplete="new-password"
                                        placeholder="Sin cambios"
                                    />
                                    {fieldError('password') && <p className="text-xs text-destructive">{fieldError('password')}</p>}
                                </div>
                                <div className="space-y-1.5">
                                    <Label htmlFor="password_confirmation">Confirmar</Label>
                                    <PasswordInput
                                        id="password_confirmation"
                                        value={form.password_confirmation}
                                        onChange={(e) => set('password_confirmation', e.target.value)}
                                        autoComplete="new-password"
                                    />
                                </div>
                            </div>
                            <div className="text-xs text-muted-foreground bg-muted/60 rounded-md px-3 py-2">
                                La contraseña debe tener mínimo 8 caracteres, al menos una letra, un número y un símbolo.
                            </div>
                        </>
                    )}

                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                            Cancelar
                        </Button>
                        <Button type="submit" disabled={saving} className="bg-[#004479] hover:bg-[#00305a]">
                            {saving ? 'Guardando…' : isEdit ? 'Guardar cambios' : 'Enviar invitación'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
