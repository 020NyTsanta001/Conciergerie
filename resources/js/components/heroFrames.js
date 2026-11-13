// Chargement unique et partagé des 72 images du hero.
// Le preloader et HeroScroll utilisent ce même module : chaque image n'est téléchargée et décodée qu'une fois.
//
// Règle : la page n'est affichée que lorsque TOUTES les images sont chargées ET décodées.
//   - aucun délai d'abandon : on attend tant que le réseau avance (connexion lente, mobile ou desktop)
//   - chaque image est retentée si elle échoue (coupure, timeout) ; hors-ligne, on attend le retour du réseau
//   - seule une image réellement introuvable (erreur 404 sur les deux dossiers) est ignorée :
//     HeroScroll affiche alors l'image voisine, et le preloader ne reste pas bloqué pour toujours
//
// Cache : Image décodée ici = affichage sans saccade ensuite (drawImage sur une image déjà décodée).

/* ───────── RÉGLAGES ───────── */
const DIR_DESKTOP = '/images/hero-frames';
const DIR_MOBILE = '/images/hero-frames-m';   // si ce dossier n'existe pas, le desktop est utilisé
export const FRAME_COUNT = 72;
const PAD = 3;
const EXT = 'webp';
const PARALLEL = 6;          // téléchargements simultanés
const ATTEMPTS = 4;          // tentatives par image avant de l'ignorer
const TIMEOUT_MS = 30000;    // une requête silencieuse plus de 30 s est annulée puis retentée
const RETRY_DELAY_MS = 800;  // pause avant retentative (×1, ×2, ×3…)
const COVER_DESKTOP = '/images/hero-villa.webp';
/* ──────────────────────────── */

const frames = new Array(FRAME_COUNT).fill(null); // Image décodée | null, rempli en place
const subs = new Set();
let started = false;
let ready = false;
let readyTotal = FRAME_COUNT;
let readyLeft = new Set();
let resolveReady;
const readyPromise = new Promise((r) => { resolveReady = r; });

const isMobile = () => typeof window !== 'undefined' && window.innerWidth < 768;
const frameName = (i) => `f_${String(i + 1).padStart(PAD, '0')}.${EXT}`;

// Ordre « grossier → fin » : même si le chargement est lent, la couverture reste régulière.
function buildOrder(n) {
    const seen = new Set();
    const order = [];
    const add = (i) => { if (i >= 0 && i < n && !seen.has(i)) { seen.add(i); order.push(i); } };
    [12, 3, 1].forEach((s) => { for (let i = 0; i < n; i += s) add(i); });
    add(n - 1);
    return order;
}

const markReady = () => { if (!ready) { ready = true; resolveReady(frames); } };
const notify = () => subs.forEach((f) => f());

/** Image « hero-page » nette, affichée tant que le visiteur n'a pas défilé (mobile : portrait, desktop : paysage). */
export const coverSrc = () => (isMobile() ? `${DIR_MOBILE}/${frameName(0)}` : COVER_DESKTOP);

/** Images (null = pas encore chargée). Le tableau est modifié en place au fil du chargement. */
export const getHeroFrames = () => frames;

/** Vrai quand les 72 images sont prêtes : le hero est alors fluide partout. */
export const heroFramesReady = () => ready;

/** Promesse résolue quand toutes les images sont prêtes. */
export const whenHeroFramesReady = () => readyPromise;

/** Avancement, de 0 à 1 (pour la barre du preloader). */
export const heroReadyProgress = () => (ready ? 1 : started ? 1 - readyLeft.size / readyTotal : 0);

/** cb() est appelée à chaque image arrivée. Retourne la fonction pour se désabonner. */
export function subscribeHeroFrames(cb) {
    subs.add(cb);
    return () => subs.delete(cb);
}

/** Conservé pour compatibilité : toutes les images étant chargées avant l'affichage, l'ordre de priorité n'a plus d'effet. */
export const setHeroFocus = () => {};

// Télécharge (annulable) puis décode l'image : elle sera dessinée sans saccade.
async function fetchDecoded(src) {
    const ctrl = new AbortController();
    const timer = setTimeout(() => ctrl.abort(), TIMEOUT_MS);
    try {
        const res = await fetch(src, { signal: ctrl.signal });
        if (!res.ok) { const e = new Error(`HTTP ${res.status}`); e.status = res.status; throw e; }
        const blob = await res.blob();
        const url = URL.createObjectURL(blob);
        const im = new Image();
        im.decoding = 'async';
        im.src = url;
        try { await im.decode(); } finally { URL.revokeObjectURL(url); }
        return im;
    } finally {
        clearTimeout(timer);
    }
}

const waitOnline = () => new Promise((r) => window.addEventListener('online', r, { once: true }));
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

/** Lance le chargement. Sans danger à rappeler : il ne démarre qu'une fois. */
export function startHeroFrames() {
    if (started || typeof window === 'undefined') return;
    started = true;

    const dirs = isMobile() ? [DIR_MOBILE, DIR_DESKTOP] : [DIR_DESKTOP];
    let dirIndex = 0;
    const order = buildOrder(FRAME_COUNT);
    readyLeft = new Set(order);
    readyTotal = order.length;

    const loadFrame = async (i) => {
        for (let attempt = 0; attempt < ATTEMPTS; attempt++) {
            const dir = dirs[dirIndex];
            try {
                return await fetchDecoded(`${dir}/${frameName(i)}`);
            } catch (err) {
                // dossier mobile absent → on bascule sur le desktop, sans compter la tentative
                if (err.status === 404 && dirIndex + 1 < dirs.length && dirs[dirIndex] === dir) { dirIndex++; attempt--; continue; }
                if (err.status === 404) return null;           // vraiment introuvable : inutile d'insister
                if (navigator.onLine === false) { await waitOnline(); attempt--; continue; } // hors-ligne : on attend le retour du réseau
                await sleep(RETRY_DELAY_MS * (attempt + 1));
            }
        }
        return null;
    };

    let next = 0;
    const worker = async () => {
        while (next < order.length) {
            const i = order[next++];
            const im = await loadFrame(i);
            if (im) frames[i] = im;
            readyLeft.delete(i);
            notify();
        }
    };
    Promise.all(Array.from({ length: PARALLEL }, worker)).then(markReady);
}
