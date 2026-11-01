// Chargement unique et partagé des images du hero.
// Le preloader du site et HeroScroll appellent les mêmes fonctions : les images ne sont téléchargées
// et décodées qu'une seule fois, et le preloader peut attendre qu'elles soient TOUTES prêtes.

/* ───────── RÉGLAGES ───────── */
const FRAMES_DESKTOP = '/images/hero-frames';
const FRAMES_MOBILE = '/images/hero-frames-m';
export const FRAME_COUNT = 125; 
const PAD = 3;                  
const EXT = 'webp';
const PARALLEL = 8;             // téléchargements simultanés
const GIVE_UP_MS = 30000;       // le preloader ne bloque jamais le site plus longtemps que ça
/* ──────────────────────────── */

const frames = new Array(FRAME_COUNT).fill(null); // Image | null, rempli au fur et à mesure
let loaded = 0;
let promise = null;
const subs = new Set();

// Ordre « grossier → fin » : 1re, dernière, puis 1 image sur 32, sur 8, sur 4, sur 2, le reste.
const loadOrder = (n) => {
    const seen = new Set([0, n - 1]);
    const out = [0, n - 1];
    [32, 8, 4, 2, 1].forEach((stride) => {
        for (let i = 0; i < n; i += stride) if (!seen.has(i)) { seen.add(i); out.push(i); }
    });
    return out;
};

/** Tableau des images (null = pas encore chargée). Il est modifié en place au fil du chargement. */
export const getHeroFrames = () => frames;

/** Avancement de 0 à 1. */
export const heroFramesProgress = () => loaded / FRAME_COUNT;

/** Appelle cb(progress) à chaque image arrivée. Retourne la fonction pour se désabonner. */
export function subscribeHeroFrames(cb) {
    subs.add(cb);
    return () => subs.delete(cb);
}

/**
 * Lance le chargement (une seule fois, les appels suivants réutilisent le même) et retourne une promesse
 * résolue quand toutes les images sont téléchargées ET décodées (ou après GIVE_UP_MS).
 * onProgress(0..1) est optionnel : pratique pour une barre de chargement.
 */
export function loadHeroFrames(onProgress) {
    const unsub = onProgress ? subscribeHeroFrames(onProgress) : null;
    if (onProgress) onProgress(loaded / FRAME_COUNT);

    if (!promise) {
        promise = new Promise((resolve) => {
            if (typeof window === 'undefined') return resolve(frames);
            const dir = window.innerWidth < 768 ? FRAMES_MOBILE : FRAMES_DESKTOP;
            const queue = loadOrder(FRAME_COUNT);
            let active = 0;
            let finished = 0;
            const giveUp = setTimeout(() => resolve(frames), GIVE_UP_MS);

            const next = () => {
                while (active < PARALLEL && queue.length) {
                    const i = queue.shift();
                    const im = new Image();
                    im.decoding = 'async';
                    active++;
                    im.src = `${dir}/f_${String(i + 1).padStart(PAD, '0')}.${EXT}`;
                    // decode() : l'image est décodée AVANT d'être déclarée prête, donc pas de saccade au premier affichage
                    im.decode()
                        .then(() => { frames[i] = im; loaded++; })
                        .catch(() => { if (i === 0) console.warn('[HeroFrames] image introuvable :', im.src); })
                        .finally(() => {
                            active--;
                            finished++;
                            subs.forEach((f) => f(loaded / FRAME_COUNT));
                            if (finished === FRAME_COUNT) { clearTimeout(giveUp); resolve(frames); } else next();
                        });
                }
            };
            next();
        });
    }
    return promise.finally(() => { if (unsub) unsub(); });
}
