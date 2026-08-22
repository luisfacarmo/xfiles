const webpackConfig = require('@nextcloud/webpack-vue-config')

// Add the init entry point (for FileAction registration in Files app)
webpackConfig.entry.init = './src/init.js'

module.exports = webpackConfig
