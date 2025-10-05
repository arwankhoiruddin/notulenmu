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

### Sicara Plugin Database Tables
NotulenMu sometimes requires access to tables from the Sicara plugin. Below are the Sicara plugin table definitions:

#### Organization Structure Tables
- `wp_sicara_pwm` - Pimpinan Wilayah Muhammadiyah (Regional Leadership)
  - `id_pwm` (int, AUTO_INCREMENT, PRIMARY KEY)
  - `wilayah` (varchar(255), NOT NULL)
  - `date_created` (DATETIME, NOT NULL, DEFAULT CURRENT_TIMESTAMP)
  - `last_modified` (DATETIME, NOT NULL, DEFAULT CURRENT_TIMESTAMP ON UPDATE)
  - `latitude` (float, NOT NULL)
  - `longitude` (float, NOT NULL)
  - `kodepos` (varchar(10), NOT NULL)

- `wp_sicara_pdm` - Pimpinan Daerah Muhammadiyah (District Leadership)
  - `id_pdm` (int, AUTO_INCREMENT, PRIMARY KEY)
  - `id_pwm` (int, NOT NULL, FOREIGN KEY to wp_sicara_pwm)
  - `daerah` (varchar(255), NOT NULL)
  - `latitude` (float, NOT NULL)
  - `longitude` (float, NOT NULL)
  - `kodepos` (varchar(10), NOT NULL)
  - `date_created` (DATETIME, NOT NULL, DEFAULT CURRENT_TIMESTAMP)
  - `last_modified` (DATETIME, NOT NULL, DEFAULT CURRENT_TIMESTAMP ON UPDATE)

- `wp_sicara_pcm` - Pimpinan Cabang Muhammadiyah (Branch Leadership)
  - `id_pcm` (int, AUTO_INCREMENT, PRIMARY KEY)
  - `id_pdm` (int, NOT NULL, FOREIGN KEY to wp_sicara_pdm)
  - `cabang` (varchar(255), NOT NULL)
  - `latitude` (float, NOT NULL)
  - `longitude` (float, NOT NULL)
  - `kodepos` (varchar(10), NOT NULL)
  - `date_created` (DATETIME, NOT NULL, DEFAULT CURRENT_TIMESTAMP)
  - `last_modified` (DATETIME, NOT NULL, DEFAULT CURRENT_TIMESTAMP ON UPDATE)

- `wp_sicara_prm` - Pimpinan Ranting Muhammadiyah (Sub-branch Leadership)
  - `id_prm` (int, AUTO_INCREMENT, PRIMARY KEY)
  - `id_pcm` (int, NOT NULL, FOREIGN KEY to wp_sicara_pcm)
  - `ranting` (varchar(255), NOT NULL)
  - `latitude` (float, NOT NULL)
  - `longitude` (float, NOT NULL)
  - `kodepos` (varchar(10), NOT NULL)
  - `date_created` (DATETIME, NOT NULL, DEFAULT CURRENT_TIMESTAMP)
  - `last_modified` (DATETIME, NOT NULL, DEFAULT CURRENT_TIMESTAMP ON UPDATE)

#### Settings and Personnel Tables
- `wp_sicara_settings` - User organization settings (already mentioned above, used by NotulenMu)
  - `id_setting` (int, AUTO_INCREMENT, PRIMARY KEY)
  - `user_id` (int, NOT NULL)
  - `pwm` (int, NOT NULL)
  - `pdm` (int, NOT NULL)
  - `pcm` (int, NOT NULL)
  - `prm` (int, NOT NULL)
  - `latitude` (float, NOT NULL)
  - `longitude` (float, NOT NULL)

- `wp_sicara_pengurus` - Organization personnel/management data
  - `id` (int, AUTO_INCREMENT, PRIMARY KEY)
  - `tingkat` (ENUM: 'wilayah', 'daerah', 'cabang', 'ranting', NOT NULL)
  - `id_tingkat` (int, NOT NULL)
  - `nama` (varchar(255), NOT NULL)
  - `jabatan` (varchar(255), NOT NULL)
  - `no_hp` (varchar(20), NOT NULL)
  - `tanggal_lahir` (date, NOT NULL)
  - `pendidikan_terakhir` (ENUM: 'SD', 'SMP', 'SMA', 'D1', 'D2', 'D3', 'S1', 'S2', 'S3', NOT NULL)
  - `pekerjaan` (ENUM: 'PNS', 'SWASTA', 'WIRAUSAHA', 'LAINNYA', NOT NULL)
  - `tempat_kerja` (varchar(255), NOT NULL)
  - `alamat` (varchar(255), NOT NULL)
  - `date_created` (DATETIME, NOT NULL, DEFAULT CURRENT_TIMESTAMP)
  - `last_modified` (DATETIME, NOT NULL, DEFAULT CURRENT_TIMESTAMP ON UPDATE)

