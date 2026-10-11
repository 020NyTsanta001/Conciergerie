import { useEffect, useRef, useState } from 'react';
import { Link, useLocation, useNavigate } from 'react-router-dom';
import { useAuth } from '../auth';
import { eur } from '../api';


export default function UserMenu({ transparent }) {
    const { user, logout } = useAuth();
    const nav = useNavigate();
    const { pathname } = useLocation();
    const [open, setOpen] = useState(false);
    const ref = useRef(null);

    const initial = user.name.trim().charAt(0).toUpperCase();

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
                aria-label="Menu du compte"
                aria-haspopup="menu"
                aria-expanded={open}
                className={`flex h-9 w-9 items-center justify-center rounded-full border font-semibold transition-colors md:h-auto md:w-auto md:gap-2 md:px-4 md:py-2 md:font-medium ${
                    transparent
                        ? 'border-white/70 text-white hover:bg-white/15'
                        : 'border-neutral-300 text-neutral-900 hover:bg-neutral-50'
                }`}
            >
                {/* mobile : initiale seule */}
                <span className="md:hidden">{initial}</span>
                {/* ordinateur : nom complet + flèche */}
                <span className="hidden max-w-[10rem] truncate md:inline">{user.name}</span>
                <span className={`hidden text-xs transition-transform md:inline ${open ? 'rotate-180' : ''}`}>▾</span>
            </button>

            {open && (
                <div role="menu" className="absolute right-0 mt-2 w-52 overflow-hidden rounded-2xl border border-neutral-200 bg-white text-neutral-900 shadow-xl">
                <div className="border-b border-neutral-100 px-5 py-3 text-xs text-neutral-500">
                    Solde : <b className="text-neutral-900">{eur(user.balance)}</b>
                </div>
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