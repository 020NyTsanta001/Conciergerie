import { Component } from 'react';

export default class ErrorBoundary extends Component {
    state = { error: null };

    static getDerivedStateFromError(error) {
        return { error };
    }

    componentDidCatch(error, info) {
        console.error(error, info.componentStack);
    }

    render() {
        if (!this.state.error) return this.props.children;

        return (
            <div className="mx-auto max-w-2xl px-5 py-20">
                <h1 className="text-2xl font-semibold">Une erreur est survenue</h1>
                <p className="mt-3 text-sm text-neutral-600">Rechargez la page. Si le problème continue, voici le détail technique :</p>
                <pre className="mt-4 overflow-auto rounded-xl bg-neutral-100 p-4 text-xs">
                    {String(this.state.error?.stack || this.state.error)}
                </pre>
                <button onClick={() => window.location.reload()} className="mt-6 rounded-full bg-black px-6 py-3 text-sm font-semibold text-white">
                    Recharger
                </button>
            </div>
        );
    }
}