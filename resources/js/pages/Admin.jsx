import { useEffect, useState } from 'react';
import { api, eur } from '../api';

export default function Admin() {
    const [villas, setVillas] = useState([]);
    const load = () => api('/admin/properties', { params: { status: 'pending' } }).then((r) => setVillas(r.data));
    useEffect(() => { load(); }, []);

    const moderate = async (v, status) => {
        await api(`/admin/properties/${v.id}/moderate`, { method: 'PATCH', body: { status } });
        load();
    };

    return (
        <div className="mx-auto max-w-4xl px-5 py-12">
            <h1 className="text-4xl tracking-tight mb-6">Annonces à modérer</h1>
            {villas.length === 0 && <p className="text-neutral-500">Aucune annonce en attente.</p>}
            <div className="space-y-3">
                {villas.map((v) => (
                    <div key={v.id} className="flex items-center gap-4 rounded-2xl border border-neutral-200 p-4">
                        <img src={v.images?.[0]} alt="" className="h-16 w-24 rounded-xl object-cover bg-neutral-200" />
                        <div className="flex-1 text-sm"><p className="font-semibold">{v.title}</p><p className="text-neutral-500">{v.city} · {eur(v.price_per_night)} · par {v.owner?.name}</p></div>
                        <button onClick={() => moderate(v, 'approved')} className="rounded-full bg-black text-white px-4 py-2 text-xs font-semibold">Approuver</button>
                        <button onClick={() => moderate(v, 'rejected')} className="rounded-full border border-rose-300 text-rose-700 px-4 py-2 text-xs font-semibold">Rejeter</button>
                    </div>
                ))}
            </div>
        </div>
    );
}
