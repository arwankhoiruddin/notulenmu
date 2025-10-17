<?php
// Prevent direct access
defined('ABSPATH') || exit;

function notulenmu_list_page()
{
    global $wpdb;
    $user_id = get_current_user_id();

    // Handle delete action only after WordPress is fully loaded
    add_action('admin_init', function() {
        if (isset($_GET['delete_notulen']) && !empty($_GET['delete_notulen'])) {
            global $wpdb;
            $user_id = get_current_user_id();
            $delete_id = intval($_GET['delete_notulen']);
            if (isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'delete_notulen_' . $delete_id)) {
                $table_name = $wpdb->prefix . 'salammu_notulenmu';
                $deleted = $wpdb->delete($table_name, array('id' => $delete_id, 'user_id' => $user_id));
                if ($deleted) {
                    set_transient('notulenmu_admin_notice', 'Notulen berhasil dihapus.', 5);
                } else {
                    set_transient('notulenmu_admin_notice', 'Gagal menghapus notulen.', 5);
                }
                if (!function_exists('wp_redirect')) {
                    require_once(ABSPATH . WPINC . '/pluggable.php');
                }
                wp_redirect(admin_url('admin.php?page=notulenmu-list'));
                exit;
            }
        }
    });

    // Determine user organizational level
    $current_user = wp_get_current_user();
    $user_level = '';
    $is_arwan = false;
    
    // Check if user is arwan (PP - Pimpinan Pusat)
    if (strpos($current_user->user_login, 'arwan') === 0) {
        $is_arwan = true;
    } else if (strpos($current_user->user_login, 'pwm.') === 0) {
        $user_level = 'pwm';
    } else if (strpos($current_user->user_login, 'pdm.') === 0) {
        $user_level = 'pdm';
    } else if (strpos($current_user->user_login, 'pcm.') === 0) {
        $user_level = 'pcm';
    } else if (strpos($current_user->user_login, 'prm.') === 0) {
        $user_level = 'prm';
    }
    
    // Initialize id_tingkat_list
    $id_tingkat_list = array();
    
    // For user arwan, allow access to all notulen (no filtering)
    if (!$is_arwan) {
        // For other users, get settings and filter by organizational hierarchy
        // $setting_table = $wpdb->prefix . 'salammu_notulenmu_setting';
        $setting_table = $wpdb->prefix . 'sicara_settings';
        $settings = $wpdb->get_row($wpdb->prepare(
            "SELECT pwm, pdm, pcm, prm FROM $setting_table WHERE user_id = %d",
            $user_id
        ), ARRAY_A);

        if (!$settings) {
            echo "<p>Data tidak ditemukan.</p>";
            return;
        }
        
        // Get all accessible id_tingkat based on organizational hierarchy
        $id_tingkat_list = notulenmu_get_accessible_id_tingkat($settings, $user_level);

        if (empty($id_tingkat_list)) {
            echo "<p>You do not have sufficient permissions to access this page.</p>";
            return;
        }
    }

    $search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
    
    // For user arwan, get filter parameters
    $filter_tingkat = isset($_GET['filter_tingkat']) ? sanitize_text_field($_GET['filter_tingkat']) : '';
    $filter_pwm = isset($_GET['filter_pwm']) ? intval($_GET['filter_pwm']) : 0;
    $filter_pdm = isset($_GET['filter_pdm']) ? intval($_GET['filter_pdm']) : 0;
    $filter_pcm = isset($_GET['filter_pcm']) ? intval($_GET['filter_pcm']) : 0;
    $filter_prm = isset($_GET['filter_prm']) ? intval($_GET['filter_prm']) : 0;
    
    // Get organizational data from Sicara tables for arwan user filters
    $pwm_list = array();
    $pdm_list = array();
    $pcm_list = array();
    $prm_list = array();
    
    if ($is_arwan) {
        // Get all PWM
        $pwm_table = $wpdb->prefix . 'sicara_pwm';
        $pwm_list = $wpdb->get_results("SELECT id_pwm, wilayah FROM $pwm_table ORDER BY wilayah");
        
        // Get PDM based on selected PWM (if any)
        if ($filter_pwm > 0 || !empty($filter_tingkat)) {
            $pdm_table = $wpdb->prefix . 'sicara_pdm';
            if ($filter_pwm > 0) {
                $pdm_list = $wpdb->get_results($wpdb->prepare(
                    "SELECT id_pdm, daerah FROM $pdm_table WHERE id_pwm = %d ORDER BY daerah",
                    $filter_pwm
                ));
            } else {
                $pdm_list = $wpdb->get_results("SELECT id_pdm, daerah FROM $pdm_table ORDER BY daerah");
            }
        }
        
        // Get PCM based on selected PDM (if any)
        if ($filter_pdm > 0 || (!empty($filter_tingkat) && in_array($filter_tingkat, ['cabang', 'ranting']))) {
            $pcm_table = $wpdb->prefix . 'sicara_pcm';
            if ($filter_pdm > 0) {
                $pcm_list = $wpdb->get_results($wpdb->prepare(
                    "SELECT id_pcm, cabang FROM $pcm_table WHERE id_pdm = %d ORDER BY cabang",
                    $filter_pdm
                ));
            } else {
                $pcm_list = $wpdb->get_results("SELECT id_pcm, cabang FROM $pcm_table ORDER BY cabang");
            }
        }
        
        // Get PRM based on selected PCM (if any)
        if ($filter_pcm > 0 || $filter_tingkat === 'ranting') {
            $prm_table = $wpdb->prefix . 'sicara_prm';
            if ($filter_pcm > 0) {
                $prm_list = $wpdb->get_results($wpdb->prepare(
                    "SELECT id_prm, ranting FROM $prm_table WHERE id_pcm = %d ORDER BY ranting",
                    $filter_pcm
                ));
            } else {
                $prm_list = $wpdb->get_results("SELECT id_prm, ranting FROM $prm_table ORDER BY ranting");
            }
        }
    }
    
    $table_name = $wpdb->prefix . 'salammu_notulenmu';

    // Build query based on whether user is arwan or not
    if ($is_arwan) {
        // User arwan can see all notulen, with optional filtering
        $query = "SELECT * FROM $table_name WHERE 1=1";
        $params = array();
        
        // Apply filters based on selected tingkat and organization
        if (!empty($filter_tingkat)) {
            $query .= " AND tingkat = %s";
            $params[] = $filter_tingkat;
            
            // Apply id_tingkat filter based on the selected organizational level
            if ($filter_tingkat === 'wilayah' && $filter_pwm > 0) {
                $query .= " AND id_tingkat = %d";
                $params[] = $filter_pwm;
            } else if ($filter_tingkat === 'daerah' && $filter_pdm > 0) {
                $query .= " AND id_tingkat = %d";
                $params[] = $filter_pdm;
            } else if ($filter_tingkat === 'cabang' && $filter_pcm > 0) {
                $query .= " AND id_tingkat = %d";
                $params[] = $filter_pcm;
            } else if ($filter_tingkat === 'ranting' && $filter_prm > 0) {
                $query .= " AND id_tingkat = %d";
                $params[] = $filter_prm;
            }
        }
    } else {
        // Other users see only notulen from their organizational hierarchy
        $placeholders = implode(',', array_fill(0, count($id_tingkat_list), '%s'));
        $query = "SELECT * FROM $table_name WHERE id_tingkat IN ($placeholders)";
        $params = $id_tingkat_list;
    }

    if (!empty($search)) {
        $query .= " AND (tingkat LIKE %s OR topik_rapat LIKE %s OR tempat_rapat LIKE %s)";
        $search_term = '%' . $wpdb->esc_like($search) . '%';
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
    }

    if (!empty($params)) {
        $sql = $wpdb->prepare($query, $params);
    } else {
        $sql = $query;
    }
    $rows = $wpdb->get_results($sql);

    // Group notulen by organizational level (tingkat), then by entity (tingkat + id_tingkat)
    $grouped_notulen = array();
    foreach ($rows as $row) {
        $tingkat = $row->tingkat;
        $entity_key = $row->tingkat . '_' . $row->id_tingkat;
        
        if (!isset($grouped_notulen[$tingkat])) {
            $grouped_notulen[$tingkat] = array();
        }
        
        if (!isset($grouped_notulen[$tingkat][$entity_key])) {
            $grouped_notulen[$tingkat][$entity_key] = array(
                'tingkat' => $row->tingkat,
                'id_tingkat' => $row->id_tingkat,
                'entity_name' => notulenmu_get_entity_name($row->tingkat, $row->id_tingkat),
                'notulen' => array()
            );
        }
        $grouped_notulen[$tingkat][$entity_key]['notulen'][] = $row;
    }
    
    // Sort by organizational hierarchy: wilayah > daerah > cabang > ranting
    $tingkat_order = array('wilayah' => 1, 'daerah' => 2, 'cabang' => 3, 'ranting' => 4);
    uksort($grouped_notulen, function($a, $b) use ($tingkat_order) {
        $order_a = isset($tingkat_order[$a]) ? $tingkat_order[$a] : 999;
        $order_b = isset($tingkat_order[$b]) ? $tingkat_order[$b] : 999;
        return $order_a - $order_b;
    });

