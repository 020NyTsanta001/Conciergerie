import { useEffect, useLayoutEffect, useRef, useState } from 'react';
import { useLocation } from 'react-router-dom';
import {
    coverSrc, heroFramesReady, heroReadyProgress, startHeroFrames, subscribeHeroFrames, whenHeroFramesReady,
} from './heroFrames';

const MIN_MS = 500;   // durée minimale d'affichage (évite un flash quand tout est déjà en cache)
const LEAVE_MS = 900; // durée du fondu de sortie : le site se révèle progressivement, sans coupure

// Le hero-page apparaît en fondu derrière le chargement :
//   0 % de visibilité jusqu'à HERO_FROM %, puis montée progressive jusqu'à HERO_MAX à HERO_TO %.
// À la sortie, le fond blanc, le logo et la barre s'effacent : l'image continue de se révéler jusqu'à 100 %.
const HERO_FROM = 60;
const HERO_TO = 99;
const HERO_MAX = 0.25;
// Aucun plafond de durée : le preloader attend que les 72 images soient chargées et décodées,
// aussi lente que soit la connexion. Les erreurs sont gérées dans heroFrames.js (retentatives).
const WEIGHTS = { logos: 1, fonts: 1, cover: 1, hero: 8 };
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
    const [run, setRun] = useState(0);             // +1 à chaque relance (ex. connexion → accueil)
    const [offline, setOffline] = useState(typeof navigator !== 'undefined' && navigator.onLine === false);
    const start = useRef(performance.now());
    const target = useRef(0);
    const destination = useRef(window.location.pathname); // page vers laquelle on charge
    const phaseRef = useRef(phase);
    phaseRef.current = phase;
    const { pathname } = useLocation();

    const finish = (key, value = true) => {
        setDone((d) => (d[key] === value ? d : { ...d, [key]: value }));
    };

    // Message si la connexion est coupée (le chargement reprend tout seul au retour du réseau)
    useEffect(() => {
        const on = () => setOffline(false);
        const off = () => setOffline(true);
        window.addEventListener('online', on);
        window.addEventListener('offline', off);
        return () => { window.removeEventListener('online', on); window.removeEventListener('offline', off); };
    }, []);

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

    // Arrivée sur l'accueil alors que les images ne sont pas toutes prêtes (ex. on a démarré sur /login) :
    // on réaffiche le preloader AVANT le premier affichage (layout effect) pour éviter tout flash de hero vide.
    // Si elles sont déjà prêtes (cas courant : elles chargent pendant qu'on est sur /login), rien ne s'affiche.
    useLayoutEffect(() => {
        if (pathname === '/' && phaseRef.current === 'gone' && !heroFramesReady()) {
            window.dispatchEvent(new CustomEvent('preloader:show', { detail: { to: '/' } }));
        }
    }, [pathname]);

    // Ressources de la page de destination
    useEffect(() => {
        const needsHero = destination.current === '/';
        let unsub = () => {};
        if (needsHero) loadImage(coverSrc()).then(() => finish('cover')); // demandé avant les 72 images
        startHeroFrames(); // toujours : sur /login, les images du hero chargent déjà en arrière-plan

        Promise.all([loadImage('/images/logo-black.svg'), loadImage('/images/logo-white.svg')]).then(() => finish('logos'));

        if (needsHero) {
            finish('hero', heroReadyProgress());
            unsub = subscribeHeroFrames(() => finish('hero', heroReadyProgress()));
            whenHeroFramesReady().then(() => finish('hero', true));  // les 72 images chargées et décodées
        } else {
            finish('cover');
            finish('hero');
        }

        (document.fonts
            ? Promise.all([document.fonts.load('16px "Manrope Variable"'), document.fonts.ready])
            : Promise.resolve()
        ).catch(() => {}).then(() => finish('fonts'));

        return unsub;
    }, [run]);

    // Pourcentage réel visé
    target.current = (Object.entries(WEIGHTS).reduce((s, [k, w]) => s + w * (done[k] === true ? 1 : done[k] || 0), 0) / TOTAL) * 100;

    // Barre fluide : le pourcentage affiché rattrape vite le pourcentage réel
    const active = phase !== 'gone';
    useEffect(() => {
        if (!active) return;
        let raf;
        const tick = () => {
            setShown((s) => Math.min(target.current, s + (target.current - s) * 0.25 + (target.current > s ? 0.2 : 0)));
            raf = requestAnimationFrame(tick);
        };
        raf = requestAnimationFrame(tick);
        return () => cancelAnimationFrame(raf);
    }, [active]);

    // Sortie : 100 % atteint (toutes les images prêtes) + durée minimale écoulée
    useEffect(() => {
        if (phase !== 'loading' || shown < 99.5) return;
        const wait = Math.max(0, MIN_MS - (performance.now() - start.current));
        const t = setTimeout(() => setPhase('leaving'), wait);
        return () => clearTimeout(t);
    }, [shown, phase]);

    useEffect(() => {
        if (phase !== 'leaving') return;
        const t = setTimeout(() => setPhase('gone'), LEAVE_MS); // = durée du fondu
        return () => clearTimeout(t);
    }, [phase]);

    // Pas de défilement derrière l'écran de chargement
    useEffect(() => {
        if (phase === 'gone') return;
        document.documentElement.style.overflow = 'hidden';
        return () => { document.documentElement.style.overflow = ''; };
    }, [phase]);

    if (phase === 'gone') return null;

    const leaving = phase === 'leaving';
    const showHero = destination.current === '/';
    const heroOpacity = showHero ? HERO_MAX * Math.min(1, Math.max(0, (shown - HERO_FROM) / (HERO_TO - HERO_FROM))) : 0;

    return (
        <div className={`fixed inset-0 z-[100] ${leaving ? 'pointer-events-none' : ''}`}>
            {/* fond : blanc + hero-page qui apparaît avec l'avancement ; il s'efface en douceur à la sortie */}
            <div
                className="absolute inset-0 bg-white transition-opacity ease-in-out"
                style={{ opacity: leaving ? 0 : 1, transitionDuration: `${LEAVE_MS}ms` }}
            >
                {showHero && (
                    <img
                        src={coverSrc()}
                        alt=""
                        aria-hidden="true"
                        decoding="async"
                        fetchPriority="high"
                        // même cadrage que le hero (h-svh, object-cover) : l'image ne bouge pas quand le chargement se termine
                        className="absolute inset-x-0 top-0 h-svh w-full object-cover"
                        style={{ opacity: heroOpacity }}
                    />
                )}
            </div>

            {/* logo + barre : ils partent plus vite que le fond */}
            <div
                className="relative flex h-full flex-col items-center justify-center gap-5 transition-opacity duration-300"
                style={{ opacity: leaving ? 0 : 1 }}
            >
                <img src="/images/logo-black.svg" alt="Hoxen" className="h-14 w-auto md:h-16" />
                <div className="w-1/3 md:w-2/5">
                    <div className="h-[3px] w-full overflow-hidden rounded-full bg-neutral-200">
                        <div className="h-full rounded-full bg-black" style={{ width: `${shown}%` }} />
                    </div>
                    <p className="mt-2 text-center text-[10px] uppercase tracking-[0.25em] text-neutral-500">
                        {offline ? 'En attente de connexion…' : `Chargement… ${Math.round(shown)} %`}
                    </p>
                </div>
            </div>
        </div>
    );
}
