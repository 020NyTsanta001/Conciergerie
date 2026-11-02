import { useEffect, useState } from 'react';

const NEAR_END_PX = 400;  // le bouton apparaît quand il reste moins de 400 px avant le bas de la page
const MS_PER_SCREEN = 350; // vitesse du retour : durée par écran parcouru
const MIN_MS = 1200;
const MAX_MS = 4000;

export default function ScrollTopButton() {
    const [show, setShow] = useState(false);

    useEffect(() => {
        let raf = 0;
        const check = () => {
            raf = 0;
            const el = document.documentElement;
            setShow(window.scrollY > window.innerHeight && el.scrollHeight - (window.scrollY + window.innerHeight) < NEAR_END_PX);
        };
        const onScroll = () => { if (!raf) raf = requestAnimationFrame(check); };
        check();
        window.addEventListener('scroll', onScroll, { passive: true });
        window.addEventListener('resize', onScroll);
        return () => {
            cancelAnimationFrame(raf);
            window.removeEventListener('scroll', onScroll);
            window.removeEventListener('resize', onScroll);
        };
    }, []);

    // Retour animé (ease-in-out). Si le visiteur reprend la main (molette / doigt), on s'arrête.
    const goTop = () => {
        const start = window.scrollY;
        const duration = Math.min(MAX_MS, Math.max(MIN_MS, (start / window.innerHeight) * MS_PER_SCREEN));
        const t0 = performance.now();
        const ease = (t) => -(Math.cos(Math.PI * t) - 1) / 2;
        let cancelled = false;
        const cancel = () => { cancelled = true; };
        window.addEventListener('wheel', cancel, { passive: true, once: true });
        window.addEventListener('touchstart', cancel, { passive: true, once: true });
        const tick = (now) => {
            if (cancelled) return;
            const t = Math.min(1, (now - t0) / duration);
            window.scrollTo(0, start * (1 - ease(t)));
            if (t < 1) requestAnimationFrame(tick);
            else {
                window.removeEventListener('wheel', cancel);
                window.removeEventListener('touchstart', cancel);
            }
        };
        requestAnimationFrame(tick);
    };

    return (
        <button
            type="button"
            onClick={goTop}
            aria-label="Revenir en haut de la page"
            tabIndex={show ? 0 : -1}
            className={`btn btn-light fixed bottom-5 right-5 z-40 flex h-11 w-11 items-center justify-center rounded-full border border-neutral-300 bg-white/90 text-neutral-900 shadow-md backdrop-blur transition-all duration-500 md:bottom-8 md:right-8 ${
                show ? 'translate-y-0 opacity-100' : 'pointer-events-none translate-y-4 opacity-0'
            }`}
        >
            <svg viewBox="0 0 24 24" className="h-5 w-5" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
                <path d="M12 19V5M5 12l7-7 7 7" />
            </svg>
        </button>
    );
}
