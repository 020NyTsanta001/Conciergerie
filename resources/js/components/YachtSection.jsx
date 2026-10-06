import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { api } from '../api';

const SLOTS = 4;

export default function YachtSection() {
    const [yachts, setYachts] = useState(null); // null = chargement en cours

    useEffect(() => {
        api('/yachts').then(setYachts).catch(() => setYachts([]));
    }, []);

    // undefined = squelette de chargement ; null = emplacement « À venir » ; objet = vrai yacht
    const slots = yachts
        ? [...yachts, ...Array(Math.max(0, SLOTS - yachts.length)).fill(null)]
        : Array(SLOTS).fill(undefined);

    return (
        <section className="mx-auto max-w-7xl px-5 pb-24">
            <div className="mb-8">
                <h2 className="text-5xl tracking-tight">Your Luxury <span className="italic font-extralight">Escape</span></h2>
                <p className="mt-3 text-sm text-neutral-500">Embarquez depuis votre villa : chaque yacht est rattaché à une villa partenaire.</p>
            </div>

            <div className="grid grid-cols-2 gap-4 md:grid-cols-4">
                {slots.map((y, i) => {
                    if (y === undefined) {
                        return <div key={i} className="aspect-[3/4] animate-pulse rounded-3xl bg-neutral-100" />;
                    }
                    if (y === null) {
                        return (
                            <div key={i} className="flex aspect-[3/4] items-center justify-center rounded-3xl border border-dashed border-neutral-300 bg-neutral-50">
                                <span className="text-xs uppercase tracking-[0.3em] text-neutral-400">À venir</span>
                            </div>
                        );
                    }
                    return (
                        <Link key={y.villa_id} to={`/villas/${y.villa_id}`} className="group relative block aspect-[3/4] overflow-hidden rounded-3xl bg-neutral-200">
                            <img
                                src={y.image}
                                alt={`Yacht lié à ${y.villa_title}`}
                                loading="lazy"
                                className="absolute inset-0 h-full w-full object-cover transition duration-700 group-hover:scale-105"
                            />
                            <div className="absolute inset-0 bg-gradient-to-t from-black/70 via-black/0 to-transparent" />
                            <span className="absolute left-4 top-4 rounded-full bg-white/90 px-3 py-1 text-[10px] font-semibold uppercase tracking-widest text-neutral-900">Yacht</span>
                            <div className="absolute bottom-0 p-5 text-white">
                                <p className="text-lg font-semibold">{y.villa_title}</p>
                                <p className="text-sm text-white/80">{y.city}, {y.country}</p>
                                <p className="mt-2 text-xs font-medium opacity-0 transition group-hover:opacity-100">Voir la villa →</p>
                            </div>
                        </Link>
                    );
                })}
            </div>
        </section>
    );
}