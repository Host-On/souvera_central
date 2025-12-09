/**
 * Webpack Configuration für Souvera Central
 */

const path = require('path')
const { VueLoaderPlugin } = require('vue-loader')

module.exports = (env, argv) => {
  const isProduction = argv.mode === 'production'

  return {
  entry: {
    main: path.join(__dirname, 'src', 'main.js')
  },
  output: {
    path: path.resolve(__dirname, 'js'),
    filename: 'souvera_central-[name].js',
    chunkFilename: 'souvera_central-[name].js'
  },
  // Source Maps: nur in Development (Production = keine Source Maps, keine Kommentare)
  devtool: isProduction ? false : 'source-map',
  module: {
    rules: [
      {
        test: /\.vue$/,
        loader: 'vue-loader',
        options: {
          compilerOptions: {
            // HTML-Kommentare in Production entfernen
            comments: !isProduction
          }
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
              // Keine CSS Source Maps in Production
              sourceMap: !isProduction
            }
          }
        ]
      }
    ]
  },
  plugins: [
    new VueLoaderPlugin()
  ],
  resolve: {
    extensions: ['.js', '.vue'],
    alias: {
      '@': path.resolve(__dirname, 'src')
    }
  }
  }
}
