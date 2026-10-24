import { useEffect, useRef, useState } from 'react';
import { api, eur } from '../api';
import { toAr, fromAr, currencyLabel } from '../money';
import { useDots } from '../dots';

const field = 'rounded-xl border border-neutral-300 px-3 py-2.5 text-sm w-full outline-none focus:border-black';
const empty = {
    title: '', description: '', city: '', country: '', price_per_night: '',
    bedrooms: 1, capacity: 2, has_pool: false, has_yacht: false, images: ['', '', ''],
    yacht_image: '', yacht_name: '', yacht_description: '', yacht_place1: '', yacht_place2: '',
    chef_price: '', chauffeur_price: '', yacht_price: '',
};
const BADGE = { pending: 'bg-amber-100 text-amber-800', approved: 'bg-emerald-100 text-emerald-800', rejected: 'bg-rose-100 text-rose-800' };

export default function Host() {
    const { clear } = useDots();
    const [villas, setVillas] = useState([]);
    const [notifs, setNotifs] = useState([]);
    const [f, setF] = useState(empty);
    const [editing, setEditing] = useState(null);   // villa en cours de modification (null = création)
    const [deleting, setDeleting] = useState(null); // villa en attente de confirmation de suppression
    const [msg, setMsg] = useState('');
    const formRef = useRef(null);

    const load = () => {
        api('/host/properties').then((r) => setVillas(r.data));
        // le point rouge « Mes villas » disparaît quand le propriétaire ouvre la page ; les nouvelles notifications restent en gras
        api('/notifications').then((n) => { setNotifs(n.filter((x) => x.area !== 'bookings')); clear('villas'); }).catch(() => {});
    };
    useEffect(() => { load(); }, []);

    const set = (k) => (e) => setF({ ...f, [k]: e.target.type === 'checkbox' ? e.target.checked : e.target.value });

    const startEdit = (v) => {
        setEditing(v);
        setMsg('');
        setF({
            title: v.title, description: v.description, city: v.city, country: v.country,
            price_per_night: fromAr(v.price_per_night), bedrooms: v.bedrooms, capacity: v.capacity,
            has_pool: v.has_pool, has_yacht: v.has_yacht, images: [0, 1, 2].map((i) => v.images?.[i] ?? ''),
            yacht_image: v.yacht_image ?? '', yacht_name: v.yacht_name ?? '', yacht_description: v.yacht_description ?? '',
            yacht_place1: v.yacht_places?.[0] ?? '', yacht_place2: v.yacht_places?.[1] ?? '',
            chef_price: v.chef_price != null ? fromAr(v.chef_price) : '',
            chauffeur_price: v.chauffeur_price != null ? fromAr(v.chauffeur_price) : '',
            yacht_price: v.yacht_price != null ? fromAr(v.yacht_price) : '',
        });
        formRef.current?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };

    const cancelEdit = () => { setEditing(null); setF(empty); };

    const submit = async (e) => {
        e.preventDefault();
        setMsg('');
        const { images, yacht_image, yacht_name, yacht_description, yacht_place1, yacht_place2, ...rest } = f;
        const body = {
            ...rest, price_per_night: toAr(rest.price_per_night),
            images: images.map((u) => u.trim()), // exactement 3 photos, vérifié aussi par le serveur
            // prix des services (monnaie du compte -> Ar) ; vide = service non proposé
            chef_price: rest.chef_price !== '' ? toAr(rest.chef_price) : null,
            chauffeur_price: rest.chauffeur_price !== '' ? toAr(rest.chauffeur_price) : null,
            yacht_price: f.has_yacht && rest.yacht_price !== '' ? toAr(rest.yacht_price) : null,
            yacht_image: f.has_yacht ? yacht_image : null,
            yacht_name: f.has_yacht ? yacht_name.trim() : null,
            yacht_description: f.has_yacht ? yacht_description.trim() : null,
            yacht_places: f.has_yacht ? [yacht_place1, yacht_place2].map((s) => s.trim()).filter(Boolean) : null,
        };
        try {
            if (editing) {
                const r = await api(`/host/properties/${editing.id}`, { method: 'PUT', body });
                setMsg(
                    r.data.status === 'pending'
                        ? 'Villa modifiée : elle est retirée du site et renvoyée en modération. Elle réapparaîtra après approbation.'
                        : 'Aucun changement à enregistrer.'
                );
            } else {
                await api('/host/properties', { method: 'POST', body });
                setMsg('Villa soumise : en attente de validation par un concierge.');
            }
            setEditing(null);
            setF(empty);
            load();
        } catch (err) { setMsg(err.message); }
    };

    const confirmDelete = async () => {
        try {
            await api(`/host/properties/${deleting.id}`, { method: 'DELETE' });
            if (editing?.id === deleting.id) cancelEdit();
            setMsg('Villa supprimée.');
            load();
        } catch (err) { setMsg(err.message); }
        setDeleting(null);
    };

    return (
        <div className="mx-auto max-w-6xl px-5 py-12 grid lg:grid-cols-2 gap-10">
            {notifs.length > 0 && (
                <div className="lg:col-span-2 rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm space-y-1">
                    <p className="font-semibold">Notifications</p>
                    {notifs.slice(0, 5).map((n) => <p key={n.id} className={n.read ? '' : 'font-semibold'}>• {n.message}</p>)}
                </div>
            )}
            {msg && <p className="lg:col-span-2 rounded-2xl bg-neutral-100 px-5 py-3 text-sm">{msg}</p>}

            <div>
                <h1 className="text-4xl tracking-tight mb-6">Mes villas</h1>
                <div className="space-y-3">
                    {villas.length === 0 && <p className="text-neutral-500">Aucune villa pour le moment.</p>}
                    {villas.map((v) => (
                        <div key={v.id} className="flex flex-wrap items-center gap-4 rounded-2xl border border-neutral-200 p-4">
                            <img src={v.images?.[0]} alt="" className="h-16 w-24 rounded-xl object-cover bg-neutral-200" />
                            <div className="min-w-40 flex-1 text-sm">
                                <p className="font-semibold">{v.title}</p>
                                <p className="text-neutral-500">
                                    {v.city} · {eur(v.price_per_night)} / nuit{v.has_pool ? ' · Piscine' : ''}{v.has_yacht ? ' · Yacht' : ''}
                                </p>
                            </div>
                            <span className={`rounded-full px-3 py-1 text-xs ${BADGE[v.status]}`}>{v.status}</span>
                            <div className="flex gap-2">
                                <button onClick={() => startEdit(v)} className="rounded-full border border-neutral-300 px-4 py-2 text-xs font-semibold">Modifier</button>
                                <button onClick={() => setDeleting(v)} className="rounded-full border border-rose-300 px-4 py-2 text-xs font-semibold text-rose-700">Supprimer</button>
                            </div>
                        </div>
                    ))}
                </div>
            </div>

            <form ref={formRef} onSubmit={submit} className="space-y-3 rounded-3xl border border-neutral-200 p-6 h-fit">
                <h2 className="font-semibold text-lg">{editing ? `Modifier « ${editing.title} »` : 'Publier une villa'}</h2>
                <input className={field} placeholder="Titre" value={f.title} onChange={set('title')} required />
                <textarea className={field} placeholder="Description" value={f.description} onChange={set('description')} required />
                <div className="grid grid-cols-2 gap-3">
                    <input className={field} placeholder="Ville" value={f.city} onChange={set('city')} required />
                    <input className={field} placeholder="Pays" value={f.country} onChange={set('country')} required />
                    <input className={field} type="number" placeholder={`Prix / nuit (${currencyLabel()})`} value={f.price_per_night} onChange={set('price_per_night')} required />
                    <input className={field} type="number" min="1" placeholder="Chambres" value={f.bedrooms} onChange={set('bedrooms')} />
                    <input className={field} type="number" min="1" placeholder="Capacité" value={f.capacity} onChange={set('capacity')} />
                    <div className="flex items-center gap-5 text-sm">
                        <label className="flex items-center gap-2"><input type="checkbox" checked={f.has_pool} onChange={set('has_pool')} /> Piscine</label>
                        <label className="flex items-center gap-2"><input type="checkbox" checked={f.has_yacht} onChange={set('has_yacht')} /> Yacht</label>
                    </div>
                </div>

                {f.has_yacht && (
                    <div className="space-y-3 rounded-2xl bg-neutral-50 p-4">
                        <p className="text-xs font-semibold uppercase tracking-widest text-neutral-500">Votre yacht</p>
                        <input className={field} type="url" placeholder="Lien de la photo du yacht (https://…)" value={f.yacht_image} onChange={set('yacht_image')} required />
                        <input className={field} placeholder="Nom du yacht" maxLength={80} value={f.yacht_name} onChange={set('yacht_name')} required />
                        <textarea className={field} rows="3" maxLength={300} placeholder="Courte description du yacht (300 caractères max.)" value={f.yacht_description} onChange={set('yacht_description')} required />
                        <input className={field} type="number" min="1" placeholder={`Prix de la sortie en yacht (${currencyLabel()})`} value={f.yacht_price} onChange={set('yacht_price')} required />
                        <div className="grid grid-cols-2 gap-3">
                            <input className={field} maxLength={80} placeholder="Lieu de sortie n°1" value={f.yacht_place1} onChange={set('yacht_place1')} required />
                            <input className={field} maxLength={80} placeholder="Lieu de sortie n°2 (facultatif)" value={f.yacht_place2} onChange={set('yacht_place2')} />
                        </div>
                    </div>
                )}

                <div className="space-y-3 rounded-2xl bg-neutral-50 p-4">
                    <p className="text-xs font-semibold uppercase tracking-widest text-neutral-500">Services que vous proposez (facultatif)</p>
                    <p className="text-xs text-neutral-500">Indiquez votre prix pour chaque service que vous assurez ; laissez vide si vous ne le proposez pas.</p>
                    <div className="grid grid-cols-2 gap-3">
                        <input className={field} type="number" min="1" placeholder={`Chef à domicile (${currencyLabel()})`} value={f.chef_price} onChange={set('chef_price')} />
                        <input className={field} type="number" min="1" placeholder={`Chauffeur privé (${currencyLabel()})`} value={f.chauffeur_price} onChange={set('chauffeur_price')} />
                    </div>
                </div>

                <div className="space-y-3">
                    <p className="text-xs font-semibold uppercase tracking-widest text-neutral-500">Photos de la villa (exactement 3)</p>
                    {[0, 1, 2].map((i) => (
                        <input
                            key={i}
                            className={field}
                            type="url"
                            placeholder={`Lien de la photo ${i + 1} (https://…)`}
                            value={f.images[i]}
                            onChange={(e) => setF({ ...f, images: f.images.map((u, j) => (j === i ? e.target.value : u)) })}
                            required
                        />
                    ))}
                </div>

                <div className="flex gap-3">
                    <button className="rounded-full bg-black text-white px-6 py-3 text-sm font-semibold">{editing ? 'Enregistrer les modifications' : 'Soumettre'}</button>
                    {editing && <button type="button" onClick={cancelEdit} className="rounded-full border border-neutral-300 px-6 py-3 text-sm">Annuler</button>}
                </div>
            </form>

            {deleting && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-5" onClick={() => setDeleting(null)}>
                    <div className="w-full max-w-md rounded-3xl bg-white p-8 shadow-2xl" onClick={(e) => e.stopPropagation()}>
                        <p className="text-lg font-semibold">Voulez-vous vraiment supprimer « {deleting.title} » ?</p>
                        <p className="mt-3 text-sm text-neutral-600">Cette action est irréversible.</p>
                        <div className="mt-8 flex justify-end gap-3">
                            <button onClick={() => setDeleting(null)} className="rounded-full border border-neutral-300 px-5 py-2.5 text-sm">Annuler</button>
                            <button onClick={confirmDelete} className="rounded-full bg-rose-600 px-5 py-2.5 text-sm font-semibold text-white">Oui, supprimer</button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}