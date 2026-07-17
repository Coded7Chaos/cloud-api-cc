<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Cloud API CC')</title>
    @vite(['resources/css/app.css', 'resources/js/blade.js'])
</head>
<body class="min-h-screen bg-[#eef1f6] text-slate-800">
<div class="min-h-screen md:flex">
    <aside class="fixed inset-x-0 bottom-0 z-50 bg-[#004479] text-white shadow-[0_-8px_24px_rgba(0,0,0,.18)] md:static md:w-64 md:min-h-screen md:p-4 md:shadow-none">
        <a href="{{ auth()->user()->is_admin ? route('chats.index') : route('agent.dashboard') }}" class="hidden md:flex items-center gap-3 px-2 py-3">
            <span class="grid place-items-center size-10 rounded-xl bg-[#ffcc00] text-[#004479] font-bold">CC</span>
            <span><strong class="block">Cloud Contact</strong><small class="text-white/60">Panel de atención</small></span>
        </a>
        <nav class="flex gap-1 overflow-x-auto px-2 py-2 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden md:mt-6 md:grid md:grid-cols-1 md:gap-2 md:overflow-visible md:px-0 md:py-0" aria-label="Navegación principal">
            @if(!auth()->user()->is_admin)
                <x-nav-link icon="home" :href="route('agent.dashboard')" :active="request()->routeIs('agent.dashboard')">Inicio</x-nav-link>
                <x-nav-link icon="history" :href="route('agent.history')" :active="request()->routeIs('agent.history')">Historial</x-nav-link>
            @endif
            <x-nav-link icon="chat" :href="route('chats.index')" :active="request()->routeIs('chats.*')">Chats</x-nav-link>
            @if(auth()->user()->is_admin)
                <x-nav-link icon="users" :href="route('admin.usuarios.index')" :active="request()->routeIs('admin.usuarios.*')">Usuarios</x-nav-link>
                <x-nav-link icon="calendar" :href="route('admin.schedules.index')" :active="request()->routeIs('admin.schedules.*')">Horarios</x-nav-link>
                <x-nav-link icon="task" :href="route('admin.tasks.create')" :active="request()->routeIs('admin.tasks.*')">Tareas</x-nav-link>
                <x-nav-link icon="tracking" :href="route('admin.tracking')" :active="request()->routeIs('admin.tracking')">Seguimiento</x-nav-link>
            @endif
        </nav>
        <div class="hidden md:flex mt-6 md:mt-auto md:fixed md:bottom-5 md:w-56 px-2 items-center justify-between gap-3">
            <div class="min-w-0"><p class="text-sm truncate">{{ auth()->user()->name }} {{ auth()->user()->last_name }}</p><p class="text-xs text-white/60">{{ auth()->user()->is_admin ? 'Administrador' : 'Agente' }}</p></div>
            <form method="POST" action="{{ route('logout') }}">@csrf<button class="rounded-lg px-3 py-2 text-sm bg-white/10 hover:bg-white/20" title="Cerrar sesión">Salir</button></form>
        </div>
    </aside>
    <main class="flex-1 min-w-0 p-4 pb-24 md:p-6">
        <header class="-mx-4 -mt-4 mb-4 flex items-center gap-2 bg-[#004479] px-3 py-2.5 shadow-md md:hidden">
            <a href="{{ auth()->user()->is_admin ? route('chats.index') : route('agent.dashboard') }}" class="grid size-9 shrink-0 place-items-center rounded-lg bg-[#ffcc00] text-sm font-black text-[#004479]">CC</a>
            <label class="flex min-w-0 flex-1 items-center gap-2 rounded-full bg-white/10 px-3 py-2 text-white/70">
                <svg class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                <input type="search" placeholder="Buscar usuarios, chats y más..." class="min-w-0 flex-1 bg-transparent text-xs text-white placeholder:text-white/60 outline-none">
            </label>
            <details class="relative shrink-0">
                <summary class="relative grid size-9 cursor-pointer list-none place-items-center text-white" aria-label="Cuenta y notificaciones">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/><path d="M10 21h4"/></svg>
                    <span class="absolute right-1.5 top-1.5 size-2 rounded-full bg-[#ffcc00] ring-2 ring-[#004479]"></span>
                </summary>
                <div class="absolute right-0 top-11 z-50 w-52 rounded-xl bg-white p-3 text-slate-700 shadow-xl ring-1 ring-black/5">
                    <p class="truncate text-sm font-medium text-[#004479]">{{ auth()->user()->name }} {{ auth()->user()->last_name }}</p>
                    <p class="mb-3 text-xs text-slate-500">{{ auth()->user()->is_admin ? 'Administrador' : 'Agente' }}</p>
                    <form method="POST" action="{{ route('logout') }}">@csrf<button class="w-full rounded-lg bg-slate-100 px-3 py-2 text-left text-xs font-medium text-[#004479]">Cerrar sesión</button></form>
                </div>
            </details>
        </header>
        <x-flash />
        @yield('content')
    </main>
</div>
@stack('scripts')
</body>
</html>
