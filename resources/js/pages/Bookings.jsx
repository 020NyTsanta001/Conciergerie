import { useEffect, useState } from 'react';
import { api, eur, STATUS } from '../api';
import { useAuth } from '../auth';

const ACTIONS = {
    validate: ['Valider', 'bg-black text-white', (b) => api(`/bookings/${b.id}/status`, { method: 'PATCH', body: { action: 'validate' } })],
    pay_deposit: ["Payer l'acompte", 'bg-black text-white', (b) => api(`/bookings/${b.id}/pay`, { method: 'POST', body: { type: 'deposit' } })],
    pay_balance: ['Payer le solde', 'bg-black text-white', (b) => api(`/bookings/${b.id}/pay`, { method: 'POST', body: { type: 'balance' } })],
    start: ['Démarrer le séjour', 'bg-emerald-600 text-white', (b) => api(`/bookings/${b.id}/status`, { method: 'PATCH', body: { action: 'start' } })],
    close: ['Clôturer', 'bg-neutral-700 text-white', (b) => api(`/bookings/${b.id}/status`, { method: 'PATCH', body: { action: 'close' } })],
    cancel: ['Annuler / refuser', 'border border-rose-300 text-rose-700', (b) => api(`/bookings/${b.id}/status`, { method: 'PATCH', body: { action: 'cancel' } })],
};

export default function Bookings() {
    const { user } = useAuth();
    const [items, setItems] = useState([]);
    const [notifs, setNotifs] = useState([]);
    const [error, setError] = useState('');

    const load = () => {
        api('/bookings').then((r) => setItems(r.data));
        api('/notifications').then(setNotifs);
    };
    useEffect(load, []);

    const run = async (b, action) => {
        setError('');
        try { await ACTIONS[action][2](b); load(); } catch (e) { setError(e.message); }
    };

    const revenue = items.reduce((s, b) => s + b.paid_amount, 0);

    return (
        <div className="mx-auto max-w-5xl px-5 py-12">
            <h1 className="text-5xl tracking-tight mb-8">Réservations</h1>
            {user.role !== 'client' && (
                <p className="mb-6 rounded-2xl bg-neutral-100 px-5 py-4 text-sm">Encaissé à ce jour : <b>{eur(revenue)}</b></p>
            )}
            {notifs.length > 0 && (
                <div className="mb-8 rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm space-y-1">
                    <p className="font-semibold">Notifications</p>
                    {notifs.slice(0, 5).map((n) => <p key={n.id}>• {n.message}</p>)}
                </div>
            )}
            {error && <p className="mb-4 text-sm text-rose-600">{error}</p>}
            <div className="space-y-4">
                {items.length === 0 && <p className="text-neutral-500">Aucune réservation.</p>}
                {items.map((b) => {
                    const [label, cls] = STATUS[b.status];
                    return (
                        <div key={b.id} className="flex flex-wrap gap-5 rounded-3xl border border-neutral-200 p-5">
                            <img src={b.villa.images?.[0]} alt="" className="h-28 w-40 rounded-2xl object-cover bg-neutral-200" />
                            <div className="flex-1 min-w-52 text-sm">
                                <p className="text-lg font-semibold">{b.villa.title}</p>
                                <p className="text-neutral-500">{b.check_in} → {b.check_out} · {b.guests} voyageur(s) · client : {b.client.name}</p>
                                {b.services.length > 0 && <p className="text-neutral-500">Services : {b.services.map((s) => s.name).join(', ')}</p>}
                                <p className="mt-1">Total {eur(b.total_price)} · payé {eur(b.paid_amount)}</p>
                                <span className={`mt-2 inline-block rounded-full px-3 py-1 text-xs font-medium ${cls}`}>{label}</span>
                            </div>
                            <div className="flex flex-col gap-2 justify-center">
                                {b.actions.map((a) => (
                                    <button key={a} onClick={() => run(b, a)} className={`rounded-full px-5 py-2 text-xs font-semibold ${ACTIONS[a][1]}`}>{ACTIONS[a][0]}</button>
                                ))}
                            </div>
                        </div>
                    );
                })}
            </div>
        </div>
    );
}
