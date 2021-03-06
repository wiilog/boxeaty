const Encore = require("@symfony/webpack-encore");
const CopyPlugin = require('copy-webpack-plugin');

// Manually configure the runtime environment if not already configured yet by the "encore" command.
// It"s useful when you use tools that rely on webpack.config.js file.
if (!Encore.isRuntimeEnvironmentConfigured()) {
    Encore.configureRuntimeEnvironment(process.env.NODE_ENV || "dev");
}

Encore
    .setOutputPath("public/build/")
    .setPublicPath("/build")

    .addEntry("app", "./assets/app.js")
    .addEntry("security", "./assets/pages/security.js")
    .addEntry("box_list", "./assets/pages/box-list.js")
    .addEntry("box_show", "./assets/pages/box-show.js")
    .addEntry("deposit_ticket_list", "./assets/pages/deposit-ticket-list.js")
    .addEntry("movement_list", "./assets/pages/movement-list.js")
    .addEntry("location_list", "./assets/pages/location-list.js")
    .addEntry("group_list", "./assets/pages/group-list.js")
    .addEntry("client_list", "./assets/pages/client-list.js")
    .addEntry("box_type_list", "./assets/pages/box-type-list.js")
    .addEntry("setting", "./assets/pages/setting.js")
    .addEntry("user_list", "./assets/pages/user-list.js")
    .addEntry("role_list", "./assets/pages/role-list.js")
    .addEntry("quality_list", "./assets/pages/quality-list.js")
    .addEntry("import_list", "./assets/pages/import-list.js")
    .addEntry("order_list", "./assets/pages/order-list.js")
    .addEntry("order", "./assets/pages/order.js")
    .autoProvidejQuery()

    .addPlugin(new CopyPlugin({
        patterns : [
            {
                from: 'node_modules/qr-scanner/qr-scanner-worker.min.js',
                to: 'vendor/qr-scanner-worker.min.js'
            },
            {
                from: 'node_modules/qr-scanner/qr-scanner-worker.min.js.map',
                to: 'vendor/qr-scanner-worker.min.js.map'
            }
        ]
    }))

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
