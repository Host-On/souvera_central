/**
 * Webpack Configuration für Souvera Central
 *
 * Erweitert um @nextcloud/vue v9 (Nextcloud 34): SCSS, Asset-Handling und
 * .mjs-Auflösung für die nativen Nextcloud-Komponenten.
 */

const path = require('path')
const { VueLoaderPlugin } = require('vue-loader')

module.exports = (env, argv) => {
    const isProduction = argv.mode === 'production'

    return {
        entry: {
            main: path.join(__dirname, 'src', 'main.js'),
            branding: path.join(__dirname, 'src', 'branding.js'),
            help: path.join(__dirname, 'src', 'help-main.js'),
            changelog: path.join(__dirname, 'src', 'changelog-main.js')
        },
        output: {
            path: path.resolve(__dirname, 'js'),
            filename: 'souvera_central-[name].js',
            chunkFilename: 'souvera_central-[name].js?v=[contenthash]',
            clean: false
        },
        devtool: isProduction ? false : 'source-map',
        module: {
            rules: [
                {
                    test: /\.vue$/,
                    loader: 'vue-loader',
                    options: {
                        compilerOptions: {
                            comments: !isProduction
                        }
                    }
                },
                {
                    // @nextcloud/vue ships fully-specified .mjs; allow webpack to resolve them
                    test: /\.m?js$/,
                    resolve: {
                        fullySpecified: false
                    }
                },
                {
                    test: /\.js$/,
                    loader: 'babel-loader',
                    exclude: /node_modules/
                },
                {
                    test: /\.css$/,
                    use: [
                        'style-loader',
                        {
                            loader: 'css-loader',
                            options: {
                                sourceMap: !isProduction,
                                url: false
                            }
                        }
                    ]
                },
                {
                    test: /\.scss$/,
                    use: [
                        'style-loader',
                        {
                            loader: 'css-loader',
                            options: {
                                sourceMap: !isProduction,
                                url: false
                            }
                        },
                        {
                            loader: 'sass-loader',
                            options: {
                                sourceMap: !isProduction
                            }
                        }
                    ]
                },
                {
                    test: /\.(png|jpe?g|gif|svg|woff2?|eot|ttf)$/,
                    type: 'asset/inline'
                }
            ]
        },
        plugins: [
            new VueLoaderPlugin()
        ],
        resolve: {
            extensions: ['.js', '.mjs', '.vue'],
            alias: {
                '@': path.resolve(__dirname, 'src')
            }
        }
    }
}
