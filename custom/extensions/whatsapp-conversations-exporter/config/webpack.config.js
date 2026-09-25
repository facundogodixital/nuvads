'use strict';

const { merge } = require('webpack-merge');

const PATHS = require('./paths');
const webpack = require('webpack');
const common = require('./webpack.common.js');


// Merge webpack configuration files
const config = (env, argv) =>
  merge(common, {
    entry: {
      popup: PATHS.src + '/popup.js',
      background: PATHS.src + '/background.js',
      'clienty.content': PATHS.src + '/clienty.content.js',
      'web.whatsapp.content': PATHS.src + '/web.whatsapp.content.js',
      'web.whatsapp.content.accessible': PATHS.src + '/web.whatsapp.content.accessible.js',
    },
    devtool: argv.mode === 'production' ? false : 'source-map',
    resolve: {
      alias: {
        '@': PATHS.src,
      },
    },
    plugins: [
      new webpack.DefinePlugin({
        'process.env': {
          NODE_ENV: JSON.stringify(argv.mode),
        },
      }),
    ],
  });

module.exports = config;
