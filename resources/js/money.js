/**
 * ===== PRIX : tout l'affichage et la conversion passent par ce fichier =====
 * Les montants venant de l'API sont TOUJOURS en Ariary (Ar), en entiers.
 * Le taux 1 $ = 5 000 Ar vient de config/money.php (injecté par app.blade.php dans window.__MONEY__).
 */
const RATE = window.__MONEY__?.usd_rate ?? 5000;
let currency = 'MGA'; // 'MGA' (Ar, par défaut) ou 'USD' ($), selon le compte connecté

export const USD_RATE = RATE;
export const setCurrency = (c) => { currency = c === 'USD' ? 'USD' : 'MGA'; };
export const getCurrency = () => currency;
export const currencyLabel = () => (currency === 'USD' ? '$' : 'Ar');

/** Affiche un montant (en Ar) dans la monnaie de l'utilisateur */
export function money(ar) {
    const n = Number(ar) || 0;
    const sign = n < 0 ? '-' : '';
    const abs = Math.abs(n);
    if (currency === 'USD') {
        return sign + '$' + new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(abs / RATE);
    }
    return sign + new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(Math.round(abs)) + ' Ar';
}

/** Valeur saisie dans la monnaie de l'utilisateur -> Ariary entier (à envoyer à l'API) */
export const toAr = (value) => {
    const v = Number(value) || 0;
    return Math.round(currency === 'USD' ? v * RATE : v);
};

/** Ariary -> valeur à mettre dans un champ de saisie (monnaie de l'utilisateur) */
export const fromAr = (ar) => {
    const v = Number(ar) || 0;
    return currency === 'USD' ? +(v / RATE).toFixed(2) : Math.round(v);
};