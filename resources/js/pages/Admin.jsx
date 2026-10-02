import { useEffect, useState } from 'react';
import { api, eur } from '../api';

const TABS = [['villas', 'Annonces'], ['client', 'Voyageurs'], ['host', 'Propriétaires'], ['activity', 'Activité']];
const ROLE_DOT = { client: 'bg-sky-500', host: 'bg-amber-500', admin: 'bg-neutral-800' };
const field = 'rounded-full border border-neutral-300 px-4 py-2.5 text-sm outline-none focus:border-black';

const fmtDate = (iso) => new Date(iso).toLocaleDateString('fr-FR');
const fmtWhen = (iso) => {
    const d = new Date(iso);
    return `à ${d.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })} · ${d.toLocaleDateString('fr-FR')}`;
};

/* ---------- Onglet 1 : annonces à modérer ---------- */
function PendingVillas() {
    const [villas, setVillas] = useState([]);
    const load = () => api('/admin/properties', { params: { status: 'pending' } }).then((r) => setVillas(r.data));
    useEffect(() => { load(); }, []);

    const moderate = async (v, status) => {
        await api(`/admin/properties/${v.id}/moderate`, { method: 'PATCH', body: { status } });
        load();
    };

    return (
        <div className="space-y-3">
            {villas.length === 0 && <p className="text-neutral-500">Aucune annonce en attente.</p>}
            {villas.map((v) => (
                <div key={v.id} className="flex items-center gap-4 rounded-2xl border border-neutral-200 p-4">
                    <img src={v.images?.[0]} alt="" className="h-16 w-24 rounded-xl object-cover bg-neutral-200" />
                    <div className="flex-1 text-sm"><p className="font-semibold">{v.title}</p><p className="text-neutral-500">{v.city} · {eur(v.price_per_night)} · par {v.owner?.name}</p></div>
                    <button onClick={() => moderate(v, 'approved')} className="rounded-full bg-black text-white px-4 py-2 text-xs font-semibold">Approuver</button>
                    <button onClick={() => moderate(v, 'rejected')} className="rounded-full border border-rose-300 text-rose-700 px-4 py-2 text-xs font-semibold">Rejeter</button>
                </div>
            ))}
        </div>
    );
}

/* ---------- Onglets 2 et 3 : comptes voyageurs / propriétaires ---------- */
function UsersTab({ role, onActivity }) {
    const [users, setUsers] = useState([]);
    const [q, setQ] = useState('');
    const [error, setError] = useState('');

    const load = () => api('/admin/users', { params: { role, search: q } }).then(setUsers).catch((e) => setError(e.message));
    useEffect(() => { load(); }, []); // le composant est remonté à chaque changement d'onglet (key)

    const toggle = async (u) => {
        setError('');
        try {
            await api(`/admin/users/${u.id}/suspend`, { method: 'PATCH', body: { suspended: !u.suspended } });
            load();
        } catch (e) { setError(e.message); }
    };

    return (
        <div>
            <form onSubmit={(e) => { e.preventDefault(); load(); }} className="mb-5 flex gap-3">
                <input className={`${field} flex-1`} placeholder="Rechercher par nom ou e-mail" value={q} onChange={(e) => setQ(e.target.value)} />
                <button className="rounded-full bg-black px-6 text-sm text-white">Rechercher</button>
            </form>
            {error && <p className="mb-3 text-sm text-rose-600">{error}</p>}
            <p className="mb-3 text-sm text-neutral-500">{users.length} compte(s)</p>
            <div className="space-y-3">
                {users.map((u) => (
                    <div key={u.id} className="flex flex-wrap items-center gap-4 rounded-2xl border border-neutral-200 p-4">
                        <div className="min-w-52 flex-1 text-sm">
                            <p className="font-semibold">
                                {u.name}
                                {u.suspended && <span className="ml-2 rounded-full bg-rose-100 px-2 py-0.5 text-xs text-rose-700">Suspendu</span>}
                            </p>
                            <p className="text-neutral-500">{u.email}{u.phone ? ` · ${u.phone}` : ''}</p>
                            <p className="text-neutral-500">
                                Inscrit le {fmtDate(u.created_at)} · {role === 'host' ? `${u.villas_count} villa(s)` : `${u.bookings_count} réservation(s)`}
                            </p>
                        </div>
                        <button onClick={() => onActivity(u)} className="rounded-full border border-neutral-300 px-4 py-2 text-xs font-semibold">Voir l'activité</button>
                        <button
                            onClick={() => toggle(u)}
                            className={`rounded-full px-4 py-2 text-xs font-semibold ${u.suspended ? 'bg-black text-white' : 'border border-rose-300 text-rose-700'}`}
                        >
                            {u.suspended ? 'Réactiver' : 'Suspendre'}
                        </button>
                    </div>
                ))}
            </div>
        </div>
    );
}

