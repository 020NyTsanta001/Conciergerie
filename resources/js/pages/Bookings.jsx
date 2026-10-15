import { useEffect, useState } from 'react';
import { api, eur, STATUS } from '../api';
import { useAuth } from '../auth';
import { useDots } from '../dots';
import PayModal from '../components/PayModal';

const ACTIONS = {
    validate: ['Valider', 'bg-black text-white'],
    pay_deposit: ["Payer l'acompte", 'bg-black text-white'],
    pay_balance: ['Payer le solde', 'bg-black text-white'],
    confirm_onsite: ['Valider le paiement reçu sur place', 'bg-emerald-600 text-white'],
    start: ['Démarrer le séjour', 'bg-emerald-600 text-white'],
    close: ['Clôturer', 'bg-neutral-700 text-white'],
    cancel: ['Annuler / refuser', 'border border-rose-300 text-rose-700'],
    request_refund: ["Demander le remboursement de l'acompte", 'border border-amber-400 text-amber-800'],
    refund: ['Accepter le remboursement', 'bg-amber-600 text-white'],
    decline_refund: ['Refuser le remboursement', 'border border-rose-300 text-rose-700'],
};

/** Fenêtre avec une cause à écrire (obligatoire) : demande de remboursement du voyageur, refus de l'hôte. */
function ReasonDialog({ title, intro, placeholder, confirmLabel, confirmClass, onClose, onSubmit }) {
    const [reason, setReason] = useState('');
    const [error, setError] = useState('');
    const [busy, setBusy] = useState(false);

    const submit = async () => {
        setBusy(true);
        setError('');
        try {
            await onSubmit(reason.trim());
        } catch (e) {
            setError(e.message);
            setBusy(false);
        }
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-5" onClick={onClose}>
            <div className="w-full max-w-md rounded-3xl bg-white p-8 shadow-2xl" onClick={(e) => e.stopPropagation()}>
                <p className="text-lg font-semibold">{title}</p>
                <p className="mt-3 text-sm text-neutral-600">{intro}</p>
                <textarea
                    className="mt-4 w-full rounded-xl border border-neutral-300 px-4 py-3 text-sm outline-none focus:border-black"
                    rows="4"
                    maxLength={500}
                    placeholder={placeholder}
                    value={reason}
                    onChange={(e) => setReason(e.target.value)}
                />
                {error && <p className="mt-2 text-sm text-rose-600">{error}</p>}
                <div className="mt-6 flex justify-end gap-3">
                    <button onClick={onClose} className="rounded-full border border-neutral-300 px-5 py-2.5 text-sm">Annuler</button>
                    <button onClick={submit} disabled={busy || reason.trim().length < 5} className={`rounded-full px-5 py-2.5 text-sm font-semibold disabled:opacity-50 ${confirmClass}`}>
                        {confirmLabel}
                    </button>
                </div>
            </div>
        </div>
    );
}

