@extends('layouts.app')
@section('title',$user->exists?'Editar usuario':'Nuevo usuario')
@section('content')
<div class="mx-auto max-w-2xl rounded-2xl bg-white overflow-hidden"><header class="border-b px-6 py-5"><h1 class="text-xl font-semibold text-[#004479]">{{ $user->exists?'Editar usuario':'Nuevo usuario' }}</h1></header>
<form method="POST" action="{{ $user->exists?route('admin.usuarios.update',$user):route('admin.usuarios.store') }}" class="grid gap-5 p-6 sm:grid-cols-2">@csrf @if($user->exists)@method('PUT')@endif
@foreach(['name'=>'Nombre','last_name'=>'Apellido'] as $field=>$label)<label class="text-sm font-medium">{{ $label }}<input name="{{ $field }}" value="{{ old($field,$user->$field) }}" required class="mt-1.5 w-full rounded-xl border bg-slate-50 px-4 py-3"></label>@endforeach
<label class="sm:col-span-2 text-sm font-medium">Correo<input name="email" type="email" value="{{ old('email',$user->email) }}" required class="mt-1.5 w-full rounded-xl border bg-slate-50 px-4 py-3"></label>
<label class="text-sm font-medium">Contraseña<input name="password" type="password" {{ $user->exists?'':'required' }} class="mt-1.5 w-full rounded-xl border bg-slate-50 px-4 py-3"></label><label class="text-sm font-medium">Confirmar<input name="password_confirmation" type="password" {{ $user->exists?'':'required' }} class="mt-1.5 w-full rounded-xl border bg-slate-50 px-4 py-3"></label>
<label class="sm:col-span-2 flex items-center gap-2 rounded-xl bg-slate-50 p-4 text-sm"><input type="checkbox" name="is_admin" value="1" @checked(old('is_admin',$user->is_admin))> Otorgar rol de administrador</label>
<div class="sm:col-span-2 flex justify-end gap-2"><a href="{{ route('admin.usuarios.index') }}" class="rounded-xl border px-4 py-2.5">Cancelar</a><button class="rounded-xl bg-[#004479] px-5 py-2.5 text-white">Guardar</button></div></form></div>
@endsection
