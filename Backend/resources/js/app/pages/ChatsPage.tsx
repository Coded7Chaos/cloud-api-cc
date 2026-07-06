import { useCallback, useEffect, useState } from 'react';
import { toast } from 'sonner';
import { api } from '../../lib/api';
import { ChatList } from './chats/ChatList';
import { Conversation } from './chats/Conversation';
import { ProfilePanel } from './chats/ProfilePanel';
import type { ConversationDetail, ConversationSummary } from './chats/types';

type MobileView = 'list' | 'chat' | 'profile';

// Cada cuánto se refresca la bandeja y el chat abierto (mensajes entrantes).
const POLL_MS = 4000;

export default function ChatsPage() {
    const [conversations, setConversations] = useState<ConversationSummary[]>([]);
    const [loadingList, setLoadingList] = useState(true);
    const [selectedId, setSelectedId] = useState<number | null>(null);
    const [detail, setDetail] = useState<ConversationDetail | null>(null);
    const [loadingDetail, setLoadingDetail] = useState(false);
    const [sending, setSending] = useState(false);
    const [mobileView, setMobileView] = useState<MobileView>('list');

    // silent = refresco de fondo: sin spinners ni toasts, para no parpadear.
    const loadConversations = useCallback(async (silent = false): Promise<ConversationSummary[] | null> => {
        if (!silent) setLoadingList(true);
        try {
            const res = await api.get('/conversations');
            const data: ConversationSummary[] = res.data.data;
            setConversations(data);
            return data;
        } catch {
            if (!silent) toast.error('No se pudieron cargar las conversaciones.');
            return null;
        } finally {
            if (!silent) setLoadingList(false);
        }
    }, []);

    const loadDetail = useCallback(async (id: number, silent = false): Promise<void> => {
        if (!silent) setLoadingDetail(true);
        try {
            const res = await api.get(`/conversations/${id}`);
            setDetail(res.data.data);
        } catch {
            if (!silent) toast.error('No se pudo abrir la conversación.');
        } finally {
            if (!silent) setLoadingDetail(false);
        }
    }, []);

    // Carga inicial de la bandeja + preselección de la primera conversación.
    useEffect(() => {
        loadConversations().then((data) => {
            if (data && data.length) {
                setSelectedId((current) => current ?? data[0].id);
            }
        });
    }, [loadConversations]);

    // Auto-refresh de la bandeja (mensajes nuevos, orden, no leídos).
    useEffect(() => {
        const timer = setInterval(() => loadConversations(true), POLL_MS);
        return () => clearInterval(timer);
    }, [loadConversations]);

    // Carga + auto-refresh del hilo abierto.
    useEffect(() => {
        if (selectedId == null) {
            setDetail(null);
            return;
        }
        loadDetail(selectedId);
        const timer = setInterval(() => loadDetail(selectedId, true), POLL_MS);
        return () => clearInterval(timer);
    }, [selectedId, loadDetail]);

    const openChat = useCallback((id: number) => {
        setSelectedId(id);
        setMobileView('chat');
    }, []);

    const sendMessage = useCallback(
        async (body: string) => {
            if (selectedId == null) return;
            setSending(true);
            try {
                const res = await api.post(`/conversations/${selectedId}/messages`, { body });
                const message = res.data.data;
                // Añadimos el mensaje al hilo abierto (el polling luego lo reconcilia).
                setDetail((prev) => (prev ? { ...prev, messages: [...prev.messages, message] } : prev));
                // Y actualizamos la vista previa + reordenamos la bandeja.
                setConversations((prev) => {
                    const updated = prev.map((c) =>
                        c.id === selectedId ? { ...c, preview: body, last_message_at: message.sent_at } : c,
                    );
                    return [...updated].sort((a, b) =>
                        (b.last_message_at ?? '').localeCompare(a.last_message_at ?? ''),
                    );
                });
                if (message.status === 'failed') {
                    toast.warning('El mensaje se guardó pero no pudo entregarse a WhatsApp.');
                }
            } catch {
                toast.error('No se pudo enviar el mensaje.');
            } finally {
                setSending(false);
            }
        },
        [selectedId],
    );

    return (
        <div className="h-full grid gap-3 md:gap-4 grid-cols-1 md:grid-cols-[280px_1fr] lg:grid-cols-[300px_1fr_300px]">
            <div className={`${mobileView === 'list' ? 'block' : 'hidden'} md:block h-full overflow-hidden`}>
                <ChatList
                    conversations={conversations}
                    selectedId={selectedId}
                    loading={loadingList}
                    onSelect={openChat}
                />
            </div>

            <div className={`${mobileView === 'chat' ? 'block' : 'hidden'} md:block h-full overflow-hidden`}>
                <Conversation
                    conversation={detail}
                    loading={loadingDetail}
                    sending={sending}
                    onBack={() => setMobileView('list')}
                    onOpenProfile={() => setMobileView('profile')}
                    onSend={sendMessage}
                />
            </div>

            <div className={`${mobileView === 'profile' ? 'block' : 'hidden'} lg:block h-full overflow-hidden`}>
                <ProfilePanel conversation={detail} onClose={() => setMobileView('chat')} />
            </div>
        </div>
    );
}
