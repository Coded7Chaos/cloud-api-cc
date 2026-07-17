@extends('layouts.app')
@section('title','Mi panel')
@section('content')
<header class="mb-5"><h1 class="text-2xl font-semibold text-[#004479]">Mi panel de agente</h1><p class="text-sm text-slate-500">Tu jornada, tareas y conversaciones en un solo lugar.</p></header>
<div class="grid gap-4 lg:grid-cols-3">
    <section class="rounded-2xl bg-white overflow-hidden"><h2 class="border-b px-5 py-4 font-semibold text-[#004479]">Mi horario</h2><div class="p-4 space-y-2">
        @forelse($schedule?->shifts ?? [] as $shift)<div class="flex justify-between rounded-xl bg-slate-50 px-3 py-2 text-sm"><strong>{{ ['', 'Lunes','Martes','Miércoles','Jueves','Viernes','Sábado','Domingo'][$shift->weekday] }}</strong><span>{{ substr($shift->start_time,0,5) }} – {{ substr($shift->end_time,0,5) }}</span></div>@empty<p class="py-8 text-center text-sm text-slate-500">Sin horario asignado.</p>@endforelse
    </div></section>
    <section class="rounded-2xl bg-white overflow-hidden"><h2 class="border-b px-5 py-4 font-semibold text-[#004479]">Mis tareas <span class="float-right rounded-full bg-slate-100 px-2 text-xs">{{ $tasks->count() }}</span></h2><div class="max-h-[32rem] overflow-auto p-4 space-y-2">
        @forelse($tasks as $task)<article class="rounded-xl border p-3"><div class="flex justify-between gap-2"><strong class="text-sm text-[#004479]">{{ $task->titulo }}</strong>@if($task->pivot->status === 'completed')<span class="text-xs text-emerald-700">✓ Realizada</span>@else<form method="POST" action="{{ route('agent.tasks.complete',$task) }}">@csrf @method('PATCH')<button class="rounded-full bg-[#ffcc00]/40 px-2 py-1 text-xs text-[#004479]">Marcar realizada</button></form>@endif</div>@if($task->descripcion)<p class="mt-2 text-xs text-slate-500">{{ $task->descripcion }}</p>@endif</article>@empty<p class="py-8 text-center text-sm text-slate-500">Sin tareas asignadas.</p>@endforelse
    </div></section>
    <section class="rounded-2xl bg-white overflow-hidden"><h2 class="border-b px-5 py-4 font-semibold text-[#004479]">Chats <span class="float-right rounded-full bg-slate-100 px-2 text-xs">{{ $chats->count() }}</span></h2><div class="max-h-[32rem] overflow-auto p-3 space-y-1">
        @forelse($chats as $chat)<a href="{{ route('chats.index',$chat) }}" class="block rounded-xl border p-3 hover:bg-slate-50"><div class="flex justify-between"><strong class="text-sm text-[#004479]">{{ $chat->contact->profile_name ?: $chat->contact->phone }}</strong><span class="text-xs {{ $chat->status==='closed'?'text-slate-400':'text-emerald-600' }}">{{ $chat->status==='closed'?'Cerrado':'Abierto' }}</span></div><p class="truncate text-xs text-slate-500">{{ $chat->latestMessage?->body ?? 'Sin mensajes' }}</p></a>@empty<p class="py-8 text-center text-sm text-slate-500">No hay chats.</p>@endforelse
    </div></section>
</div>
@endsection
