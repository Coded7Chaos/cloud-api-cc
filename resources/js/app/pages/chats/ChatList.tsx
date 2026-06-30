import { Search } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Avatar, AvatarFallback } from '../../components/ui/avatar';
import type { ConversationSummary } from './types';

type Props = {
    conversations: ConversationSummary[];
    selectedId: number | null;
    loading: boolean;
    onSelect: (id: number) => void;
};

export function ChatList({ conversations, selectedId, loading, onSelect }: Props) {
    const [query, setQuery] = useState('');

    const filtered = useMemo(() => {
        const q = query.trim().toLowerCase();
        if (!q) return conversations;
        return conversations.filter((c) => c.contact.name.toLowerCase().includes(q));
    }, [conversations, query]);

    return (
        <div className="flex flex-col h-full bg-white rounded-2xl overflow-hidden">
            <div className="px-5 pt-5 pb-3">
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
            </div>

            <div className="flex-1 overflow-y-auto px-2 pb-3">
                {loading && <p className="px-3 py-4 text-xs text-muted-foreground">Cargando conversaciones…</p>}

                {!loading && filtered.length === 0 && (
                    <p className="px-3 py-4 text-xs text-muted-foreground">No hay conversaciones.</p>
                )}

                {filtered.map((c) => {
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
                                {c.status === 'open' && (
                                    <span className="absolute bottom-0 right-0 w-2.5 h-2.5 rounded-full bg-[#FFCC00] ring-2 ring-white" />
                                )}
                            </div>
                            <div className="flex-1 min-w-0">
                                <div className="flex items-center justify-between">
                                    <span className="text-sm truncate text-[#004479]">{c.contact.name}</span>
                                    {c.unread_count > 0 && (
                                        <span className="ml-2 bg-[#FFCC00] text-[#004479] text-[10px] rounded-full px-1.5 min-w-[18px] h-[18px] flex items-center justify-center font-semibold">
                                            {c.unread_count}
                                        </span>
                                    )}
                                </div>
                                <p className="text-xs text-muted-foreground truncate">
                                    {c.preview ?? 'Sin mensajes todavía'}
                                </p>
                            </div>
                        </button>
                    );
                })}
            </div>
        </div>
    );
}
