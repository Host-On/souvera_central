/**
 * Webpack Configuration für Souvera Central
 */

const path = require('path')
const { VueLoaderPlugin } = require('vue-loader')

module.exports = {
  entry: {
    main: path.join(__dirname, 'src', 'main.js')
  },
  output: {
    path: path.resolve(__dirname, 'js'),
    filename: 'souvera_central-[name].js',
    chunkFilename: 'souvera_central-[name].js'
  },
  // Source Maps ohne eval() für Nextcloud CSP-Kompatibilität
  devtool: 'source-map',
  module: {
    rules: [
      {
        test: /\.vue$/,
        loader: 'vue-loader'
      },
      {
        test: /\.js$/,
        loader: 'babel-loader',
        exclude: /node_modules/
      },
      {
        test: /\.css$/,
        use: ['style-loader', 'css-loader']
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
