import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

let instance: Echo<'reverb'> | null = null;

/** Conexão única com o Reverb, criada só quando alguma tela precisa. */
export function echo(): Echo<'reverb'> | null {
    if (instance) return instance;
    if (!import.meta.env.VITE_REVERB_APP_KEY) return null;

    (window as unknown as { Pusher: typeof Pusher }).Pusher = Pusher;
    const scheme = import.meta.env.VITE_REVERB_SCHEME ?? 'https';

    instance = new Echo({
        broadcaster: 'reverb',
        key: import.meta.env.VITE_REVERB_APP_KEY,
        wsHost: import.meta.env.VITE_REVERB_HOST,
        wsPort: Number(import.meta.env.VITE_REVERB_PORT ?? 80),
        wssPort: Number(import.meta.env.VITE_REVERB_PORT ?? 443),
        forceTLS: scheme === 'https',
        enabledTransports: ['ws', 'wss'],
    });

    return instance;
}
