import { Avatar } from "@/components/common/Avatar";
import { Icon } from "@/components/common/Icon";

export function TopNavbar() {
  return (
    <header className="flex h-16 shrink-0 items-center gap-4 bg-[#0B4C8C] px-4 text-white lg:px-6">
      <div className="flex h-10 w-20 shrink-0 items-center justify-center rounded-md bg-[#F4C430] text-sm font-black text-[#0B4C8C]">
        LOGO
      </div>

      <label className="relative hidden max-w-2xl flex-1 md:block">
        <Icon
          className="absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-white/65"
          name="search"
        />
        <input
          className="h-10 w-full rounded-full border border-white/15 bg-white/12 px-11 text-sm text-white outline-none placeholder:text-white/65 focus:border-white/55 focus:bg-white/18"
          placeholder="Buscar usuarios, eventos y mas..."
          type="search"
        />
      </label>

      <div className="ml-auto flex items-center gap-3">
        <button
          aria-label="Notificaciones"
          className="relative flex h-10 w-10 items-center justify-center rounded-full bg-white/10 transition-colors hover:bg-white/20"
          type="button"
        >
          <Icon name="bell" />
          <span className="absolute right-2 top-2 h-2.5 w-2.5 rounded-full bg-[#F4C430] ring-2 ring-[#0B4C8C]" />
        </button>

        <div className="flex min-w-0 items-center gap-3">
          <Avatar initials="DP" size="h-10 w-10" />
          <div className="hidden leading-tight sm:block">
            <p className="truncate text-sm font-semibold">Dipoli Patra</p>
            <p className="text-xs text-emerald-200">Online</p>
          </div>
        </div>
      </div>
    </header>
  );
}
