import js from '@eslint/js';
import eslintConfigPrettier from 'eslint-config-prettier';
import pluginVue from 'eslint-plugin-vue';
import globals from 'globals';

export default [
  js.configs.recommended,
  ...pluginVue.configs['flat/vue2-essential'],
  ...pluginVue.configs['flat/vue2-recommended'],
  ...pluginVue.configs['flat/vue2-strongly-recommended'],
  {
    languageOptions: {
      sourceType: 'module',
      globals: {
        ...globals.browser,
      },
    },
    files: ['**/*.{js,vue}'],
    plugins: {
      vue: pluginVue,
    },
    rules: {
      'array-bracket-spacing': ['error', 'never'],
      indent: ['error', 2],
      'linebreak-style': ['error', 'unix'],
      'no-undef': 0,
      'no-trailing-spaces': [
        'error',
        {
          ignoreComments: true,
          skipBlankLines: true,
        },
      ],
      semi: ['error', 'always'],
      'semi-spacing': [
        'error',
        {
          before: false,
          after: true,
        },
      ],
      'semi-style': ['error', 'last'],
      'vue/component-name-in-template-casing': 0,
      'vue/html-end-tags': 'error',
      'vue/html-self-closing': 0,
      'vue/no-v-model-argument': 0,
      'vue/no-v-html': 0,
      'vue/multi-word-component-names': 0,
      'vue/max-attributes-per-line': [
        'error',
        {
          singleline: 6,
          multiline: 6,
        },
      ],
      'vue/singleline-html-element-content-newline': 0,
      'vue/no-deprecated-events-api': 0,
      'vue/require-default-prop': 0,
      'vue/attributes-order': 0,
      'vue/next-tick-style': [
        // https://eslint.vuejs.org/rules/next-tick-style.html
        'error',
        'promise',
      ],
    },
  },
  eslintConfigPrettier,
];
