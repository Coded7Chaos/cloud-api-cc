import { Phone, X, UserCheck, MessageSquare } from 'lucide-react';
import { useAuth } from '../../../lib/auth';
import { Avatar, AvatarFallback } from '../../components/ui/avatar';
import type { ConversationDetail } from './types';

type Props = {
    conversation: ConversationDetail | null;
    onClose: () => void;
};

export function ProfilePanel({ conversation, onClose }: Props) {
    const { user } = useAuth();
    const isAdmin = user?.role?.name === 'administrador';

    if (!conversation) {
        return <div className="hidden lg:block h-full bg-white rounded-2xl" />;
    }

    const { contact, assignee, messages } = conversation;
    const inbound = messages.filter((m) => m.direction === 'inbound').length;
    const outbound = messages.length - inbound;
    const attendedBy = outbound > 0 && assignee ? assignee.name : 'Sin asignar';

    return (
        <div className="flex flex-col h-full bg-white rounded-2xl overflow-hidden">
            <div className="relative h-28 shrink-0 bg-gradient-to-br from-[#004479] to-[#00305a]">
                <button
                    onClick={onClose}
                    className="lg:hidden absolute top-3 right-3 w-8 h-8 rounded-full bg-white/90 text-[#004479] flex items-center justify-center"
                >
                    <X size={16} />
                </button>
                <Avatar className="absolute -bottom-8 left-1/2 -translate-x-1/2 w-20 h-20 ring-4 ring-white">
                    <AvatarFallback className="bg-[#FFCC00] text-[#004479] text-2xl font-semibold">
                        {contact.name[0]?.toUpperCase()}
                    </AvatarFallback>
                </Avatar>
            </div>

            <div className="pt-10 px-5 pb-4 text-center">
                <p className="text-[#004479] font-semibold">{contact.name}</p>
                <p className="text-xs text-muted-foreground">Contacto de WhatsApp</p>
            </div>

            <div className="px-5 pb-5 space-y-3 border-t border-black/5 pt-4">
                <div className="flex items-center gap-3 text-sm">
                    <div className="w-8 h-8 rounded-lg bg-[#FFCC00]/30 text-[#004479] flex items-center justify-center">
                        <Phone size={14} />
                    </div>
                    <span className="truncate">{contact.phone ?? contact.wa_id}</span>
                </div>
                {isAdmin && (
                    <div className="flex items-center gap-3 text-sm">
                        <div className="w-8 h-8 rounded-lg bg-[#FFCC00]/30 text-[#004479] flex items-center justify-center">
                            <UserCheck size={14} />
                        </div>
                        <span className="min-w-0">
                            <span className="text-muted-foreground">Atendido por: </span>
                            <span>{attendedBy}</span>
                        </span>
                    </div>
                )}
            </div>

            <div className="px-5 pb-5">
                <p className="text-xs text-muted-foreground mb-2 font-semibold">Actividad</p>
                <div className="grid grid-cols-2 gap-2">
                    <div className="bg-[#f4f6f9] rounded-lg p-3">
                        <p className="text-lg font-semibold text-[#004479]">{inbound}</p>
                        <p className="text-[11px] text-muted-foreground flex items-center gap-1">
                            <MessageSquare size={11} /> Recibidos
                        </p>
                    </div>
                    <div className="bg-[#f4f6f9] rounded-lg p-3">
                        <p className="text-lg font-semibold text-[#004479]">{outbound}</p>
                        <p className="text-[11px] text-muted-foreground flex items-center gap-1">
                            <MessageSquare size={11} /> Enviados
                        </p>
                    </div>
                </div>
            </div>
        </div>
    );
}
