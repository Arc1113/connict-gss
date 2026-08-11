import '../css/app.css';

import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import ApplicationRoot from './components/ApplicationRoot';

const pages = import.meta.glob('./pages/**/*.jsx');

createInertiaApp({
    title: (title) =>
        title ? `${title} — Graduation Stage System` : 'Graduation Stage System',
    resolve: (name) => pages[`./pages/${name}.jsx`](),
    setup({ el, App, props }) {
        createRoot(el).render(<ApplicationRoot App={App} props={props} />);
    },
    progress: {
        color: '#1769e0',
    },
});
