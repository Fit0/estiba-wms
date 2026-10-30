import js from '@eslint/js';
import prettier from 'eslint-config-prettier';
import jsxA11y from 'eslint-plugin-jsx-a11y';
import reactHooks from 'eslint-plugin-react-hooks';
import globals from 'globals';
import tseslint from 'typescript-eslint';

export default tseslint.config(
  { ignores: ['src/api/schema.d.ts', 'dist', 'coverage', 'playwright-report', 'test-results'] },

  {
    files: ['**/*.{ts,tsx}'],
    extends: [
      js.configs.recommended,
      ...tseslint.configs.strictTypeChecked,
      ...tseslint.configs.stylisticTypeChecked,
      reactHooks.configs.flat.recommended,
      jsxA11y.flatConfigs.recommended,
      prettier,
    ],
    languageOptions: {
      ecmaVersion: 2023,
      globals: globals.browser,
      parserOptions: {
        projectService: true,
        tsconfigRootDir: import.meta.dirname,
      },
    },
    rules: {
      // No `any` and no `!` to silence the compiler (CLAUDE.md): use `unknown` and narrow it.
      '@typescript-eslint/no-explicit-any': 'error',
      '@typescript-eslint/no-non-null-assertion': 'error',
      // `import type` for types, as `verbatimModuleSyntax` asks for.
      '@typescript-eslint/consistent-type-imports': 'error',
      // Strict comparisons and no loose truthiness, as in the backend.
      eqeqeq: ['error', 'always'],
      '@typescript-eslint/strict-boolean-expressions': 'error',
    },
  },

  // Playwright fixtures call `use`, which is not React's hook.
  {
    files: ['e2e/**/*.ts'],
    rules: { 'react-hooks/rules-of-hooks': 'off' },
  },

  // The configuration files run in Node.
  {
    files: ['*.config.{js,ts}'],
    languageOptions: { globals: globals.node },
  },
  {
    files: ['**/*.js'],
    extends: [tseslint.configs.disableTypeChecked],
  },
);
