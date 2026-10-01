import { useEffect, useState } from 'react';
import { api, eur } from '../api';

const field = 'rounded-xl border border-neutral-300 px-3 py-2.5 text-sm w-full outline-none focus:border-black';
const empty = { title: '', description: '', city: '', country: '', price_per_night: '', bedrooms: 1, capacity: 2, has_pool: false, image: '' };
const BADGE = { pending: 'bg-amber-100 text-amber-800', approved: 'bg-emerald-100 text-emerald-800', rejected: 'bg-rose-100 text-rose-800' };

export default function Host() {
    const [villas, setVillas] = useState([]);
    const [f, setF] = useState(empty);
    const [msg, setMsg] = useState('');
    const load = () => api('/host/properties').then((r) => setVillas(r.data));
    useEffect(() => { load(); }, []);
    const set = (k) => (e) => setF({ ...f, [k]: e.target.type === 'checkbox' ? e.target.checked : e.target.value });

    const submit = async (e) => {
        e.preventDefault();
        setMsg('');
        try {
            const { image, ...rest } = f;
            await api('/host/properties', { method: 'POST', body: { ...rest, images: image ? [image] : [] } });
            setF(empty);
            setMsg('Villa soumise : en attente de validation par un concierge.');
            load();
        } catch (err) { setMsg(err.message); }
    };

    return (
        <div className="mx-auto max-w-6xl px-5 py-12 grid lg:grid-cols-2 gap-10">
            <div>
                <h1 className="text-4xl tracking-tight mb-6">Mes villas</h1>
                <div className="space-y-3">
                    {villas.map((v) => (
                        <div key={v.id} className="flex items-center gap-4 rounded-2xl border border-neutral-200 p-4">
                            <img src={v.images?.[0]} alt="" className="h-16 w-24 rounded-xl object-cover bg-neutral-200" />
                            <div className="flex-1 text-sm"><p className="font-semibold">{v.title}</p><p className="text-neutral-500">{v.city} · {eur(v.price_per_night)} / nuit</p></div>
                            <span className={`rounded-full px-3 py-1 text-xs ${BADGE[v.status]}`}>{v.status}</span>
                        </div>
                    ))}
                </div>
            </div>
            <form onSubmit={submit} className="space-y-3 rounded-3xl border border-neutral-200 p-6">
                <h2 className="font-semibold text-lg">Publier une villa</h2>
                <input className={field} placeholder="Titre" value={f.title} onChange={set('title')} required />
                <textarea className={field} placeholder="Description" value={f.description} onChange={set('description')} required />
                <div className="grid grid-cols-2 gap-3">
                    <input className={field} placeholder="Ville" value={f.city} onChange={set('city')} required />
                    <input className={field} placeholder="Pays" value={f.country} onChange={set('country')} required />
                    <input className={field} type="number" placeholder="Prix / nuit (€)" value={f.price_per_night} onChange={set('price_per_night')} required />
                    <input className={field} type="number" min="1" placeholder="Chambres" value={f.bedrooms} onChange={set('bedrooms')} />
                    <input className={field} type="number" min="1" placeholder="Capacité" value={f.capacity} onChange={set('capacity')} />
                    <label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={f.has_pool} onChange={set('has_pool')} /> Piscine</label>
                </div>
                <input className={field} type="url" placeholder="URL d'une photo (https://…)" value={f.image} onChange={set('image')} />
                {msg && <p className="text-sm text-neutral-600">{msg}</p>}
                <button className="rounded-full bg-black text-white px-6 py-3 text-sm font-semibold">Soumettre</button>
            </form>
        </div>
    );
}
