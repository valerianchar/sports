import { createApp, h } from 'vue';
import { createInertiaApp, router } from '@inertiajs/vue3';
import { setAudioMode } from './audio';
import { alertsAllowed, cancelAllAlerts } from './alerts';
import AppLayout from './layouts/AppLayout.vue';
import { registerServiceWorker } from './pwa';

const appName = import.meta.env.VITE_APP_NAME || 'Séance';

createInertiaApp({
    title: (title) => (title ? `${title} — ${appName}` : appName),
    resolve: (name) => {
        const pages = import.meta.glob('./pages/**/*.vue', { eager: true });
        const page = pages[`./pages/${name}.vue`];

        // Les écrans connectés partagent le cadre de l'application ; les écrans
        // invités déclarent explicitement `layout = null`.
        page.default.layout = page.default.layout === undefined ? AppLayout : page.default.layout;

        return page;
    },
    setup({ el, App, props, plugin }) {
        // Le mode du son (avec la musique ou prioritaire) suit le compte connecté.
        setAudioMode(props.initialPage.props.auth?.user?.audio_mode);
        router.on('navigate', (event) => setAudioMode(event.detail.page.props.auth?.user?.audio_mode));

        // L'appli à l'écran, sur n'importe quelle page : les alertes en attente n'ont plus lieu
        // d'être — séance reprise, finie ou abandonnée, même sans repasser par le lecteur.
        const signedIn = () => Boolean(router.page?.props?.auth?.user ?? props.initialPage.props.auth?.user);
        const stopAlerts = () => {
            if (document.visibilityState === 'visible' && signedIn() && alertsAllowed()) {
                cancelAllAlerts();
            }
        };

        stopAlerts();
        document.addEventListener('visibilitychange', stopAlerts);

        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
    progress: {
        color: '#d4ff3a',
        showSpinner: false,
    },
});

registerServiceWorker();
