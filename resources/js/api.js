const BASE = '/api/v1';

export async function api(path, { method = 'GET', body, params } = {}) {
    const url = new URL(BASE + path, window.location.origin);
    if (params) {
        Object.entries(params).forEach(([k, v]) => {
            if (v !== '' && v != null && v !== false) url.searchParams.set(k, v);
        });
    }
    const token = localStorage.getItem('token');
    const res = await fetch(url, {
        method,
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            ...(token ? { Authorization: `Bearer ${token}` } : {}),
        },
        body: body ? JSON.stringify(body) : undefined,
    });
    if (res.status === 204) return null;
    const data = await res.json().catch(() => null);
    if (!res.ok) {
        const firstError = data?.errors ? Object.values(data.errors)[0][0] : null;
        const err = new Error(firstError || data?.message || 'Erreur serveur');
        err.status = res.status;
        throw err;
    }
    return data;
}

export const eur = (n) =>
    new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 }).format(n ?? 0);

export const STATUS = {
    submitted: ['Soumise', 'bg-neutral-100 text-neutral-700'],
    pending_host: ['En attente du propriétaire', 'bg-amber-100 text-amber-800'],
    validated: ['Validée', 'bg-sky-100 text-sky-800'],
    deposit_paid: ['Acompte payé', 'bg-indigo-100 text-indigo-800'],
    balance_paid: ['Solde payé', 'bg-violet-100 text-violet-800'],
    in_progress: ['Séjour en cours', 'bg-emerald-100 text-emerald-800'],
    closed: ['Clôturée', 'bg-neutral-200 text-neutral-700'],
    cancelled: ['Annulée', 'bg-rose-100 text-rose-800'],
};
