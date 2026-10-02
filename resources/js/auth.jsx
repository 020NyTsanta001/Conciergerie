import { createContext, useContext, useEffect, useState } from 'react';
import { api } from './api';

const Ctx = createContext(null);
export const useAuth = () => useContext(Ctx);

export function AuthProvider({ children }) {
    const [user, setUser] = useState(null);
    const [ready, setReady] = useState(false);

    useEffect(() => {
        if (!localStorage.getItem('token')) return setReady(true);
        api('/me').then(setUser).catch(() => localStorage.removeItem('token')).finally(() => setReady(true));
    }, []);

    const authenticate = async (path, body) => {
        const r = await api(path, { method: 'POST', body });
        localStorage.setItem('token', r.token);
        setUser(r.user);
        return r.user;
    };

    const logout = async () => {
        try { await api('/logout', { method: 'POST' }); } catch { /* token déjà invalide */ }
        localStorage.removeItem('token');
        setUser(null);
    };

    return (
        <Ctx.Provider value={{ user, setUser, ready, login: (b) => authenticate('/login', b), register: (b) => authenticate('/register', b), logout }}>
            {children}
        </Ctx.Provider>
    );
}
