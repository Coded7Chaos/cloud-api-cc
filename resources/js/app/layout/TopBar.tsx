import { Search, Bell, LogOut, ChevronDown } from 'lucide-react';
import { Avatar, AvatarFallback } from '../components/ui/avatar';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '../components/ui/dropdown-menu';
import { useAuth } from '../../lib/auth';
import { initials } from './nav-items';

export function TopBar() {
    const { user, logout } = useAuth();
    const fullName = [user?.name, user?.last_name].filter(Boolean).join(' ');

    return (
        <header className="flex items-center gap-3 md:gap-6 px-4 md:px-8 py-3 bg-[#004479] text-white">
            <div className="flex items-center gap-2">
                <div className="w-10 h-10 rounded-lg bg-[#FFCC00] text-[#004479] flex items-center justify-center font-bold">
                    CC
                </div>
                <span className="hidden sm:inline font-semibold">Cloud API CC</span>
            </div>

            <div className="flex-1 max-w-2xl mx-auto relative">
                <Search size={16} className="absolute left-4 top-1/2 -translate-y-1/2 text-white/60" />
                <input
                    type="text"
                    placeholder="Buscar usuarios, chats y más..."
                    className="w-full bg-white/10 text-white placeholder:text-white/60 rounded-full pl-10 pr-4 py-2 outline-none focus:bg-white/15 transition"
                />
            </div>

            <button className="relative p-2 rounded-full hover:bg-white/10 transition">
                <Bell size={18} />
                <span className="absolute top-1.5 right-1.5 w-2 h-2 rounded-full bg-[#FFCC00]" />
            </button>

            <DropdownMenu>
                <DropdownMenuTrigger className="flex items-center gap-2 outline-none rounded-full hover:bg-white/10 pl-1 pr-2 py-1 transition">
                    <Avatar className="w-9 h-9 ring-2 ring-[#FFCC00]">
                        <AvatarFallback className="bg-white text-[#004479] text-xs">
                            {initials(user?.name, user?.last_name)}
                        </AvatarFallback>
                    </Avatar>
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
                    <DropdownMenuItem onClick={() => logout()} variant="destructive">
                        <LogOut size={14} />
                        Cerrar sesión
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        </header>
    );
}
