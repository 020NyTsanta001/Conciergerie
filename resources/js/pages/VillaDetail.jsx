import { useEffect, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { api, eur } from '../api';
import { useAuth } from '../auth';

const field = 'rounded-xl border border-neutral-300 px-3 py-2.5 text-sm w-full outline-none focus:border-black';

export default function VillaDetail() {
    const { id } = useParams();
    const { user } = useAuth();
    const nav = useNavigate();
    const [villa, setVilla] = useState(null);
    const [services, setServices] = useState([]);
    const [form, setForm] = useState({ check_in: '', check_out: '', guests: 2 });
    const [picked, setPicked] = useState({});
    const [quote, setQuote] = useState(null);
    const [error, setError] = useState('');

    useEffect(() => {
        api(`/properties/${id}`).then((r) => setVilla(r.data));
        api('/services').then(setServices);
    }, [id]);

    const payload = () => ({
        villa_id: Number(id), ...form, guests: Number(form.guests),
        services: Object.keys(picked).filter((k) => picked[k]).map((k) => ({ id: Number(k), quantity: 1 })),
    });

    useEffect(() => {
        setQuote(null);
        if (!user || !form.check_in || !form.check_out || form.check_out <= form.check_in) return;
        api('/bookings/quote', { method: 'POST', body: payload() }).then(setQuote).catch((e) => setError(e.message));
    }, [form, picked, user]); // eslint-disable-line

    const reserve = async () => {
        if (!user) return nav('/login');
        setError('');
        try {
            await api('/bookings', { method: 'POST', body: payload() });
            nav('/bookings');
        } catch (e) { setError(e.message); }
    };

    if (!villa) return <p className="p-10 text-neutral-500">Chargement…</p>;

    return (
        <div className="mx-auto max-w-7xl px-5 py-10 grid lg:grid-cols-3 gap-10">
            <div className="lg:col-span-2">
                <div className="grid grid-cols-3 grid-rows-2 gap-3 h-[420px]">
                    {villa.images.slice(0, 3).map((src, i) => (
                        <img key={src} src={src} alt="" className={`h-full w-full object-cover rounded-3xl ${i === 0 ? 'col-span-2 row-span-2' : ''}`} />
                    ))}
                </div>
                <h1 className="text-5xl tracking-tight mt-8">{villa.title}</h1>
                <p className="text-neutral-500 mt-2">{villa.city}, {villa.country} · {villa.bedrooms} chambres · {villa.capacity} voyageurs{villa.has_pool && ' · Piscine'}</p>
                <p className="mt-6 text-neutral-700 leading-relaxed">{villa.description}</p>
                {villa.seasons?.length > 0 && (
                    <div className="mt-8">
                        <h2 className="font-semibold mb-3">Tarifs saisonniers</h2>
                        <ul className="text-sm text-neutral-600 space-y-1">
                            {villa.seasons.map((s) => <li key={s.id}>{s.name} ({s.start_date} → {s.end_date}) : <b>{eur(s.price_per_night)}</b> / nuit</li>)}
                        </ul>
                    </div>
                )}
            </div>

            <aside className="rounded-3xl border border-neutral-200 p-6 h-fit sticky top-24 space-y-4">
                <p className="text-2xl font-semibold">{eur(villa.price_per_night)} <span className="text-sm font-normal text-neutral-500">/ nuit</span></p>
                <div className="grid grid-cols-2 gap-3">
                    <input className={field} type="date" value={form.check_in} onChange={(e) => setForm({ ...form, check_in: e.target.value })} />
                    <input className={field} type="date" value={form.check_out} onChange={(e) => setForm({ ...form, check_out: e.target.value })} />
                </div>
                <input className={field} type="number" min="1" max={villa.capacity} value={form.guests} onChange={(e) => setForm({ ...form, guests: e.target.value })} />
                <div>
                    <p className="text-sm font-semibold mb-2">Services exclusifs</p>
                    {services.map((s) => (
                        <label key={s.id} className="flex justify-between text-sm py-1">
                            <span><input type="checkbox" className="mr-2" checked={!!picked[s.id]} onChange={(e) => setPicked({ ...picked, [s.id]: e.target.checked })} />{s.name}</span>
                            <span className="text-neutral-500">{eur(s.price)}</span>
                        </label>
                    ))}
                </div>
                {quote && (
                    <div className="text-sm border-t pt-4 space-y-1">
                        <p className="flex justify-between"><span>{quote.nights_count} nuit(s)</span><span>{eur(quote.stay_total)}</span></p>
                        <p className="flex justify-between"><span>Services</span><span>{eur(quote.services_total)}</span></p>
                        <p className="flex justify-between font-semibold text-base"><span>Total</span><span>{eur(quote.total)}</span></p>
                        <p className="text-neutral-500">Acompte à la validation : {eur(quote.deposit)}</p>
                    </div>
                )}
                {error && <p className="text-sm text-rose-600">{error}</p>}
                <button onClick={reserve} className="w-full rounded-full bg-black text-white py-3.5 text-sm font-semibold">
                    {user ? 'Réserver' : 'Se connecter pour réserver'}
                </button>
            </aside>
        </div>
    );
}