export default function Bookings() {
    const { user, refresh } = useAuth();
    const { clear } = useDots();
    const [items, setItems] = useState([]);
    const [notifs, setNotifs] = useState([]);
    const [payouts, setPayouts] = useState(null);
    const [error, setError] = useState('');
    const [pay, setPay] = useState(null);             // { booking, kind } : fenêtre de paiement du voyageur
    const [onsite, setOnsite] = useState(null);       // réservation dont l'hôte valide le paiement reçu sur place
    const [refund, setRefund] = useState(null);       // réservation dont l'hôte accepte le remboursement
    const [askRefund, setAskRefund] = useState(null); // réservation pour laquelle le voyageur écrit sa demande
    const [decline, setDecline] = useState(null);     // réservation dont l'hôte écrit la cause du refus

    const load = () => {
        api('/bookings').then((r) => setItems(r.data));
        // les nouvelles notifications restent en gras pendant cette visite ; le point rouge « Réservations » disparaît
        api('/notifications').then((n) => { setNotifs(n); clear('bookings'); });
        if (user.role === 'host') api('/host/payouts').then(setPayouts).catch(() => {});
    };
    useEffect(load, []);

    const run = async (b, action) => {
        setError('');
        if (action === 'pay_deposit') return setPay({ booking: b, kind: 'deposit' });
        if (action === 'pay_balance') return setPay({ booking: b, kind: 'balance' });
        if (action === 'confirm_onsite') return setOnsite(b);
        if (action === 'refund') return setRefund(b);               // la requête part seulement après confirmation
        if (action === 'request_refund') return setAskRefund(b);    // idem, avec la cause écrite par le voyageur
        if (action === 'decline_refund') return setDecline(b);      // idem, avec la cause écrite par l'hôte
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

    // actions avec cause : en cas d'erreur la fenêtre reste ouverte et affiche le message
    const sendWithReason = (target, action, close) => async (reason) => {
        await api(`/bookings/${target.id}/status`, { method: 'PATCH', body: { action, reason } });
        close(null);
        await refresh();
        load();
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
                    {notifs.slice(0, 8).map((n) => (
                        <p key={n.id} className={n.read ? '' : 'font-semibold'}>• {n.message}</p>
                    ))}
                </div>
            )}
            {error && <p className="mb-4 text-sm text-rose-600">{error}</p>}

            <div className="space-y-4">
                {items.length === 0 && <p className="text-neutral-500">Aucune réservation.</p>}
                {items.map((b) => {
                    const [label, cls] = STATUS[b.status];
                    const rf = b.refund ?? {};
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

                                {/* ----- suivi de la demande de remboursement ----- */}
                                {rf.status === 'requested' && (
                                    <p className="mt-2 rounded-xl bg-amber-50 px-3 py-2 text-amber-900">
                                        {user.role === 'client'
                                            ? "Demande de remboursement envoyée : en attente de la réponse du propriétaire."
                                            : "Le voyageur demande le remboursement de l'acompte."}
                                        <br />Cause : {rf.reason}
                                    </p>
                                )}
                                {rf.status === 'declined' && (
                                    <p className="mt-2 rounded-xl bg-rose-50 px-3 py-2 text-rose-900">
                                        Remboursement refusé par le propriétaire.<br />Cause du refus : {rf.decline_reason}
                                    </p>
                                )}
                                {rf.status === 'accepted' && (
                                    <p className="mt-2 rounded-xl bg-emerald-50 px-3 py-2 text-emerald-900">Acompte remboursé : la réservation est annulée.</p>
                                )}
                                {user.role === 'client' && b.status === 'deposit_paid' && !rf.status && (
                                    <p className="mt-1 text-neutral-500">Un empêchement ? Avant la date du séjour, demandez le remboursement de l'acompte : le propriétaire l'acceptera ou le refusera.</p>
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
            </div>

            {pay && (
                <PayModal
                    booking={pay.booking}
                    kind={pay.kind}
                    onClose={() => setPay(null)}
                    onDone={() => { setPay(null); load(); }}
                />
            )}

            {askRefund && (
                <ReasonDialog
                    title={`Demander le remboursement de l'acompte (${eur(askRefund.deposit_amount)}) ?`}
                    intro={`Réservation #${askRefund.id} : expliquez au propriétaire pourquoi vous demandez le remboursement. Il pourra l'accepter ou le refuser (avec sa propre cause). Une seule demande par réservation.`}
                    placeholder="Ex. : un empêchement familial m'oblige à annuler mon voyage…"
                    confirmLabel="Envoyer la demande"
                    confirmClass="bg-amber-600 text-white"
                    onClose={() => setAskRefund(null)}
                    onSubmit={sendWithReason(askRefund, 'request_refund', setAskRefund)}
                />
            )}

            {decline && (
                <ReasonDialog
                    title="Refuser le remboursement ?"
                    intro={`Réservation #${decline.id} : le voyageur demande le remboursement (cause : « ${decline.refund?.reason} »). Écrivez la cause de votre refus : elle lui sera envoyée dans sa notification.`}
                    placeholder="Ex. : les dates sont trop proches du séjour, des frais ont déjà été engagés…"
                    confirmLabel="Refuser et envoyer la cause"
                    confirmClass="bg-rose-600 text-white"
                    onClose={() => setDecline(null)}
                    onSubmit={sendWithReason(decline, 'decline_refund', setDecline)}
                />
            )}

            {refund && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-5" onClick={() => setRefund(null)}>
                    <div className="w-full max-w-md rounded-3xl bg-white p-8 shadow-2xl" onClick={(e) => e.stopPropagation()}>
                        <p className="text-lg font-semibold">Accepter le remboursement de {eur(refund.deposit_amount)} ?</p>
                        <p className="mt-3 text-sm text-neutral-600">
                            Réservation #{refund.id} : l'acompte est repris sur votre solde et rendu au voyageur (aucune cause à fournir).
                            La réservation sera annulée et la villa redeviendra disponible pour ces dates. Cette action est irréversible.
                        </p>
                        {refund.refund?.reason && <p className="mt-3 rounded-xl bg-neutral-100 px-3 py-2 text-sm">Cause du voyageur : {refund.refund.reason}</p>}
                        <div className="mt-8 flex justify-end gap-3">
                            <button onClick={() => setRefund(null)} className="rounded-full border border-neutral-300 px-5 py-2.5 text-sm">Annuler</button>
                            <button onClick={confirmRefund} className="rounded-full bg-amber-600 px-5 py-2.5 text-sm font-semibold text-white">Oui, rembourser</button>
                        </div>
                    </div>
                </div>
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
