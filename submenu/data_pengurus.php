<?php
global $wpdb;

// Definisi tabel data pengurus
define('DATA_PENGURUS_TABLE', $wpdb->prefix . 'salammu_data_pengurus');

function data_pengurus_page()
{
    global $wpdb;

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_name']) && $_POST['form_name'] === 'add_pengurus_form') {
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : null;
        $nama_lengkap_gelar = $_POST['nama_lengkap_gelar'] ?? '';
        $jabatan = $_POST['jabatan'] ?? '';
        $tingkat = strtolower($_POST['tingkat'] ?? '');
        $no_hp = $_POST['no_hp'] ?? '';

        if (!$user_id) {
            echo '<div class="error"><p>Error: User ID tidak ditemukan.</p></div>';
            return;
        }

        $setting_table_name = $wpdb->prefix . 'salammu_notulenmu_setting';
        $tingkat_id = null;

        if ($tingkat === 'wilayah') {
            $row = $wpdb->get_row($wpdb->prepare("SELECT pwm FROM $setting_table_name WHERE user_id = %d", $user_id));
            $tingkat_id = $row ? $row->pwm : null;
        } elseif ($tingkat === 'daerah') {
            $row = $wpdb->get_row($wpdb->prepare("SELECT pdm FROM $setting_table_name WHERE user_id = %d", $user_id));
            $tingkat_id = $row ? $row->pdm : null;
        } elseif ($tingkat === 'cabang') {
            $row = $wpdb->get_row($wpdb->prepare("SELECT pcm FROM $setting_table_name WHERE user_id = %d", $user_id));
            $tingkat_id = $row ? $row->pcm : null;
        } elseif ($tingkat === 'ranting') {
            $row = $wpdb->get_row($wpdb->prepare("SELECT prm FROM $setting_table_name WHERE user_id = %d", $user_id));
            $tingkat_id = $row ? $row->prm : null;
        }

        if (is_null($tingkat_id)) {
            echo "<div class='error'><p>Error: ID tingkat tidak ditemukan untuk user ID $user_id.</p></div>";
            return;
        }

        if (!empty($nama_lengkap_gelar) && !empty($tingkat)) {
            $wpdb->insert(
                DATA_PENGURUS_TABLE,
                [
                    'user_id'             => $user_id,
                    'tingkat'             => $tingkat,
                    'id_tingkat'          => $tingkat_id,
                    'nama_lengkap_gelar'  => $nama_lengkap_gelar,
                    'jabatan'             => $jabatan,
                    'no_hp'               => $no_hp
                ],
                ['%d', '%s', '%d', '%s', '%s', '%s']
            );
            echo '<div class="updated"><p>Pengurus berhasil ditambahkan.</p></div>';
        } else {
            echo '<div class="error"><p>Harap isi semua bidang.</p></div>';
        }
    }

    if (isset($_POST['edit_pengurus'])) {
        $id = intval($_POST['id']);
        $nama_lengkap_gelar = sanitize_text_field($_POST['nama_lengkap_gelar']);
        $jabatan = sanitize_text_field($_POST['jabatan']);
        $tingkat = sanitize_text_field($_POST['tingkat']);
        $no_hp = sanitize_text_field($_POST['no_hp']);

        $wpdb->update(
            DATA_PENGURUS_TABLE,
            [
                'nama_lengkap_gelar' => $nama_lengkap_gelar,
                'tingkat' => $tingkat,
                'jabatan' => $jabatan,
                'no_hp' => $no_hp
            ],
            ['id' => $id],
            ['%s', '%s', '%s', '%s'],
            ['%d']
        );
        echo '<div class="updated"><p>Data pengurus berhasil diperbarui.</p></div>';
    }

    if (isset($_POST['delete_pengurus'])) {
        $id = intval($_POST['id']);
        $wpdb->query("DELETE FROM " . DATA_PENGURUS_TABLE . " WHERE id = $id");

        $wpdb->query("SET @new_id = 0");
        $wpdb->query("UPDATE " . DATA_PENGURUS_TABLE . " SET id = (@new_id := @new_id + 1) ORDER BY id");

        $wpdb->query("ALTER TABLE " . DATA_PENGURUS_TABLE . " AUTO_INCREMENT = 1");
        echo '<div class="updated"><p>Data pengurus berhasil dihapus.</p></div>';
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_delete'])) {
        if (!empty($_POST['selected_ids'])) {
            $ids_to_delete = implode(',', array_map('intval', $_POST['selected_ids']));
            $wpdb->query("DELETE FROM " . DATA_PENGURUS_TABLE . " WHERE id IN ($ids_to_delete)");

            // Merapikan kembali ID agar berurutan
            $wpdb->query("SET @new_id = 0");
            $wpdb->query("UPDATE " . DATA_PENGURUS_TABLE . " SET id = (@new_id := @new_id + 1) ORDER BY id");
            $wpdb->query("ALTER TABLE " . DATA_PENGURUS_TABLE . " AUTO_INCREMENT = 1");

            echo '<div class="updated"><p>Data pengurus berhasil dihapus</p></div>';
        }
    }
