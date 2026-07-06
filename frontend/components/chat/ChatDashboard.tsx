import { ChatListPanel } from "@/components/chat/ChatListPanel";
import { ChatWindow } from "@/components/chat/ChatWindow";
import { ProfilePanel } from "@/components/chat/ProfilePanel";
import { Sidebar } from "@/components/layout/Sidebar";
import { TopNavbar } from "@/components/layout/TopNavbar";

export function ChatDashboard() {
  return (
    <main className="h-screen overflow-hidden bg-[#F4F6F9] font-sans text-slate-900">
      <div className="flex h-full flex-col">
        <TopNavbar />
        <div className="flex min-h-0 flex-1">
          <Sidebar />
          <div className="grid min-w-0 flex-1 grid-cols-1 overflow-hidden lg:grid-cols-[20rem_minmax(0,1fr)] xl:grid-cols-[20rem_minmax(0,1fr)_20.5rem]">
            <ChatListPanel />
            <ChatWindow />
            <ProfilePanel />
          </div>
        </div>
      </div>
    </main>
  );
}
