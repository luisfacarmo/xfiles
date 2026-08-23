const webpackConfig = require('@nextcloud/webpack-vue-config')

// Add the init entry point (for FileAction registration in Files app)
webpackConfig.entry.init = './src/init.js'

// Keep the standalone ESM module (xfiles-init.mjs) that NC 34 loads
// as type="module" for shared registry access. Don't clean it.
webpackConfig.output.clean = {
	keep: /xfiles-init\.mjs$/,
}

module.exports = webpackConfig
