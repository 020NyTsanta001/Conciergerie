import { useEffect, useState } from 'react';
import { api } from '../api';
import { useAuth } from '../auth';
import { Stars, StarInput } from './Stars';

const fmt = (iso) => new Date(iso).toLocaleDateString('fr-FR', { month: 'short', year: 'numeric' });

/** Avis d'une villa. data = { average, count, items } (chargé par la page) ; reload() le recharge. */
export default function Reviews({ villaId, data, reload }) {
    const { user } = useAuth();
    const [eligible, setEligible] = useState(null);  // séjour clôturé de cette villa, sans avis encore
    const [form, setForm] = useState({ rating: 0, comment: '' });
    const [editing, setEditing] = useState(null);     // id de l'avis en cours de modification
    const [error, setError] = useState('');

    // Le voyageur peut donner son avis s'il a un séjour clôturé dans cette villa, sans avis
    useEffect(() => {
        if (user?.role !== 'client') { setEligible(null); return; }
        api('/bookings')
            .then((r) => setEligible(r.data.find((b) => b.villa.id === villaId && b.status === 'closed' && !b.review) ?? null))
            .catch(() => {});
    }, [user?.id, villaId, data]);

    const submit = async (e) => {
        e.preventDefault();
        setError('');
        if (form.rating < 1) return setError('Choisissez une note de 1 à 5 étoiles.');
        try {
            if (editing) await api(`/reviews/${editing}`, { method: 'PUT', body: form });
            else await api(`/bookings/${eligible.id}/review`, { method: 'POST', body: form });
            setForm({ rating: 0, comment: '' });
            setEditing(null);
            reload();
        } catch (err) { setError(err.message); }
    };

    const startEdit = (r) => { setEditing(r.id); setForm({ rating: r.rating, comment: r.comment ?? '' }); setError(''); };
    const cancel = () => { setEditing(null); setForm({ rating: 0, comment: '' }); setError(''); };

    const remove = async (r) => {
        if (!window.confirm('Supprimer cet avis ?')) return;
        try { await api(`/reviews/${r.id}`, { method: 'DELETE' }); reload(); }
        catch (err) { setError(err.message); }
    };

    const items = data?.items ?? [];

    return (
        <div className="mt-10 border-t border-neutral-100 pt-6">
            <h2 className="font-semibold">Avis des voyageurs</h2>
            {data?.count > 0 && (
                <p className="mt-1 flex items-center gap-2 text-xs text-neutral-500">
                    <Stars value={data.average} /> {data.average.toFixed(1)} sur 5 · {data.count} avis
                </p>
            )}

            {(eligible || editing) && (
                <form onSubmit={submit} className="mt-4 space-y-3 rounded-2xl bg-neutral-50 p-4">
                    <p className="text-sm font-medium">{editing ? 'Modifier mon avis' : 'Donner mon avis sur mon séjour'}</p>
                    <StarInput value={form.rating} onChange={(rating) => setForm({ ...form, rating })} />
                    <textarea
                        className="w-full rounded-xl border border-neutral-300 px-3 py-2 text-sm outline-none focus:border-black"
                        rows="2"
                        maxLength={300}
                        placeholder="Un petit mot (facultatif, 300 caractères max.)"
                        value={form.comment}
                        onChange={(e) => setForm({ ...form, comment: e.target.value })}
                    />
                    {error && <p className="text-xs text-rose-600">{error}</p>}
                    <div className="flex gap-2">
                        <button className="rounded-full bg-black px-4 py-2 text-xs font-semibold text-white">{editing ? 'Enregistrer' : 'Publier'}</button>
                        {editing && <button type="button" onClick={cancel} className="rounded-full border border-neutral-300 px-4 py-2 text-xs">Annuler</button>}
                    </div>
                </form>
            )}
            {!eligible && !editing && error && <p className="mt-3 text-xs text-rose-600">{error}</p>}

            <ul className="mt-4 space-y-4">
                {items.length === 0 && <li className="text-sm text-neutral-400">Aucun avis pour le moment.</li>}
                {items.map((r) => {
                    const mine = user?.id === r.user_id;
                    return (
                        <li key={r.id} className="text-sm">
                            <div className="flex flex-wrap items-center gap-x-3 gap-y-1">
                                <Stars value={r.rating} />
                                <span className="font-medium">{r.author}</span>
                                <span className="text-xs text-neutral-400">{fmt(r.created_at)}{r.updated_at !== r.created_at ? ' · modifié' : ''}</span>
                                <span className="ml-auto flex gap-3 text-xs text-neutral-500">
                                    {mine && <button onClick={() => startEdit(r)} className="hover:text-black">Modifier</button>}
                                    {(mine || user?.role === 'admin') && <button onClick={() => remove(r)} className="hover:text-rose-600">Supprimer</button>}
                                </span>
                            </div>
                            {r.comment && <p className="mt-1 break-words text-neutral-600">{r.comment}</p>}
                        </li>
                    );
                })}
            </ul>
        </div>
    );
}