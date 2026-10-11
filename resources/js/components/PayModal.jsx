import { useState } from 'react';
import { api, eur } from '../api';
import { useAuth } from '../auth';

/** Confirmation avant tout paiement. kind : 'deposit' (acompte) ou 'balance' (solde du séjour). */
export default function PayModal({ booking, kind, onClose, onDone }) {
    const { user, refresh } = useAuth();
    const isDeposit = kind === 'deposit';
    const amount = isDeposit ? booking.deposit_amount : booking.total_price - booking.deposit_amount;

    const [method, setMethod] = useState('wallet'); // wallet | on_site
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');

    const onSite = !isDeposit && method === 'on_site';
    const blocked = !onSite && user.balance < amount;

    const confirm = async () => {
        setBusy(true);
        setError('');
        try {
            await api(`/bookings/${booking.id}/pay`, { method: 'POST', body: { type: kind, method: isDeposit ? 'wallet' : method } });
            await refresh();
            onDone();
        } catch (e) {
            setError(e.message);
            setBusy(false);
        }
    };

    const option = (value, label) => (
        <label className={`flex cursor-pointer items-center gap-3 rounded-2xl border p-3 ${method === value ? 'border-black' : 'border-neutral-200'}`}>
            <input type="radio" name="method" checked={method === value} onChange={() => setMethod(value)} />
            {label}
        </label>
    );

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-5" onClick={onClose}>
            <div className="w-full max-w-md rounded-3xl bg-white p-8 shadow-2xl" onClick={(e) => e.stopPropagation()}>
                <p className="text-lg font-semibold">{isDeposit ? "Payer l'acompte" : 'Payer le solde du séjour'}</p>
                <p className="mt-1 text-sm text-neutral-500">Réservation #{booking.id} · {booking.villa.title}</p>

                {!isDeposit && (
                    <div className="mt-5 space-y-2 text-sm">
                        {option('wallet', 'Payer avec mon solde')}
                        {option('on_site', 'Payer sur place, au début du séjour')}
                    </div>
                )}

                <dl className="mt-5 space-y-1 rounded-2xl bg-neutral-50 p-4 text-sm">
                    <div className="flex justify-between"><dt>{isDeposit ? 'Acompte' : 'Solde du séjour'}</dt><dd className="font-semibold">{eur(amount)}</dd></div>
                    {!onSite && (
                        <>
                            <div className="flex justify-between"><dt>Votre solde</dt><dd>{eur(user.balance)}</dd></div>
                            <div className="flex justify-between"><dt>Solde après paiement</dt><dd>{eur(user.balance - amount)}</dd></div>
                        </>
                    )}
                </dl>

                {onSite && (
                    <p className="mt-3 text-sm text-neutral-600">
                        Vous réglerez {eur(amount)} directement au propriétaire à votre arrivée. Votre solde n'est pas débité.
                    </p>
                )}
                {blocked && (
                    <p className="mt-3 text-sm text-rose-600">
                        Solde insuffisant : il vous manque {eur(amount - user.balance)}. Récupérez des bonus dans « À propos & Contact » sur l'accueil.
                    </p>
                )}
                {error && <p className="mt-3 text-sm text-rose-600">{error}</p>}

                <div className="mt-6 flex justify-end gap-3">
                    <button onClick={onClose} className="rounded-full border border-neutral-300 px-5 py-2.5 text-sm">Annuler</button>
                    <button onClick={confirm} disabled={busy || blocked} className="rounded-full bg-black px-5 py-2.5 text-sm font-semibold text-white disabled:opacity-40">
                        {onSite ? 'Confirmer : payer sur place' : `Confirmer le paiement de ${eur(amount)}`}
                    </button>
                </div>
            </div>
        </div>
    );
}