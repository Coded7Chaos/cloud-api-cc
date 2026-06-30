import { MoreVertical, Smile, Paperclip, Send, ArrowLeft, User } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Avatar, AvatarFallback } from '../../components/ui/avatar';
import { shortTime, type ConversationDetail } from './types';

type Props = {
    conversation: ConversationDetail | null;
    loading: boolean;
    sending: boolean;
    onBack: () => void;
    onOpenProfile: () => void;
    onSend: (body: string) => void;
};

export function Conversation({ conversation, loading, sending, onBack, onOpenProfile, onSend }: Props) {
    const [input, setInput] = useState('');
    const bottomRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        bottomRef.current?.scrollIntoView({ behavior: 'smooth' });
    }, [conversation?.messages.length]);

    const submit = () => {
        const body = input.trim();
        if (!body) return;
        onSend(body);
        setInput('');
    };

    if (!conversation) {
        return (
            <div className="hidden md:flex flex-col h-full bg-white rounded-2xl items-center justify-center text-center px-6">
                <p className="text-sm text-muted-foreground">
                    {loading ? 'Cargando…' : 'Selecciona una conversación para empezar.'}
                </p>
            </div>
        );
    }

    return (
        <div className="flex flex-col h-full bg-white rounded-2xl overflow-hidden">
            <div className="flex items-center gap-3 px-4 md:px-5 py-3 border-b border-black/5">
                <button onClick={onBack} className="md:hidden text-[#004479]">
                    <ArrowLeft size={20} />
                </button>
                <div className="relative">
                    <Avatar className="w-10 h-10">
                        <AvatarFallback className="bg-[#004479]/10 text-[#004479] text-sm">
                            {conversation.contact.name[0]?.toUpperCase()}
                        </AvatarFallback>
                    </Avatar>
                    {conversation.status === 'open' && (
                        <span className="absolute bottom-0 right-0 w-2.5 h-2.5 rounded-full bg-[#FFCC00] ring-2 ring-white" />
                    )}
                </div>
                <div className="flex-1 min-w-0">
                    <p className="text-sm truncate text-[#004479] font-semibold">{conversation.contact.name}</p>
                    <p className="text-[11px] text-muted-foreground">{conversation.contact.phone ?? conversation.contact.wa_id}</p>
                </div>
                <button
                    onClick={onOpenProfile}
                    className="lg:hidden w-9 h-9 rounded-full bg-[#004479]/8 text-[#004479] flex items-center justify-center hover:bg-[#004479]/15"
                >
                    <User size={16} />
                </button>
                <button className="hidden md:flex w-9 h-9 rounded-full bg-[#004479]/8 text-[#004479] items-center justify-center hover:bg-[#004479]/15">
                    <MoreVertical size={16} />
                </button>
            </div>

            <div className="flex-1 overflow-y-auto px-4 md:px-6 py-4 space-y-3 bg-[#fafbfd]">
                {conversation.messages.length === 0 && (
                    <p className="text-center text-xs text-muted-foreground mt-4">Aún no hay mensajes.</p>
                )}
                {conversation.messages.map((m) => {
                    const mine = m.direction === 'outbound';
                    return (
                        <div key={m.id} className={`flex ${mine ? 'justify-end' : 'justify-start'}`}>
                            <div
                                className={`max-w-[75%] px-4 py-2 rounded-2xl text-sm ${
                                    mine
                                        ? 'bg-[#004479] text-white rounded-br-sm'
                                        : 'bg-white border border-black/5 text-foreground rounded-bl-sm'
                                }`}
                            >
                                <p className="whitespace-pre-wrap break-words">{m.body}</p>
                                <span
                                    className={`block text-[10px] mt-1 ${mine ? 'text-white/70' : 'text-muted-foreground'}`}
                                >
                                    {shortTime(m.sent_at ?? m.created_at)}
                                    {mine && m.status ? ` · ${m.status}` : ''}
                                </span>
                            </div>
                        </div>
                    );
                })}
                <div ref={bottomRef} />
            </div>

            <div className="px-3 md:px-5 py-3 border-t border-black/5 bg-white">
                <div className="flex items-center gap-2 bg-[#f4f6f9] rounded-full px-3 py-2">
                    <button className="text-[#004479] hover:opacity-70">
                        <Smile size={18} />
                    </button>
                    <button className="text-[#004479] hover:opacity-70">
                        <Paperclip size={18} />
                    </button>
                    <input
                        value={input}
                        onChange={(e) => setInput(e.target.value)}
                        onKeyDown={(e) => e.key === 'Enter' && !e.shiftKey && (e.preventDefault(), submit())}
                        placeholder="Escribe un mensaje..."
                        className="flex-1 bg-transparent outline-none text-sm px-1"
                    />
                    <button
                        onClick={submit}
                        disabled={sending || !input.trim()}
                        className="w-9 h-9 rounded-full bg-[#FFCC00] text-[#004479] flex items-center justify-center hover:brightness-95 transition disabled:opacity-50"
                    >
                        <Send size={16} />
                    </button>
                </div>
            </div>
        </div>
    );
}
