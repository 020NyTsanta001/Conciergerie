import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../auth';

const field = 'w-full rounded-xl border border-neutral-300 px-4 py-3 text-sm outline-none focus:border-black';

export default function Login() {
    const { login, register } = useAuth();
    const nav = useNavigate();
    const [mode, setMode] = useState('login');
    const [f, setF] = useState({ name: '', email: '', password: '', role: 'client' });
    const [error, setError] = useState('');
    const set = (k) => (e) => setF({ ...f, [k]: e.target.value });

    const submit = async (e) => {
        e.preventDefault();
        setError('');
        try {
            if (mode === 'login') await login({ email: f.email, password: f.password });
            else await register(f);
            nav('/');
        } catch (err) { setError(err.message); }
    };

    return (
        <form onSubmit={submit} className="mx-auto max-w-md px-5 py-20 space-y-4">
            <h1 className="text-4xl tracking-tight mb-6">{mode === 'login' ? 'Connexion' : 'Créer un compte'}</h1>
            {mode === 'register' && (
                <>
                    <input className={field} placeholder="Nom complet" value={f.name} onChange={set('name')} required />
                    <select className={field} value={f.role} onChange={set('role')}>
                        <option value="client">Voyageur</option>
                        <option value="host">Propriétaire</option>
                    </select>
                </>
            )}
            <input className={field} type="email" placeholder="E-mail" value={f.email} onChange={set('email')} required />
            <input className={field} type="password" placeholder="Mot de passe (8 caractères min.)" value={f.password} onChange={set('password')} required />
            {error && <p className="text-sm text-rose-600">{error}</p>}
            <button className="w-full rounded-full bg-black text-white py-3.5 text-sm font-semibold">{mode === 'login' ? 'Se connecter' : "S'inscrire"}</button>
            <button type="button" onClick={() => setMode(mode === 'login' ? 'register' : 'login')} className="text-sm text-neutral-500 underline">
                {mode === 'login' ? 'Pas de compte ? Inscription' : 'Déjà inscrit ? Connexion'}
            </button>
            <p className="text-xs text-neutral-400 pt-4">Démo : client@luxe.test · host@luxe.test · admin@luxe.test / password</p>
        </form>
    );
}
