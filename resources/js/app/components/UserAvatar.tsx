import { Avatar, AvatarFallback, AvatarImage } from './ui/avatar';
import { initials } from '../layout/nav-items';

type Props = {
    name?: string;
    lastName?: string;
    avatarUrl?: string | null;
    className?: string;
    fallbackClassName?: string;
};

/**
 * Avatar del usuario: muestra la foto si la tiene configurada, o si no las
 * iniciales. Radix cae solo al fallback cuando la imagen no está o no carga.
 * Lo comparten la barra superior, la lateral y la página de perfil.
 */
export function UserAvatar({ name, lastName, avatarUrl, className, fallbackClassName }: Props) {
    return (
        <Avatar className={className}>
            <AvatarImage src={avatarUrl ?? undefined} alt="" className="object-cover" />
            <AvatarFallback className={fallbackClassName}>{initials(name, lastName)}</AvatarFallback>
        </Avatar>
    );
}
