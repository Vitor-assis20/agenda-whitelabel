import '../css/app.css';

import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import { route as routeFn } from 'ziggy-js';
import { initializeTheme } from './hooks/use-appearance';
import { type SharedData } from './types';

declare global {
    const route: typeof routeFn;
}

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

// Nas páginas de um tenant, a aba mostra a marca dele, nunca a da plataforma.
// O tenant não muda durante a navegação: trocar de subdomínio recarrega a página.
let brand = appName;

createInertiaApp({
    title: (title) => (title ? `${title} - ${brand}` : brand),
    resolve: (name) => resolvePageComponent(`./pages/${name}.tsx`, import.meta.glob('./pages/**/*.tsx')),
    setup({ el, App, props }) {
        brand = (props.initialPage.props as unknown as SharedData).tenant?.name ?? appName;

        const root = createRoot(el);

        root.render(<App {...props} />);
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on load...
initializeTheme();
