import { useEffect, useState } from 'react';
import { api, eur, STATUS } from '../api';
import { useAuth } from '../auth';
import PayModal from '../components/PayModal';

const ACTIONS = {
    validate: ['Valider', 'bg-black text-white'],
    pay_deposit: ["Payer l'acompte", 'bg-black text-white'],
    pay_balance: ['Payer le solde', 'bg-black text-white'],
    confirm_onsite: ['Valider le paiement reçu sur place', 'bg-emerald-600 text-white'],
    start: ['Démarrer le séjour', 'bg-emerald-600 text-white'],
    close: ['Clôturer', 'bg-neutral-700 text-white'],
    cancel: ['Annuler / refuser', 'border border-rose-300 text-rose-700'],
    refund: ["Rembourser l'acompte", 'border border-amber-400 text-amber-800'],
};

export default function Bookings() {
    const { user, refresh } = useAuth();
    const [items, setItems] = useState([]);
    const [notifs, setNotifs] = useState([]);
    const [payouts, setPayouts] = useState(null);
    const [error, setError] = useState('');
    const [pay, setPay] = useState(null);       // { booking, kind } : fenêtre de paiement du voyageur
    const [onsite, setOnsite] = useState(null); // réservation dont l'hôte valide le paiement reçu sur place
    const [refund, setRefund] = useState(null); // réservation dont l'hôte confirme le remboursement

    const load = () => {
        api('/bookings').then((r) => setItems(r.data));
        api('/notifications').then(setNotifs);
        if (user.role === 'host') api('/host/payouts').then(setPayouts).catch(() => {});
    };
    useEffect(load, []);

    const run = async (b, action) => {
        setError('');
        if (action === 'pay_deposit') return setPay({ booking: b, kind: 'deposit' });
        if (action === 'pay_balance') return setPay({ booking: b, kind: 'balance' });
        if (action === 'confirm_onsite') return setOnsite(b);
        if (action === 'refund') return setRefund(b); // la requête part seulement après confirmation (confirmRefund)
        try {
            await api(`/bookings/${b.id}/status`, { method: 'PATCH', body: { action } });
            load();
        } catch (e) { setError(e.message); }
    };

    const confirmOnsite = async () => {
        try {
            await api(`/bookings/${onsite.id}/status`, { method: 'PATCH', body: { action: 'confirm_onsite' } });
            await refresh();
            load();
        } catch (e) { setError(e.message); }
        setOnsite(null);
    };
    
    const confirmRefund = async () => {
        try {
            await api(`/bookings/${refund.id}/status`, { method: 'PATCH', body: { action: 'refund' } });
            await refresh();
            load();
        } catch (e) { setError(e.message); }
        setRefund(null);
    };

    return (
        <div className="mx-auto max-w-5xl px-5 py-12">
            <h1 className="text-5xl tracking-tight mb-8">Réservations</h1>

            {user.role === 'client' && (
                <p className="mb-6 rounded-2xl bg-neutral-100 px-5 py-4 text-sm">Votre solde : <b>{eur(user.balance)}</b></p>
            )}
            {user.role === 'host' && (
                <div className="mb-6 grid gap-3 sm:grid-cols-2">
                    <p className="rounded-2xl bg-neutral-100 px-5 py-4 text-sm">Votre solde : <b>{eur(user.balance)}</b></p>
                    {payouts && (
                        <p className="rounded-2xl bg-emerald-50 px-5 py-4 text-sm">
                            Total reçu : <b>{eur(payouts.received)}</b>{' '}
                            <span className="text-neutral-500">(acomptes + part des séjours payés en intégralité)</span>
                        </p>
                    )}
                </div>
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
                                {b.status === 'deposit_paid' && b.balance_method === 'on_site' && (
                                    <p className="mt-1 text-amber-700">Solde de {eur(b.total_price - b.deposit_amount)} à régler sur place au début du séjour.</p>
                                    
                                )}
                                {user.role === 'client' && b.status === 'deposit_paid' && (
                                    <p className="mt-1 text-neutral-500">L'acompte n'est remboursable que par le propriétaire, avant la date du séjour.</p>
                                )}
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
            {refund && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-5" onClick={() => setRefund(null)}>
                    <div className="w-full max-w-md rounded-3xl bg-white p-8 shadow-2xl" onClick={(e) => e.stopPropagation()}>
                        <p className="text-lg font-semibold">Rembourser l'acompte de {eur(refund.deposit_amount)} ?</p>
                        <p className="mt-3 text-sm text-neutral-600">
                            Réservation #{refund.id} : l'acompte est repris sur votre solde et rendu au voyageur. La réservation sera annulée
                            et la villa redeviendra disponible pour ces dates. Cette action est irréversible.
                        </p>
                        <div className="mt-8 flex justify-end gap-3">
                            <button onClick={() => setRefund(null)} className="rounded-full border border-neutral-300 px-5 py-2.5 text-sm">Annuler</button>
                            <button onClick={confirmRefund} className="rounded-full bg-amber-600 px-5 py-2.5 text-sm font-semibold text-white">Oui, rembourser</button>
                        </div>
                    </div>
                </div>
            )}
            </div>

            {pay && (
                <PayModal
                    booking={pay.booking}
                    kind={pay.kind}
                    onClose={() => setPay(null)}
                    onDone={() => { setPay(null); load(); }}
                />
            )}

            {onsite && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-5" onClick={() => setOnsite(null)}>
                    <div className="w-full max-w-md rounded-3xl bg-white p-8 shadow-2xl" onClick={(e) => e.stopPropagation()}>
                        <p className="text-lg font-semibold">Confirmer la réception du solde sur place ?</p>
                        <p className="mt-3 text-sm text-neutral-600">
                            Réservation #{onsite.id} : {eur(onsite.total_price - onsite.deposit_amount)} reçus du voyageur en main propre (simulation).
                            Son solde n'est pas débité, et la commission de la plateforme sera prélevée sur votre solde.
                        </p>
                        <div className="mt-8 flex justify-end gap-3">
                            <button onClick={() => setOnsite(null)} className="rounded-full border border-neutral-300 px-5 py-2.5 text-sm">Annuler</button>
                            <button onClick={confirmOnsite} className="rounded-full bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white">Oui, j'ai reçu le paiement</button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}