?>
<div class="notulenmu-container">
    <div class="overflow-x-auto bg-white p-6 rounded-lg shadow-md mt-7">
        <div class="flex justify-between items-center">
            <h1 class="text-2xl font-semibold text-gray-700">List Notulen</h1>
        </div>
        <div class="mb-4 flex flex-col gap-3">
            <div>
                <a href="<?php echo esc_url(admin_url('admin.php?page=notulenmu-add')); ?>" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded" style="color:#fff !important;">+ Tambah Notulen</a>
            </div>
            
            <?php if ($is_arwan) { ?>
                <!-- Filter dropdowns for user arwan -->
                <form method="get" id="filter-form" class="flex flex-col gap-3" action="">
                    <input type="hidden" name="page" value="notulenmu-list">
                    
                    <div class="flex flex-col md:flex-row md:items-center gap-2">
                        <!-- Tingkat dropdown -->
                        <div class="flex items-center gap-2">
                            <label for="filter_tingkat" class="font-semibold whitespace-nowrap">Tingkat:</label>
                            <select name="filter_tingkat" id="filter_tingkat" class="p-2 border rounded-md" onchange="handleTingkatChange()">
                                <option value="">-- Semua Tingkat --</option>
                                <option value="wilayah" <?php echo $filter_tingkat === 'wilayah' ? 'selected' : ''; ?>>PWM (Wilayah)</option>
                                <option value="daerah" <?php echo $filter_tingkat === 'daerah' ? 'selected' : ''; ?>>PDM (Daerah)</option>
                                <option value="cabang" <?php echo $filter_tingkat === 'cabang' ? 'selected' : ''; ?>>PCM (Cabang)</option>
                                <option value="ranting" <?php echo $filter_tingkat === 'ranting' ? 'selected' : ''; ?>>PRM (Ranting)</option>
                            </select>
                        </div>
                        
                        <!-- PWM dropdown - shown when tingkat is selected -->
                        <div id="pwm-filter" class="flex items-center gap-2" style="display: <?php echo (!empty($filter_tingkat)) ? 'flex' : 'none'; ?>;">
                            <label for="filter_pwm" class="font-semibold whitespace-nowrap">PWM:</label>
                            <select name="filter_pwm" id="filter_pwm" class="p-2 border rounded-md" onchange="handlePWMChange()">
                                <option value="0">-- Pilih PWM --</option>
                                <?php foreach ($pwm_list as $pwm) { ?>
                                    <option value="<?php echo esc_attr($pwm->id_pwm); ?>" <?php echo $filter_pwm == $pwm->id_pwm ? 'selected' : ''; ?>>
                                        <?php echo esc_html($pwm->wilayah); ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </div>
                        
                        <!-- PDM dropdown - shown when tingkat is daerah, cabang, or ranting -->
                        <div id="pdm-filter" class="flex items-center gap-2" style="display: <?php echo (in_array($filter_tingkat, ['daerah', 'cabang', 'ranting'])) ? 'flex' : 'none'; ?>;">
                            <label for="filter_pdm" class="font-semibold whitespace-nowrap">PDM:</label>
                            <select name="filter_pdm" id="filter_pdm" class="p-2 border rounded-md" onchange="handlePDMChange()">
                                <option value="0">-- Pilih PDM --</option>
                                <?php foreach ($pdm_list as $pdm) { ?>
                                    <option value="<?php echo esc_attr($pdm->id_pdm); ?>" <?php echo $filter_pdm == $pdm->id_pdm ? 'selected' : ''; ?>>
                                        <?php echo esc_html($pdm->daerah); ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </div>
                        
                        <!-- PCM dropdown - shown when tingkat is cabang or ranting -->
                        <div id="pcm-filter" class="flex items-center gap-2" style="display: <?php echo (in_array($filter_tingkat, ['cabang', 'ranting'])) ? 'flex' : 'none'; ?>;">
                            <label for="filter_pcm" class="font-semibold whitespace-nowrap">PCM:</label>
                            <select name="filter_pcm" id="filter_pcm" class="p-2 border rounded-md" onchange="handlePCMChange()">
                                <option value="0">-- Pilih PCM --</option>
                                <?php foreach ($pcm_list as $pcm) { ?>
                                    <option value="<?php echo esc_attr($pcm->id_pcm); ?>" <?php echo $filter_pcm == $pcm->id_pcm ? 'selected' : ''; ?>>
                                        <?php echo esc_html($pcm->cabang); ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </div>
                        
                        <!-- PRM dropdown - shown when tingkat is ranting -->
                        <div id="prm-filter" class="flex items-center gap-2" style="display: <?php echo ($filter_tingkat === 'ranting') ? 'flex' : 'none'; ?>;">
                            <label for="filter_prm" class="font-semibold whitespace-nowrap">PRM:</label>
                            <select name="filter_prm" id="filter_prm" class="p-2 border rounded-md">
                                <option value="0">-- Pilih PRM --</option>
                                <?php foreach ($prm_list as $prm) { ?>
                                    <option value="<?php echo esc_attr($prm->id_prm); ?>" <?php echo $filter_prm == $prm->id_prm ? 'selected' : ''; ?>>
                                        <?php echo esc_html($prm->ranting); ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </div>
                        
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded">Filter</button>
                        <?php if (!empty($filter_tingkat) || $filter_pwm > 0 || $filter_pdm > 0 || $filter_pcm > 0 || $filter_prm > 0) { ?>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=notulenmu-list')); ?>" class="bg-gray-500 hover:bg-gray-600 text-white font-semibold py-2 px-4 rounded">Reset</a>
                        <?php } ?>
                    </div>
                    
                    <!-- Search box -->
                    <div class="flex items-center gap-2">
                        <input type="text" name="search" id="search" class="p-2 border rounded-md" style="width: 320px;" placeholder="Cari tingkat/topik/tempat rapat..." value="<?php echo esc_attr($search); ?>">
                        <button type="submit" class="bg-gray-600 hover:bg-gray-700 text-white font-semibold py-2 px-4 rounded">Cari</button>
                    </div>
                </form>
                
                <script>
                function handleTingkatChange() {
                    var tingkat = document.getElementById('filter_tingkat').value;
                    var pwmFilter = document.getElementById('pwm-filter');
                    var pdmFilter = document.getElementById('pdm-filter');
                    var pcmFilter = document.getElementById('pcm-filter');
                    var prmFilter = document.getElementById('prm-filter');
                    
                    // Hide all filters initially
                    pwmFilter.style.display = 'none';
                    pdmFilter.style.display = 'none';
                    pcmFilter.style.display = 'none';
                    prmFilter.style.display = 'none';
                    
                    // Show filters based on selected tingkat
                    if (tingkat === 'wilayah') {
                        pwmFilter.style.display = 'flex';
                    } else if (tingkat === 'daerah') {
                        pwmFilter.style.display = 'flex';
                        pdmFilter.style.display = 'flex';
                    } else if (tingkat === 'cabang') {
                        pwmFilter.style.display = 'flex';
                        pdmFilter.style.display = 'flex';
                        pcmFilter.style.display = 'flex';
                    } else if (tingkat === 'ranting') {
                        pwmFilter.style.display = 'flex';
                        pdmFilter.style.display = 'flex';
                        pcmFilter.style.display = 'flex';
                        prmFilter.style.display = 'flex';
                    }
                }
                
                function handlePWMChange() {
                    // Submit form to reload PDM options based on selected PWM
                    document.getElementById('filter-form').submit();
                }
                
                function handlePDMChange() {
                    // Submit form to reload PCM options based on selected PDM
                    document.getElementById('filter-form').submit();
                }
                
                function handlePCMChange() {
                    // Submit form to reload PRM options based on selected PCM
                    document.getElementById('filter-form').submit();
                }
                </script>
            <?php } else { ?>
                <!-- Search box for non-arwan users -->
                <div class="flex flex-col md:flex-row md:items-center gap-2">
                    <form method="get" class="flex items-center gap-2" action="">
                        <input type="hidden" name="page" value="notulenmu-list">
                        <input type="text" name="search" id="search" class="p-2 border rounded-md" style="width: 320px;" placeholder="Cari tingkat/topik/tempat rapat..." value="<?php echo esc_attr($search); ?>">
                        <button type="submit" class="bg-gray-600 hover:bg-gray-700 text-white font-semibold py-2 px-4 rounded">Cari</button>
                    </form>
                </div>
            <?php } ?>
        </div>

        <?php if (empty($grouped_notulen)) { ?>
            <p class="text-gray-600 text-center py-4">Tidak ada data notulen yang ditemukan.</p>
        <?php } else { ?>
            <?php 
            // Define tingkat labels for display
            $tingkat_labels = array(
                'wilayah' => 'PWM (Pimpinan Wilayah Muhammadiyah)',
                'daerah' => 'PDM (Pimpinan Daerah Muhammadiyah)',
                'cabang' => 'PCM (Pimpinan Cabang Muhammadiyah)',
                'ranting' => 'PRM (Pimpinan Ranting Muhammadiyah)'
            );
            
            foreach ($grouped_notulen as $tingkat => $entities) { ?>
                <!-- Organizational Level Header -->
                <div class="mt-8 mb-4">
                    <h2 class="text-2xl font-bold text-gray-900 border-b-4 border-blue-500 pb-3">
                        <?php echo esc_html(isset($tingkat_labels[$tingkat]) ? $tingkat_labels[$tingkat] : ucfirst($tingkat)); ?>
                    </h2>
                </div>

                <?php foreach ($entities as $entity_key => $entity_data) { ?>
                    <!-- Entity Header -->
                    <div class="mt-6 mb-3">
                        <h3 class="text-xl font-semibold text-gray-800 border-b-2 border-gray-300 pb-2">
                            <?php echo esc_html($entity_data['entity_name']); ?>
                        </h3>
                    </div>

                    <!-- Tabel for this entity -->
                    <table class="min-w-full border border-gray-300 mb-6">
                        <thead class="bg-gray-100 text-gray-700">
                            <tr>
                                <th class="py-2 px-4 border border-gray-300">Tingkat</th>
                                <th class="py-2 px-4 border border-gray-300">Topik Rapat</th>
                                <th class="py-2 px-4 border border-gray-300">Tanggal Rapat</th>
                                <th class="py-2 px-4 border border-gray-300">Tempat Rapat</th>
                                <th class="py-2 px-4 border border-gray-300">Detail</th>
                                <th class="py-2 px-4 border border-gray-300">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php foreach ($entity_data['notulen'] as $row) { ?>
                                <tr class="hover:bg-gray-100">
                                    <td class="py-2 px-4 border border-gray-300"><?php echo esc_html($row->tingkat); ?></td>
                                    <td class="py-2 px-4 border border-gray-300"><?php echo esc_html($row->topik_rapat); ?></td>
                                    <td class="py-2 px-4 border border-gray-300"><?php echo date('Y-m-d', strtotime($row->tanggal_rapat)); ?></td>
                                    <td class="py-2 px-4 border border-gray-300"><?php echo esc_html($row->tempat_rapat); ?></td>
                                    <td class="py-2 px-4 border border-gray-300 text-center">
                                        <a href="<?php echo admin_url('admin.php?page=notulenmu-view&id=' . $row->id); ?>" class="text-blue-500 hover:text-blue-700">View Details</a>
                                    </td>
                                    <td class="py-2 px-4 border border-gray-300 text-center">
                                        <a href="<?php echo admin_url('admin.php?page=notulenmu-add&edit=true&id=' . $row->id); ?>" class="text-green-500 hover:text-green-700 mr-2">Edit</a>
                                        <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=notulenmu-list&delete_notulen=' . $row->id), 'delete_notulen_' . $row->id); ?>" class="text-red-500 hover:text-red-700" onclick="return confirm('Yakin ingin menghapus notulen ini?');">Delete</a>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                <?php } ?>
            <?php } ?>
        <?php } ?>
    </div>
</div>

<?php } ?>