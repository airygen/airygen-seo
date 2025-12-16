/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    './packages/admin/**/*.{js,jsx,ts,tsx}',
    './packages/block-editor/**/*.{js,jsx,ts,tsx}',
    './packages/classic-editor/**/*.{js,jsx,ts,tsx}',
    './src/**/*.php',
    './templates/**/*.php',
  ],
  theme: {
    extend: {},
  },
  plugins: [],
};
