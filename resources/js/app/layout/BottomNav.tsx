import { NavLink } from 'react-router';
import { navItems } from './nav-items';

export function BottomNav() {
    return (
        <nav className="md:hidden fixed bottom-0 inset-x-0 z-30 bg-[#004479] text-white px-2 py-2 flex justify-around items-center shadow-[0_-4px_20px_rgba(0,0,0,0.15)]">
            {navItems.map(({ to, label, icon: Icon }) => (
                <NavLink
                    key={to}
                    to={to}
                    className={({ isActive }) =>
                        `flex flex-col items-center justify-center gap-0.5 px-4 py-1.5 rounded-xl transition-colors ${
                            isActive ? 'bg-white text-[#004479]' : 'text-white/70'
                        }`
                    }
                >
                    <Icon size={20} />
                    <span className="text-[10px]">{label}</span>
                </NavLink>
            ))}
        </nav>
    );
}
