import { navItems } from "@/lib/data";
import { Icon } from "@/components/common/Icon";

export function Sidebar() {
  return (
    <aside className="hidden w-20 shrink-0 flex-col items-center gap-4 bg-[#083E73] py-5 text-white md:flex">
      {navItems.map((item) => (
        <button
          aria-label={item.label}
          className={`flex h-12 w-12 items-center justify-center rounded-2xl transition-colors ${
            item.active
              ? "bg-[#F4C430] text-[#0B4C8C]"
              : "text-white/75 hover:bg-white/10 hover:text-white"
          }`}
          key={item.label}
          title={item.label}
          type="button"
        >
          <Icon name={item.icon} />
        </button>
      ))}
    </aside>
  );
}
