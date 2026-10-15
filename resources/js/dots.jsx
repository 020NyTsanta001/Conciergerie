import { createContext, useCallback, useContext, useEffect, useState } from 'react';
import { api } from './api';
import { useAuth } from './auth';

/**
 * Points rouges de notification.
 * Le serveur renvoie le nombre de notifications non lues par zone : { bookings: 2, villas: 1, ... }
 *   admin  : moderation (Annonces) | commissions | messages
 *   host   : villas | bookings
 *   client : bookings
 */
const Ctx = createContext({ dots: {}, clear: () => {}, refreshDots: () => {} });
export const useDots = () => useContext(Ctx);

const POLL_MS = 30000; // le serveur est interrogé toutes les 30 secondes

export const adminCount = (d) => (d.moderation ?? 0) + (d.commissions ?? 0) + (d.messages ?? 0);

export function DotsProvider({ children }) {
    const { user } = useAuth();
    const userId = user?.id ?? null;
    const [dots, setDots] = useState({});

    const refreshDots = useCallback(() => {
        if (!userId) return setDots({});
        api('/notifications/dots').then((d) => setDots(d || {})).catch(() => {});
    }, [userId]);

    useEffect(() => {
        refreshDots();
        if (!userId) return undefined;
        const timer = setInterval(refreshDots, POLL_MS);
        window.addEventListener('focus', refreshDots);
        return () => {
            clearInterval(timer);
            window.removeEventListener('focus', refreshDots);
        };
    }, [refreshDots, userId]);

    // L'utilisateur a vu la zone : le point disparaît tout de suite, puis le serveur marque les notifications comme lues
    const clear = useCallback((area) => {
        setDots((d) => {
            if (!d[area]) return d;
            const { [area]: _seen, ...rest } = d;
            return rest;
        });
        if (userId) api('/notifications/read', { method: 'POST', body: { area } }).catch(() => {});
    }, [userId]);

    return <Ctx.Provider value={{ dots, clear, refreshDots }}>{children}</Ctx.Provider>;
}