?>
    <div class="wrap">
        <h1>Tambah Pengurus</h1>
        <form method="post" action="">
            <input type="hidden" name="form_name" value="add_pengurus_form">
            <input type="hidden" name="user_id" value="<?php echo get_current_user_id(); ?>">
            <table class="form-table">
                <tr>
                    <th><label for="tingkat">Tingkat</label></th>
                    <td>
                        <select name="tingkat" id="tingkat-filter" required onchange="filterByTingkat()">
                            <option value="wilayah">Pimpinan Wilayah</option>
                            <option value="daerah">Pimpinan Daerah</option>
                            <option value="cabang">Pimpinan Cabang</option>
                            <option value="ranting">Pimpinan Ranting</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="nama_lengkap_gelar">Nama Lengkap & Gelar</label></th>
                    <td><input name="nama_lengkap_gelar" id="nama_lengkap_gelar" type="text" class="regular-text" required></td>
                </tr>
                <tr>
                    <th><label for="jabatan">Jabatan</label></th>
                    <td><input name="jabatan" id="jabatan" type="text" class="regular-text" required></td>
                </tr>
                <tr>
                    <th><label for="no_hp">No HP</label></th>
                    <td><input type="tel" pattern="[0-9]{1,15}"
                            maxlength="15"
                            oninput="this.value = this.value.replace(/\D/g, '')" name="no_hp" id="no_hp" type="text" class="regular-text" required></td>
                </tr>
            </table>
            <input type="submit" value="Tambah Pengurus" class="button-primary">
        </form>

        <?php tampilkan_tabel_pengurus(); ?>
    </div>
    <?php
}

