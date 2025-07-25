/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    './templates/**/*.html.twig',
    './assets/**/*.js',
  ],
  theme: {
    extend: {
      fontFamily: {
        sans: ['jakarta', 'sans-serif'],
      },
     colors: {
        trash: '#4baddb', /* Global blue color */
        trash_bold: '#3a8ac0',
      },
     keyframes: {
      'gentle-ping': {
        '0%, 100%': { transform: 'scale(1)', opacity: '1' },
        '50%': { transform: 'scale(1.1)', opacity: '0.7' },
      },
    },
    animation: {
      'gentle-ping': 'gentle-ping 2s ease-in-out infinite',
    },
    },
  },
  plugins: [],
}
