import { MessageCircle, Users, CalendarClock, type LucideIcon } from 'lucide-react';

export type NavItem = {
    to: string;
    label: string;
    icon: LucideIcon;
};

// Las tres secciones del panel. Compartidas por el Sidebar (desktop) y el
// BottomNav (mobile) para que no se desincronicen.
export const navItems: NavItem[] = [
    { to: '/chats', label: 'Chats', icon: MessageCircle },
    { to: '/usuarios', label: 'Usuarios', icon: Users },
    { to: '/horarios', label: 'Horarios', icon: CalendarClock },
];

export function initials(name?: string, lastName?: string): string {
    const a = name?.trim()?.[0] ?? '';
    const b = lastName?.trim()?.[0] ?? '';
    return (a + b).toUpperCase() || 'U';
}
