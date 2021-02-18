const Encore = require("@symfony/webpack-encore");

// Manually configure the runtime environment if not already configured yet by the "encore" command.
// It"s useful when you use tools that rely on webpack.config.js file.
if (!Encore.isRuntimeEnvironmentConfigured()) {
    Encore.configureRuntimeEnvironment(process.env.NODE_ENV || "dev");
}

Encore
    .setOutputPath("public/build/")
    .setPublicPath("/build")

    .addEntry("app", "./assets/app.js")
    .addEntry("user_list", "./assets/pages/user-list.js")
    .addEntry("role_list", "./assets/pages/role-list.js")
    .addEntry("location_list", "./assets/pages/location-list.js")
    .addEntry("group_list", "./assets/pages/group-list.js")
    .addEntry("client_list", "./assets/pages/client-list.js")
    .addEntry("setting", "./assets/pages/setting.js")
    .autoProvidejQuery()

    .splitEntryChunks()
    .enableSingleRuntimeChunk()
    .cleanupOutputBeforeBuild()
    .enableSourceMaps(!Encore.isProduction())
    .enableVersioning(Encore.isProduction())

    .configureBabel((config) => {
        config.plugins.push("@babel/plugin-proposal-class-properties");
    })

    // enables @babel/preset-env polyfills
    .configureBabelPresetEnv((config) => {
        config.useBuiltIns = "usage";
        config.corejs = 3;
    })

    .enableSassLoader();

module.exports = Encore.getWebpackConfig();
