import { Outlet } from 'react-router';
import { TopBar } from './TopBar';
import { Sidebar } from './Sidebar';
import { BottomNav } from './BottomNav';

export function AppLayout() {
    return (
        <div className="h-screen w-full flex flex-col bg-[#eef1f6]">
            <TopBar />

            <div className="flex-1 flex overflow-hidden">
                <Sidebar />

                <main className="flex-1 p-3 md:p-4 pb-20 md:pb-4 overflow-hidden">
                    <Outlet />
                </main>
            </div>

            <BottomNav />
        </div>
    );
}
