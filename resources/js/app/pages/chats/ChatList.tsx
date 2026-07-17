import { Search, Archive, ArrowLeft, ChevronRight, X } from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import axios from 'axios';
import { toast } from 'sonner';
import { api } from '../../../lib/api';
import { useAuth } from '../../../lib/auth';
import { Avatar, AvatarFallback } from '../../components/ui/avatar';
import { Badge } from '../../components/ui/badge';
import { shortDate, type ConversationSummary } from './types';

type Props = {
    conversations: ConversationSummary[];
    archivedCount: number;
    selectedId: number | null;
    loading: boolean;
    onSelect: (id: number) => void;
};

export function ChatList({ conversations, archivedCount, selectedId, loading, onSelect }: Props) {
    const { user } = useAuth();
    const isAdmin = user?.role?.name === 'administrador';

    const [query, setQuery] = useState('');
    const [mode, setMode] = useState<'active' | 'archived'>('active');
    const [archived, setArchived] = useState<ConversationSummary[]>([]);
    const [loadingArchived, setLoadingArchived] = useState(false);
    // Rango de fecha de registro para filtrar archivados (YYYY-MM-DD, vacío = todas).
    const [archivedDateFrom, setArchivedDateFrom] = useState('');
    const [archivedDateTo, setArchivedDateTo] = useState('');

    const loadArchived = useCallback(async (dateFrom: string, dateTo: string) => {
        setLoadingArchived(true);
        try {
            const res = await api.get('/conversations', {
                params: {
                    archived: 1,
                    date_from: dateFrom || undefined,
                    date_to: dateTo || undefined,
                },
            });
            setArchived(res.data.data);
        } catch (err) {
            const msg = axios.isAxiosError(err) ? err.response?.data?.message : null;
            toast.error(msg ?? 'No se pudieron cargar los chats archivados.');
        } finally {
            setLoadingArchived(false);
        }
    }, []);

    const openArchived = useCallback(() => {
        setMode('archived');
        loadArchived(archivedDateFrom, archivedDateTo);
    }, [loadArchived, archivedDateFrom, archivedDateTo]);

    // Recarga cuando cambia el rango, mientras se está mirando archivados.
    useEffect(() => {
        if (mode === 'archived') loadArchived(archivedDateFrom, archivedDateTo);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [archivedDateFrom, archivedDateTo]);

    const hasArchivedDateFilter = archivedDateFrom || archivedDateTo;

    const clearArchivedDateFilter = () => {
        setArchivedDateFrom('');
        setArchivedDateTo('');
    };

    const filtered = useMemo(() => {
        const q = query.trim().toLowerCase();
        if (!q) return conversations;
        return conversations.filter((c) => c.contact.name.toLowerCase().includes(q));
    }, [conversations, query]);

    const rows = mode === 'active' ? filtered : archived;
    const rowsLoading = mode === 'active' ? loading : loadingArchived;

    return (
        <div className="flex flex-col h-full bg-white rounded-2xl overflow-hidden">
            <div className="px-5 pt-5 pb-3">
                {mode === 'active' ? (
                    <>
                        <h3 className="mb-3 text-lg font-medium text-[#004479]">Chats</h3>
                        <div className="relative">
                            <Search size={14} className="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground" />
                            <input
                                type="text"
                                value={query}
                                onChange={(e) => setQuery(e.target.value)}
                                placeholder="Buscar contacto"
                                className="w-full bg-[#f4f6f9] rounded-full pl-9 pr-3 py-2 text-sm outline-none focus:ring-2 focus:ring-[#004479]/20"
                            />
                        </div>
                        <button
                            onClick={openArchived}
                            className="w-full flex items-center gap-2 mt-3 px-3 py-2 rounded-xl bg-[#f4f6f9] hover:bg-black/5 transition text-left"
                        >
                            <Archive size={16} className="text-[#004479]" />
                            <span className="flex-1 text-sm text-[#004479]">Chats archivados</span>
                            {archivedCount > 0 && (
                                <Badge className="bg-[#004479]/10 text-[#004479] border-transparent">{archivedCount}</Badge>
                            )}
                            <ChevronRight size={14} className="text-muted-foreground" />
                        </button>
                    </>
                ) : (
                    <>
                        <div className="flex items-center gap-2 mb-3">
                            <button
                                onClick={() => setMode('active')}
                                className="p-1.5 -ml-1.5 rounded-full hover:bg-black/5 text-[#004479]"
                            >
                                <ArrowLeft size={18} />
                            </button>
                            <h3 className="text-lg font-medium text-[#004479]">Chats archivados</h3>
                        </div>
                        <div className="grid grid-cols-[1fr_1fr_auto] items-center gap-2">
                            <label className="min-w-0">
                                <span className="sr-only">Desde</span>
                                <input
                                    type="date"
                                    value={archivedDateFrom}
                                    onChange={(e) => setArchivedDateFrom(e.target.value)}
                                    aria-label="Filtrar desde fecha de registro"
                                    className="w-full bg-[#f4f6f9] rounded-full px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-[#004479]/20"
                                />
                            </label>
                            <label className="min-w-0">
                                <span className="sr-only">Hasta</span>
                                <input
                                    type="date"
                                    value={archivedDateTo}
                                    onChange={(e) => setArchivedDateTo(e.target.value)}
                                    aria-label="Filtrar hasta fecha de registro"
                                    className="w-full bg-[#f4f6f9] rounded-full px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-[#004479]/20"
                                />
                            </label>
                            {hasArchivedDateFilter && (
                                <button
                                    onClick={clearArchivedDateFilter}
                                    title="Quitar filtro de fechas"
                                    className="p-2 rounded-full hover:bg-black/5 text-muted-foreground"
                                >
                                    <X size={14} />
                                </button>
                            )}
                        </div>
                    </>
                )}
            </div>

            <div className="flex-1 overflow-y-auto px-2 pb-3">
                {rowsLoading && <p className="px-3 py-4 text-xs text-muted-foreground">Cargando conversaciones…</p>}

                {!rowsLoading && rows.length === 0 && (
                    <p className="px-3 py-4 text-xs text-muted-foreground">
                        {mode === 'active' ? 'No hay conversaciones.' : 'No hay chats archivados.'}
                    </p>
                )}

                {rows.map((c) => {
                    const selected = c.id === selectedId;
                    return (
                        <button
                            key={c.id}
                            onClick={() => onSelect(c.id)}
                            className={`w-full flex items-center gap-3 px-3 py-2.5 rounded-xl transition-colors mb-1 text-left ${
                                selected ? 'bg-[#004479]/8' : 'hover:bg-black/5'
                            }`}
                        >
                            <div className="relative">
                                <Avatar className="w-10 h-10">
                                    <AvatarFallback className="bg-[#004479]/10 text-[#004479] text-sm">
                                        {c.contact.name[0]?.toUpperCase()}
                                    </AvatarFallback>
                                </Avatar>
                            </div>
                            <div className="flex-1 min-w-0">
                                <div className="flex items-center justify-between gap-2">
                                    <span className="text-sm truncate text-[#004479]">{c.contact.name}</span>
                                    <div className="flex items-center gap-1 shrink-0">
                                        {mode === 'archived' && (
                                            <span className="text-[11px] text-muted-foreground">{shortDate(c.created_at)}</span>
                                        )}
                                        {isAdmin && c.assignee && (
                                            <span className="text-[11px] text-muted-foreground truncate max-w-[64px]">
                                                {c.assignee.name}
                                            </span>
                                        )}
                                        {c.unread_count > 0 && (
                                            <span className="bg-[#FFCC00] text-[#004479] text-[10px] rounded-full px-1.5 min-w-[18px] h-[18px] flex items-center justify-center font-semibold">
                                                {c.unread_count}
                                            </span>
                                        )}
                                    </div>
                                </div>
                                <p className="text-xs text-muted-foreground truncate">{c.preview ?? 'Sin mensajes todavía'}</p>
                            </div>
                        </button>
                    );
                })}
            </div>
        </div>
    );
}
