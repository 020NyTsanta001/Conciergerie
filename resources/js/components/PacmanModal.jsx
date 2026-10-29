import { useEffect, useRef, useState } from 'react';
import { api, eur } from '../api';

export default function PacmanModal({ pac, gamesLeft, onClose, onDone }) {
    const frame = useRef(null);
    const start = useRef(null);   // promesse de création de la partie
    const done = useRef(false);
    const [phase, setPhase] = useState('ready');   // ready → playing → over
    const [result, setResult] = useState(null);
    const [error, setError] = useState('');

    useEffect(() => {
        const onMessage = async (e) => {
            if (e.origin !== window.location.origin || e.source !== frame.current?.contentWindow) return;
            try {
                if (e.data?.type === 'pacman:started' && !start.current) {
                    start.current = api('/games/pacman/start', { method: 'POST' });
                    setPhase('playing');
                    await start.current;
                }
                if (e.data?.type === 'pacman:gameover' && start.current && !done.current) {
                    done.current = true;
                    const s = await start.current;
                    const r = await api(`/games/pacman/${s.id}/finish`, { method: 'POST' });
                    setResult(r);
                    setPhase('over');
                    onDone?.(r);
                }
            } catch (err) { setError(err.message); }
        };
        window.addEventListener('message', onMessage);
        return () => window.removeEventListener('message', onMessage);
    }, []);

    const step = pac?.step_seconds ?? 20;
    const lines = Array.from({ length: pac?.max_steps ?? 5 }, (_, i) => `${(i + 1) * step} s = ${eur((i + 1) * (pac?.step_amount ?? 1_000_000))}`);

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4">
            <div className="max-h-full w-full max-w-[560px] overflow-y-auto rounded-3xl bg-white p-5">
                <div className="mb-3 flex items-center justify-between">
                    <h3 className="text-lg font-semibold">Pac-Man</h3>
                    <button onClick={onClose} className="text-sm text-neutral-500 hover:text-black">Fermer ✕</button>
                </div>

                <p className="mb-3 text-xs text-neutral-600">
                    {gamesLeft > 0
                        ? `Parties rémunérées restantes : ${gamesLeft} sur ${pac?.max_paid_games}. `
                        : 'Vos parties rémunérées sont épuisées : vous pouvez jouer, sans gain. '}
                    {lines.join(' · ')}. Le chronomètre démarre à votre premier mouvement ; quitter avant le game over fait perdre la partie.
                </p>

                <iframe
                    ref={frame}
                    src="/pacman/pacman.html"
                    title="Pac-Man"
                    onLoad={(e) => e.target.contentWindow?.focus()}
                    className="mx-auto block aspect-[420/490] w-full max-w-[420px] rounded-xl border-0 bg-black"
                />

                {error && <p className="mt-3 text-sm text-rose-600">{error}</p>}
                {phase === 'over' && result && (
                    <div className="mt-3 rounded-xl bg-neutral-100 p-3 text-sm">
                        <p>Partie terminée : {result.seconds} s.</p>
                        <p className="font-semibold">
                            {result.amount > 0 ? `+${eur(result.amount)} ajoutés à votre solde.`
                                : result.paid ? `Moins de ${step} s : pas de gain.` : 'Partie non rémunérée.'}
                        </p>
                    </div>
                )}
            </div>
        </div>
    );
}