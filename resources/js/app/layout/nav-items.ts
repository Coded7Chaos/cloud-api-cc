import { MessageCircle, Users, CalendarClock, ClipboardList, type LucideIcon } from 'lucide-react';

export type NavItem = {
    to: string;
    label: string;
    icon: LucideIcon;
    roles: string[];
};

// Las tres secciones del panel. Compartidas por el Sidebar (desktop) y el
// BottomNav (mobile) para que no se desincronicen.
export const navItems: NavItem[] = [
    { to: '/chats', label: 'Chats', icon: MessageCircle, roles: ['administrador', 'soporte'] },
    { to: '/usuarios', label: 'Usuarios', icon: Users, roles: ['administrador'] },
    { to: '/horarios', label: 'Horarios', icon: CalendarClock, roles: ['administrador'] },
    { to: '/auditoria', label: 'Auditoría', icon: ClipboardList, roles: ['administrador'] },
];

export function visibleNavItems(roleName?: string | null): NavItem[] {
    return navItems.filter((item) => roleName && item.roles.includes(roleName));
}

export function initials(name?: string, lastName?: string): string {
    const a = name?.trim()?.[0] ?? '';
    const b = lastName?.trim()?.[0] ?? '';
    return (a + b).toUpperCase() || 'U';
}
