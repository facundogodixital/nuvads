import js from '@eslint/js';
import globals from 'globals';
import pluginVue from 'eslint-plugin-vue';
import stylistic from '@stylistic/eslint-plugin';

const fetchRestriction = { name: 'fetch', message: 'Las llamadas HTTP pasan por el helper APICall.' };
// Todo lo que se guarda en el navegador pasa por authStorage y preferencesStorage, para tenerlo en un solo lugar.
const storageMessage = 'El storage del navegador se usa solo desde authStorage.js y preferencesStorage.js.';
const storageRestrictions = [
  { name: 'localStorage', message: storageMessage },
  { name: 'sessionStorage', message: storageMessage },
];

export default [
  {
    ignores: ['vendor/**', 'node_modules/**', 'public/**', 'storage/**'],
  },

  js.configs.recommended,
  ...pluginVue.configs['flat/recommended'],

  {
    files: ['resources/js/**/*.{js,vue}'],
    plugins: {
      '@stylistic': stylistic,
    },
    languageOptions: {
      globals: {
        ...globals.browser,
      },
    },
    rules: {
      // Indentación de 2 espacios (AGENTS.md, sección 3).
      '@stylistic/indent': ['error', 2],
      '@stylistic/semi': ['error', 'always'],

      // Comillas simples; backticks solo cuando el string es compuesto (AGENTS.md, sección 3).
      '@stylistic/quotes': ['error', 'single', { avoidEscape: true }],

      // Estructura de los componentes (skill frontend-vue).
      'vue/block-order': ['error', { order: ['template', 'script', 'style'] }],
      'vue/component-api-style': ['error', ['script-setup']],
      'vue/require-prop-types': 'error',
      'vue/require-default-prop': 'error',
      'vue/define-macros-order': ['error', { order: ['defineProps', 'defineEmits'] }],
      'vue/html-indent': ['error', 2],

      // Toda llamada HTTP pasa por APICall (skill frontend-vue).
      'no-restricted-imports': ['error', {
        paths: [{ name: 'axios', message: 'Las llamadas HTTP pasan por el helper APICall.' }],
      }],
      'no-restricted-globals': ['error', fetchRestriction, ...storageRestrictions],
      'no-restricted-properties': ['error',
        { object: 'window', property: 'localStorage', message: storageMessage },
        { object: 'window', property: 'sessionStorage', message: storageMessage },
      ],
    },
  },

  {
    // Dentro de <script setup> el contenido va sin indentación base.
    files: ['resources/js/**/*.vue'],
    rules: {
      '@stylistic/indent': 'off',
      'vue/script-indent': ['error', 2, { baseIndent: 0 }],
    },
  },

  {
    // El único lugar donde se habla HTTP directo. Sus firmas son un contrato
    // (skill frontend-vue), por eso no se marcan los argumentos sin usar.
    files: ['resources/js/helpers/APICall.js'],
    rules: {
      'no-restricted-imports': 'off',
      'no-restricted-globals': ['error', ...storageRestrictions],
      'no-unused-vars': ['error', { args: 'none' }],
    },
  },

  {
    // Los únicos lugares que leen y escriben el storage del navegador.
    files: ['resources/js/helpers/authStorage.js', 'resources/js/helpers/preferencesStorage.js'],
    rules: {
      'no-restricted-globals': ['error', fetchRestriction],
      'no-restricted-properties': 'off',
    },
  },
];
