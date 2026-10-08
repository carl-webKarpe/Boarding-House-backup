/** Tailwind build for the student Browse Rooms page. Rebuild with: npm run build:tenant */
module.exports = {
  content: ['./php/browse-rooms.php', './registerJS/rooms.js'],
  theme: {
    extend: {
      colors: {
        brand: { DEFAULT: '#073F3B', light: '#0B5C55' },
        lime: '#A8F15A',
        mint: '#DFF8C5',
        cream: '#F8F8F1',
        ink: '#10201E',
        muted: '#5B6B68',
      },
      fontFamily: {
        display: ['Poppins', 'system-ui', 'sans-serif'],
        sans: ['Inter', 'system-ui', 'sans-serif'],
      },
      boxShadow: {
        card: '0 1px 2px rgba(7, 63, 59, 0.05), 0 12px 32px -16px rgba(7, 63, 59, 0.22)',
      },
    },
  },
  plugins: [],
};
