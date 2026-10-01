import { useState } from 'react';
import { api } from '../api';

const field = 'w-full rounded-xl border border-neutral-300 px-4 py-3 text-sm outline-none focus:border-black';
// Antananarivo, vue sur toute l'île (bbox = ouest,sud,est,nord)
const MAP_URL =
    'https://www.openstreetmap.org/export/embed.html?bbox=42.0%2C-26.0%2C51.0%2C-11.5&layer=mapnik&marker=-18.8792%2C47.5079';

export default function ContactSection() {
    const [f, setF] = useState({ name: '', email: '', subject: '', message: '' });
    const [status, setStatus] = useState({ type: '', text: '' });
    const [sending, setSending] = useState(false);
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
            <h2 className="text-5xl tracking-tight mb-8">
                Contactez-<span className="italic font-extralight">nous</span>
            </h2>
            <div className="grid lg:grid-cols-2 gap-6">
                {/* Gauche : carte de Madagascar */}
                <div className="overflow-hidden rounded-3xl border border-neutral-200 min-h-[420px]">
                    <iframe
                        title="Carte de Madagascar"
                        src={MAP_URL}
                        loading="lazy"
                        className="h-full w-full min-h-[420px] border-0"
                    />
                </div>

                {/* Droite : formulaire */}
                <form onSubmit={submit} className="rounded-3xl border border-neutral-200 p-6 md:p-8 space-y-4">
                    <div className="grid sm:grid-cols-2 gap-4">
                        <input className={field} placeholder="Nom complet" value={f.name} onChange={set('name')} required />
                        <input className={field} type="email" placeholder="E-mail" value={f.email} onChange={set('email')} required />
                    </div>
                    <input className={field} placeholder="Sujet (facultatif)" value={f.subject} onChange={set('subject')} />
                    <textarea className={field} rows="6" placeholder="Votre message (10 caractères minimum)" value={f.message} onChange={set('message')} required />
                    {status.text && (
                        <p className={`text-sm ${status.type === 'ok' ? 'text-emerald-600' : 'text-rose-600'}`}>{status.text}</p>
                    )}
                    <button disabled={sending} className="rounded-full bg-black text-white px-8 py-3.5 text-sm font-semibold disabled:opacity-50">
                        {sending ? 'Envoi…' : 'Envoyer le message'}
                    </button>
                </form>
            </div>
        </section>
    );
}