@extends('layouts.app')
@section('title','Crear tarea')
@section('content')
<div class="mx-auto max-w-3xl rounded-2xl bg-white overflow-hidden"><header class="border-b px-6 py-5"><h1 class="text-xl font-semibold text-[#004479]">Crear nueva tarea</h1><p class="text-sm text-slate-500">Define la actividad y asígnala a varios agentes.</p></header>
<form method="POST" action="{{ route('admin.tasks.store') }}" class="space-y-5 p-6">@csrf
<label class="block text-sm font-medium">Título<input name="titulo" value="{{ old('titulo') }}" required class="mt-1.5 w-full rounded-xl border bg-slate-50 px-4 py-3"></label>
<label class="block text-sm font-medium">Descripción<textarea name="descripcion" rows="5" class="mt-1.5 w-full rounded-xl border bg-slate-50 px-4 py-3">{{ old('descripcion') }}</textarea></label>
<fieldset><legend class="mb-2 text-sm font-medium">Asignar agentes</legend><div class="max-h-72 overflow-auto rounded-xl border divide-y">@forelse($users as $user)<label class="flex cursor-pointer items-center gap-3 px-4 py-3 hover:bg-slate-50"><input type="checkbox" name="usuarios[]" value="{{ $user->id }}" @checked(in_array($user->id,old('usuarios',[])))><span><strong class="block text-sm">{{ $user->name }} {{ $user->last_name }}</strong><small class="text-slate-500">{{ $user->email }}</small></span></label>@empty<p class="p-4 text-sm text-slate-500">No existen agentes.</p>@endforelse</div></fieldset>
<div class="text-right"><button class="rounded-xl bg-[#004479] px-5 py-3 text-sm font-medium text-white">Crear y asignar tarea</button></div></form></div>
@endsection
