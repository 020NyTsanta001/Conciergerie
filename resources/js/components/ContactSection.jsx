import { useState } from 'react';
import { Link } from 'react-router-dom';
import { api } from '../api';


const LINKS = {
    github: 'https://github.com/020NyTsanta001',
    orgGithub: 'https://github.com/MIT452', 
    cv: '/cv.pdf',  // fichier à placer dans public/cv.pdf
};
const PHOTO = '/images/profile.jpg';

const field = 'w-full rounded-xl border border-neutral-300 px-4 py-3 text-sm outline-none focus:border-black';

function LinkButton({ to, href, primary = false, children }) {
    const base = 'flex w-full items-center justify-between rounded-full px-5 py-3.5 text-sm font-semibold transition-colors';
    const style = primary
        ? 'bg-black text-white hover:bg-neutral-800'
        : 'border border-neutral-300 text-neutral-900 hover:bg-neutral-50';

    if (to) {
        return <Link to={to} className={`${base} ${style}`}>{children}<span aria-hidden="true">→</span></Link>;
    }
    if (!href) {
        return (
            <span title="Lien bientôt disponible" className={`${base} cursor-not-allowed border border-dashed border-neutral-300 text-neutral-400`}>
                {children}<span aria-hidden="true">…</span>
            </span>
        );
    }
    return (
        <a href={href} target="_blank" rel="noopener noreferrer" className={`${base} ${style}`}>
            {children}<span aria-hidden="true">↗</span>
        </a>
    );
}

export default function ContactSection() {
    const [f, setF] = useState({ name: '', email: '', subject: '', message: '' });
    const [status, setStatus] = useState({ type: '', text: '' });
    const [sending, setSending] = useState(false);
    const [photoOk, setPhotoOk] = useState(true);
    const set = (k) => (e) => setF({ ...f, [k]: e.target.value });

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
                    {/* 1/2 : photo */}
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

                    {/* 2/2 : boutons liens */}
                    <div className="flex flex-col justify-center gap-3 p-6">
                        <LinkButton to="/" primary>À propos de moi</LinkButton>
                        <LinkButton href={LINKS.github}>Mon GitHub</LinkButton>
                        <LinkButton href={LINKS.orgGithub}>GitHub de l'entreprise (stage)</LinkButton>
                        <LinkButton href={LINKS.cv}>Mon CV</LinkButton>
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