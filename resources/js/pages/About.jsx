import { useState } from 'react';

/* ⚠️ À remplacer par tes vrais liens avant la mise en ligne */
const CONTACT = {
    github: 'https://github.com/020NyTsanta001',
    facebook: 'https://www.facebook.com/',          // ← ton lien Facebook
    phone: '038 27 878 73',
    email: 'nytsantaf@gmail.com',                 // ← ton adresse e-mail
};
const PHOTO = '/images/profile.jpg';                // même photo que la carte « À propos & Contact »
const NAME = 'RABETOKOTANY Ny Tsanta Fiderana';

const SKILLS = ['Laravel', 'PHP', 'React', 'Node.js / Express', 'MySQL', 'Tailwind CSS', 'API REST', 'Vite'];

const PROJECTS = [
    { name: 'Hoxen — conciergerie de luxe', text: 'Plateforme de location de villas : réservations en plusieurs étapes, paiement acompte/solde, avis, espaces voyageur, propriétaire et concierge. Laravel + React.' },
    { name: 'Cursus', text: 'Plateforme de cours en ligne : espace client, tableau de bord et cours pilote sur Node.js & Express.js.' },
    { name: 'ORMada', text: 'Conversion d\'un site web existant vers Laravel 12 avec MySQL, interface reproduite à l\'identique.' },
    { name: 'HRLite', text: 'Application de gestion des ressources humaines pour PME.' },
    { name: 'Invox', text: 'Application de gestion des devis et factures pour petites entreprises.' },
];

/* Icônes en noir (currentColor) */
const GithubIcon = () => (
    <svg viewBox="0 0 24 24" className="h-5 w-5" fill="currentColor" aria-hidden="true">
        <path d="M12 .5C5.65.5.5 5.65.5 12c0 5.08 3.29 9.38 7.86 10.9.58.1.79-.25.79-.56v-2c-3.2.7-3.87-1.37-3.87-1.37-.52-1.33-1.28-1.69-1.28-1.69-1.05-.72.08-.7.08-.7 1.15.08 1.76 1.19 1.76 1.19 1.03 1.77 2.7 1.26 3.36.96.1-.75.4-1.26.73-1.55-2.55-.29-5.24-1.28-5.24-5.69 0-1.26.45-2.28 1.19-3.09-.12-.29-.52-1.46.11-3.04 0 0 .97-.31 3.17 1.18a11 11 0 0 1 5.77 0c2.2-1.49 3.17-1.18 3.17-1.18.63 1.58.23 2.75.11 3.04.74.81 1.19 1.83 1.19 3.09 0 4.42-2.7 5.4-5.27 5.68.41.36.78 1.06.78 2.14v3.17c0 .31.21.67.8.56A11.5 11.5 0 0 0 23.5 12C23.5 5.65 18.35.5 12 .5Z" />
    </svg>
);
const FacebookIcon = () => (
    <svg viewBox="0 0 24 24" className="h-5 w-5" fill="currentColor" aria-hidden="true">
        <path d="M22 12a10 10 0 1 0-11.56 9.88v-6.99H7.9V12h2.54V9.8c0-2.5 1.49-3.89 3.77-3.89 1.09 0 2.24.2 2.24.2v2.46h-1.26c-1.24 0-1.63.77-1.63 1.56V12h2.78l-.44 2.89h-2.34v6.99A10 10 0 0 0 22 12Z" />
    </svg>
);
const PhoneIcon = () => (
    <svg viewBox="0 0 24 24" className="h-5 w-5" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
        <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.8 19.8 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.12 4.18 2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92Z" />
    </svg>
);
const MailIcon = () => (
    <svg viewBox="0 0 24 24" className="h-5 w-5" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
        <rect x="3" y="5" width="18" height="14" rx="2" />
        <path d="m3 7 9 6 9-6" />
    </svg>
);

/* Ligne de contact : icône noire + texte, jamais souligné */
function ContactLink({ href, icon, children, external = false }) {
    return (
        <a
            href={href}
            {...(external ? { target: '_blank', rel: 'noopener noreferrer' } : {})}
            className="flex items-center gap-3 text-sm font-medium text-black no-underline hover:no-underline hover:opacity-60 transition-opacity"
        >
            {icon}
            <span className="break-all">{children}</span>
        </a>
    );
}

export default function About() {
    const [photoOk, setPhotoOk] = useState(true);

    return (
        <div className="mx-auto max-w-7xl px-5 py-12">
            <h1 className="mb-10 text-5xl tracking-tight">
                À <span className="italic font-extralight">propos</span>
            </h1>

            <div className="grid gap-6 lg:grid-cols-3">
                {/* ---------- Colonne 1 : photo ---------- */}
                <div className="relative min-h-[420px] overflow-hidden rounded-3xl bg-neutral-200">
                    {photoOk ? (
                        <img
                            src={PHOTO}
                            alt={`Photo de ${NAME}`}
                            onError={() => setPhotoOk(false)}
                            className="absolute inset-0 h-full w-full object-cover"
                        />
                    ) : (
                        <div className="absolute inset-0 flex items-center justify-center text-6xl font-light text-neutral-400">NT</div>
                    )}
                    <div className="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent" />
                    <div className="absolute bottom-0 p-6 text-white">
                        <p className="text-lg font-semibold leading-tight">{NAME}</p>
                        <p className="mt-1 text-xs text-white/80">Développeur web fullstack</p>
                    </div>
                </div>

                {/* ---------- Colonne 2 : description ---------- */}
                <div className="space-y-6 rounded-3xl border border-neutral-200 p-6 md:p-8">
                    <div>
                        <h2 className="text-xl font-semibold">Développeur web Laravel</h2>
                        <p className="mt-3 text-sm leading-relaxed text-neutral-600">
                            Je conçois et réalise des applications web complètes : back-end Laravel (API REST, authentification,
                            règles métier, paiements simulés), interfaces React et bases de données MySQL. J'aime les projets
                            concrets, du cahier des charges à la mise en ligne.
                        </p>
                    </div>

                    <div>
                        <p className="mb-2 text-xs uppercase tracking-widest text-neutral-500">Technologies</p>
                        <div className="flex flex-wrap gap-2">
                            {SKILLS.map((s) => (
                                <span key={s} className="rounded-full border border-neutral-200 px-3 py-1 text-xs text-neutral-700">{s}</span>
                            ))}
                        </div>
                    </div>

                    <div>
                        <p className="mb-3 text-xs uppercase tracking-widest text-neutral-500">Exemples de projets</p>
                        <ul className="space-y-3">
                            {PROJECTS.map((p) => (
                                <li key={p.name} className="text-sm">
                                    <p className="font-semibold">{p.name}</p>
                                    <p className="text-neutral-600">{p.text}</p>
                                </li>
                            ))}
                        </ul>
                    </div>
                </div>

                {/* ---------- Colonne 3 : contact ---------- */}
                <div className="h-fit space-y-5 rounded-3xl border border-neutral-200 p-6 md:p-8">
                    <h2 className="text-xl font-semibold">Contact</h2>
                    <ContactLink href={CONTACT.github} icon={<GithubIcon />} external>GitHub</ContactLink>
                    <ContactLink href={CONTACT.facebook} icon={<FacebookIcon />} external>Facebook</ContactLink>
                    <ContactLink href={`tel:${CONTACT.phone.replace(/\s/g, '')}`} icon={<PhoneIcon />}>{CONTACT.phone}</ContactLink>
                    <ContactLink href={`mailto:${CONTACT.email}`} icon={<MailIcon />}>{CONTACT.email}</ContactLink>
                </div>
            </div>
        </div>
    );
}
