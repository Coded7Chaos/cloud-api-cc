@extends('layouts.app')
@section('title','Chats')
@section('content')
<div class="grid h-[calc(100vh-3rem)] min-h-[600px] gap-4 lg:grid-cols-[320px_1fr]">
<aside class="rounded-2xl bg-white overflow-hidden flex flex-col"><header class="p-5 border-b"><h1 class="text-xl font-semibold text-[#004479]">Chats</h1><input type="search" data-chat-search placeholder="Buscar contacto" class="mt-3 w-full rounded-xl bg-slate-100 px-4 py-2.5 text-sm"></header><div class="flex-1 overflow-auto p-2">
@forelse($conversations as $chat)@php($name=$chat->contact->profile_name ?: $chat->contact->phone ?: $chat->contact->wa_id)<a data-chat-item data-name="{{ strtolower($name) }}" href="{{ route('chats.index',$chat) }}" @class(['mb-1 block rounded-xl p-3','bg-[#004479]/10'=>$selected?->is($chat),'hover:bg-slate-50'=>!$selected?->is($chat)])><div class="flex justify-between gap-2"><strong class="truncate text-sm text-[#004479]">{{ $name }}</strong><span class="size-2 rounded-full {{ $chat->status==='closed'?'bg-slate-300':'bg-[#ffcc00]' }}"></span></div><p class="truncate text-xs text-slate-500">{{ $chat->latestMessage?->body ?? 'Sin mensajes' }}</p></a>@empty<p class="p-5 text-sm text-slate-500">No existen conversaciones.</p>@endforelse
</div></aside>
<section class="rounded-2xl bg-white overflow-hidden flex flex-col">
@if($selected)@php($contactName=$selected->contact->profile_name ?: $selected->contact->phone ?: $selected->contact->wa_id)
<header class="border-b px-5 py-4"><div class="flex items-center justify-between"><div><h2 class="font-semibold text-[#004479]">{{ $contactName }}</h2><p class="text-xs text-slate-500">{{ $selected->contact->phone ?: $selected->contact->wa_id }}</p></div><span class="rounded-full px-3 py-1 text-xs {{ $selected->status==='closed'?'bg-slate-100 text-slate-500':'bg-emerald-50 text-emerald-700' }}">{{ $selected->status==='closed'?'Cerrado':'Abierto' }}</span></div></header>
<div id="message-list" data-conversation="{{ $selected->id }}" class="flex-1 overflow-auto bg-slate-50 p-5 space-y-3">
@foreach($selected->messages as $message)<div class="flex {{ $message->direction==='outbound'?'justify-end':'justify-start' }}"><article class="max-w-[78%] rounded-2xl px-4 py-2.5 text-sm {{ $message->direction==='outbound'?'bg-[#004479] text-white rounded-br-sm':'bg-white border rounded-bl-sm' }}"><p class="whitespace-pre-wrap break-words">{{ $message->body }}</p><small class="mt-1 block opacity-70">{{ ($message->sent_at ?: $message->created_at)->format('H:i') }}{{ $message->direction==='outbound'&&$message->status?' · '.$message->status:'' }}</small></article></div>@endforeach
</div>
@if($selected->status==='closed')<div class="border-t bg-slate-100 p-4 text-center text-sm text-slate-500">Esta conversación está cerrada. No se pueden enviar mensajes.</div>@else<form id="message-form" data-endpoint="{{ url('/api/conversations/'.$selected->id.'/messages') }}" class="flex gap-2 border-t p-4"><input name="body" maxlength="4096" required autocomplete="off" placeholder="Escribe un mensaje…" class="flex-1 rounded-full bg-slate-100 px-4 py-3 text-sm outline-none focus:ring-2 focus:ring-[#004479]/20"><button class="rounded-full bg-[#ffcc00] px-5 font-medium text-[#004479]">Enviar</button></form>@endif
@else<div class="grid h-full place-items-center text-sm text-slate-500">Selecciona una conversación.</div>@endif
</section></div>
@endsection
