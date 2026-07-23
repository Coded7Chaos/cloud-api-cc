import { NavLink } from 'react-router';
import { LogOut } from 'lucide-react';
import { UserAvatar } from '../components/UserAvatar';
import { useAuth } from '../../lib/auth';
import { visibleNavItems } from './nav-items';

export function Sidebar() {
    const { user, logout } = useAuth();

    return (
        <aside className="hidden md:flex w-16 shrink-0 bg-[#004479] text-white flex-col items-center py-5 gap-2 rounded-r-2xl">
            <nav className="flex-1 flex flex-col items-center gap-2 mt-4">
                {visibleNavItems(user?.role?.name).map(({ to, label, icon: Icon }) => (
                    <NavLink
                        key={to}
                        to={to}
                        title={label}
                        className={({ isActive }) =>
                            `w-10 h-10 flex items-center justify-center rounded-lg transition-colors ${
                                isActive ? 'bg-white text-[#004479]' : 'text-white/70 hover:text-white hover:bg-white/10'
                            }`
                        }
                    >
                        <Icon size={20} />
                    </NavLink>
                ))}
            </nav>

            <div className="flex flex-col items-center gap-2 pb-1">
                <button
                    onClick={() => logout()}
                    title="Cerrar sesión"
                    className="w-10 h-10 flex items-center justify-center rounded-lg text-white/70 hover:text-white hover:bg-white/10 transition-colors"
                >
                    <LogOut size={18} />
                </button>
                {/* Acceso directo al perfil: al hacer click va a /perfil. */}
                <NavLink
                    to="/perfil"
                    title="Mi perfil"
                    className={({ isActive }) =>
                        `rounded-full transition ${isActive ? 'ring-2 ring-white' : 'hover:opacity-90'}`
                    }
                >
                    <UserAvatar
                        name={user?.name}
                        lastName={user?.last_name}
                        avatarUrl={user?.avatar_url}
                        className="w-9 h-9 ring-2 ring-[#FFCC00]"
                        fallbackClassName="bg-white text-[#004479] text-xs"
                    />
                </NavLink>
            </div>
        </aside>
    );
}
