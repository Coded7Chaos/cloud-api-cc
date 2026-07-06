import { Avatar } from "@/components/common/Avatar";
import { Icon } from "@/components/common/Icon";
import { sharedFiles } from "@/lib/data";
import type { IconName } from "@/types/chat";

function ContactItem({
  icon,
  label,
  value,
}: {
  icon: IconName;
  label: string;
  value: string;
}) {
  return (
    <div className="flex items-center gap-3">
      <span className="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-100 text-[#0B4C8C]">
        <Icon name={icon} />
      </span>
      <div className="min-w-0">
        <p className="text-xs text-slate-400">{label}</p>
        <p className="truncate text-sm font-semibold text-slate-800">
          {value}
        </p>
      </div>
    </div>
  );
}

export function ProfilePanel() {
  return (
    <aside className="hidden h-full min-h-0 w-[20.5rem] shrink-0 flex-col overflow-y-auto border-l border-slate-200 bg-white xl:flex">
      <div className="p-6 text-center">
        <div className="mx-auto h-28 w-28 overflow-hidden rounded-full bg-gradient-to-br from-[#F4C430] via-[#18A0A8] to-[#0B4C8C] p-1">
          <div className="flex h-full w-full items-center justify-center rounded-full bg-white text-3xl font-black text-[#0B4C8C]">
            SS
          </div>
        </div>
        <h2 className="mt-4 text-xl font-bold text-slate-950">
          Sayali Sontakke
        </h2>
        <p className="mt-1 text-sm font-medium text-slate-500">
          Disenadora Web
        </p>
        <p className="mt-2 flex items-center justify-center gap-1 text-sm text-slate-400">
          <Icon className="h-4 w-4" name="pin" />
          La Paz, Bolivia
        </p>
        <div className="mt-5 flex justify-center gap-3">
          {["f", "ig", "in"].map((label) => (
            <button
              className="flex h-10 w-10 items-center justify-center rounded-full bg-[#EAF3FC] text-xs font-black text-[#0B4C8C] transition-colors hover:bg-[#0B4C8C] hover:text-white"
              key={label}
              type="button"
            >
              {label}
            </button>
          ))}
        </div>
      </div>

      <div className="border-t border-slate-100 px-6 py-5">
        <h3 className="text-sm font-bold text-slate-950">Contacto</h3>
        <div className="mt-4 space-y-3">
          <ContactItem icon="phone" label="Telefono" value="+591 7654 2210" />
          <ContactItem
            icon="mail"
            label="Correo electronico"
            value="sayali@ucb.edu.bo"
          />
        </div>
      </div>

      <div className="border-t border-slate-100 px-6 py-5">
        <h3 className="text-sm font-bold text-slate-950">
          Archivos compartidos
        </h3>
        <div className="mt-4 space-y-3">
          {sharedFiles.map((file) => (
            <button
              className="flex w-full items-center gap-3 rounded-xl p-2 text-left transition-colors hover:bg-slate-50"
              key={file.name}
              type="button"
            >
              <span
                className={`flex h-11 w-11 shrink-0 items-center justify-center rounded-xl ${
                  file.type === "pdf"
                    ? "bg-red-50 text-red-500"
                    : "bg-blue-50 text-blue-500"
                }`}
              >
                <Icon name={file.type === "pdf" ? "file" : "image"} />
              </span>
              <span className="min-w-0 flex-1">
                <span className="block truncate text-sm font-semibold text-slate-800">
                  {file.name}
                </span>
                <span className="block text-xs text-slate-400">
                  {file.size}
                </span>
              </span>
            </button>
          ))}
        </div>
      </div>
    </aside>
  );
}
