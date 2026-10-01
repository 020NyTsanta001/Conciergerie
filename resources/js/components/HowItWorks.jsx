import { useState } from 'react';
import { Link } from 'react-router-dom';

const PROFILES = {
    voyageur: {
        label: 'Voyageur',
        intro: 'De la première recherche à la clôture du séjour, tout se fait depuis votre espace.',
        cta: { to: '/villas', text: 'Découvrir les villas' },
        steps: [
            { title: 'Choisir la villa idéale', text: "Filtrez par ville, budget, nombre de voyageurs, piscine ou disponibilité sur vos dates. Chaque fiche présente photos, description et tarifs par saison.", tags: ['Filtres avancés', 'Tarifs saisonniers'] },
            { title: 'Composer son séjour', text: "Choisissez vos dates et ajoutez des expériences : chef à domicile, chauffeur privé, sortie en yacht. Le devis se met à jour en direct.", tags: ['Devis instantané', 'Services exclusifs'] },
            { title: 'Valider et payer en deux temps', text: "Le propriétaire valide votre demande, puis vous réglez un acompte de 30 %. Le solde est payé avant l'arrivée.", tags: ['Acompte 30 %', 'Solde avant le séjour'] },
            { title: "Séjourner l'esprit libre", text: "Suivez l'état de votre réservation, de la demande jusqu'à la clôture du séjour, depuis votre espace personnel.", tags: ['Suivi des statuts', "Annulation avant l'acompte"] },
        ],
    },
    proprietaire: {
        label: 'Propriétaire',
        intro: 'Valorisez votre bien sans gérer la logistique : la plateforme et ses concierges s\'en chargent.',
        cta: { to: '/host', text: 'Publier ma villa' },
        steps: [
            { title: 'Publier sa villa', text: "Décrivez votre bien, ajoutez vos photos, fixez le prix de base, le nombre de chambres et la capacité depuis votre espace propriétaire.", tags: ['Photos', 'Prix de base'] },
            { title: 'Passer la validation du concierge', text: "Chaque annonce est examinée par un concierge avant d'être visible : une garantie de qualité pour les voyageurs comme pour vous.", tags: ['Annonce vérifiée', 'Statut en attente → approuvée'] },
            { title: 'Piloter ses tarifs saisonniers', text: "Un prix par nuit peut être défini pour chaque saison (fêtes de fin d'année, haute saison d'été…) : le total du séjour s'adapte automatiquement.", tags: ['Prix par saison', 'Calcul automatique'] },
            { title: 'Gérer les demandes et encaisser', text: "Recevez une notification à chaque nouvelle demande, validez ou refusez, démarrez et clôturez le séjour, puis suivez les montants encaissés.", tags: ['Notifications', 'Revenus encaissés'] },
        ],
    },
    concierge: {
        label: 'Concierge',
        intro: "Le gardien de l'exigence : rien n'est publié ni réservé sans son regard.",
        cta: { to: '/admin', text: "Accéder à l'espace concierge" },
        steps: [
            { title: 'Examiner les annonces', text: "Les villas soumises par les propriétaires arrivent dans une file de modération, avec leurs photos, leur prix et leur propriétaire.", tags: ['File de modération'] },
            { title: 'Approuver ou rejeter', text: "Seules les annonces approuvées apparaissent dans le catalogue : vous garantissez le niveau d'exigence de la plateforme.", tags: ['Contrôle qualité'] },
            { title: 'Superviser les réservations', text: "Une vue d'ensemble de toutes les réservations, avec leur statut, les montants et les services choisis par les voyageurs.", tags: ['Vue globale', 'Statuts'] },
            { title: 'Intervenir à chaque étape', text: "Validez, démarrez ou clôturez un séjour, annulez si nécessaire ; chaque nouvelle demande génère une notification.", tags: ['Cycle de vie complet', 'Notifications'] },
        ],
    },
};

export default function HowItWorks() {
    const [tab, setTab] = useState('voyageur');
    const p = PROFILES[tab];

    return (
        <div>
            <p className="text-xs uppercase tracking-[0.25em] text-neutral-500 mb-4">Comment ça marche</p>

            {/* onglets */}
            <div className="inline-flex rounded-full bg-neutral-100 p-1 text-sm font-medium">
                {Object.entries(PROFILES).map(([key, v]) => (
                    <button
                        key={key}
                        onClick={() => setTab(key)}
                        className={`rounded-full px-5 py-2 transition-colors ${tab === key ? 'bg-black text-white' : 'text-neutral-600 hover:text-black'}`}
                    >
                        {v.label}
                    </button>
                ))}
            </div>

            <p className="mt-5 text-sm text-neutral-600 max-w-md">{p.intro}</p>

            {/* frise verticale */}
            <ol className="relative mt-8 space-y-7 border-l border-neutral-200 pl-8">
                {p.steps.map((s, i) => (
                    <li key={s.title} className="relative">
                        <span className="absolute -left-[3.125rem] top-0 flex h-9 w-9 items-center justify-center rounded-full bg-black text-sm font-semibold text-white">
                            {i + 1}
                        </span>
                        <h3 className="font-semibold text-lg leading-tight">{s.title}</h3>
                        <p className="mt-1.5 text-sm text-neutral-600 leading-relaxed">{s.text}</p>
                        <div className="mt-3 flex flex-wrap gap-2">
                            {s.tags.map((t) => (
                                <span key={t} className="rounded-full border border-neutral-200 px-3 py-1 text-xs text-neutral-600">{t}</span>
                            ))}
                        </div>
                    </li>
                ))}
            </ol>

            <Link to={p.cta.to} className="mt-9 inline-block rounded-full bg-black px-7 py-3.5 text-sm font-semibold text-white">
                {p.cta.text} →
            </Link>
        </div>
    );
}