/* ---------- Onglet 4 : journal d'activité ---------- */
function ActivityTab({ user, onClear }) {
    const [role, setRole] = useState('');
    const [page, setPage] = useState(1);
    const [res, setRes] = useState({ data: [], current_page: 1, last_page: 1 });

    useEffect(() => {
        api('/admin/activity', { params: { role, user_id: user?.id, page } }).then(setRes);
    }, [role, user, page]);

    return (
        <div>
            <div className="mb-5 flex flex-wrap items-center gap-3">
                <select className={field} value={role} onChange={(e) => { setRole(e.target.value); setPage(1); }}>
                    <option value="">Tous les profils</option>
                    <option value="client">Voyageurs</option>
                    <option value="host">Propriétaires</option>
                    <option value="admin">Concierges</option>
                </select>
                {user && (
                    <span className="flex items-center gap-2 rounded-full bg-neutral-100 px-4 py-2 text-sm">
                        Compte : <b>{user.name}</b>
                        <button onClick={onClear} className="text-neutral-500 hover:text-black" aria-label="Retirer le filtre">✕</button>
                    </span>
                )}
            </div>

            <ul className="divide-y divide-neutral-100 rounded-2xl border border-neutral-200">
                {res.data.length === 0 && <li className="p-5 text-sm text-neutral-500">Aucune activité.</li>}
                {res.data.map((l) => (
                    <li key={l.id} className="flex items-start gap-3 p-4 text-sm">
                        <span className={`mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full ${ROLE_DOT[l.actor_role] ?? 'bg-neutral-400'}`} />
                        <p className="flex-1">{l.description}</p>
                        <span className="shrink-0 text-xs text-neutral-500">{fmtWhen(l.created_at)}</span>
                    </li>
                ))}
            </ul>

            {res.last_page > 1 && (
                <div className="mt-5 flex items-center justify-center gap-4 text-sm">
                    <button disabled={page <= 1} onClick={() => setPage(page - 1)} className="rounded-full border border-neutral-300 px-4 py-2 disabled:opacity-40">← Précédent</button>
                    <span className="text-neutral-500">Page {res.current_page} / {res.last_page}</span>
                    <button disabled={page >= res.last_page} onClick={() => setPage(page + 1)} className="rounded-full border border-neutral-300 px-4 py-2 disabled:opacity-40">Suivant →</button>
                </div>
            )}
        </div>
    );
}

/* ---------- Page ---------- */
export default function Admin() {
    const [tab, setTab] = useState('villas');
    const [activityUser, setActivityUser] = useState(null);

    const openActivity = (u) => { setActivityUser(u); setTab('activity'); };

    return (
        <div className="mx-auto max-w-5xl px-5 py-12">
            <h1 className="mb-6 text-4xl tracking-tight">Espace concierge</h1>

            <div className="mb-8 inline-flex rounded-full bg-neutral-100 p-1 text-sm font-medium">
                {TABS.map(([key, label]) => (
                    <button
                        key={key}
                        onClick={() => { if (key !== 'activity') setActivityUser(null); setTab(key); }}
                        className={`rounded-full px-5 py-2 transition-colors ${tab === key ? 'bg-black text-white' : 'text-neutral-600 hover:text-black'}`}
                    >
                        {label}
                    </button>
                ))}
            </div>

            {tab === 'villas' && <PendingVillas />}
            {(tab === 'client' || tab === 'host') && <UsersTab key={tab} role={tab} onActivity={openActivity} />}
            {tab === 'activity' && <ActivityTab user={activityUser} onClear={() => setActivityUser(null)} />}
        </div>
    );
}