import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { api } from '../api';
import { useAuth } from '../auth';

const field = 'w-full rounded-xl border border-neutral-300 px-4 py-3 text-sm outline-none focus:border-black';
const btn = 'rounded-full bg-black px-6 py-3 text-sm font-semibold text-white disabled:opacity-50';

function Msg({ m }) {
    if (!m.text) return null;
    return <p className={`text-sm ${m.type === 'ok' ? 'text-emerald-600' : 'text-rose-600'}`}>{m.text}</p>;
}

export default function Account() {
    const { user, setUser, logout } = useAuth();
    const nav = useNavigate();

    const [name, setName] = useState(user.name);
    const [nameMsg, setNameMsg] = useState({});
    const [pw, setPw] = useState({ current_password: '', password: '', password_confirmation: '' });
    const [pwMsg, setPwMsg] = useState({});
    const [confirming, setConfirming] = useState(false);
    const [delError, setDelError] = useState('');

    const isHost = user.role === 'host';

    const saveName = async (e) => {
        e.preventDefault();
        setNameMsg({});
        try {
            setUser(await api('/account/name', { method: 'PATCH', body: { name } }));
            setNameMsg({ type: 'ok', text: 'Nom mis à jour.' });
        } catch (err) { setNameMsg({ type: 'error', text: err.message }); }
    };

    const savePassword = async (e) => {
        e.preventDefault();
        setPwMsg({});
        try {
            const r = await api('/account/password', { method: 'PATCH', body: pw });
            setPw({ current_password: '', password: '', password_confirmation: '' });
            setPwMsg({ type: 'ok', text: r.message });
        } catch (err) { setPwMsg({ type: 'error', text: err.message }); }
    };

    const removeAccount = async () => {
        setDelError('');
        try {
            await api('/account', { method: 'DELETE' });
            await logout();
            nav('/');
        } catch (err) {
            setDelError(err.message);
            setConfirming(false);
        }
    };

    return (
        <div className="mx-auto max-w-2xl px-5 py-12 space-y-8">
            <div>
                <h1 className="text-5xl tracking-tight">Mon <span className="italic font-extralight">compte</span></h1>
                <p className="mt-3 text-sm text-neutral-500">{user.email} · {isHost ? 'Propriétaire' : 'Voyageur'}</p>
            </div>

            <form onSubmit={saveName} className="rounded-3xl border border-neutral-200 p-6 space-y-4">
                <h2 className="font-semibold text-lg">Changer mon nom</h2>
                <input className={field} value={name} onChange={(e) => setName(e.target.value)} required maxLength={120} />
                <Msg m={nameMsg} />
                <button className={btn}>Enregistrer</button>
            </form>

            <form onSubmit={savePassword} className="rounded-3xl border border-neutral-200 p-6 space-y-4">
                <h2 className="font-semibold text-lg">Changer mon mot de passe</h2>
                <input className={field} type="password" placeholder="Mot de passe actuel" value={pw.current_password} onChange={(e) => setPw({ ...pw, current_password: e.target.value })} required />
                <input className={field} type="password" placeholder="Nouveau mot de passe (8 caractères min.)" value={pw.password} onChange={(e) => setPw({ ...pw, password: e.target.value })} required />
                <input className={field} type="password" placeholder="Confirmer le nouveau mot de passe" value={pw.password_confirmation} onChange={(e) => setPw({ ...pw, password_confirmation: e.target.value })} required />
                <Msg m={pwMsg} />
                <button className={btn}>Modifier le mot de passe</button>
            </form>

            <div className="rounded-3xl border border-rose-200 bg-rose-50/50 p-6 space-y-4">
                <h2 className="font-semibold text-lg text-rose-800">Supprimer mon compte</h2>
                {delError && <p className="text-sm text-rose-600">{delError}</p>}
                <button onClick={() => setConfirming(true)} className="rounded-full border border-rose-400 px-6 py-3 text-sm font-semibold text-rose-700 hover:bg-rose-100">
                    Supprimer mon compte
                </button>
            </div>

            {confirming && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-5" onClick={() => setConfirming(false)}>
                    <div className="w-full max-w-md rounded-3xl bg-white p-8 shadow-2xl" onClick={(e) => e.stopPropagation()}>
                        <p className="text-lg font-semibold">Voulez-vous vraiment supprimer votre compte ?</p>
                        {isHost && (
                            <p className="mt-3 text-sm text-neutral-600">
                                Toutes les informations concernant votre villa seront effacées. Ceci est irréversible.
                            </p>
                        )}
                        <div className="mt-8 flex justify-end gap-3">
                            <button onClick={() => setConfirming(false)} className="rounded-full border border-neutral-300 px-5 py-2.5 text-sm">Annuler</button>
                            <button onClick={removeAccount} className="rounded-full bg-rose-600 px-5 py-2.5 text-sm font-semibold text-white">Oui, supprimer</button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}