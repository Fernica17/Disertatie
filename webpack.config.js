const Encore = require('@symfony/webpack-encore');

if (!Encore.isRuntimeEnvironmentConfigured()) {
    Encore.configureRuntimeEnvironment(process.env.NODE_ENV || 'dev');
}

Encore
    .setOutputPath('public/build/')
    .setPublicPath('/build')
    .addEntry('app', './assets/js/app.js')
    .addEntry('admin', './assets/js/admin.js')
    .addEntry('login', './assets/js/login.js')
    .addEntry('face-login', './assets/js/face-login.js')
    .addEntry('profile-edit', './assets/js/profile-edit.js')
    .addEntry('profile', './assets/js/profile.js')
    .enableStimulusBridge('./assets/controllers.json')
    .splitEntryChunks()

    // enables the Symfony UX Stimulus bridge (used in assets/stimulus_bootstrap.js)
    .enableStimulusBridge('./assets/controllers.json')
    .enableSingleRuntimeChunk()
    .cleanupOutputBeforeBuild()
    .enableSourceMaps(!Encore.isProduction())
    // Versioned filenames in dev too: the node container runs `encore dev --watch`
    // (not dev-server), so without a content hash the browser keeps serving a
    // stale bundle after every asset change.
    .enableVersioning()
    .enableSassLoader()
    .enablePostCssLoader()
    .configureBabelPresetEnv((config) => {
        config.useBuiltIns = 'usage';
        config.corejs = '3.38';
    })
;

module.exports = Encore.getWebpackConfig();
