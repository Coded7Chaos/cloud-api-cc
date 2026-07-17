import { Navigate, Outlet, Route, Routes } from 'react-router';
import { useAuth } from '../lib/auth';
import { AppLayout } from './layout/AppLayout';
import LoginPage from './pages/LoginPage';
import ForgotPasswordPage from './pages/ForgotPasswordPage';
import ResetPasswordPage from './pages/ResetPasswordPage';
import ChatsPage from './pages/ChatsPage';
import UsuariosPage from './pages/UsuariosPage';
import HorariosPage from './pages/HorariosPage';

function FullScreenLoader() {
    return (
        <div className="h-screen w-full flex items-center justify-center bg-[#eef1f6]">
            <div className="w-8 h-8 rounded-full border-2 border-[#004479]/20 border-t-[#004479] animate-spin" />
        </div>
    );
}

/** Sólo deja pasar a usuarios autenticados; si no, manda al login. */
function RequireAuth() {
    const { user, loading } = useAuth();
    if (loading) return <FullScreenLoader />;
    if (!user) return <Navigate to="/login" replace />;
    return <Outlet />;
}

function RequireAdmin() {
    const { user } = useAuth();
    if (user?.role?.name !== 'administrador') return <Navigate to="/chats" replace />;
    return <Outlet />;
}

export default function App() {
    const { user, loading } = useAuth();

    return (
        <Routes>
            <Route
                path="/login"
                element={loading ? <FullScreenLoader /> : user ? <Navigate to="/chats" replace /> : <LoginPage />}
            />
            <Route path="/forgot-password" element={<ForgotPasswordPage />} />
            <Route path="/reset-password" element={<ResetPasswordPage />} />

            <Route element={<RequireAuth />}>
                <Route element={<AppLayout />}>
                    <Route index element={<Navigate to="/chats" replace />} />
                    <Route path="/chats" element={<ChatsPage />} />
                    <Route element={<RequireAdmin />}>
                        <Route path="/usuarios" element={<UsuariosPage />} />
                        <Route path="/horarios" element={<HorariosPage />} />
                    </Route>
                </Route>
            </Route>

            <Route path="*" element={<Navigate to="/chats" replace />} />
        </Routes>
    );
}
