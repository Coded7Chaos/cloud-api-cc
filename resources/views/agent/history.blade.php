@extends('layouts.app')
@section('title','Mi historial')
@section('content')
<div class="rounded-2xl bg-white overflow-hidden"><header class="border-b px-6 py-5"><h1 class="text-xl font-semibold text-[#004479]">Mi historial</h1><p class="text-sm text-slate-500">Conversaciones asignadas o respondidas por ti.</p></header>
<div class="overflow-x-auto"><table class="w-full text-sm"><thead class="bg-slate-50 text-left text-slate-500"><tr><th class="p-4">Contacto</th><th class="p-4">Estado</th><th class="p-4">Inicio</th><th class="p-4">Última interacción</th><th class="p-4 text-center">Recibidos</th><th class="p-4 text-center">Enviados</th><th class="p-4 text-center">Total</th></tr></thead><tbody class="divide-y">
@forelse($conversations as $chat)<tr><td class="p-4 font-medium text-[#004479]">{{ $chat->contact->profile_name ?: $chat->contact->phone ?: $chat->contact->wa_id }}</td><td class="p-4">{{ $chat->status==='closed'?'Cerrado':'Abierto' }}</td><td class="p-4">{{ $chat->created_at->format('d/m/Y H:i') }}</td><td class="p-4">{{ $chat->last_message_at?->format('d/m/Y H:i') ?? 'Sin mensajes' }}</td><td class="p-4 text-center">{{ $chat->received_messages_count }}</td><td class="p-4 text-center">{{ $chat->sent_messages_count }}</td><td class="p-4 text-center font-medium">{{ $chat->messages_count }}</td></tr>@empty<tr><td colspan="7" class="p-10 text-center text-slate-500">No hay actividad registrada.</td></tr>@endforelse
</tbody></table></div></div>
@endsection
