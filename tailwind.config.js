/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    "./submenu/**/*.php",
    "./templates/**/*.php",
    "./includes/**/*.php",
    "./*.php"
  ],
  theme: {
    extend: {},
  },
  plugins: [],
  // Important: Prevent Tailwind from purging styles when used as a WordPress plugin
  important: true,
  // Prefix Tailwind classes to avoid conflicts with other plugins
  prefix: 'nmu-'
}
