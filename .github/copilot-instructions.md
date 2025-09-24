# NotulenMu WordPress Plugin

**ALWAYS follow these instructions first and fallback to search or bash commands only when you encounter unexpected information that does not match the info here.**

NotulenMu is a WordPress plugin designed for documentation of meeting minutes (notulen) at Muhammadiyah organization levels (wilayah, daerah, cabang, ranting). The plugin also includes KegiatanMu for documenting activities. Built with PHP, WordPress APIs, TailwindCSS, and PostCSS.

## Working Effectively

### Bootstrap and Build the Repository
- Install Node.js dependencies:
  ```bash
  npm install
  ```
  - Takes approximately 10 seconds
  - NEVER CANCEL: Set timeout to 60+ seconds to handle network variations

- Build CSS assets:
  ```bash
  npm run build
  ```
  - Takes approximately 1 second
  - NEVER CANCEL: Set timeout to 30+ seconds
  - Builds TailwindCSS from `assets/css/src/styles.css` to `assets/css/dist/styles.css`
  - Warning about "No utility classes detected" is expected and harmless

- Development CSS watching:
  ```bash
  npm run watch
  ```
  - Runs continuously watching for changes
  - NEVER CANCEL: Let it run indefinitely when needed for development

### WordPress Environment Setup
**CRITICAL**: This is a WordPress plugin and requires a WordPress installation to run properly.

- WordPress plugin installation directory: `wp-content/plugins/notulenmu/`
- The plugin depends on WordPress core functions: `wp_enqueue_script`, `get_current_user_id`, `wp_die`, `$wpdb`, etc.
- Database tables are created automatically when plugin is activated through WordPress admin
- **Cannot be run standalone** - requires full WordPress environment

### PHP Validation
- Check PHP syntax for all files:
  ```bash
  php -l notulenmu.php
  find submenu -name "*.php" -exec php -l {} \;
  find includes -name "*.php" -exec php -l {} \;
  ```
  - All files should return "No syntax errors detected"
  - PHP 8.3+ is supported

## Validation

### Build Validation
- ALWAYS run `npm install` and `npm run build` after making changes to:
  - `package.json`
  - `tailwind.config.js` 
  - `postcss.config.js`
  - `assets/css/src/styles.css`

### PHP Validation
- ALWAYS run PHP syntax check after modifying PHP files:
  ```bash
  php -l [modified-file.php]
  ```

### WordPress Plugin Validation Scenarios
**CRITICAL**: Since this is a WordPress plugin, full functional testing requires a WordPress environment. However, you can validate:

1. **Syntax validation** - All PHP files should pass `php -l` checks
2. **WordPress function usage** - Ensure proper WordPress hooks and functions are used
3. **Database queries** - Check `$wpdb` usage follows WordPress standards
4. **Security** - Verify `wp_nonce` usage and input sanitization with `sanitize_text_field()`

### Manual Testing Requirements
**Since you cannot run WordPress locally**, focus on:
- PHP syntax validation
- CSS build verification
- Code review for WordPress best practices
- Verify file structure remains intact after changes

## Common Tasks

### File Structure
```
notulenmu/
├── .github/
│   └── copilot-instructions.md
├── assets/
│   ├── css/
│   │   ├── src/styles.css      # TailwindCSS source
│   │   └── dist/styles.css     # Built CSS output
│   └── img/
├── includes/
│   └── styles.php              # WordPress script enqueue
├── submenu/                    # Plugin admin pages
│   ├── about_notulen.php
│   ├── input_notulen.php
│   ├── list_notulen.php
│   ├── setting_notulen.php
│   ├── tambah_notulen.php
│   └── [other admin pages]
├── notulenmu.php              # Main plugin file
├── package.json               # Node.js dependencies
├── tailwind.config.js         # TailwindCSS configuration
└── postcss.config.js          # PostCSS configuration
```

### Key WordPress Functions Used
- `wp_enqueue_script()` - Loading Tailwind CSS from CDN
- `get_current_user_id()` - User authentication
- `current_user_can()` - Permission checking
- `wp_die()` - Error handling
- `$wpdb` - Database operations
- `wp_nonce_field()` / `wp_verify_nonce()` - Security nonces
- `sanitize_text_field()` - Input sanitization

### Database Tables Created
The plugin creates several custom tables with prefix `wp_`:
- `wp_salammu_notulenmu` - Meeting minutes storage
- `wp_sicara_settings` - User organization settings
- `wp_salammu_data_pengurus` - Organization member data
- `wp_salammu_data_kegiatan` - Activities data

### TailwindCSS Configuration
- Content paths include: `submenu/**/*.php`, `templates/**/*.php`, `includes/**/*.php`, `./*.php`
- Uses prefix `nmu-` to avoid conflicts with other WordPress plugins
- Important: true to override WordPress theme styles
- PostCSS processes with autoprefixer

## Troubleshooting

### Common Issues
1. **"No utility classes detected" warning**: Expected when no TailwindCSS classes are found in PHP files
2. **WordPress function errors**: Plugin must be used within WordPress environment
3. **Database errors**: WordPress database configuration required
4. **Permission errors**: User must have appropriate WordPress capabilities

### Build Process
- CSS build is extremely fast (~1 second)
- Node.js dependencies install in ~10 seconds
- No complex compilation or transpilation steps
- Output files are automatically generated in `assets/css/dist/`

### WordPress Development Notes
- Plugin version: 2.1
- WordPress admin pages use TailwindCSS for styling
- External API calls to `https://old.sicara.id/api/v0/organisation/`
- File uploads handled through WordPress media library
- Rich text editing uses `wp_editor()` function

## Development Workflow

### Making Changes
1. **PHP files**: Edit directly, validate syntax with `php -l`
2. **CSS changes**: Edit `assets/css/src/styles.css`, run `npm run build`
3. **JavaScript**: Located in individual PHP files (inline scripts)
4. **Configuration**: Modify `tailwind.config.js` or `postcss.config.js` as needed

### Before Committing
- Run `php -l` on all modified PHP files
- Run `npm run build` if CSS or config files were modified
- Verify file structure integrity
- Check that all WordPress functions are properly used

**NEVER** attempt to run the plugin outside of WordPress environment - it will fail due to missing WordPress core functions and database setup.