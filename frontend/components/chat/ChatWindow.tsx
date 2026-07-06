import { Avatar } from "@/components/common/Avatar";
import { Icon } from "@/components/common/Icon";
import { messages } from "@/lib/data";
import type { IconName, Message } from "@/types/chat";

function ChatHeader() {
  return (
    <div className="flex h-20 shrink-0 items-center gap-3 border-b border-slate-200 bg-white px-4 lg:px-6">
      <Avatar initials="SS" size="h-12 w-12" />
      <div className="min-w-0 flex-1">
        <h1 className="truncate text-lg font-bold text-slate-950">
          Sayali Sontakke
        </h1>
        <p className="flex items-center gap-2 text-sm text-slate-500">
          <span className="h-2 w-2 rounded-full bg-emerald-500" />
          Online ahora
        </p>
      </div>
      <div className="flex items-center gap-2 text-slate-600">
        {(["phone", "camera", "more"] as IconName[]).map((icon) => (
          <button
            aria-label={icon}
            className="flex h-10 w-10 items-center justify-center rounded-full border border-slate-200 bg-white transition-colors hover:border-[#0B4C8C] hover:text-[#0B4C8C]"
            key={icon}
            type="button"
          >
            <Icon name={icon} />
          </button>
        ))}
      </div>
    </div>
  );
}

function MessageBubble({ message }: { message: Message }) {
  const sent = message.variant === "sent";

  return (
    <div className={`flex ${sent ? "justify-end" : "justify-start"}`}>
      <div
        className={`max-w-[82%] rounded-2xl px-4 py-3 shadow-sm sm:max-w-[68%] ${
          sent
            ? "rounded-br-md bg-[#0B4C8C] text-white"
            : "rounded-bl-md bg-white text-slate-800"
        }`}
      >
        <p className="text-sm leading-6">{message.text}</p>
        <p
          className={`mt-1 text-right text-xs ${
            sent ? "text-white/70" : "text-slate-400"
          }`}
        >
          {message.time}
        </p>
      </div>
    </div>
  );
}

export function ChatWindow() {
  return (
    <section className="flex min-h-0 flex-1 flex-col bg-[#F4F6F9]">
      <ChatHeader />

      <div className="min-h-0 flex-1 overflow-y-auto px-4 py-6 lg:px-8">
        <div className="mx-auto flex max-w-3xl flex-col gap-4">
          <div className="self-center rounded-full bg-white px-4 py-1 text-xs font-semibold text-slate-400 shadow-sm">
            Hoy
          </div>
          {messages.map((message) => (
            <MessageBubble
              key={`${message.time}-${message.text}`}
              message={message}
            />
          ))}
        </div>
      </div>

      <div className="shrink-0 border-t border-slate-200 bg-white p-4 lg:px-6">
        <div className="flex items-center gap-2 rounded-2xl border border-slate-200 bg-slate-50 px-3 py-2">
          <button
            className="flex h-10 w-10 items-center justify-center rounded-full text-slate-500 hover:bg-white hover:text-[#0B4C8C]"
            type="button"
          >
            <Icon name="smile" />
          </button>
          <input
            className="h-10 min-w-0 flex-1 bg-transparent text-sm text-slate-900 outline-none placeholder:text-slate-400"
            placeholder="Escribe un mensaje..."
            type="text"
          />
          <button
            className="flex h-10 w-10 items-center justify-center rounded-full text-slate-500 hover:bg-white hover:text-[#0B4C8C]"
            type="button"
          >
            <Icon name="clip" />
          </button>
          <button
            aria-label="Enviar mensaje"
            className="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-[#F4C430] text-[#0B4C8C] shadow-sm transition-colors hover:bg-[#E7B722]"
            type="button"
          >
            <Icon name="send" />
          </button>
        </div>
      </div>
    </section>
  );
}
