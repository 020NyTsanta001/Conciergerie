import { useEffect, useRef, useState } from 'react';

const MIN_MS = 700;   // durée minimale d'affichage (évite un flash quand tout est déjà en cache)
const MAX_MS = 6000;  // sécurité : on entre dans le site quoi qu'il arrive
const WEIGHTS = { logos: 2, hero: 5, fonts: 2 };
const TOTAL = Object.values(WEIGHTS).reduce((a, b) => a + b, 0);

const HERO = '/images/hero-villa.webp';


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
    const [run, setRun] = useState(0);             // +1 à chaque relance (ex. après une connexion)
    const start = useRef(performance.now());
    const target = useRef(0);
    const destination = useRef(window.location.pathname); // page vers laquelle on charge

    const finish = (key) => setDone((d) => (d[key] ? d : { ...d, [key]: true }));

    // Relance à la demande :
    //   window.dispatchEvent(new CustomEvent('preloader:show', { detail: { to: '/' } }))
    useEffect(() => {
        const onShow = (e) => {
            destination.current = e.detail?.to ?? window.location.pathname;
            start.current = performance.now();
            target.current = 0;
            setDone({});
            setShown(0);
            setPhase('loading');
            setRun((r) => r + 1);
        };
        window.addEventListener('preloader:show', onShow);
        return () => window.removeEventListener('preloader:show', onShow);
    }, []);

    // 1) Ressources du design de la page de destination (pas les villas : elles dépendent d'internet)
    useEffect(() => {
        const path = destination.current;
        const pageImage = path === '/' ? HERO : null;

        Promise.all([loadImage('/images/logo-black.svg'), loadImage('/images/logo-white.svg')]).then(() => finish('logos'));
        (pageImage ? loadImage(pageImage) : Promise.resolve()).then(() => finish('hero'));
        (document.fonts
            ? Promise.all([document.fonts.load('16px "Manrope Variable"'), document.fonts.ready])
            : Promise.resolve()
        ).catch(() => {}).then(() => finish('fonts'));

    }, [run]);

    // 2) Pourcentage réel visé
    target.current = (Object.entries(WEIGHTS).reduce((s, [k, w]) => s + (done[k] ? w : 0), 0) / TOTAL) * 100;

    // 3) Barre fluide : le pourcentage affiché rattrape progressivement le pourcentage réel
    const active = phase !== 'gone';
    useEffect(() => {
        if (!active) return;
        let raf;
        const tick = () => {
            setShown((s) => Math.min(target.current, s + (target.current - s) * 0.12 + (target.current > s ? 0.15 : 0)));
            raf = requestAnimationFrame(tick);
        };
        raf = requestAnimationFrame(tick);
        return () => cancelAnimationFrame(raf);
    }, [active]);

    // 4) Sortie : 100 % atteint + durée minimale écoulée
    useEffect(() => {
        if (phase !== 'loading' || shown < 99.9) return;
        const wait = Math.max(0, MIN_MS - (performance.now() - start.current)) + 150;
        const t = setTimeout(() => setPhase('leaving'), wait);
        return () => clearTimeout(t);
    }, [shown, phase]);

    // Délai de sécurité (relancé à chaque run)
    useEffect(() => {
        const t = setTimeout(() => setPhase((p) => (p === 'loading' ? 'leaving' : p)), MAX_MS);
        return () => clearTimeout(t);
    }, [run]);

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