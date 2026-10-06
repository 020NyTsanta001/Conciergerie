import { useEffect, useRef, useState } from 'react';
import { NavLink, useLocation } from 'react-router-dom';

export default function MobileNav({ user, transparent }) {
    const { pathname } = useLocation();
    const [open, setOpen] = useState(false);
    const ref = useRef(null);

    useEffect(() => { setOpen(false); }, [pathname]);

    useEffect(() => {
        const onDown = (e) => { if (ref.current && !ref.current.contains(e.target)) setOpen(false); };
        const onKey = (e) => { if (e.key === 'Escape') setOpen(false); };
        document.addEventListener('mousedown', onDown);
        document.addEventListener('keydown', onKey);
        return () => {
            document.removeEventListener('mousedown', onDown);
            document.removeEventListener('keydown', onKey);
        };
    }, []);

    const item = ({ isActive }) =>
        `block px-5 py-3 text-sm hover:bg-neutral-50 ${isActive ? 'font-semibold text-neutral-900' : 'text-neutral-600'}`;

    return (
        <div ref={ref} className="relative md:hidden">
            <button
                onClick={() => setOpen(!open)}
                aria-label="Menu de navigation"
                aria-expanded={open}
                className={`flex h-9 w-9 items-center justify-center rounded-full border transition-colors ${
                    transparent
                        ? 'border-white/70 text-white hover:bg-white/15'
                        : 'border-neutral-300 text-neutral-900 hover:bg-neutral-50'
                }`}
            >
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round">
                    {open ? <path d="M3 3l10 10M13 3L3 13" /> : <path d="M2 4h12M2 8h12M2 12h12" />}
                </svg>
            </button>

            {open && (
                <div role="menu" className="absolute right-0 mt-2 w-56 overflow-hidden rounded-2xl border border-neutral-200 bg-white shadow-xl">
                    <NavLink to="/villas" className={item}>Propriétés</NavLink>
                    {user && <NavLink to="/bookings" className={item}>Réservations</NavLink>}
                    {user && ['host', 'admin'].includes(user.role) && <NavLink to="/host" className={item}>Mes villas</NavLink>}
                    {user?.role === 'admin' && <NavLink to="/admin" className={item}>Modération</NavLink>}
                </div>
            )}
        </div>
    );
}