import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { api } from '../api';

const DELAY = 5000; // 5 secondes entre deux yachts

function PinIcon() {
    return (
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="shrink-0">
            <path d="M12 21s-7-6.2-7-11a7 7 0 0114 0c0 4.8-7 11-7 11z" />
            <circle cx="12" cy="10" r="2.5" />
        </svg>
    );
}

export default function YachtSection() {
    const [yachts, setYachts] = useState(null); // null = chargement en cours
    const [i, setI] = useState(0);              // position du carrousel (peut valoir n : copie de la 1re carte)
    const [anim, setAnim] = useState(true);     // animation de glissement active ou non

    useEffect(() => {
        api('/yachts').then(setYachts).catch(() => setYachts([]));
    }, []);

    const n = yachts?.length ?? 0;
    const current = n ? i % n : 0; // yacht affiché à droite (synchronisé avec la photo)

    // Défilement automatique toutes les 5 s (seulement s'il y a au moins 2 yachts)
    useEffect(() => {
        if (n < 2) return;
        const t = setTimeout(() => { setAnim(true); setI((x) => x + 1); }, DELAY);
        return () => clearTimeout(t);
    }, [n, current]);

    // Boucle continue : après la copie de la 1re carte, on revient à la vraie 1re carte sans animation
    const onTrackEnd = () => { if (n > 1 && i >= n) { setAnim(false); setI(0); } };

    const goTo = (k) => { setAnim(true); setI(k); };

    const Title = (
        <div className="mb-8">
            <h2 className="text-5xl tracking-tight">Your Luxury <span className="italic font-extralight">Escape</span></h2>
            <p className="mt-3 text-sm text-neutral-500">Embarquez depuis votre villa : chaque yacht est rattaché à une villa partenaire.</p>
        </div>
    );

    /* ---------- Chargement ---------- */
    if (yachts === null) {
        return (
            <section className="mx-auto max-w-7xl px-5 pb-24">
                {Title}
                <div className="grid items-center gap-8 md:grid-cols-[26rem_1fr] lg:grid-cols-[26rem_1fr]">
                    <div className="aspect-[5/4] animate-pulse rounded-3xl bg-neutral-100" />
                    <div className="h-48 animate-pulse rounded-3xl bg-neutral-100" />
                </div>
            </section>
        );
    }

    /* ---------- Aucun yacht ---------- */
    if (n === 0) {
        return (
            <section className="mx-auto max-w-7xl px-5 pb-24">
                {Title}
                <div className="grid items-stretch gap-8 md:grid-cols-[26rem_1fr] lg:grid-cols-[26rem_1fr]">
                    <div className="flex aspect-[5/4] items-center justify-center rounded-3xl border border-dashed border-neutral-300 bg-neutral-50 px-8 text-center">
                        <p className="text-sm uppercase tracking-[0.25em] text-neutral-400">Aucune sortie disponible pour le moment</p>
                    </div>

                    {/* « Tampon » gris en arrière-plan : grand emoji droit et grisé, texte incliné par-dessus */}
                    <div className="relative flex min-h-[320px] items-center justify-center overflow-hidden rounded-3xl">
                        <span aria-hidden="true" className="pointer-events-none select-none text-[11rem] leading-none opacity-20 grayscale md:text-[16rem]">🙂</span>
                        <p className="absolute -rotate-[10deg] rounded-xl border-4 border-double border-neutral-400/70 px-6 py-3 text-center text-2xl font-extrabold uppercase tracking-widest text-neutral-500/80 md:text-3xl">
                            Sortie merveilleuse à venir
                        </p>
                    </div>
                </div>
            </section>
        );
    }

    /* ---------- 1 à 4 yachts ---------- */
    const slides = n > 1 ? [...yachts, yachts[0]] : yachts; // copie de la 1re carte à la fin : défilement continu vers la droite

    return (
        <section className="mx-auto max-w-7xl px-5 pb-24">
            {Title}
            <div className="grid items-center gap-8 md:grid-cols-[26rem_1fr] lg:grid-cols-[26rem_1fr]">
                {/* Gauche : carte qui défile vers la droite */}
                <div className="overflow-hidden rounded-3xl bg-neutral-200">
                    <div
                        className="flex aspect-[5/4]"
                        style={{ transform: `translateX(-${i * 100}%)`, transition: anim ? 'transform 700ms ease-in-out' : 'none' }}
                        onTransitionEnd={onTrackEnd}
                    >
                        {slides.map((y, k) => (
                            <Link key={`${y.villa_id}-${k}`} to={`/villas/${y.villa_id}`} className="relative block h-full w-full shrink-0">
                                <img src={y.image} alt={y.name} className="absolute inset-0 h-full w-full object-cover" />
                                <div className="absolute inset-0 bg-gradient-to-t from-black/70 via-black/0 to-transparent" />
                                <span className="absolute left-4 top-4 rounded-full bg-white/90 px-3 py-1 text-[10px] font-semibold uppercase tracking-widest text-neutral-900">Yacht</span>
                                <div className="absolute bottom-0 p-5 text-white">
                                    <p className="text-xl font-semibold">{y.name}</p>
                                    <p className="text-sm text-white/80">Depuis {y.villa_title} · {y.city}</p>
                                </div>
                            </Link>
                        ))}
                    </div>
                </div>

                {/* Droite : texte du yacht, en fondu, synchronisé avec la photo */}
                <div>
                    <div className="grid">
                        {yachts.map((y, k) => (
                            <div
                                key={y.villa_id}
                                className={`col-start-1 row-start-1 transition-opacity duration-700 ease-in-out ${k === current ? 'opacity-100' : 'pointer-events-none opacity-0'}`}
                            >
                                <p className="text-xs uppercase tracking-[0.3em] text-neutral-500">Yacht à embarquer</p>
                                <h3 className="mt-3 text-3xl font-semibold tracking-tight md:text-4xl">{y.name}</h3>
                                {y.description && <p className="mt-4 max-w-md leading-relaxed text-neutral-600">{y.description}</p>}

                                {y.places?.length > 0 && (
                                    <div className="mt-6">
                                        <p className="text-xs uppercase tracking-widest text-neutral-500">{y.places.length > 1 ? 'Lieux de sortie' : 'Lieu de sortie'}</p>
                                        <ul className="mt-2 space-y-1.5 text-sm text-neutral-800">
                                            {y.places.map((p) => (
                                                <li key={p} className="flex items-center gap-2"><PinIcon />{p}</li>
                                            ))}
                                        </ul>
                                    </div>
                                )}

                                <Link to={`/villas/${y.villa_id}`} className="mt-8 inline-block rounded-full border border-neutral-300 px-6 py-3 text-sm font-semibold hover:bg-neutral-50">
                                    Voir la villa associée →
                                </Link>
                            </div>
                        ))}
                    </div>

                    {n > 1 && (
                        <div className="mt-8 flex items-center gap-2">
                            {yachts.map((y, k) => (
                                <button
                                    key={y.villa_id}
                                    onClick={() => goTo(k)}
                                    aria-label={`Yacht ${k + 1}`}
                                    className={`h-1.5 rounded-full transition-all duration-300 ${k === current ? 'w-8 bg-black' : 'w-3 bg-neutral-300 hover:bg-neutral-400'}`}
                                />
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </section>
    );
}