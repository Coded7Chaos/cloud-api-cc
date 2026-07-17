import { useEffect, useState, type FormEvent } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router';
import { Lock } from 'lucide-react';
import axios from 'axios';
import { Button } from '../components/ui/button';
import { PasswordInput } from '../components/ui/password-input';
import { api } from '../../lib/api';

type InviteStatus = 'loading' | 'pending' | 'already_set' | 'invalid';

export default function AcceptInvitationPage() {
    const [searchParams] = useSearchParams();
    const navigate = useNavigate();

    const token = searchParams.get('token') ?? '';
    const email = searchParams.get('email') ?? '';

    const [status, setStatus] = useState<InviteStatus>('loading');
    const [password, setPassword] = useState('');
    const [passwordConfirmation, setPasswordConfirmation] = useState('');
    const [error, setError] = useState<string | null>(null);
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        if (!token || !email) {
            setStatus('invalid');
            return;
        }

        api.get('/invitations/status', { params: { token, email } })
            .then((res) => setStatus(res.data.status === 'already_set' ? 'already_set' : 'pending'))
            .catch(() => setStatus('invalid'));
    }, [token, email]);

    const submit = async (e: FormEvent) => {
        e.preventDefault();
        setError(null);
        setLoading(true);
        try {
            const res = await api.post('/invitations/accept', {
                token,
                email,
                password,
                password_confirmation: passwordConfirmation,
            });

            if (res.data?.status === 'already_set') {
                setStatus('already_set');
                return;
            }

            navigate('/login', {
                replace: true,
                state: { message: res.data?.message ?? 'Tu contraseña fue creada. Ya puedes iniciar sesión.' },
            });
        } catch (err) {
            if (axios.isAxiosError(err) && err.response?.status === 422) {
                const errors = err.response.data?.errors;
                setError(errors?.password?.[0] ?? errors?.email?.[0] ?? 'No se pudo crear la contraseña.');
            } else {
                setError('No se pudo crear la contraseña. Intenta de nuevo.');
            }
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="min-h-screen w-full flex items-center justify-center bg-gradient-to-br from-[#004479] to-[#00305a] p-4">
            <div className="w-full max-w-md bg-white rounded-2xl shadow-xl overflow-hidden">
                <div className="bg-[#004479] px-8 py-8 text-center">
                    <div className="w-14 h-14 rounded-xl bg-[#FFCC00] text-[#004479] flex items-center justify-center font-bold text-xl mx-auto mb-3">
                        CC
                    </div>
                    <h1 className="text-white text-xl font-semibold">Crear contraseña</h1>
                    <p className="text-white/70 text-sm mt-1">Activa tu acceso a Cloud API CC</p>
                </div>

                {status === 'loading' && (
                    <div className="px-8 py-8 flex justify-center">
                        <div className="w-7 h-7 rounded-full border-2 border-[#004479]/20 border-t-[#004479] animate-spin" />
                    </div>
                )}

                {status === 'invalid' && (
                    <div className="px-8 py-8 space-y-4">
                        <div className="text-sm text-destructive bg-destructive/10 rounded-lg px-3 py-2">
                            Este enlace de invitación no es válido o ya venció.
                        </div>
                        <Link to="/login" className="block text-center text-sm text-[#004479] font-medium hover:underline">
                            Ir a iniciar sesión
                        </Link>
                    </div>
                )}

                {status === 'already_set' && (
                    <div className="px-8 py-8 space-y-4">
                        <div className="text-sm text-[#004479] bg-[#004479]/10 rounded-lg px-3 py-2">
                            ya se estableció la contraseña para esta cuenta
                        </div>
                        <Link to="/login" className="block text-center text-sm text-[#004479] font-medium hover:underline">
                            Ir a iniciar sesión
                        </Link>
                    </div>
                )}

                {status === 'pending' && (
                    <form onSubmit={submit} className="px-8 py-8 space-y-4">
                        <div className="text-sm text-muted-foreground">
                            Cuenta: <span className="font-medium text-[#004479]">{email}</span>
                        </div>

                        <div>
                            <label className="text-sm text-[#004479] font-medium">Contraseña</label>
                            <PasswordInput
                                wrapperClassName="mt-1.5"
                                leftIcon={<Lock size={16} />}
                                value={password}
                                onChange={(e) => setPassword(e.target.value)}
                                required
                                autoFocus
                                autoComplete="new-password"
                                placeholder="••••••••"
                                className="h-auto border-0 bg-[#f4f6f9] rounded-lg py-2.5 text-sm focus-visible:ring-[#004479]/30 focus-visible:ring-2 focus-visible:border-transparent"
                            />
                        </div>

                        <div>
                            <label className="text-sm text-[#004479] font-medium">Confirmar contraseña</label>
                            <PasswordInput
                                wrapperClassName="mt-1.5"
                                leftIcon={<Lock size={16} />}
                                value={passwordConfirmation}
                                onChange={(e) => setPasswordConfirmation(e.target.value)}
                                required
                                autoComplete="new-password"
                                placeholder="••••••••"
                                className="h-auto border-0 bg-[#f4f6f9] rounded-lg py-2.5 text-sm focus-visible:ring-[#004479]/30 focus-visible:ring-2 focus-visible:border-transparent"
                            />
                        </div>

                        <div className="text-xs text-muted-foreground bg-[#f4f6f9] rounded-lg px-3 py-2">
                            La contraseña debe tener mínimo 8 caracteres, al menos una letra, un número y un símbolo.
                        </div>

                        {error && (
                            <div className="text-sm text-destructive bg-destructive/10 rounded-lg px-3 py-2">{error}</div>
                        )}

                        <Button
                            type="submit"
                            disabled={loading}
                            className="w-full bg-[#004479] hover:bg-[#00305a] h-11 text-base"
                        >
                            {loading ? 'Guardando…' : 'Crear contraseña'}
                        </Button>
                    </form>
                )}
            </div>
        </div>
    );
}
