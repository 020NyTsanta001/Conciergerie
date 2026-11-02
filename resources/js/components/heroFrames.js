// Chargement unique et partagé des images du hero.
// Le preloader et HeroScroll utilisent ce même module : chaque image n'est téléchargée et décodée qu'une fois.
//
// Stratégie :
//   1) un lot « de base » (1 image sur READY_STRIDE + la dernière) est chargé en priorité, seul : c'est lui que le preloader attend.
//      Plus il est dense, plus le scroll est fluide dès l'entrée, mais plus le preloader dure.
//   2) le reste charge ensuite en arrière-plan, en commençant par les images DEVANT la position de scroll du visiteur
//      (HeroScroll la communique avec setHeroFocus) : s'il scrolle vite, les images dont il a besoin arrivent en premier.
// HeroScroll affiche toujours l'image chargée la plus proche.

/* ───────── RÉGLAGES ───────── */
const DIR_DESKTOP = '/images/hero-frames';
const DIR_MOBILE = '/images/hero-frames-m';  // si ce dossier n'existe pas, le desktop est utilisé
export const FRAME_COUNT = 72;
const PAD = 3;
const EXT = 'webp';
const READY_STRIDE = 3;      // lot de base : 1 image sur 4 (≈ 19 images). 3 = plus fluide mais plus long · 6 = plus rapide mais moins fluide
const READY_PARALLEL = 8;    // téléchargements simultanés pendant le lot de base
const BACKGROUND_PARALLEL = 6; // téléchargements simultanés pour le reste (modéré pour ne pas gêner la page)
const GIVE_UP_MS = 30000;    // au-delà, le lot minimal est déclaré prêt même incomplet
/* ──────────────────────────── */

const frames = new Array(FRAME_COUNT).fill(null); // Image décodée | null, rempli en place
const subs = new Set();
let started = false;
let ready = false;
let readyTotal = 1;
let readyLeft = new Set();
let focus = 0; // numéro d'image que le visiteur regarde (mis à jour par HeroScroll)
let resolveReady;
const readyPromise = new Promise((r) => { resolveReady = r; });

// Ordre « grossier → fin ». Chaque niveau est complet avant le suivant, donc la couverture est toujours régulière.
function buildOrder(n) {
    const seen = new Set();
    const order = [];
    const add = (i) => { if (i >= 0 && i < n && !seen.has(i)) { seen.add(i); order.push(i); } };
    // lot de base : d'abord très espacé (12), puis complété jusqu'à READY_STRIDE : même interrompu, il reste régulier
    [12, READY_STRIDE].forEach((s) => { for (let i = 0; i < n; i += s) add(i); });
    add(n - 1);
    return { order, readyList: [...order] };
}

const markReady = () => { if (!ready) { ready = true; resolveReady(frames); } };
const notify = () => subs.forEach((f) => f());

/** Images (null = pas encore chargée). Le tableau est modifié en place au fil du chargement. */
export const getHeroFrames = () => frames;

/** Vrai quand le lot minimal est prêt : le hero est alors fluide, même si le reste charge encore. */
export const heroFramesReady = () => ready;

/** Promesse résolue quand le lot minimal est prêt. */
export const whenHeroFramesReady = () => readyPromise;

/** Avancement du lot minimal, de 0 à 1 (pour la barre du preloader). */
export const heroReadyProgress = () => (ready ? 1 : started ? 1 - readyLeft.size / readyTotal : 0);

/** cb() est appelée à chaque image arrivée. Retourne la fonction pour se désabonner. */
export function subscribeHeroFrames(cb) {
    subs.add(cb);
    return () => subs.delete(cb);
}

/** Indique l'image regardée : les images chargées ensuite sont celles qui sont juste devant (puis juste derrière). */
export const setHeroFocus = (i) => { focus = i; };

/** Lance le chargement. Sans danger à rappeler : il ne démarre qu'une fois. */
export function startHeroFrames() {
    if (started || typeof window === 'undefined') return;
    started = true;

    const dirs = window.innerWidth < 768 ? [DIR_MOBILE, DIR_DESKTOP] : [DIR_DESKTOP];
    let dirIndex = 0;
    const { order, readyList } = buildOrder(FRAME_COUNT);
    readyLeft = new Set(readyList);
    readyTotal = readyList.length;
    const queue = [...order];   // lot de base, dans l'ordre
    const rest = new Set();     // le reste : choisi à la volée selon la position du visiteur
    for (let i = 0; i < FRAME_COUNT; i++) if (!order.includes(i)) rest.add(i);
    let active = 0;
    let finished = 0;
    const giveUp = setTimeout(markReady, GIVE_UP_MS);

    const load = (i) => {
        const dir = dirs[dirIndex];
        const im = new Image();
        im.decoding = 'async';
        if (!readyLeft.has(i)) im.fetchPriority = 'low'; // le reste ne doit pas ralentir le lot minimal ni la page
        im.src = `${dir}/f_${String(i + 1).padStart(PAD, '0')}.${EXT}`;
        // decode() : l'image est décodée AVANT d'être déclarée prête, donc pas de saccade à l'affichage
        return im.decode().then(() => im).catch(() => {
            if (dirIndex + 1 < dirs.length && dirs[dirIndex] === dir) dirIndex++; // dossier mobile absent → desktop
            return dirs[dirIndex] !== dir ? load(i) : null;
        });
    };

    // Tant que le lot minimal n'est pas là, on ne charge QUE lui (toute la bande passante lui est réservée)
    const pump = () => {
        const limit = readyLeft.size ? READY_PARALLEL : BACKGROUND_PARALLEL;
        while (active < limit && (queue.length || (!readyLeft.size && rest.size))) {
            let i;
            if (queue.length) i = queue.shift();
            else {
                // la plus proche devant le visiteur d'abord ; derrière, l'écart compte double
                let best = Infinity;
                rest.forEach((k) => { const d = k >= focus ? k - focus : (focus - k) * 2; if (d < best) { best = d; i = k; } });
                rest.delete(i);
            }
            active++;
            load(i)
                .then((im) => { if (im) frames[i] = im; })
                .finally(() => {
                    active--;
                    finished++;
                    readyLeft.delete(i);
                    if (!readyLeft.size) markReady();
                    if (finished === FRAME_COUNT) clearTimeout(giveUp);
                    notify();
                    pump();
                });
        }
    };
    pump();
}
