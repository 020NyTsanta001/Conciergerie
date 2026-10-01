import { useEffect, useRef, useState } from 'react';

// Seuils de progression du scroll (0 → 1) auxquels chaque élément apparaît :
// 1) Where  2) Life Belongs + bouton Explorer  3) paragraphe  4) chiffres
const STEPS = [0.1, 0.3, 0.5, 0.7];

const reveal = (on) =>
    `transition-all duration-700 ease-out ${on ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-6 pointer-events-none'}`;

export default function HeroScroll({ image, stats }) {
    const box = useRef(null);   // grand conteneur (400vh) : sert à mesurer le scroll
    const pin = useRef(null);   // écran figé (sticky)
    const img = useRef(null);
    const [step, setStep] = useState(0); // nombre d'éléments visibles (0 à 4)

    useEffect(() => {
        let raf = 0;

        const update = () => {
            raf = 0;
            const r = box.current.getBoundingClientRect();
            const total = r.height - pin.current.offsetHeight; // distance de scroll pendant laquelle le hero reste figé
            const p = total > 0 ? Math.min(1, Math.max(0, -r.top / total)) : 0;

            // L'image mesure 133,33 % de l'écran. -25 % de sa largeur = son 1er quart caché à gauche.
            // p = 0 → quart gauche caché ; p = 1 → quart droit caché.
            img.current.style.transform = `translateX(${-25 * (1 - p)}%)`;
            setStep(STEPS.filter((s) => p >= s).length);
        };

        const onScroll = () => { if (!raf) raf = requestAnimationFrame(update); };

        update();
        window.addEventListener('scroll', onScroll, { passive: true });
        window.addEventListener('resize', onScroll);
        return () => {
            cancelAnimationFrame(raf);
            window.removeEventListener('scroll', onScroll);
            window.removeEventListener('resize', onScroll);
        };
    }, []);

        // Défilement animé (ease-in-out) vers « Every Home Begins », sans repasser par tout le scroll du hero
    const goToNext = () => {
        const target = document.getElementById('every-home');
        if (!target) return;

        const start = window.scrollY;
        const dist = target.getBoundingClientRect().top; // distance restante jusqu'à la section
        const duration = 1200; // ms
        const t0 = performance.now();
        const ease = (t) => (t < 0.5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2); // easeInOutCubic

        // si l'utilisateur reprend la main (molette / doigt), on arrête l'animation
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
        <section id="hero" ref={box} className="relative -mt-16 h-[400vh]">
            <div ref={pin} className="sticky top-0 h-svh overflow-hidden bg-neutral-900">
                <img
                    ref={img}
                    src={image}
                    alt=""
                    className="absolute left-0 top-0 h-full max-w-none object-cover will-change-transform"
                    style={{ width: '133.334%', transform: 'translateX(-25%)' }}
                />
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
                                                <button
                            type="button"
                            onClick={goToNext}
                            className="inline-block rounded-full border border-white/70 bg-transparent px-8 py-4 text-sm font-semibold text-white transition-colors hover:bg-white/15"
                        >
                            Explorer
                        </button>
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

                {/* indice de défilement, disparaît dès que l'animation démarre */}
                <p className={`absolute bottom-6 left-1/2 -translate-x-1/2 text-xs uppercase tracking-[0.3em] text-white/80 transition-opacity duration-500 ${step === 0 ? 'opacity-100' : 'opacity-0'}`}>
                    Défilez
                </p>
            </div>
        </section>
    );
}