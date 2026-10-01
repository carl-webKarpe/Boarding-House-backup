/** Tailwind build for the admin dashboard. Rebuild with: npm run build:admin-css */
module.exports = {
  content: ['./admin/**/*.php', './admin/assets/js/**/*.js'],
  theme: {
    extend: {
      colors: {
        primary: { DEFAULT: '#16A34A', light: '#22C55E', dark: '#15803D', 50: '#F0FDF4', 100: '#DCFCE7' },
        accent: '#10B981',
        ink: '#1E293B',
        canvas: '#F8FAFC',
      },
      fontFamily: {
        display: ['Poppins', 'system-ui', 'sans-serif'],
        sans: ['Inter', 'system-ui', 'sans-serif'],
      },
      boxShadow: {
        card: '0 1px 2px rgba(15, 23, 42, 0.04), 0 8px 24px -12px rgba(15, 23, 42, 0.12)',
      },
    },
  },
  plugins: [],
};
