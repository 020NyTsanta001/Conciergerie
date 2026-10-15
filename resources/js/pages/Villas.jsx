import { useEffect, useRef, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { api } from '../api';
import VillaCard from '../components/VillaCard';
import { toAr, currencyLabel } from '../money';

const field = 'rounded-full border border-neutral-300 px-4 py-2.5 text-sm outline-none focus:border-black';
const pill = (on) =>
    `rounded-full border px-4 py-2.5 text-sm transition-colors ${on ? 'border-black bg-black text-white' : 'border-neutral-300 hover:border-black'}`;
const EMPTY = { q: '', price_max: '', guests: '', has_pool: false, has_yacht: false };

export default function Villas() {
    const [params] = useSearchParams();
    const [filters, setFilters] = useState({ ...EMPTY, q: params.get('q') ?? '' });
    const [villas, setVillas] = useState([]);
    const [lastPage, setLastPage] = useState(1);
    const [page, setPage] = useState(1);
    const [loading, setLoading] = useState(true);
    const seq = useRef(0); // ignore les réponses arrivées en retard

    const fetchPage = (p, replace) => {
        const id = ++seq.current;
        setLoading(true);
        api('/properties', {
            params: { ...filters, q: filters.q.trim(), price_max: filters.price_max ? toAr(filters.price_max) : '', page: p },
        })
            .then((r) => {
                if (id !== seq.current) return;
                setVillas((old) => (replace ? r.data : [...old, ...r.data]));
                setPage(r.meta?.current_page ?? p);
                setLastPage(r.meta?.last_page ?? 1);
            })
            .catch(() => { if (id === seq.current && replace) setVillas([]); })
            .finally(() => { if (id === seq.current) setLoading(false); });
    };

    // Recherche en direct : on attend 350 ms après la dernière frappe, sans bouton « Filtrer »
    useEffect(() => {
        const t = setTimeout(() => fetchPage(1, true), 350);
        return () => clearTimeout(t);
    }, [filters]); // eslint-disable-line

    const set = (k) => (e) => setFilters({ ...filters, [k]: e.target.value });
    const toggle = (k) => () => setFilters({ ...filters, [k]: !filters[k] });
    const dirty = JSON.stringify(filters) !== JSON.stringify(EMPTY);

    return (
        <div className="mx-auto max-w-7xl px-5 py-12">
            <h1 className="text-5xl tracking-tight mb-8">Nos <span className="italic font-extralight">propriétés</span></h1>

            <input
                type="search"
                className={`${field} w-full py-3.5 text-base`}
                placeholder="Rechercher une ville, une villa ou un yacht…"
                value={filters.q}
                onChange={set('q')}
                aria-label="Recherche"
            />

            <div className="mt-3 mb-8 flex flex-wrap items-center gap-3">
                <input className={`${field} w-44`} type="number" min="0" placeholder={`Prix max / nuit (${currencyLabel()})`} value={filters.price_max} onChange={set('price_max')} />
                <input className={`${field} w-36`} type="number" min="1" placeholder="Voyageurs" value={filters.guests} onChange={set('guests')} />
                <button type="button" onClick={toggle('has_pool')} className={pill(filters.has_pool)} aria-pressed={filters.has_pool}>Piscine</button>
                <button type="button" onClick={toggle('has_yacht')} className={pill(filters.has_yacht)} aria-pressed={filters.has_yacht}>Yacht</button>
                {dirty && (
                    <button type="button" onClick={() => setFilters(EMPTY)} className="px-2 text-sm text-neutral-500 underline hover:text-black">Tout effacer</button>
                )}
            </div>

            {villas.length === 0 && loading ? (
                <p className="text-neutral-500">Chargement…</p>
            ) : villas.length === 0 ? (
                <p className="text-neutral-500">Aucune villa ni yacht ne correspond. Essayez un autre mot ou retirez un filtre.</p>
            ) : (
                <>
                    <div className="grid sm:grid-cols-2 lg:grid-cols-3 gap-5 auto-rows-[320px]">
                        {villas.map((v) => <VillaCard key={v.id} villa={v} />)}
                    </div>
                    {page < lastPage && (
                        <div className="mt-10 text-center">
                            <button
                                onClick={() => fetchPage(page + 1, false)}
                                disabled={loading}
                                className="rounded-full border border-neutral-300 px-6 py-3 text-sm font-semibold hover:border-black disabled:opacity-50"
                            >
                                {loading ? 'Chargement…' : 'Voir plus'}
                            </button>
                        </div>
                    )}
                </>
            )}
        </div>
    );
}
