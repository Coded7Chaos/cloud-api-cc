import { createContext, useContext, useEffect, useState, type ReactNode } from 'react';
import { api } from './api';

export type AuthUser = {
    id: number;
    name: string;
    last_name: string;
    email: string;
    role: { id: number; name: string } | null;
};

type AuthContextValue = {
    user: AuthUser | null;
    loading: boolean;
    login: (email: string, password: string, remember?: boolean) => Promise<void>;
    logout: () => Promise<void>;
};

const AuthContext = createContext<AuthContextValue>(null!);

export function AuthProvider({ children }: { children: ReactNode }) {
    const [user, setUser] = useState<AuthUser | null>(null);
    const [loading, setLoading] = useState(true);

    // Al cargar el SPA, preguntamos quién es el usuario de la sesión actual.
    useEffect(() => {
        api
            .get('/user')
            .then((res) => setUser(res.data.user))
            .catch(() => setUser(null))
            .finally(() => setLoading(false));
    }, []);

    const login = async (email: string, password: string, remember = false) => {
        const res = await api.post('/login', { email, password, remember });
        setUser(res.data.user);
    };

    const logout = async () => {
        await api.post('/logout');
        setUser(null);
    };

    return <AuthContext.Provider value={{ user, loading, login, logout }}>{children}</AuthContext.Provider>;
}

// eslint-disable-next-line react-refresh/only-export-components
export const useAuth = () => useContext(AuthContext);
