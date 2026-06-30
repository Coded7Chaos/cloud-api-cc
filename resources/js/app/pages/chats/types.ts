export type ChatMessage = {
    id: number;
    direction: 'inbound' | 'outbound';
    type: string;
    body: string | null;
    status: string | null;
    sent_at: string | null;
    created_at: string;
    sender: { id: number; name: string } | null;
};

export type ConversationSummary = {
    id: number;
    status: string;
    last_message_at: string | null;
    unread_count: number;
    contact: { id: number; wa_id: string; name: string; phone: string | null };
    assignee: { id: number; name: string } | null;
    preview: string | null;
};

export type ConversationDetail = ConversationSummary & {
    messages: ChatMessage[];
};

/** Hora corta (HH:MM) a partir de un ISO string. */
export function shortTime(iso: string | null): string {
    if (!iso) return '';
    const d = new Date(iso);
    return d.toLocaleTimeString('es', { hour: '2-digit', minute: '2-digit' });
}
