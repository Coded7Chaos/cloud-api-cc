import { MoreVertical, Smile, Paperclip, Send, ArrowLeft, User, Lock, Clock, Check, CheckCheck, CircleAlert, FileText, Download } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Avatar, AvatarFallback } from '../../components/ui/avatar';
import { shortTime, type ChatMessage, type ConversationDetail } from './types';

const EMOJIS = ['😀', '😃', '😄', '😁', '😊', '🙂', '😉', '😍', '😘', '😎', '🥳', '😅', '😂', '🤣', '😢', '😮', '👍', '👏', '🙌', '🙏', '💪', '👌', '✅', '❌', '⭐', '🔥', '❤️', '💛', '💙', '🎉', '📌', '⏰'];

type Props = {
    conversation: ConversationDetail | null;
    loading: boolean;
    sending: boolean;
    onBack: () => void;
    onOpenProfile: () => void;
    onSend: (body: string, media?: File | null) => Promise<void>;
};

export function Conversation({ conversation, loading, sending, onBack, onOpenProfile, onSend }: Props) {
    const [input, setInput] = useState('');
    const [media, setMedia] = useState<File | null>(null);
    const [emojiOpen, setEmojiOpen] = useState(false);
    const bottomRef = useRef<HTMLDivElement>(null);
    const fileInputRef = useRef<HTMLInputElement>(null);
    const inputRef = useRef<HTMLInputElement>(null);
    const emojiPickerRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        bottomRef.current?.scrollIntoView({ behavior: 'smooth' });
    }, [conversation?.messages.length]);

    useEffect(() => {
        if (!emojiOpen) return;

        const close = (event: MouseEvent) => {
            if (emojiPickerRef.current?.contains(event.target as Node)) return;
            setEmojiOpen(false);
        };

        const closeOnEscape = (event: KeyboardEvent) => {
            if (event.key === 'Escape') setEmojiOpen(false);
        };

        document.addEventListener('mousedown', close);
        document.addEventListener('keydown', closeOnEscape);

        return () => {
            document.removeEventListener('mousedown', close);
            document.removeEventListener('keydown', closeOnEscape);
        };
    }, [emojiOpen]);

    // El audio va sin caption: la Cloud API lo rechaza (ver MessageController).
    const mediaIsAudio = media?.type?.startsWith('audio/') ?? false;

    const submit = async () => {
        const body = input.trim();
        if (!body && !media) return;

        await onSend(mediaIsAudio ? '' : body, media);
        // Con audio no se envió el texto, así que se conserva lo tipeado.
        if (!mediaIsAudio) setInput('');
        setMedia(null);
        setEmojiOpen(false);
    };

    const insertEmoji = (emoji: string) => {
        const inputEl = inputRef.current;
        const start = inputEl?.selectionStart ?? input.length;
        const end = inputEl?.selectionEnd ?? input.length;
        const nextValue = `${input.slice(0, start)}${emoji}${input.slice(end)}`;
        const nextCaret = start + emoji.length;

        setInput(nextValue);
        requestAnimationFrame(() => {
            inputEl?.focus();
            inputEl?.setSelectionRange(nextCaret, nextCaret);
        });
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
                                <MessageMedia message={m} />
                                {m.body && <p className="whitespace-pre-wrap break-words">{m.body}</p>}
                                <MessageMeta message={m} mine={mine} />
                            </div>
                        </div>
                    );
                })}
                <div ref={bottomRef} />
            </div>

            <div className="px-3 md:px-5 py-3 border-t border-black/5 bg-white">
                {conversation.can_send ? (
                    <div className="space-y-2">
                        {media && (
                            <div className="rounded-lg bg-[#f4f6f9] px-3 py-2 text-xs text-[#004479]">
                                <div className="flex items-center justify-between gap-2">
                                    <span className="truncate">
                                        {media.name}
                                        <span className="text-muted-foreground"> · {formatBytes(media.size)}</span>
                                    </span>
                                    <button
                                        onClick={() => setMedia(null)}
                                        className="text-muted-foreground hover:text-destructive shrink-0"
                                    >
                                        Quitar
                                    </button>
                                </div>
                                {mediaIsAudio && (
                                    <p className="mt-1 text-[11px] text-muted-foreground">
                                        WhatsApp no permite texto junto a un audio: se envía solo el audio.
                                    </p>
                                )}
                            </div>
                        )}
                        <div className="flex items-center gap-2 bg-[#f4f6f9] rounded-full px-3 py-2">
                            <div ref={emojiPickerRef} className="relative">
                                <button
                                    type="button"
                                    onClick={() => setEmojiOpen((current) => !current)}
                                    className="text-[#004479] hover:opacity-70"
                                    title="Insertar emoji"
                                    aria-label="Insertar emoji"
                                >
                                    <Smile size={18} />
                                </button>
                                {emojiOpen && (
                                    <div className="absolute bottom-9 left-0 z-20 w-64 rounded-xl border border-black/10 bg-white p-2 shadow-xl">
                                        <div className="grid grid-cols-8 gap-1">
                                            {EMOJIS.map((emoji) => (
                                                <button
                                                    key={emoji}
                                                    type="button"
                                                    onClick={() => insertEmoji(emoji)}
                                                    className="h-7 rounded-md text-base hover:bg-[#004479]/10"
                                                >
                                                    {emoji}
                                                </button>
                                            ))}
                                        </div>
                                    </div>
                                )}
                            </div>
                            <button
                                type="button"
                                onClick={() => fileInputRef.current?.click()}
                                className="text-[#004479] hover:opacity-70"
                                title="Adjuntar imagen, video, audio o documento"
                            >
                                <Paperclip size={18} />
                            </button>
                            <input
                                ref={fileInputRef}
                                type="file"
                                accept="image/*,video/*,audio/*,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.zip"
                                className="hidden"
                                onChange={(e) => setMedia(e.target.files?.[0] ?? null)}
                            />
                            <input
                                ref={inputRef}
                                value={input}
                                onChange={(e) => setInput(e.target.value)}
                                onKeyDown={(e) => e.key === 'Enter' && !e.shiftKey && (e.preventDefault(), submit())}
                                disabled={mediaIsAudio}
                                placeholder={
                                    mediaIsAudio
                                        ? 'El audio se envía sin texto'
                                        : media
                                          ? 'Agrega un caption...'
                                          : 'Escribe un mensaje...'
                                }
                                className="flex-1 bg-transparent outline-none text-sm px-1 disabled:text-muted-foreground"
                            />
                            <button
                                type="button"
                                onClick={submit}
                                disabled={sending || (!input.trim() && !media)}
                                className="w-9 h-9 rounded-full bg-[#FFCC00] text-[#004479] flex items-center justify-center hover:brightness-95 transition disabled:opacity-50"
                            >
                                <Send size={16} />
                            </button>
                        </div>
                    </div>
                ) : (
                    <div className="flex items-center gap-2 bg-[#f4f6f9] rounded-full px-4 py-2.5 text-xs text-muted-foreground">
                        <Lock size={14} className="shrink-0" />
                        Pasaron más de 24 horas desde el último mensaje del cliente. Vas a poder responder cuando vuelva a escribir.
                    </div>
                )}
            </div>
        </div>
    );
}

