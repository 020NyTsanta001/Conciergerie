import { useEffect, useState } from 'react';
import { Link, NavLink, Outlet, useLocation } from 'react-router-dom';
import { useAuth } from '../auth';
import UserMenu from './UserMenu';

export default function Layout() {
    const { user } = useAuth();
    const { pathname } = useLocation();
    const isHome = pathname === '/';
    const [scrolled, setScrolled] = useState(false);

    // Sur l'accueil : le header devient « normal » quand on a dépassé le hero
    useEffect(() => {
        if (!isHome) {
            setScrolled(false);
            return;
        }
        const onScroll = () => {
            const hero = document.getElementById('hero');
            const limit = hero ? hero.offsetHeight - 64 : 0; // 64 = hauteur du header (h-16)
            setScrolled(window.scrollY > limit);
        };
        onScroll();
        window.addEventListener('scroll', onScroll, { passive: true });
        window.addEventListener('resize', onScroll);
        return () => {
            window.removeEventListener('scroll', onScroll);
            window.removeEventListener('resize', onScroll);
        };
    }, [isHome]);

    const transparent = isHome && !scrolled;

    const link = ({ isActive }) =>
        transparent
            ? isActive ? 'text-white' : 'text-white/75 hover:text-white'
            : isActive ? 'text-neutral-900' : 'text-neutral-500 hover:text-neutral-900';

    return (
        <div className="min-h-screen flex flex-col">
            <header
                className={`sticky top-0 z-30 border-b transition-colors duration-300 ${
                    transparent
                        ? 'bg-transparent border-transparent'
                        : 'backdrop-blur bg-white/80 border-neutral-100'
                }`}
            >
                <div className="mx-auto max-w-7xl px-5 h-16 flex items-center justify-between text-sm font-medium">

                    <Link to="/" className="relative flex items-center h-20 w-48">
                        {/* 1. LOGO BLANC (affiché quand le header est transparent) */}
                        <img
                            src="/images/logo-white.svg"
                            alt="Hoxen"
                            className={`absolute inset-0 h-full w-auto object-contain transition-opacity duration-300 ease-in-out ${
                                transparent ? 'opacity-100 pointer-events-auto' : 'opacity-0 pointer-events-none'
                            }`}
                            loading="eager"
                            fetchPriority="high"
                        />

                        {/* 2. LOGO NOIR (affiché au scroll et sur les autres pages) */}
                        <img
                            src="/images/logo-black.svg"
                            alt="Hoxen"
                            className={`absolute inset-0 h-full w-auto object-contain transition-opacity duration-300 ease-in-out ${
                                transparent ? 'opacity-0 pointer-events-none' : 'opacity-100 pointer-events-auto'
                            }`}
                            loading="eager"
                            fetchPriority="high"
                        />
                    </Link>

                    <nav className="hidden md:flex gap-8">
                        <NavLink to="/villas" className={link}>Propriétés</NavLink>
                        {user && <NavLink to="/bookings" className={link}>Réservations</NavLink>}
                        {user && ['host', 'admin'].includes(user.role) && <NavLink to="/host" className={link}>Mes villas</NavLink>}
                        {user?.role === 'admin' && <NavLink to="/admin" className={link}>Modération</NavLink>}
                    </nav>

                    {user ? (
                        <UserMenu transparent={transparent} />
                    ) : (
                        <Link
                            to="/login"
                            className={`rounded-full px-5 py-2.5 transition-colors ${
                                transparent ? 'bg-white text-black hover:bg-white/90' : 'bg-black text-white'
                            }`}
                        >
                            Connexion
                        </Link>
                    )}
                </div>
            </header>
            <main className="flex-1"><Outlet /></main>
            <footer className="border-t border-neutral-100 py-8 text-center text-xs text-neutral-500">
                © {new Date().getFullYear()} Maison Élite — Conciergerie immobilière & expériences de luxe
            </footer>
        </div>
    );
}