#### Organization Details Tables
- `wp_sicara_organisasi` - Organization information for PDM/PWM
  - `id_organisasi` (int, NOT NULL, PRIMARY KEY)
  - `tahun_berdiri` (int, NOT NULL)
  - `nomor_sk` (varchar(50), NOT NULL)
  - `periode_muktamar` (ENUM: '46', '47', '48', NOT NULL)
  - `alamat` (varchar(255), NOT NULL)
  - `kodepos` (varchar(10), NOT NULL)
  - `website` (varchar(255), NOT NULL)
  - `email` (varchar(255), NOT NULL)
  - `social_media` (varchar(255), NOT NULL)
  - `date_created` (DATETIME, NOT NULL, DEFAULT CURRENT_TIMESTAMP)
  - `last_modified` (DATETIME, NOT NULL, DEFAULT CURRENT_TIMESTAMP ON UPDATE)

- `wp_sicara_organisasi_cabang` - Branch organization information
  - `id_pdm` (int, NOT NULL, PRIMARY KEY with id_pcm)
  - `id_pcm` (int, NOT NULL, PRIMARY KEY with id_pdm)
  - `tahun_berdiri` (int, NOT NULL)
  - `nomor_sk` (varchar(50), NOT NULL)
  - `periode_muktamar` (ENUM: '46', '47', '48', NOT NULL)
  - `kecamatan` (varchar(255), NOT NULL)
  - `alamat` (varchar(255), NOT NULL)
  - `kodepos` (varchar(10), NOT NULL)
  - `website` (varchar(255), NOT NULL)
  - `email` (varchar(255), NOT NULL)
  - `social_media` (varchar(255), NOT NULL)

- `wp_sicara_organisasi_ranting` - Sub-branch organization information
  - `id_pcm` (int, NOT NULL, PRIMARY KEY with id_prm)
  - `id_prm` (int, NOT NULL, PRIMARY KEY with id_pcm)
  - `tahun_berdiri` (int, NOT NULL)
  - `nomor_sk` (varchar(50), NOT NULL)
  - `periode_muktamar` (ENUM: '46', '47', '48', NOT NULL)
  - `kecamatan` (varchar(255), NOT NULL)
  - `desa_kelurahan` (varchar(255), NOT NULL)
  - `alamat` (varchar(255), NOT NULL)
  - `kodepos` (varchar(10), NOT NULL)
  - `website` (varchar(255), NOT NULL)
  - `email` (varchar(255), NOT NULL)
  - `social_media` (varchar(255), NOT NULL)

#### Assessment and Scoring Tables
- `wp_sicara_questions` - Assessment questions
  - `id_question` (int, AUTO_INCREMENT, PRIMARY KEY)
  - `pcm_prm` (ENUM: 'PCM', 'PRM', NOT NULL)
  - `question_text` (text, NOT NULL)
  - `question_type` (ENUM: 'yes_no', 'multiple_choice', 'multiple_selection', 'short_text', NOT NULL)
  - `bukti` (BOOLEAN, NOT NULL, DEFAULT FALSE)
  - `syarat_pokok` (BOOLEAN, NOT NULL, DEFAULT FALSE)
  - `batas_unggul` (INT)
  - `batas_hijau` (INT)
  - `batas_kuning` (INT)
  - `batas_merah` (INT)
  - `date_created` (DATETIME, NOT NULL, DEFAULT CURRENT_TIMESTAMP)
  - `last_modified` (DATETIME, NOT NULL, DEFAULT CURRENT_TIMESTAMP ON UPDATE)

- `wp_sicara_options` - Multiple choice options for questions
  - `id_option` (INT, AUTO_INCREMENT, PRIMARY KEY)
  - `question_id` (INT, NOT NULL, FOREIGN KEY to wp_sicara_questions)
  - `option_label` (VARCHAR(255), NOT NULL)
  - `option_value` (VARCHAR(10), NOT NULL)
  - `weight` (INT, NOT NULL)

