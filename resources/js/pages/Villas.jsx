import { useEffect, useState } from 'react';
import { api } from '../api';
import VillaCard from '../components/VillaCard';

const field = 'rounded-full border border-neutral-300 px-4 py-2.5 text-sm outline-none focus:border-black';

export default function Villas() {
    const [filters, setFilters] = useState({ city: '', price_max: '', guests: '', has_pool: false, available_from: '', available_to: '' });
    const [villas, setVillas] = useState([]);
    const [loading, setLoading] = useState(true);

    const load = () => {
        setLoading(true);
        api('/properties', { params: filters }).then((r) => setVillas(r.data)).finally(() => setLoading(false));
    };
    useEffect(load, []); // eslint-disable-line

    const set = (k) => (e) => setFilters({ ...filters, [k]: e.target.type === 'checkbox' ? e.target.checked : e.target.value });

    return (
        <div className="mx-auto max-w-7xl px-5 py-12">
            <h1 className="text-5xl tracking-tight mb-8">Nos <span className="italic font-extralight">propriétés</span></h1>
            <form onSubmit={(e) => { e.preventDefault(); load(); }} className="flex flex-wrap gap-3 mb-10">
                <input className={field} placeholder="Ville" value={filters.city} onChange={set('city')} />
                <input className={field} type="number" placeholder="Prix max / nuit" value={filters.price_max} onChange={set('price_max')} />
                <input className={field} type="number" placeholder="Voyageurs" value={filters.guests} onChange={set('guests')} />
                <input className={field} type="date" value={filters.available_from} onChange={set('available_from')} />
                <input className={field} type="date" value={filters.available_to} onChange={set('available_to')} />
                <label className="flex items-center gap-2 text-sm px-2"><input type="checkbox" checked={filters.has_pool} onChange={set('has_pool')} /> Piscine</label>
                <button className="rounded-full bg-black text-white px-6 py-2.5 text-sm">Filtrer</button>
            </form>
            {loading ? <p className="text-neutral-500">Chargement…</p> : villas.length === 0 ? <p className="text-neutral-500">Aucune villa ne correspond.</p> : (
                <div className="grid sm:grid-cols-2 lg:grid-cols-3 gap-5 auto-rows-[320px]">
                    {villas.map((v) => <VillaCard key={v.id} villa={v} />)}
                </div>
            )}
        </div>
    );
}
