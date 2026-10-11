import { useState } from 'react';

/** Étoiles en lecture seule : petites et discrètes */
export function Stars({ value, className = 'text-xs' }) {
    const full = Math.round(value);
    return (
        <span className={`${className} tracking-widest`} role="img" aria-label={`${value} sur 5`}>
            {[1, 2, 3, 4, 5].map((i) => (
                <span key={i} className={i <= full ? 'text-amber-500' : 'text-neutral-300'}>★</span>
            ))}
        </span>
    );
}

/** Sélecteur de note : 5 étoiles cliquables */
export function StarInput({ value, onChange }) {
    const [hover, setHover] = useState(0);
    return (
        <div className="flex gap-1" onMouseLeave={() => setHover(0)}>
            {[1, 2, 3, 4, 5].map((i) => (
                <button
                    key={i}
                    type="button"
                    onClick={() => onChange(i)}
                    onMouseEnter={() => setHover(i)}
                    aria-label={`${i} étoile${i > 1 ? 's' : ''}`}
                    className={`text-2xl leading-none transition-colors ${i <= (hover || value) ? 'text-amber-500' : 'text-neutral-300'}`}
                >
                    ★
                </button>
            ))}
        </div>
    );
}