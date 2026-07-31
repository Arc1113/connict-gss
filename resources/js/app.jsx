import '../css/app.css';

import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import PwaStatus from './components/PwaStatus';

const pages = import.meta.glob('./pages/**/*.jsx');

createInertiaApp({
    title: (title) =>
        title ? `${title} — Graduation Stage System` : 'Graduation Stage System',
    resolve: (name) => pages[`./pages/${name}.jsx`](),
    setup({ el, App, props }) {
        createRoot(el).render(
            <>
                <App {...props} />
                <PwaStatus />
            </>,
        );
    },
    progress: {
        color: '#a96f29',
    },
});
