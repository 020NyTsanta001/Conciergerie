import { useEffect, useRef, useState } from 'react';
import { Link, useLocation, useNavigate } from 'react-router-dom';
import { useAuth } from '../auth';

export default function UserMenu({ transparent }) {
    const { user, logout } = useAuth();
    const nav = useNavigate();
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

    return (
        <div ref={ref} className="relative">
            <button
                onClick={() => setOpen(!open)}
                aria-haspopup="menu"
                aria-expanded={open}
                className={`flex items-center gap-2 rounded-full border px-4 py-2 transition-colors ${
                    transparent
                        ? 'border-white/70 text-white hover:bg-white/15'
                        : 'border-neutral-300 text-neutral-900 hover:bg-neutral-50'
                }`}
            >
                <span className="max-w-[10rem] truncate">{user.name}</span>
                <span className={`text-xs transition-transform ${open ? 'rotate-180' : ''}`}>▾</span>
            </button>

            {open && (
                <div role="menu" className="absolute right-0 mt-2 w-52 overflow-hidden rounded-2xl border border-neutral-200 bg-white text-neutral-900 shadow-xl">
                    {user.role !== 'admin' && (
                        <Link to="/account" role="menuitem" className="block px-5 py-3 text-sm hover:bg-neutral-50">Mon compte</Link>
                    )}
                    <button
                        role="menuitem"
                        onClick={async () => { setOpen(false); await logout(); nav('/'); }}
                        className="block w-full border-t border-neutral-100 px-5 py-3 text-left text-sm hover:bg-neutral-50 first:border-t-0"
                    >
                        Déconnexion
                    </button>
                </div>
            )}
        </div>
    );
}