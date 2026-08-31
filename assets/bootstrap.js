import { startStimulusApp } from '@symfony/stimulus-bridge';

export const app = startStimulusApp(require.context(
    './controllers',
    true,
    /\.(j|t)sx?$/
));

// stimulus-bridge porneste debug automat in dev, ceea ce logheaza fiecare
// actiune declansata (`face-capture #showCamera`, ...) si ineaca consola.
app.debug = false;
