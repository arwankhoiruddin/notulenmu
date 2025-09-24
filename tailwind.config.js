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
  // Scope to plugin containers to avoid conflicts
  important: '.notulenmu-container'
}
