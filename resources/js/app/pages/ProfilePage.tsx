import { useEffect, useRef, useState, type FormEvent } from 'react';
import axios from 'axios';
import { toast } from 'sonner';
import { Camera, Trash2, ShieldCheck, CalendarDays } from 'lucide-react';
import { api } from '../../lib/api';
import { useAuth, type AuthUser } from '../../lib/auth';
import { Button } from '../components/ui/button';
import { Input } from '../components/ui/input';
import { Label } from '../components/ui/label';
import { PasswordInput } from '../components/ui/password-input';
import { Badge } from '../components/ui/badge';
import { UserAvatar } from '../components/UserAvatar';

type FieldErrors = Record<string, string[]>;

export default function ProfilePage() {
    const { user, setUser } = useAuth();

    // ── Datos personales ────────────────────────────────────────────────────
    const [form, setForm] = useState({ name: '', last_name: '', email: '' });
    const [errors, setErrors] = useState<FieldErrors>({});
    const [saving, setSaving] = useState(false);

    // ── Contraseña ──────────────────────────────────────────────────────────
    const emptyPassword = { current_password: '', password: '', password_confirmation: '' };
    const [passwordForm, setPasswordForm] = useState(emptyPassword);
    const [passwordErrors, setPasswordErrors] = useState<FieldErrors>({});
    const [changingPassword, setChangingPassword] = useState(false);

    // ── Foto ──────────────────────────────────────────────────────────────────
    const fileRef = useRef<HTMLInputElement>(null);
    const [avatarBusy, setAvatarBusy] = useState(false);

    // Rellena el formulario cuando el usuario está disponible o cambia.
    useEffect(() => {
        if (user) setForm({ name: user.name, last_name: user.last_name, email: user.email });
    }, [user]);

    if (!user) return null;

    const setField = (key: keyof typeof form, value: string) => setForm((f) => ({ ...f, [key]: value }));
    const setPasswordField = (key: keyof typeof emptyPassword, value: string) =>
        setPasswordForm((f) => ({ ...f, [key]: value }));

    const saveProfile = async (e: FormEvent) => {
        e.preventDefault();
        setSaving(true);
        setErrors({});
        try {
            const res = await api.put('/profile', form);
            setUser(res.data.user as AuthUser);
            toast.success('Perfil actualizado.');
        } catch (err) {
            if (axios.isAxiosError(err) && err.response?.status === 422) {
                setErrors(err.response.data?.errors ?? {});
            } else {
                toast.error('No se pudo guardar el perfil.');
            }
        } finally {
            setSaving(false);
        }
    };

    const changePassword = async (e: FormEvent) => {
        e.preventDefault();
        setChangingPassword(true);
        setPasswordErrors({});
        try {
            await api.put('/profile/password', passwordForm);
            setPasswordForm(emptyPassword);
            toast.success('Contraseña actualizada.');
        } catch (err) {
            if (axios.isAxiosError(err) && err.response?.status === 422) {
                setPasswordErrors(err.response.data?.errors ?? {});
            } else {
                toast.error('No se pudo cambiar la contraseña.');
            }
        } finally {
            setChangingPassword(false);
        }
    };

    const onPickFile = () => fileRef.current?.click();

    const onFileChange = async (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (fileRef.current) fileRef.current.value = ''; // permite volver a elegir el mismo archivo
        if (!file) return;

        const payload = new FormData();
        payload.append('avatar', file);
        setAvatarBusy(true);
        try {
            const res = await api.post('/profile/avatar', payload);
            setUser(res.data.user as AuthUser);
            toast.success('Foto de perfil actualizada.');
        } catch (err) {
            const message =
                axios.isAxiosError(err) && err.response?.data?.errors?.avatar?.[0]
                    ? err.response.data.errors.avatar[0]
                    : 'No se pudo subir la foto.';
            toast.error(message);
        } finally {
            setAvatarBusy(false);
        }
    };

    const removeAvatar = async () => {
        setAvatarBusy(true);
        try {
            const res = await api.delete('/profile/avatar');
            setUser(res.data.user as AuthUser);
            toast.success('Foto de perfil eliminada.');
        } catch {
            toast.error('No se pudo eliminar la foto.');
        } finally {
            setAvatarBusy(false);
        }
    };

    const fieldError = (key: string) => errors[key]?.[0];
    const passwordError = (key: string) => passwordErrors[key]?.[0];
    const createdAt = new Date(user.created_at).toLocaleDateString('es', {
        day: '2-digit',
        month: 'long',
        year: 'numeric',
    });

    return (
        <div className="h-full overflow-auto">
            <div className="max-w-3xl mx-auto space-y-4 pb-6">
                <div>
                    <h1 className="text-lg font-medium text-[#004479]">Mi perfil</h1>
                    <p className="text-xs text-muted-foreground">Administra tu cuenta y tus datos de acceso.</p>
                </div>

                {/* ── Foto + datos personales ── */}
                <form onSubmit={saveProfile} className="bg-white rounded-2xl p-5 md:p-6 space-y-5">
                    <div className="flex items-center gap-4">
                        <UserAvatar
                            name={user.name}
                            lastName={user.last_name}
                            avatarUrl={user.avatar_url}
                            className="w-20 h-20 ring-2 ring-[#004479]/10"
                            fallbackClassName="bg-[#004479]/10 text-[#004479] text-xl"
                        />
                        <div className="space-y-2">
                            <p className="text-sm font-medium text-[#004479]">Foto de perfil</p>
                            <div className="flex flex-wrap gap-2">
                                <input
                                    ref={fileRef}
                                    type="file"
                                    accept="image/jpeg,image/png,image/webp"
                                    className="hidden"
                                    onChange={onFileChange}
                                />
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={onPickFile}
                                    disabled={avatarBusy}
                                >
                                    <Camera size={14} /> {user.avatar_url ? 'Cambiar' : 'Subir foto'}
                                </Button>
                                {user.avatar_url && (
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        onClick={removeAvatar}
                                        disabled={avatarBusy}
                                        className="text-destructive hover:text-destructive"
                                    >
                                        <Trash2 size={14} /> Quitar
                                    </Button>
                                )}
                            </div>
                            <p className="text-[11px] text-muted-foreground">JPG, PNG o WEBP. Máximo 4 MB.</p>
                        </div>
                    </div>

                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div className="space-y-1.5">
                            <Label htmlFor="name">Nombre</Label>
                            <Input id="name" value={form.name} onChange={(e) => setField('name', e.target.value)} />
                            {fieldError('name') && <p className="text-xs text-destructive">{fieldError('name')}</p>}
                        </div>
                        <div className="space-y-1.5">
                            <Label htmlFor="last_name">Apellido</Label>
                            <Input
                                id="last_name"
                                value={form.last_name}
                                onChange={(e) => setField('last_name', e.target.value)}
                            />
                            {fieldError('last_name') && (
                                <p className="text-xs text-destructive">{fieldError('last_name')}</p>
                            )}
                        </div>
                    </div>

                    <div className="space-y-1.5">
                        <Label htmlFor="email">Correo electrónico</Label>
                        <Input
                            id="email"
                            type="email"
                            value={form.email}
                            onChange={(e) => setField('email', e.target.value)}
                        />
                        {fieldError('email') && <p className="text-xs text-destructive">{fieldError('email')}</p>}
                    </div>

                    {/* Rol y fecha de alta: solo lectura. */}
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div className="space-y-1.5">
                            <Label>Rol</Label>
                            <div className="flex items-center h-9">
                                <Badge variant="secondary" className="gap-1 capitalize">
                                    <ShieldCheck size={12} /> {user.role?.name ?? 'Sin rol'}
                                </Badge>
                            </div>
                        </div>
                        <div className="space-y-1.5">
                            <Label>Miembro desde</Label>
                            <div className="flex items-center h-9 gap-1.5 text-sm text-muted-foreground">
                                <CalendarDays size={14} /> {createdAt}
                            </div>
                        </div>
                    </div>

                    <div className="flex justify-end">
                        <Button type="submit" disabled={saving} className="bg-[#004479] hover:bg-[#00305a]">
                            {saving ? 'Guardando…' : 'Guardar cambios'}
                        </Button>
                    </div>
                </form>

                {/* ── Cambiar contraseña ── */}
                <form onSubmit={changePassword} className="bg-white rounded-2xl p-5 md:p-6 space-y-4">
                    <div>
                        <h2 className="text-sm font-medium text-[#004479]">Cambiar contraseña</h2>
                        <p className="text-xs text-muted-foreground">
                            Para cambiarla, confirma primero tu contraseña actual.
                        </p>
                    </div>

                    <div className="space-y-1.5">
                        <Label htmlFor="current_password">Contraseña actual</Label>
                        <PasswordInput
                            id="current_password"
                            autoComplete="current-password"
                            value={passwordForm.current_password}
                            onChange={(e) => setPasswordField('current_password', e.target.value)}
                        />
                        {passwordError('current_password') && (
                            <p className="text-xs text-destructive">{passwordError('current_password')}</p>
                        )}
                    </div>

                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div className="space-y-1.5">
                            <Label htmlFor="password">Nueva contraseña</Label>
                            <PasswordInput
                                id="password"
                                autoComplete="new-password"
                                value={passwordForm.password}
                                onChange={(e) => setPasswordField('password', e.target.value)}
                            />
                            {passwordError('password') && (
                                <p className="text-xs text-destructive">{passwordError('password')}</p>
                            )}
                        </div>
                        <div className="space-y-1.5">
                            <Label htmlFor="password_confirmation">Repetir nueva contraseña</Label>
                            <PasswordInput
                                id="password_confirmation"
                                autoComplete="new-password"
                                value={passwordForm.password_confirmation}
                                onChange={(e) => setPasswordField('password_confirmation', e.target.value)}
                            />
                        </div>
                    </div>

                    <div className="text-xs text-muted-foreground bg-muted/60 rounded-md px-3 py-2">
                        La contraseña debe tener mínimo 8 caracteres, al menos una letra, un número y un símbolo.
                    </div>

                    <div className="flex justify-end">
                        <Button
                            type="submit"
                            disabled={changingPassword}
                            className="bg-[#004479] hover:bg-[#00305a]"
                        >
                            {changingPassword ? 'Actualizando…' : 'Cambiar contraseña'}
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    );
}
