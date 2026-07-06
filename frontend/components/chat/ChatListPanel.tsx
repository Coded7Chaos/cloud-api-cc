import { Avatar } from "@/components/common/Avatar";
import { Icon } from "@/components/common/Icon";
import { chats, groups } from "@/lib/data";

export function ChatListPanel() {
  return (
    <aside className="flex h-full min-h-0 w-full shrink-0 flex-col border-r border-slate-200 bg-white md:w-80">
      <div className="shrink-0 border-b border-slate-100 p-4">
        <label className="relative block">
          <Icon
            className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"
            name="search"
          />
          <input
            className="h-11 w-full rounded-xl border border-slate-200 bg-slate-50 px-10 text-sm text-slate-800 outline-none placeholder:text-slate-400 focus:border-[#0B4C8C] focus:bg-white"
            placeholder="Buscar contacto"
            type="search"
          />
        </label>
      </div>

      <div className="min-h-0 flex-1 overflow-y-auto">
        <div className="space-y-1 p-3">
          {chats.map((chat) => (
            <button
              className={`flex w-full items-center gap-3 rounded-2xl p-3 text-left transition-colors ${
                chat.active ? "bg-[#EAF3FC]" : "hover:bg-slate-50"
              }`}
              key={chat.name}
              type="button"
            >
              <div className="relative">
                <Avatar initials={chat.initials} />
                <span
                  className={`absolute bottom-0 right-0 h-3 w-3 rounded-full border-2 border-white ${
                    chat.status === "online" ? "bg-emerald-500" : "bg-amber-400"
                  }`}
                />
              </div>
              <div className="min-w-0 flex-1">
                <p className="truncate text-sm font-semibold text-slate-900">
                  {chat.name}
                </p>
                <p className="truncate text-xs text-slate-500">
                  {chat.message}
                </p>
              </div>
              {chat.unread ? (
                <span className="flex h-6 min-w-6 items-center justify-center rounded-full bg-[#F4C430] px-2 text-xs font-bold text-[#0B4C8C]">
                  {chat.unread}
                </span>
              ) : null}
            </button>
          ))}
        </div>
      </div>

      <div className="shrink-0 border-t border-slate-100 p-4">
        <div className="grid grid-cols-2 rounded-xl bg-slate-100 p-1 text-sm font-semibold text-slate-500">
          <button
            className="rounded-lg bg-white px-3 py-2 text-[#0B4C8C] shadow-sm"
            type="button"
          >
            Reunion
          </button>
          <button
            className="rounded-lg px-3 py-2 hover:text-slate-800"
            type="button"
          >
            Agenda
          </button>
        </div>

        <details className="mt-4" open>
          <summary className="cursor-pointer list-none text-sm font-bold text-slate-800">
            Grupos
          </summary>
          <div className="mt-3 space-y-2">
            {groups.map((group) => (
              <button
                className="flex w-full items-center gap-3 rounded-xl px-2 py-2 text-left transition-colors hover:bg-slate-50"
                key={group.name}
                type="button"
              >
                <span
                  className={`${group.className} flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-xs font-bold`}
                >
                  {group.initials}
                </span>
                <span className="truncate text-sm font-medium text-slate-700">
                  {group.name}
                </span>
              </button>
            ))}
          </div>
        </details>
      </div>
    </aside>
  );
}
