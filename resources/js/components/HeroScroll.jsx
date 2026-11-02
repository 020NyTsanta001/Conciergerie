import { useEffect, useLayoutEffect, useRef, useState } from 'react';
import { FRAME_COUNT, getHeroFrames, setHeroFocus, startHeroFrames, subscribeHeroFrames } from './heroFrames';

/* ───────── RÉGLAGES ───────── */

// Longueur du hero : plus c'est grand, plus la séquence est lente.
const HERO_HEIGHT = '450vh';

// Inertie du scrub : 0.1 = très doux, 0.3 = très réactif.
const SMOOTH = 0.13;

// Bouton « Explorer » : durée par écran de défilement (plus grand = plus lent)
const MS_PER_SCREEN = 625;
const MIN_MS = 2500;
const MAX_MS = 20000;

// Seuils de progression du scroll (0 → 1) auxquels chaque élément apparaît :
// 1) Where  2) Life Belongs  3) paragraphe  4) chiffres
const STEPS = [0.1, 0.3, 0.5, 0.7];

/* ──────────────────────────── */

const reveal = (on) =>
    `transition-all duration-700 ease-out ${on ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-6 pointer-events-none'}`;

// useLayoutEffect côté navigateur : la 1re image est dessinée AVANT le premier affichage (pas d'écran noir)
const useIsoLayoutEffect = typeof window !== 'undefined' ? useLayoutEffect : useEffect;

