import { useNavigate } from 'react-router';
import { LogOut, ChevronDown, UserRound } from 'lucide-react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '../components/ui/dropdown-menu';
import { UserAvatar } from '../components/UserAvatar';
import { useAuth } from '../../lib/auth';

export function TopBar() {
    const { user, logout } = useAuth();
    const navigate = useNavigate();
    const fullName = [user?.name, user?.last_name].filter(Boolean).join(' ');

    return (
        <header className="flex items-center gap-3 md:gap-6 px-4 md:px-8 py-3 bg-[#004479] text-white">
            <div className="flex items-center gap-2">
                <div className="w-10 h-10 rounded-lg bg-[#FFCC00] text-[#004479] flex items-center justify-center font-bold">
                    CC
                </div>
                <span className="hidden sm:inline font-semibold">Cloud API CC</span>
            </div>

            <DropdownMenu>
                <DropdownMenuTrigger className="ml-auto flex items-center gap-2 outline-none rounded-full hover:bg-white/10 pl-1 pr-2 py-1 transition">
                    <UserAvatar
                        name={user?.name}
                        lastName={user?.last_name}
                        avatarUrl={user?.avatar_url}
                        className="w-9 h-9 ring-2 ring-[#FFCC00]"
                        fallbackClassName="bg-white text-[#004479] text-xs"
                    />
                    <div className="hidden md:flex flex-col leading-tight text-left">
                        <span className="text-sm">{fullName || 'Usuario'}</span>
                        <span className="text-[11px] text-white/70">En línea</span>
                    </div>
                    <ChevronDown size={14} className="hidden md:block text-white/70" />
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" className="w-52">
                    <DropdownMenuLabel>
                        <div className="flex flex-col">
                            <span>{fullName || 'Usuario'}</span>
                            <span className="text-xs text-muted-foreground font-normal">{user?.email}</span>
                        </div>
                    </DropdownMenuLabel>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem onClick={() => navigate('/perfil')}>
                        <UserRound size={14} />
                        Mi perfil
                    </DropdownMenuItem>
                    <DropdownMenuItem onClick={() => logout()} variant="destructive">
                        <LogOut size={14} />
                        Cerrar sesión
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        </header>
    );
}
