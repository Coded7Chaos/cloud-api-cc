import { useCallback, useEffect, useRef, useState } from 'react';
import axios from 'axios';
import { useSearchParams } from 'react-router';
import { toast } from 'sonner';
import { api } from '../../lib/api';
import { useAuth } from '../../lib/auth';
import { ChatList } from './chats/ChatList';
import { Conversation } from './chats/Conversation';
import { ProfilePanel } from './chats/ProfilePanel';
import type { ConversationDetail, ConversationSummary } from './chats/types';

type MobileView = 'list' | 'chat' | 'profile';

// Cada cuánto se refresca la bandeja y el chat abierto (mensajes entrantes).
const POLL_MS = 4000;

export default function ChatsPage() {
    const { user } = useAuth();
    const isSoporte = user?.role?.name === 'soporte';
    const [searchParams, setSearchParams] = useSearchParams();

    const [conversations, setConversations] = useState<ConversationSummary[]>([]);
    const [archivedCount, setArchivedCount] = useState(0);
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
            setArchivedCount(res.data.meta?.archived_count ?? 0);
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
            const data: ConversationDetail = res.data.data;
            setDetail(data);
            setConversations((prev) => prev.map((c) => (c.id === id ? { ...c, unread_count: data.unread_count } : c)));
        } catch (err) {
            const status = axios.isAxiosError(err) ? err.response?.status : null;
            if (status === 409) {
                toast.warning(axios.isAxiosError(err) ? err.response?.data?.message : 'Este chat ya fue tomado por otro agente.');
                setSelectedId(null);
                setDetail(null);
                loadConversations();
            } else if (status === 404) {
                toast.error('Esa conversación ya no está disponible.');
                setSelectedId(null);
                setDetail(null);
            } else if (!silent) {
                toast.error('No se pudo abrir la conversación.');
            }
        } finally {
            if (!silent) setLoadingDetail(false);
        }
    }, [loadConversations]);

    // Carga inicial de la bandeja. Para soporte NO preseleccionamos la primera
    // conversación: abrir un chat sin asignar lo reclama (authorizeAndClaimFor),
    // así que auto-seleccionar por comodidad terminaría "tomando" un chat solo
    // por entrar a la página, sin que el agente haya hecho click a propósito.
    // Para administrador (que nunca reclama al mirar) sí es seguro y cómodo.
    useEffect(() => {
        loadConversations().then((data) => {
            if (isSoporte) return;
            if (data && data.length) {
                setSelectedId((current) => current ?? data[0].id);
            }
        });
    }, [loadConversations, isSoporte]);

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

    // Web Push abre /chats?conversation_id=N. Consumimos el parámetro una
    // sola vez para que recargar la página no intente reclamarlo de nuevo.
    useEffect(() => {
        const id = Number(searchParams.get('conversation_id'));
        if (!Number.isInteger(id) || id <= 0) return;

        openChat(id);
        setSearchParams({}, { replace: true });
    }, [openChat, searchParams, setSearchParams]);

    // Si otra pestaña/dispositivo toma el chat, el service worker retira la
    // notificación y pide refrescar la bandeja inmediatamente.
    useEffect(() => {
        const onWorkerMessage = (event: MessageEvent) => {
            if (event.data?.type !== 'conversation_claimed') return;
            loadConversations(true);
        };

        navigator.serviceWorker?.addEventListener('message', onWorkerMessage);
        return () => navigator.serviceWorker?.removeEventListener('message', onWorkerMessage);
    }, [loadConversations]);

    // Aviso de "chat nuevo sin tomar" para soporte: comparamos el set de
    // conversaciones sin asignar contra el del refresco anterior. Solo avisa
    // de las que aparecieron recién -- no re-notifica en cada poll, ni
    // dispara en la carga inicial (que solo establece la base de comparación).
    const previousUnclaimedIds = useRef<Set<number> | null>(null);
    useEffect(() => {
        if (!isSoporte) return;

        if (previousUnclaimedIds.current) {
            for (const c of conversations) {
                if (c.assignee === null && !previousUnclaimedIds.current.has(c.id)) {
                    toast.info(`Chat nuevo de ${c.contact.name}`, {
                        description: c.preview ?? undefined,
                        duration: 10000,
                        action: { label: 'Ver', onClick: () => openChat(c.id) },
                    });
                }
            }
        }

        previousUnclaimedIds.current = new Set(conversations.filter((c) => c.assignee === null).map((c) => c.id));
    }, [conversations, isSoporte, openChat]);

    const sendMessage = useCallback(
        async (body: string, media?: File | null) => {
            if (selectedId == null || detail?.can_send === false) return;
            setSending(true);
            try {
                const payload = new FormData();
                if (body) payload.append('body', body);
                if (media) payload.append('media', media);

                const res = await api.post(`/conversations/${selectedId}/messages`, payload);
                const message = res.data.data;
                // Añadimos el mensaje al hilo abierto (el polling luego lo reconcilia).
                setDetail((prev) => (prev ? { ...prev, messages: [...prev.messages, message] } : prev));
                // Y actualizamos la vista previa + reordenamos la bandeja.
                setConversations((prev) => {
                    const preview = body || (media ? media.name : '');
                    const updated = prev.map((c) =>
                        c.id === selectedId ? { ...c, preview, last_message_at: message.sent_at } : c,
                    );
                    return [...updated].sort((a, b) =>
                        (b.last_message_at ?? '').localeCompare(a.last_message_at ?? ''),
                    );
                });
                if (message.status === 'failed') {
                    toast.warning('El mensaje se guardó pero no pudo entregarse a WhatsApp.');
                }
            } catch (err) {
                const serverMessage = axios.isAxiosError(err) ? err.response?.data?.message : null;
                toast.error(serverMessage ?? 'No se pudo enviar el mensaje.');
            } finally {
                setSending(false);
            }
        },
        [selectedId, detail?.can_send],
    );

    return (
        <div className="h-full grid gap-3 md:gap-4 grid-cols-1 md:grid-cols-[280px_1fr] lg:grid-cols-[300px_1fr_300px]">
            <div className={`${mobileView === 'list' ? 'block' : 'hidden'} md:block h-full overflow-hidden`}>
                <ChatList
                    conversations={conversations}
                    archivedCount={archivedCount}
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
