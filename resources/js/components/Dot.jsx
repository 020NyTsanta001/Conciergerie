/** Petit point rouge posé en haut à droite de son contenu (lien de menu, onglet…). */
export function WithDot({ show, children }) {
    return (
        <span className="relative inline-block">
            {children}
            {show && (
                <span
                    aria-label="Nouveau"
                    className="absolute -right-2.5 -top-1 h-2 w-2 rounded-full bg-red-500"
                />
            )}
        </span>
    );
}
