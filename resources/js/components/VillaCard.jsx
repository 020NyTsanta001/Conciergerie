import { Link } from 'react-router-dom';
import { eur } from '../api';

export default function VillaCard({ villa, className = '' }) {
    return (
        <Link to={`/villas/${villa.id}`} className={`group relative block overflow-hidden rounded-3xl bg-neutral-200 ${className}`}>
            {villa.images?.[0] && (
                <img src={villa.images[0]} alt={villa.title} loading="lazy" className="absolute inset-0 h-full w-full object-cover transition duration-700 group-hover:scale-105" />
            )}
            <div className="absolute inset-0 bg-gradient-to-t from-black/70 via-black/0 to-transparent" />
            <div className="absolute bottom-0 p-5 text-white">
                <p className="text-lg font-semibold">{villa.title}</p>
                <p className="text-sm text-white/80">{villa.city}, {villa.country} · {eur(villa.price_per_night)} / nuit</p>
            </div>
        </Link>
    );
}
