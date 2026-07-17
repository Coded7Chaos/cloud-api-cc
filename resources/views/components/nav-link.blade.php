@props(['href', 'active' => false, 'icon' => 'home'])
<a href="{{ $href }}" @class(['group flex min-w-[3.65rem] flex-1 shrink-0 flex-col items-center justify-center gap-0.5 rounded-xl px-2 py-1.5 text-center text-[10px] transition md:min-w-0 md:flex-row md:justify-start md:gap-3 md:px-3 md:py-2.5 md:text-left md:text-sm', 'bg-white text-[#004479] font-medium shadow-sm' => $active, 'text-white/75 hover:bg-white/10 hover:text-white' => !$active])>
    <span class="grid size-6 place-items-center md:size-5">
        @switch($icon)
            @case('chat')<svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4z"/></svg>@break
            @case('users')<svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>@break
            @case('calendar')<svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 11h18"/></svg>@break
            @case('task')<svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="4" y="4" width="16" height="17" rx="2"/><path d="M8 2v4M16 2v4M8 11h8M8 15h6"/></svg>@break
            @case('tracking')<svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 19V9M10 19V5M16 19v-7M22 19V3"/></svg>@break
            @case('history')<svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5M12 7v5l3 2"/></svg>@break
            @default<svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m3 11 9-8 9 8v10h-6v-6H9v6H3z"/></svg>
        @endswitch
    </span>
    <span class="leading-none md:leading-normal">{{ $slot }}</span>
</a>