- `wp_sicara_responses_ranting` - Sub-branch responses to questions
  - `id_response` (INT, AUTO_INCREMENT, PRIMARY KEY)
  - `id_prm` (INT, NOT NULL, FOREIGN KEY to wp_sicara_prm)
  - `question_id` (INT, NOT NULL, FOREIGN KEY to wp_sicara_questions)
  - `answer_value` (TEXT)
  - `weight` (FLOAT)
  - `bukti` (TEXT)
  - `date_created` (DATETIME, NOT NULL, DEFAULT CURRENT_TIMESTAMP)
  - `last_modified` (DATETIME, NOT NULL, DEFAULT CURRENT_TIMESTAMP ON UPDATE)

- `wp_sicara_responses_cabang` - Branch responses to questions
  - `id_response` (INT, AUTO_INCREMENT, PRIMARY KEY)
  - `id_pcm` (INT, NOT NULL, FOREIGN KEY to wp_sicara_pcm)
  - `question_id` (INT, NOT NULL, FOREIGN KEY to wp_sicara_questions)
  - `answer_value` (TEXT)
  - `weight` (FLOAT)
  - `bukti` (TEXT)
  - `date_created` (DATETIME, NOT NULL, DEFAULT CURRENT_TIMESTAMP)
  - `last_modified` (DATETIME, NOT NULL, DEFAULT CURRENT_TIMESTAMP ON UPDATE)

- `wp_sicara_status_cabang` - Branch status/scoring
  - `id_status` (INT, AUTO_INCREMENT, PRIMARY KEY)
  - `id_pcm` (INT, NOT NULL, FOREIGN KEY to wp_sicara_pcm)
  - `nilai` (INT, NOT NULL)
  - `status` (VARCHAR(50), NOT NULL)

- `wp_sicara_status_ranting` - Sub-branch status/scoring
  - `id_status` (INT, AUTO_INCREMENT, PRIMARY KEY)
  - `id_prm` (INT, NOT NULL, FOREIGN KEY to wp_sicara_prm)
  - `nilai` (INT, NOT NULL)
  - `status` (VARCHAR(50), NOT NULL)

#### Award/Competition Tables
- `wp_sicara_nominees` - Nominees for awards/competitions
  - `id_nominee` (INT, AUTO_INCREMENT, PRIMARY KEY)
  - `type` (ENUM: 'cabang', 'ranting', NOT NULL)
  - `id_organization` (INT, NOT NULL)
  - `is_active` (BOOLEAN, NOT NULL, DEFAULT TRUE)
  - `created_at` (DATETIME, NOT NULL, DEFAULT CURRENT_TIMESTAMP)
  - UNIQUE KEY: (type, id_organization)

- `wp_sicara_jury_scores` - Jury scoring for nominees
  - `id_score` (INT, AUTO_INCREMENT, PRIMARY KEY)
  - `id_nominee` (INT, NOT NULL, FOREIGN KEY to wp_sicara_nominees)
  - `question_id` (INT, NOT NULL, FOREIGN KEY to wp_sicara_questions)
  - `jury_username` (VARCHAR(100), NOT NULL)
  - `score` (FLOAT, NOT NULL)
  - `notes` (TEXT)
  - `created_at` (DATETIME, NOT NULL, DEFAULT CURRENT_TIMESTAMP)
  - `updated_at` (DATETIME, NOT NULL, DEFAULT CURRENT_TIMESTAMP ON UPDATE)
  - UNIQUE KEY: (id_nominee, question_id, jury_username)

#### Audit Tables
- `wp_sicara_activity_logs` - Activity/audit logging
  - `id_log` (INT, AUTO_INCREMENT, PRIMARY KEY)
  - `table_name` (VARCHAR(100), NOT NULL)
  - `record_id` (VARCHAR(50), NOT NULL)
  - `action_type` (ENUM: 'INSERT', 'UPDATE', 'DELETE', NOT NULL)
  - `old_values` (TEXT)
  - `new_values` (TEXT)
  - `user_id` (INT, NOT NULL)
  - `user_login` (VARCHAR(100), NOT NULL)
  - `ip_address` (VARCHAR(45))
  - `user_agent` (TEXT)
  - `created_at` (DATETIME, NOT NULL, DEFAULT CURRENT_TIMESTAMP)
  - INDEX: (table_name, record_id), (user_id), (created_at)

#### Common Access Patterns
NotulenMu commonly accesses Sicara tables for:
- **User settings lookup**: `wp_sicara_settings` to determine which organizational levels a user can access
- **Personnel data**: `wp_sicara_pengurus` to populate meeting participant lists
- **Organization hierarchy**: `wp_sicara_pwm`, `wp_sicara_pdm`, `wp_sicara_pcm`, `wp_sicara_prm` for organizational structure navigation

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