export default function HeroScroll({ stats }) {
    const box = useRef(null);    // grand conteneur : sert à mesurer le scroll
    const pin = useRef(null);    // écran figé (sticky)
    const canvas = useRef(null); // affichage de la séquence
    const [step, setStep] = useState(0);
    const [clicked, setClicked] = useState(false);

    useIsoLayoutEffect(() => {
        const N = FRAME_COUNT;
        const imgs = getHeroFrames(); // tableau partagé avec le preloader, rempli au fil du chargement
        let raf = 0;       // boucle d'animation
        let scrollRaf = 0; // limite le scroll à 1 calcul par image
        let target = 0;    // position visée (numéro d'image, décimal)
        let current = 0;   // position lissée

        // image chargée la plus proche de i, vers le bas (-1) ou le haut (+1)
        const find = (i, dir) => {
            for (let k = i; k >= 0 && k < N; k += dir) if (imgs[k]) return k;
            return -1;
        };

        // Dessine la position courante ; entre deux images, fondu de l'une vers l'autre
        const render = () => {
            const c = canvas.current;
            if (!c) return;
            const lo = find(Math.floor(current), -1);
            const hi = find(Math.ceil(current), 1);
            const a = lo >= 0 ? lo : hi;
            const b = hi >= 0 ? hi : lo;
            if (a < 0) return;

            const A = imgs[a];
            if (c.width !== A.naturalWidth) { c.width = A.naturalWidth; c.height = A.naturalHeight; }
            const ctx = c.getContext('2d');
            ctx.globalAlpha = 1;
            ctx.drawImage(A, 0, 0, c.width, c.height);
            if (b !== a) {
                const t = (current - a) / (b - a);
                if (t > 0.01) {
                    ctx.globalAlpha = t;
                    ctx.drawImage(imgs[b], 0, 0, c.width, c.height);
                    ctx.globalAlpha = 1;
                }
            }
        };

        // La boucle ne tourne que tant que l'image n'a pas rattrapé le scroll : scroll arrêté → 0 % CPU
        const tick = () => {
            raf = 0;
            current += (target - current) * SMOOTH;
            if (Math.abs(target - current) < 0.01) current = target;
            render();
            if (current !== target) raf = requestAnimationFrame(tick);
        };

        const update = (snap = false) => {
            scrollRaf = 0;
            const r = box.current.getBoundingClientRect();
            const total = r.height - pin.current.offsetHeight;
            const p = total > 0 ? Math.min(1, Math.max(0, -r.top / total)) : 0;

            target = p * (N - 1);
            setHeroFocus(Math.round(target)); // le chargement en arrière-plan suit le visiteur
            if (snap) { current = target; render(); } // affichage immédiat, sans attendre l'animation
            else if (!raf) raf = requestAnimationFrame(tick);
            setStep(STEPS.filter((s) => p >= s).length);
            setClicked(window.scrollY > 8);
        };

        const onScroll = () => { if (!scrollRaf) scrollRaf = requestAnimationFrame(() => update()); };

        // Redessine quand une nouvelle image arrive (utile si elle est proche de la position actuelle)
        const unsub = subscribeHeroFrames(() => { if (!raf) raf = requestAnimationFrame(tick); });
        startHeroFrames(); // sans effet si le preloader l'a déjà lancé

        update(true);
        window.addEventListener('scroll', onScroll, { passive: true });
        window.addEventListener('resize', onScroll);
        return () => {
            unsub();
            cancelAnimationFrame(raf);
            cancelAnimationFrame(scrollRaf);
            window.removeEventListener('scroll', onScroll);
            window.removeEventListener('resize', onScroll);
        };
    }, []);

    // Défilement animé vers « Every Home Begins » ; la séquence suit puisqu'elle est pilotée par le scroll.
    const goToNext = () => {
        const target = document.getElementById('every-home');
        if (!target) return;

        const start = window.scrollY;
        const dist = target.getBoundingClientRect().top;
        const duration = Math.min(MAX_MS, Math.max(MIN_MS, (dist / window.innerHeight) * MS_PER_SCREEN));
        const t0 = performance.now();
        const ease = (t) => -(Math.cos(Math.PI * t) - 1) / 2; // départ et arrivée doux

        let cancelled = false;
        const cancel = () => { cancelled = true; };
        window.addEventListener('wheel', cancel, { passive: true, once: true });
        window.addEventListener('touchstart', cancel, { passive: true, once: true });

        const tick = (now) => {
            if (cancelled) return;
            const t = Math.min(1, (now - t0) / duration);
            window.scrollTo(0, start + dist * ease(t));
            if (t < 1) {
                requestAnimationFrame(tick);
            } else {
                window.removeEventListener('wheel', cancel);
                window.removeEventListener('touchstart', cancel);
            }
        };
        requestAnimationFrame(tick);
    };

    return (
        <section id="hero" ref={box} className="relative -mt-24 bg-neutral-900" style={{ height: HERO_HEIGHT }}>
            <div ref={pin} className="sticky top-0 h-svh overflow-hidden bg-neutral-900">
                <canvas ref={canvas} aria-hidden="true" className="absolute inset-0 h-full w-full object-cover" />
                {/* voiles pour garder texte et header lisibles */}
                <div className="absolute inset-0 bg-black/20" />
                <div className="absolute inset-x-0 top-0 h-40 bg-gradient-to-b from-black/50 to-transparent" />

                <div className="relative h-full flex flex-col justify-between text-white">
                    <div className="px-8 pt-32 md:px-16">
                        <h1 className="text-6xl md:text-8xl leading-none tracking-tight">
                            <span className={`block font-extralight italic ${reveal(step >= 1)}`}>Where</span>
                            <span className={`block font-light uppercase ${reveal(step >= 2)}`}>Life Belongs</span>
                        </h1>
                        <p className={`mt-6 max-w-sm text-sm text-white/90 ${reveal(step >= 3)}`}>
                            Résidences d'exception et conciergerie sur mesure : chef, chauffeur, yacht — pour un séjour sans effort.
                        </p>
                    </div>

                    <div className="p-8 md:p-16 flex flex-wrap items-end justify-between gap-6">
                        <div className={`transition-opacity duration-1000 ease-in-out ${clicked ? 'opacity-0 pointer-events-none' : 'opacity-100'}`}>
                            <button
                                type="button"
                                onClick={() => { setClicked(true); goToNext(); }}
                                className="inline-block rounded-full border border-white bg-transparent px-8 py-4 text-sm font-semibold text-white transition-colors hover:bg-white/15"
                            >
                                Explorer
                            </button>
                        </div>

                        {stats && (
                            <div className={`flex gap-4 ${reveal(step >= 4)}`}>
                                {[[`${stats.properties}+`, 'Propriétés'], [`${stats.years_experience}+`, "Ans d'expérience"]].map(([n, l]) => (
                                    <div key={l} className="rounded-2xl bg-white/85 backdrop-blur px-6 py-4 text-neutral-900">
                                        <p className="text-3xl font-medium">{n}</p><p className="text-xs text-neutral-600">{l}</p>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </section>
    );
}