function tampilkan_tabel_pengurus()
{
    global $wpdb;

    $user_id = get_current_user_id();
    $setting_table = $wpdb->prefix . 'salammu_notulenmu_setting';
    $pengurus_table = $wpdb->prefix . 'salammu_data_pengurus';

    // Ambil data pengaturan tingkat untuk user saat ini
    $settings = $wpdb->get_row($wpdb->prepare("SELECT * FROM $setting_table WHERE user_id = %d", $user_id), ARRAY_A);

    if (!$settings) {
    ?>
        <p>Pengaturan NotulenMu belum diisi.</p>
    <?php
        return;
    }

    // Filter berdasarkan id_tingkat yang sesuai dengan pengaturan user
    $query = "SELECT * FROM $pengurus_table WHERE id_tingkat IN (%d, %d, %d, %d) AND tingkat IN ('wilayah', 'daerah', 'cabang', 'ranting')";
    $query = $wpdb->prepare($query, $settings['pwm'], $settings['pdm'], $settings['pcm'], $settings['prm']);

    $results = $wpdb->get_results($query);
    ?>
    <div class="data-pengurus-container">
        <?php if ($results) { ?>
            <div class="table-wrapper">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                    <h2>Data Pengurus</h2>
                    <button type="submit" name="bulk_delete" class="bg-[#d9534f] hover:bg-[#b8302c] p-1.5 text-white rounded-sm" form="bulk-delete-form">Hapus yang Dipilih</button>
                </div>
                <form method="post" id="bulk-delete-form" onsubmit="return confirm('Apakah Anda yakin ingin menghapus data yang dipilih?');">
                    <table class="widefat" style="width: 100%; border-collapse: collapse;">
                        <thead style="background-color: #007bff; color: white; font-weight: bold; align-items: center;">
                            <tr>
                                <th style="width: 50px; border: 1px solid #ddd; padding: 10px; text-align: center; vertical-align: middle;">
                                    <input type="checkbox" id="select-all" style="margin: 0; transform: scale(1);">
                                </th>
                                <th style="border: 1px solid #ddd; padding: 10px; text-align: center; color: white; font-weight: bold;">Tingkat</th>
                                <th style="border: 1px solid #ddd; padding: 10px; text-align: center; color: white; font-weight: bold;">Nama dan Gelar</th>
                                <th style="border: 1px solid #ddd; padding: 10px; text-align: center; color: white; font-weight: bold;">Jabatan</th>
                                <th style="border: 1px solid #ddd; padding: 10px; text-align: center; color: white; font-weight: bold;">No HP</th>
                                <th style="border: 1px solid #ddd; padding: 10px; text-align: center; color: white; font-weight: bold;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $no = 1;
                            foreach ($results as $row) { ?>
                                <tr class="pengurus-row" data-tingkat="<?php echo esc_attr($row->tingkat); ?>">
                                    <td style="border: 1px solid #ddd; padding: 10px; text-align: center;">
                                        <div style="display: flex; justify-content: center; align-items: center; margin-top: 5px;">
                                            <input type="checkbox" name="selected_ids[]" value="<?php echo esc_attr($row->id); ?>">
                                        </div>
                                    </td>
                                    <td style="border: 1px solid #ddd; padding: 10px; text-align: center;"> <?= ucfirst(esc_html($row->tingkat)); ?> </td>
                                    <td style="border: 1px solid #ddd; padding: 10px; text-align: start;"> <?= esc_html($row->nama_lengkap_gelar); ?> </td>
                                    <td style="border: 1px solid #ddd; padding: 10px; text-align: start;"> <?= esc_html($row->jabatan); ?> </td>
                                    <td style="border: 1px solid #ddd; padding: 10px; text-align: start;"> <?= esc_html($row->no_hp); ?> </td>
                                    <td style="border: 1px solid #ddd; padding: 10px; text-align: center;">
                                        <button type="button" style="width: 50px; border: 1px solid orange; border-radius: 5px; color: orange;" onclick="openModal('<?php echo esc_attr($row->id); ?>', '<?php echo esc_attr($row->tingkat); ?>', '<?php echo esc_attr($row->nama_lengkap_gelar); ?>', '<?php echo esc_attr($row->jabatan); ?>', '<?php echo esc_attr($row->no_hp); ?>')">Edit</button>
                                        <form method="post" style="display:inline;" onsubmit="return confirm('Apakah Anda yakin ingin menghapus data ini?');">
                                            <input type="hidden" name="id" value="<?php echo esc_attr($row->id); ?>">
                                            <button style="border: 1px solid #d9534f; width: 50px; border-radius: 5px; color: #d9534f;" type="submit" name="delete_pengurus">Hapus</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </form>
            </div>

            <div id="editModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5);">
                <div style="background: white; width: 40%; padding: 20px; margin: 10% auto; border-radius: 5px; position: relative;">
                    <h2>Edit Data Pengurus</h2>
                    <form method="post">
                        <input type="hidden" id="edit-id" name="id">
                        <table style="line-height: 50px;">
                            <tr style="text-align: start;">
                                <td><label>Tingkat</label></td>
                                <td style="padding-left: 10px;">: <select name="tingkat" id="edit-tingkat" required>
                                        <option value="wilayah">Pimpinan Wilayah</option>
                                        <option value="daerah">Pimpinan Daerah</option>
                                        <option value="cabang">Pimpinan Cabang</option>
                                        <option value="ranting">Pimpinan Ranting</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <td><label>Nama Lengkap & Gelar</label></td>
                                <td style="padding-left: 10px;">: <input type="text" id="edit-nama" name="nama_lengkap_gelar" required></td>
                            </tr>
                            <tr>
                                <td><label>Jabatan</label></td>
                                <td style="padding-left: 10px;">: <input type="text" id="edit-jabatan" name="jabatan" required></td>
                            </tr>
                            <tr>
                                <td><label>No HP</label></td>
                                <td style="padding-left: 10px;">: <input type="text" id="edit-nohp" name="no_hp" required></td>
                            </tr>
                        </table>
                        <div style="display: flex; justify-content: end; align-items: center; gap: 10px; margin-top: 20px;">
                            <button style="padding: 7px; background: #2d3476; border-radius: 5px; color: white;" type="submit" name="edit_pengurus">Simpan</button>
                            <button style="padding: 7px; border: 1px solid #2d3476; border-radius: 5px;" type="button" onclick="closeModal()">Batal</button>
                        </div>
                    </form>
                </div>
            </div>
        <?php } else { ?>
            <p>Tidak ada data pengurus untuk tingkat Anda.</p>
        <?php } ?>
    </div>

    <script>
        document.getElementById("select-all").addEventListener("change", function() {
            let checkboxes = document.querySelectorAll("input[name='selected_ids[]']");
            checkboxes.forEach(checkbox => checkbox.checked = this.checked);
        });

        document.addEventListener("DOMContentLoaded", function() {
            let tingkatFilter = document.getElementById("tingkat-filter");
            let selectedTingkat = localStorage.getItem("selectedTingkat");

            if (selectedTingkat) {
                tingkatFilter.value = selectedTingkat;
                filterByTingkat();
            }

            tingkatFilter.addEventListener("change", function() {
                localStorage.setItem("selectedTingkat", this.value);
                filterByTingkat();
            });
        });

        function filterByTingkat() {
            let selectedTingkat = document.getElementById("tingkat-filter").value;
            let rows = document.querySelectorAll(".pengurus-row");

            rows.forEach(row => {
                if (selectedTingkat === "" || row.getAttribute("data-tingkat") === selectedTingkat) {
                    row.style.display = "";
                } else {
                    row.style.display = "none";
                }
            });
        }

        function openModal(id, tingkat, nama, jabatan, nohp) {
            document.getElementById("edit-id").value = id;
            document.getElementById("edit-tingkat").value = tingkat;
            document.getElementById("edit-nama").value = nama;
            document.getElementById("edit-jabatan").value = jabatan;
            document.getElementById("edit-nohp").value = nohp;
            document.getElementById("editModal").style.display = "block";
        }

        function closeModal() {
            document.getElementById("editModal").style.display = "none";
        }
    </script>
<?php
}

?>
