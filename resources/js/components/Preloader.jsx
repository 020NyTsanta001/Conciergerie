import { useEffect, useRef, useState } from 'react';

const MIN_MS = 700;   // durée minimale d'affichage (évite un flash quand tout est déjà en cache)
const MAX_MS = 6000;  // sécurité : on entre dans le site quoi qu'il arrive
// poids de chaque tâche dans la barre (la photo du hero est la plus lourde)
const WEIGHTS = { logos: 2, hero: 5, fonts: 2 };
const TOTAL = Object.values(WEIGHTS).reduce((a, b) => a + b, 0);

// Charge ET décode une image : elle s'affichera ensuite sans saccade
const loadImage = (src) =>
    new Promise((resolve) => {
        const img = new Image();
        img.src = src;
        if (img.decode) img.decode().then(resolve, resolve);
        else { img.onload = resolve; img.onerror = resolve; }
    });

export default function Preloader() {
    const [done, setDone] = useState({});
    const [shown, setShown] = useState(0);
    const [phase, setPhase] = useState('loading'); // loading → leaving → gone
    const start = useRef(performance.now());
    const target = useRef(0);

    const finish = (key) => setDone((d) => (d[key] ? d : { ...d, [key]: true }));

    // 1) Ressources du design (pas les villas : elles dépendent d'internet)
    useEffect(() => {
        const isHome = window.location.pathname === '/';

        Promise.all([loadImage('/images/logo-black.svg'), loadImage('/images/logo-white.svg')]).then(() => finish('logos'));
        (isHome ? loadImage('/images/hero-villa.jpg') : Promise.resolve()).then(() => finish('hero'));
        (document.fonts
            ? Promise.all([document.fonts.load('16px "Manrope Variable"'), document.fonts.ready])
            : Promise.resolve()
        ).catch(() => {}).then(() => finish('fonts'));
    }, []);

    // 2) Pourcentage réel visé
    target.current = (Object.entries(WEIGHTS).reduce((s, [k, w]) => s + (done[k] ? w : 0), 0) / TOTAL) * 100;

    // 3) Barre fluide : le pourcentage affiché rattrape progressivement le pourcentage réel
    useEffect(() => {
        let raf;
        const tick = () => {
            setShown((s) => Math.min(target.current, s + (target.current - s) * 0.12 + (target.current > s ? 0.15 : 0)));
            raf = requestAnimationFrame(tick);
        };
        raf = requestAnimationFrame(tick);
        return () => cancelAnimationFrame(raf);
    }, []);

    // 4) Sortie : 100 % atteint + durée minimale écoulée
    useEffect(() => {
        if (phase !== 'loading' || shown < 99.9) return;
        const wait = Math.max(0, MIN_MS - (performance.now() - start.current)) + 150;
        const t = setTimeout(() => setPhase('leaving'), wait);
        return () => clearTimeout(t);
    }, [shown, phase]);

    // Délai de sécurité
    useEffect(() => {
        const t = setTimeout(() => setPhase((p) => (p === 'loading' ? 'leaving' : p)), MAX_MS);
        return () => clearTimeout(t);
    }, []);

    useEffect(() => {
        if (phase !== 'leaving') return;
        const t = setTimeout(() => setPhase('gone'), 600); // = durée du fondu
        return () => clearTimeout(t);
    }, [phase]);

    // Pas de défilement derrière l'écran de chargement
    useEffect(() => {
        if (phase === 'gone') return;
        document.documentElement.style.overflow = 'hidden';
        return () => { document.documentElement.style.overflow = ''; };
    }, [phase]);

    if (phase === 'gone') return null;

    return (
        <div
            className={`fixed inset-0 z-[100] flex flex-col items-center justify-center gap-5 bg-white transition-opacity duration-500 ${
                phase === 'leaving' ? 'pointer-events-none opacity-0' : 'opacity-100'
            }`}
        >
            <img src="/images/logo-black.svg" alt="Hoxen" className="h-14 w-auto md:h-16" />
            <div className="w-1/3 md:w-2/5">
                <div className="h-[3px] w-full overflow-hidden rounded-full bg-neutral-200">
                    <div className="h-full rounded-full bg-black" style={{ width: `${shown}%` }} />
                </div>
                <p className="mt-2 text-center text-[10px] uppercase tracking-[0.25em] text-neutral-500">
                    Chargement… {Math.round(shown)} %
                </p>
            </div>
        </div>
    );
}