<?php
global $wpdb;

define('DATA_PENGURUS_TABLE', $wpdb->prefix . 'data_pengurus');

function data_pengurus_page()
{
    global $wpdb;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['form_name']) && $_POST['form_name'] === 'add_pengurus_form') {
            $nama_lengkap_gelar = sanitize_text_field($_POST['nama_lengkap_gelar']);
            $jabatan = sanitize_text_field($_POST['jabatan']);
            $tingkat = sanitize_text_field($_POST['tingkat']);
            $no_hp = sanitize_text_field($_POST['no_hp']);

            if (!empty($nama_lengkap_gelar) && !empty($tingkat)) {
                $wpdb->insert(
                    DATA_PENGURUS_TABLE,
                    [
                        'nama_lengkap_gelar' => $nama_lengkap_gelar,
                        'tingkat' => $tingkat,
                        'jabatan' => $jabatan,
                        'no_hp' => $no_hp
                    ],
                    ['%s', '%s', '%s', '%s']
                );
                echo '<div class="updated"><p>Pengurus berhasil ditambahkan.</p></div>';
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

    $pengurus_list = $wpdb->get_results("SELECT * FROM " . DATA_PENGURUS_TABLE);
?>
    <div class="wrap">
        <h1>Tambah Pengurus</h1>
        <form method="post">
            <input type="hidden" name="form_name" value="add_pengurus_form">
            <table class="form-table">
                <tr>
                    <th><label>Tingkat</label></th>
                    <td>
                        <select name="tingkat" id="tingkat-filter" required onchange="filterByTingkat()">
                            <!-- <option value="">Pilih Tingkat</option> -->
                            <option value="Wilayah">Pimpinan Wilayah</option>
                            <option value="Daerah">Pimpinan Daerah</option>
                            <option value="Cabang">Pimpinan Cabang</option>
                            <option value="Ranting">Pimpinan Ranting</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label>Nama Lengkap & Gelar</label></th>
                    <td><input name="nama_lengkap_gelar" type="text" class="regular-text" required></td>
                </tr>
                <tr>
                    <th><label>Jabatan</label></th>
                    <td><input name="jabatan" type="text" class="regular-text" required></td>
                </tr>
                <tr>
                    <th><label>No HP</label></th>
                    <td><input type="tel" pattern="[0-9]{1,15}"
                            maxlength="15"
                            oninput="this.value = this.value.replace(/\D/g, '')" name="no_hp" type="text" class="regular-text" required></td>
                </tr>
            </table>
            <div class="mt-9">
                <input type="submit" value="Tambah Pengurus" class="bg-[#007bff] hover:bg-[#0069d9] p-1.5 text-white rounded-sm">
            </div>
        </form>

        <div class="wrap">
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
                            <!-- <th style="border: 1px solid #ddd; padding: 10px; text-align: left;">No</th> -->
                            <th style="border: 1px solid #ddd; padding: 10px; text-align: center; color: white; font-weight: bold;">Tingkat</th>
                            <th style="border: 1px solid #ddd; padding: 10px; text-align: center; color: white; font-weight: bold;">Nama dan Gelar</th>
                            <th style="border: 1px solid #ddd; padding: 10px; text-align: center; color: white; font-weight: bold;">Jabatan</th>
                            <th style="border: 1px solid #ddd; padding: 10px; text-align: center; color: white; font-weight: bold;">No HP</th>
                            <th style="border: 1px solid #ddd; padding: 10px; text-align: center; color: white; font-weight: bold;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pengurus_list as $pengurus) : ?>
                            <tr class="pengurus-row" data-tingkat="<?php echo esc_attr($pengurus->tingkat); ?>">
                                <td style="border: 1px solid #ddd; padding: 10px; text-align: center;">
                                    <div style="display: flex; justify-content: center; align-items: center; margin-top: 5px;">
                                        <input type="checkbox" name="selected_ids[]" value="<?php echo esc_attr($pengurus->id); ?>">
                                    </div>
                                </td>
                                <!-- <td><?php echo esc_html($pengurus->id); ?></td> -->
                                <td style="border: 1px solid #ddd;"><?php echo esc_html($pengurus->tingkat); ?></td>
                                <td style="border: 1px solid #ddd;"><?php echo esc_html($pengurus->nama_lengkap_gelar); ?></td>
                                <td style="border: 1px solid #ddd;"><?php echo esc_html($pengurus->jabatan); ?></td>
                                <td style="border: 1px solid #ddd;"><?php echo esc_html($pengurus->no_hp); ?></td>
                                <td style="border: 1px solid #ddd; text-align: center;">
                                    <button type="button" style="width: 50px; border: 1px solid orange; border-radius: 5px; color: orange;" onclick="openModal('<?php echo esc_attr($pengurus->id); ?>', '<?php echo esc_attr($pengurus->tingkat); ?>', '<?php echo esc_attr($pengurus->nama_lengkap_gelar); ?>', '<?php echo esc_attr($pengurus->jabatan); ?>', '<?php echo esc_attr($pengurus->no_hp); ?>')">Edit</button>
                                    <form method="post" style="display:inline;" onsubmit="return confirm('Apakah Anda yakin ingin menghapus data ini?');">
                                        <input type="hidden" name="id" value="<?php echo esc_attr($pengurus->id); ?>">
                                        <button style="border: 1px solid #d9534f; width: 50px; border-radius: 5px; color: #d9534f;" type="submit" name="delete_pengurus">Hapus</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
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
                                    <option value="Wilayah">Pimpinan Wilayah</option>
                                    <option value="Daerah">Pimpinan Daerah</option>
                                    <option value="Cabang">Pimpinan Cabang</option>
                                    <option value="Ranting">Pimpinan Ranting</option>
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
