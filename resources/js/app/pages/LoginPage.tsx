import { useState, type FormEvent } from 'react';
import { Link, useLocation, useNavigate } from 'react-router';
import { Lock, Mail } from 'lucide-react';
import axios from 'axios';
import { Button } from '../components/ui/button';
import { useAuth } from '../../lib/auth';

export default function LoginPage() {
    const { login } = useAuth();
    const navigate = useNavigate();
    const location = useLocation();

    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [remember, setRemember] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [message] = useState<string | null>((location.state as { message?: string } | null)?.message ?? null);
    const [loading, setLoading] = useState(false);

    const submit = async (e: FormEvent) => {
        e.preventDefault();
        setError(null);
        setLoading(true);
        try {
            await login(email, password, remember);
            navigate('/chats', { replace: true });
        } catch (err) {
            if (axios.isAxiosError(err) && err.response?.status === 422) {
                const errors = err.response.data?.errors;
                setError(errors?.email?.[0] ?? 'Credenciales inválidas.');
            } else {
                setError('No se pudo iniciar sesión. Intenta de nuevo.');
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
                    <h1 className="text-white text-xl font-semibold">Cloud API CC</h1>
                    <p className="text-white/70 text-sm mt-1">Panel de atención por WhatsApp</p>
                </div>

                <form onSubmit={submit} className="px-8 py-8 space-y-4">
                    <div>
                        <label className="text-sm text-[#004479] font-medium">Correo electrónico</label>
                        <div className="relative mt-1.5">
                            <Mail size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground" />
                            <input
                                type="email"
                                value={email}
                                onChange={(e) => setEmail(e.target.value)}
                                required
                                autoFocus
                                placeholder="tucorreo@empresa.com"
                                className="w-full bg-[#f4f6f9] rounded-lg pl-9 pr-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-[#004479]/30"
                            />
                        </div>
                    </div>

                    <div>
                        <div className="flex items-center justify-between">
                            <label className="text-sm text-[#004479] font-medium">Contraseña</label>
                            <Link to="/forgot-password" className="text-xs text-[#004479] hover:underline">
                                ¿Olvidaste tu contraseña?
                            </Link>
                        </div>
                        <div className="relative mt-1.5">
                            <Lock size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground" />
                            <input
                                type="password"
                                value={password}
                                onChange={(e) => setPassword(e.target.value)}
                                required
                                placeholder="••••••••"
                                className="w-full bg-[#f4f6f9] rounded-lg pl-9 pr-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-[#004479]/30"
                            />
                        </div>
                    </div>

                    {message && (
                        <div className="text-sm text-emerald-700 bg-emerald-50 rounded-lg px-3 py-2">{message}</div>
                    )}

                    {error && (
                        <div className="text-sm text-destructive bg-destructive/10 rounded-lg px-3 py-2">{error}</div>
                    )}

                    <label className="flex items-center gap-2 text-sm text-[#004479]">
                        <input
                            type="checkbox"
                            checked={remember}
                            onChange={(e) => setRemember(e.target.checked)}
                            className="h-4 w-4 accent-[#004479]"
                        />
                        Mantener sesión iniciada
                    </label>

                    <Button
                        type="submit"
                        disabled={loading}
                        className="w-full bg-[#004479] hover:bg-[#00305a] h-11 text-base"
                    >
                        {loading ? 'Ingresando…' : 'Iniciar sesión'}
                    </Button>
                </form>
            </div>
        </div>
    );
}
