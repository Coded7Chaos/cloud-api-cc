const csrf = document.querySelector('meta[name="csrf-token"]')?.content;

const search = document.querySelector('[data-chat-search]');
search?.addEventListener('input', () => {
    const query = search.value.trim().toLowerCase();
    document.querySelectorAll('[data-chat-item]').forEach((item) => {
        item.hidden = !item.dataset.name.includes(query);
    });
});

const messageList = document.querySelector('#message-list');
const renderMessages = (messages) => {
    if (!messageList) return;
    messageList.replaceChildren(...messages.map((message) => {
        const row = document.createElement('div');
        row.className = `flex ${message.direction === 'outbound' ? 'justify-end' : 'justify-start'}`;
        const bubble = document.createElement('article');
        bubble.className = `max-w-[78%] rounded-2xl px-4 py-2.5 text-sm ${message.direction === 'outbound' ? 'bg-[#004479] text-white rounded-br-sm' : 'bg-white border rounded-bl-sm'}`;
        const body = document.createElement('p');
        body.className = 'whitespace-pre-wrap break-words';
        body.textContent = message.body ?? '';
        const time = document.createElement('small');
        time.className = 'mt-1 block opacity-70';
        time.textContent = new Date(message.sent_at ?? message.created_at).toLocaleTimeString('es', { hour: '2-digit', minute: '2-digit' }) + (message.direction === 'outbound' && message.status ? ` · ${message.status}` : '');
        bubble.append(body, time); row.append(bubble); return row;
    }));
    messageList.scrollTop = messageList.scrollHeight;
};

const refreshConversation = async () => {
    if (!messageList?.dataset.conversation) return;
    const response = await fetch(`/api/conversations/${messageList.dataset.conversation}`, { headers: { Accept: 'application/json' } });
    if (response.ok) renderMessages((await response.json()).data.messages);
};

document.querySelector('#message-form')?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const form = event.currentTarget;
    const input = form.elements.body;
    const response = await fetch(form.dataset.endpoint, { method: 'POST', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf }, body: JSON.stringify({ body: input.value }) });
    if (response.ok) { input.value = ''; await refreshConversation(); }
    else { const payload = await response.json(); alert(payload.message ?? 'No se pudo enviar el mensaje.'); if (response.status === 422) location.reload(); }
});

if (messageList) { messageList.scrollTop = messageList.scrollHeight; setInterval(refreshConversation, 5000); }
