import { createRoot } from 'react-dom/client';
import { BrowserRouter } from 'react-router';
import App from './app/App';
import { AuthProvider } from './lib/auth';
import { Toaster } from './app/components/ui/sonner';

createRoot(document.getElementById('root')!).render(
    <BrowserRouter>
        <AuthProvider>
            <App />
            <Toaster richColors position="top-right" />
        </AuthProvider>
    </BrowserRouter>,
);
