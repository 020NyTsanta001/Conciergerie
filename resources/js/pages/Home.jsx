import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { api } from '../api';
import VillaCard from '../components/VillaCard';
import ContactSection from '../components/ContactSection';
import HeroScroll from '../components/HeroScroll';
import HowItWorks from '../components/HowItWorks';

export default function Home() {
    const [stats, setStats] = useState(null);
    const [featured, setFeatured] = useState([]);

    useEffect(() => {
        api('/stats').then(setStats).catch(() => {});
        api('/properties', { params: { random: 5 } }).then((r) => setFeatured(r.data)).catch(() => {});
    }, []);

    

    return (
        <>
            <HeroScroll image="/images/hero-villa.jpg" stats={stats} />

            <section id="every-home" className="mx-auto max-w-7xl px-5 py-24 grid md:grid-cols-2 gap-12 items-start">
                <div className="md:sticky md:top-28">
                    <h2 className="text-5xl tracking-tight">Every Home Begins<br /><span className="italic font-extralight">vision of better living</span></h2>
                    <p className="mt-6 max-w-md text-sm text-neutral-600">Chaque séjour commence par une vision d'équilibre entre architecture et nature, forme et fonction, luxe et confort. Nos concierges sélectionnent chaque propriété et coordonnent chaque prestation.</p>
                </div>
                <HowItWorks />
            </section>

            <section className="mx-auto max-w-7xl px-5 pb-24">
                <div className="flex items-end justify-between gap-4 mb-8">
                    <div>
                        <h2 className="text-5xl tracking-tight">Your <span className="italic font-extralight">Next Stay</span></h2>
                        <p className="mt-3 text-sm text-neutral-500">Une sélection validée par nos concierges, renouvelée à chaque visite.</p>
                    </div>
                    <Link to="/villas" className="shrink-0 rounded-full border border-neutral-300 px-5 py-2 text-sm">Voir toutes les villas →</Link>
                </div>
                <div className="grid grid-cols-2 md:grid-cols-4 auto-rows-[220px] gap-4">
                    {featured.slice(0, 5).map((v, i) => (
                        <VillaCard key={v.id} villa={v} className={i === 0 ? 'col-span-2 row-span-2' : ''} />
                    ))}
                </div>
            </section>
            <ContactSection />
        
        </>
    );
}