function MessageMeta({ message, mine }: { message: ChatMessage; mine: boolean }) {
    const status = mine ? messageStatus(message.status) : null;
    const Icon = status?.icon;

    return (
        <span
            className={`mt-1 flex items-center gap-1 text-[10px] ${
                status?.tone === 'error'
                    ? 'text-red-300'
                    : status?.tone === 'read'
                      ? 'text-sky-200'
                      : mine
                        ? 'text-white/70'
                        : 'text-muted-foreground'
            }`}
        >
            <span>{shortTime(message.sent_at ?? message.created_at)}</span>
            {status && Icon && (
                <>
                    <span>·</span>
                    <Icon size={12} />
                    <span>{status.label}</span>
                </>
            )}
        </span>
    );
}

function messageStatus(status: string | null) {
    switch (status) {
        case 'pending':
            return { label: 'Pendiente', icon: Clock, tone: 'muted' as const };
        case 'sent':
            return { label: 'Enviado', icon: Check, tone: 'muted' as const };
        case 'delivered':
            return { label: 'Entregado', icon: CheckCheck, tone: 'muted' as const };
        case 'read':
            return { label: 'Leído', icon: CheckCheck, tone: 'read' as const };
        case 'failed':
            return { label: 'Error', icon: CircleAlert, tone: 'error' as const };
        default:
            return status ? { label: status, icon: Check, tone: 'muted' as const } : null;
    }
}

function MessageMedia({ message }: { message: ChatMessage }) {
    if (!message.media.length) return null;

    const mine = message.direction === 'outbound';

    return (
        <div className={`space-y-2 ${message.body ? 'mb-2' : ''}`}>
            {message.media.map((media) => {
                if (media.mime_type?.startsWith('image/')) {
                    return (
                        <img
                            key={media.id}
                            src={media.url}
                            alt={media.original_filename ?? 'Imagen adjunta'}
                            className="max-h-72 rounded-xl object-contain"
                        />
                    );
                }

                if (media.mime_type?.startsWith('video/')) {
                    return <video key={media.id} src={media.url} controls className="max-h-72 rounded-xl" />;
                }

                // Nota de voz / audio: reproductor embebido, como en WhatsApp.
                if (media.mime_type?.startsWith('audio/')) {
                    return (
                        <audio
                            key={media.id}
                            src={media.url}
                            controls
                            preload="metadata"
                            className="w-64 max-w-full h-10"
                        />
                    );
                }

                // Cualquier otra cosa (PDF, Office, zip…) va como tarjeta
                // descargable con su nombre y peso.
                return (
                    <a
                        key={media.id}
                        href={media.url}
                        target="_blank"
                        rel="noreferrer"
                        className={`flex items-center gap-2.5 rounded-xl px-3 py-2 transition ${
                            mine ? 'bg-white/15 hover:bg-white/25' : 'bg-[#f4f6f9] hover:bg-black/5'
                        }`}
                    >
                        <FileText size={22} className="shrink-0 opacity-80" />
                        <span className="min-w-0">
                            <span className="block truncate max-w-[190px] font-medium">
                                {media.original_filename ?? 'Archivo adjunto'}
                            </span>
                            <span className={`block text-[11px] ${mine ? 'text-white/70' : 'text-muted-foreground'}`}>
                                {[fileLabel(media.mime_type), formatBytes(media.size)].filter(Boolean).join(' · ')}
                            </span>
                        </span>
                        <Download size={15} className="shrink-0 opacity-70" />
                    </a>
                );
            })}
        </div>
    );
}

/** Etiqueta corta del tipo de archivo a partir del mime (PDF, DOCX, ZIP…). */
function fileLabel(mime: string | null): string {
    if (!mime) return 'Archivo';
    if (mime === 'application/pdf') return 'PDF';
    if (mime.includes('word')) return 'Word';
    if (mime.includes('sheet') || mime.includes('excel')) return 'Excel';
    if (mime.includes('presentation') || mime.includes('powerpoint')) return 'PowerPoint';
    if (mime.includes('zip') || mime.includes('compressed')) return 'ZIP';
    if (mime.startsWith('text/')) return 'Texto';
    return 'Archivo';
}

function formatBytes(bytes: number | null): string {
    if (!bytes) return '';
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${Math.round(bytes / 1024)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}
