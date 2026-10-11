import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { api, eur } from '../api';
import { useAuth } from '../auth';

// ⚠️ À renseigner : adresse du GitHub de l'entreprise (stage). Vide = bouton grisé, sans bonus.
const LINKS = {
    github: 'https://github.com/020NyTsanta001',
    orgGithub: 'https://github.com/020NyTsanta001', // '
    cv: '/cv.pdf',  // fichier à placer dans public/cv.pdf
};
const PHOTO = '/images/profile.jpg';

const field = 'w-full rounded-xl border border-neutral-300 px-4 py-3 text-sm outline-none focus:border-black';

function LinkButton({ to, href, primary = false, reward, claimed, onVisit, children }) {
    const base = 'flex w-full items-center justify-between gap-2 rounded-full px-5 py-3.5 text-sm font-semibold transition-colors';
    const style = primary
        ? 'bg-black text-white hover:bg-neutral-800'
        : 'border border-neutral-300 text-neutral-900 hover:bg-neutral-50';

    const tag = reward != null && (
        <span className={`rounded-full px-2 py-0.5 text-[10px] font-semibold ${claimed ? 'bg-emerald-100 text-emerald-700' : primary ? 'bg-white/20 text-white' : 'bg-neutral-100 text-neutral-600'}`}>
            {claimed ? '✓ reçu' : `+${eur(reward)}`}
        </span>
    );

    if (to) {
        return <Link to={to} onClick={onVisit} className={`${base} ${style}`}><span className="flex-1 text-left">{children}</span>{tag}<span aria-hidden="true">→</span></Link>;
    }
    if (!href) {
        return (
            <span title="Lien bientôt disponible" className={`${base} cursor-not-allowed border border-dashed border-neutral-300 text-neutral-400`}>
                <span className="flex-1 text-left">{children}</span><span aria-hidden="true">…</span>
            </span>
        );
    }
    return (
        <a href={href} target="_blank" rel="noopener noreferrer" onClick={onVisit} className={`${base} ${style}`}>
            <span className="flex-1 text-left">{children}</span>{tag}<span aria-hidden="true">↗</span>
        </a>
    );
}

export default function ContactSection() {
    const { user, refresh } = useAuth();
    const [f, setF] = useState({ name: '', email: '', subject: '', message: '' });
    const [status, setStatus] = useState({ type: '', text: '' });
    const [sending, setSending] = useState(false);
    const [photoOk, setPhotoOk] = useState(true);
    const [wallet, setWallet] = useState(null);   // bonus disponibles et déjà récupérés
    const [toast, setToast] = useState('');
    const set = (k) => (e) => setF({ ...f, [k]: e.target.value });

    useEffect(() => {
        if (user) api('/wallet').then(setWallet).catch(() => {});
        else setWallet(null);
    }, [user?.id]);

    // Visite d'un lien : bonus de départ, une seule fois par bouton et par compte
    const visit = (key) => async () => {
        if (!user) { setToast('Connectez-vous pour recevoir un bonus à chaque visite.'); return; }
        if (wallet?.claimed.includes(key)) return;
        try {
            const r = await api('/wallet/claim', { method: 'POST', body: { key } });
            setWallet((w) => (w ? { ...w, claimed: [...w.claimed, key] } : w));
            setToast(`+${eur(r.amount)} ajoutés à votre solde.`);
            refresh();
        } catch (e) { setToast(e.message); }
    };

    const reward = (key) => (wallet ? wallet.rewards[key] : undefined);
    const claimed = (key) => !!wallet?.claimed.includes(key);

    const submit = async (e) => {
        e.preventDefault();
        setSending(true);
        setStatus({ type: '', text: '' });
        try {
            const r = await api('/contact', { method: 'POST', body: f });
            setStatus({ type: 'ok', text: r.message });
            setF({ name: '', email: '', subject: '', message: '' });
        } catch (err) {
            setStatus({ type: 'error', text: err.message });
        } finally {
            setSending(false);
        }
    };

    return (
        <section className="mx-auto max-w-7xl px-5 pb-24">
            <h2 className="mb-8 text-5xl tracking-tight">
                À propos <span className="italic font-extralight">&amp; Contact</span>
            </h2>

            <div className="grid gap-6 lg:grid-cols-2">
                {/* Gauche : carte divisée en deux (photo | liens) */}
                <div className="grid overflow-hidden rounded-3xl border border-neutral-200 sm:grid-cols-2">
                    <div className="relative min-h-[320px] bg-neutral-200 sm:min-h-full">
                        {photoOk ? (
                            <img
                                src={PHOTO}
                                alt="Photo de RABETOKOTANY Ny Tsanta Fiderana"
                                onError={() => setPhotoOk(false)}
                                className="absolute inset-0 h-full w-full object-cover"
                            />
                        ) : (
                            <div className="absolute inset-0 flex items-center justify-center text-6xl font-light text-neutral-400">NT</div>
                        )}
                        <div className="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent" />
                        <div className="absolute bottom-0 p-5 text-white">
                            <p className="font-semibold leading-tight">RABETOKOTANY Ny Tsanta Fiderana</p>
                            <p className="mt-1 text-xs text-white/80">Développeur web fullstack</p>
                        </div>
                    </div>

                    <div className="flex flex-col justify-center gap-3 p-6">
                        <LinkButton to="/about" primary reward={reward('about')} claimed={claimed('about')} onVisit={visit('about')}>À propos de moi</LinkButton>
                        <LinkButton href={LINKS.github} reward={reward('github')} claimed={claimed('github')} onVisit={visit('github')}>Mon GitHub</LinkButton>
                        <LinkButton href={LINKS.orgGithub} reward={reward('org_github')} claimed={claimed('org_github')} onVisit={visit('org_github')}>GitHub de l'entreprise (stage)</LinkButton>
                        <LinkButton href={LINKS.cv} reward={reward('cv')} claimed={claimed('cv')} onVisit={visit('cv')}>Mon CV</LinkButton>
                        <p className="text-xs text-neutral-500">
                            {toast || 'Chaque lien visité offre un bonus à votre solde (une fois par compte, connexion requise).'}
                        </p>
                    </div>
                </div>

                {/* Droite : formulaire (inchangé) */}
                <form onSubmit={submit} className="space-y-4 rounded-3xl border border-neutral-200 p-6 md:p-8">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <input className={field} placeholder="Nom complet" value={f.name} onChange={set('name')} required />
                        <input className={field} type="email" placeholder="E-mail" value={f.email} onChange={set('email')} required />
                    </div>
                    <input className={field} placeholder="Sujet (facultatif)" value={f.subject} onChange={set('subject')} />
                    <textarea className={field} rows="6" placeholder="Votre message (10 caractères minimum)" value={f.message} onChange={set('message')} required />
                    {status.text && (
                        <p className={`text-sm ${status.type === 'ok' ? 'text-emerald-600' : 'text-rose-600'}`}>{status.text}</p>
                    )}
                    <button disabled={sending} className="rounded-full bg-black px-8 py-3.5 text-sm font-semibold text-white disabled:opacity-50">
                        {sending ? 'Envoi…' : 'Envoyer le message'}
                    </button>
                </form>
            </div>
        </section>
    );
}