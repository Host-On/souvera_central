const path = require('path')
const { VueLoaderPlugin } = require('vue-loader')

module.exports = (env, argv) => {
    const isProduction = argv.mode === 'production'
    return {
        entry: {
            preview: path.join(__dirname, 'preview-entry.js'),
            'help-preview': path.join(__dirname, 'help-entry.js')
        },
        output: {
            path: path.resolve(__dirname),
            filename: '[name].js',
            chunkFilename: '[name].chunk.js'
        },
        devtool: false,
        module: {
            rules: [
                { test: /\.vue$/, loader: 'vue-loader' },
                { test: /\.m?js$/, resolve: { fullySpecified: false } },
                { test: /\.js$/, loader: 'babel-loader', exclude: /node_modules/ },
                { test: /\.css$/, use: ['style-loader', { loader: 'css-loader', options: { url: false } }] },
                { test: /\.scss$/, use: ['style-loader', { loader: 'css-loader', options: { url: false } }, 'sass-loader'] },
                { test: /\.(png|jpe?g|gif|svg|woff2?|eot|ttf)$/, type: 'asset/inline' }
            ]
        },
        plugins: [new VueLoaderPlugin()],
        resolve: {
            extensions: ['.js', '.mjs', '.vue'],
            alias: {
                '@': path.resolve(__dirname, '..', 'src'),
                '@nextcloud/axios': path.resolve(__dirname, 'mocks', 'nc-axios.js')
            }
        }
    }
}
