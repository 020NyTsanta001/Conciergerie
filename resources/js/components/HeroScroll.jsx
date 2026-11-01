import { useEffect, useRef, useState } from 'react';

/* ───────── RÉGLAGES ───────── */

// Séquence d'images générée par ffmpeg (f_001.webp, f_002.webp, …)
const FRAMES_DESKTOP = '/images/hero-frames';
const FRAMES_MOBILE = '/images/hero-frames-m';
const FRAME_COUNT = 125; 
const PAD = 3;          
const EXT = 'webp';

// Longueur du hero : plus c'est grand, plus la séquence est lente.
// 600vh ≈ 2x · 800vh ≈ 1,5x · 1000vh ≈ 1x (vitesse de la vidéo d'origine, scroll normal)
const HERO_HEIGHT = '1000vh';

// Inertie du scrub : 0.1 = très doux, 0.3 = très réactif.
const SMOOTH = 0.16;

// true : les visiteurs ayant « réduire les animations » dans leur OS voient l'image fixe seule.
const RESPECT_REDUCED_MOTION = false;

// Seuils de progression du scroll (0 → 1) auxquels chaque élément apparaît :
// 1) Where  2) Life Belongs + bouton Explorer  3) paragraphe  4) chiffres
const STEPS = [0.1, 0.3, 0.5, 0.7];

/* ──────────────────────────── */

const reveal = (on) =>
    `transition-all duration-700 ease-out ${on ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-6 pointer-events-none'}`;

const isStill = () =>
    RESPECT_REDUCED_MOTION && typeof window !== 'undefined' && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

// Ordre de chargement « grossier → fin » : 1re, dernière, puis 1 image sur 32, sur 8, sur 4, sur 2, le reste.
// Ainsi, même si l'utilisateur scrolle vite pendant le chargement, il voit toujours quelque chose de cohérent.
const loadOrder = (n) => {
    const seen = new Set([0, n - 1]);
    const out = [0, n - 1];
    [32, 8, 4, 2, 1].forEach((stride) => {
        for (let i = 0; i < n; i += stride) if (!seen.has(i)) { seen.add(i); out.push(i); }
    });
    return out;
};

// props : image (affichée jusqu'à l'arrivée de la 1re image de la séquence), stats
export default function HeroScroll({ image, stats }) {
    const box = useRef(null);    // grand conteneur : sert à mesurer le scroll
    const pin = useRef(null);    // écran figé (sticky)
    const canvas = useRef(null); // affichage de la séquence
    const [step, setStep] = useState(0);
    const [clicked, setClicked] = useState(false);
    const [ready, setReady] = useState(false); // vrai dès que la 1re image est dessinée
    const [still] = useState(isStill);

    useEffect(() => {
        if (still) return;

        const N = FRAME_COUNT;
        const dir = window.innerWidth < 768 ? FRAMES_MOBILE : FRAMES_DESKTOP;
        const url = (i) => `${dir}/f_${String(i + 1).padStart(PAD, '0')}.${EXT}`;

        const imgs = new Array(N).fill(null); // images chargées
        let dead = false;
        let shown = false;
        let raf = 0;       // boucle d'animation
        let scrollRaf = 0; // throttle du scroll
        let target = 0;    // position visée (en numéro d'image, décimal)
        let current = 0;   // position lissée

        // plus proche image chargée en partant de i, vers le bas (step = -1) ou le haut (step = +1)
        const find = (i, step) => {
            for (let k = i; k >= 0 && k < N; k += step) if (imgs[k]) return k;
            return -1;
        };

        // Dessine l'image courante ; entre deux images, fondu de l'une vers l'autre pour adoucir le rendu
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
                ctx.globalAlpha = (current - a) / (b - a);
                ctx.drawImage(imgs[b], 0, 0, c.width, c.height);
                ctx.globalAlpha = 1;
            }
            if (!shown) { shown = true; setReady(true); }
        };

        // La boucle ne tourne que tant que l'image n'a pas rattrapé le scroll : scroll arrêté → 0 % CPU
        const tick = () => {
            raf = 0;
            current += (target - current) * SMOOTH;
            if (Math.abs(target - current) < 0.01) current = target;
            render();
            if (current !== target) raf = requestAnimationFrame(tick);
        };

        const update = () => {
            scrollRaf = 0;
            const r = box.current.getBoundingClientRect();
            const total = r.height - pin.current.offsetHeight;
            const p = total > 0 ? Math.min(1, Math.max(0, -r.top / total)) : 0;

            target = p * (N - 1);
            if (!raf) raf = requestAnimationFrame(tick);
            setStep(STEPS.filter((s) => p >= s).length);
            setClicked(window.scrollY > 8);
        };

        const onScroll = () => { if (!scrollRaf) scrollRaf = requestAnimationFrame(update); };

        // Chargement progressif : 4 images en parallèle maximum
        const queue = loadOrder(N);
        let active = 0;
        const pump = () => {
            while (!dead && active < 4 && queue.length) {
                const i = queue.shift();
                const im = new Image();
                im.decoding = 'async';
                active++;
                im.onload = () => {
                    active--;
                    imgs[i] = im;
                    if (!raf) raf = requestAnimationFrame(tick); // redessine si cette image est utile maintenant
                    pump();
                };
                im.onerror = () => {
                    active--;
                    if (i === 0) console.warn('[HeroScroll] image introuvable :', im.src);
                    pump();
                };
                im.src = url(i);
            }
        };

        update();
        pump();
        window.addEventListener('scroll', onScroll, { passive: true });
        window.addEventListener('resize', onScroll);
        return () => {
            dead = true;
            queue.length = 0;
            cancelAnimationFrame(raf);
            cancelAnimationFrame(scrollRaf);
            window.removeEventListener('scroll', onScroll);
            window.removeEventListener('resize', onScroll);
        };
    }, [still]);

    // Défilement animé vers « Every Home Begins » ; la séquence suit puisqu'elle est pilotée par le scroll.
    // Durée proportionnelle à la distance : sinon toute la séquence défilerait en 1,2 s.
    const goToNext = () => {
        const target = document.getElementById('every-home');
        if (!target) return;

        const start = window.scrollY;
        const dist = target.getBoundingClientRect().top;
        const duration = Math.min(6500, Math.max(1800, (dist / window.innerHeight) * 600)); // 1,8s fin du hero, 6s depuis le haut
        const t0 = performance.now();
        const ease = (t) => (t < 0.5 ? 2 * t * t * t : 1 - Math.pow(-2 * t + 2, 2) / 2);

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

    const mediaClass = 'absolute inset-0 h-full w-full object-cover';

    return (
        <section id="hero" ref={box} className="relative -mt-24 bg-neutral-900" style={{ height: HERO_HEIGHT }}>
            <div ref={pin} className="sticky top-0 h-svh overflow-hidden bg-neutral-900">
                {/* l'image reste dessous tant que la séquence n'a rien affiché (ou si elle est introuvable) */}
                {image && <img src={image} alt="" className={mediaClass} />}
                {!still && (
                    <canvas
                        ref={canvas}
                        aria-hidden="true"
                        className={`${mediaClass} transition-opacity duration-500 ${ready ? 'opacity-100' : 'opacity-0'}`}
                    />
                )}
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
                                className="inline-block rounded-full border border-white/70 bg-transparent px-8 py-4 text-sm font-semibold text-white transition-colors hover:bg-white/15"
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
