<?php
function notulenmu_add_page() {
    // Check if we are editing an existing notulen
    $editing = isset($_GET['edit']);

    echo '<h1>' . ($editing ? 'Edit' : 'Add New') . ' Notulen</h1>';

    // Form for adding or editing
    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
    echo '<input type="hidden" name="action" value="handle_notulen_form">';
    // If editing, include a hidden field with the ID of the notulen being edited
    if ($editing) {
        echo '<input type="hidden" name="notulen_id" value="' . esc_attr($_GET['edit']) . '">';
    }
    echo '<table class="form-table">';
    echo '<tbody>';
    echo '<tr>';
    echo '<th scope="row"><label for="tingkat">Tingkat</label></th>';
    echo '<td>';
    echo '<select name="tingkat" id="tingkat">';
    echo '<option value="Pimpinan Wilayah">Pimpinan Wilayah</option>';
    echo '<option value="Pimpinan Daerah">Pimpinan Daerah</option>';
    echo '<option value="Pimpinan Cabang">Pimpinan Cabang</option>';
    echo '</select>';
    echo '</td>';
    echo '</tr>';
    echo '<tr>';
    echo '<th scope="row"><label for="topik_rapat">Topik Rapat</label></th>';
    echo '<td><input name="topik_rapat" id="topik_rapat" type="text" value="" class="regular-text" /></td>';
    echo '</tr>';
    echo '<tr>';
    echo '<th scope="row"><label for="tanggal_rapat">Tanggal Rapat</label></th>';
    echo '<td><input name="tanggal_rapat" id="tanggal_rapat" type="date" value="" class="regular-text" /></td>';
    echo '</tr>';
    echo '<tr>';
    echo '<th scope="row"><label for="tempat_rapat">Tempat Rapat</label></th>';
    echo '<td><input name="tempat_rapat" id="tempat_rapat" type="text" value="" class="regular-text" /></td>';
    echo '</tr>';
    echo '<tr>';
    echo '<th scope="row"><label for="peserta_rapat">Peserta Rapat</label></th>';
    echo '<td><input name="peserta_rapat" id="peserta_rapat" type="text" value="" class="regular-text" /></td>';
    echo '</tr>';
    echo '<tr>';
    echo '<th scope="row"><label for="notulen_rapat">Notulen Rapat</label></th>';
    echo '<td><textarea name="notulen_rapat" id="notulen_rapat" rows="10" class="regular-text"></textarea></td>';
    echo '</tr>';
    echo '</tbody>';
    echo '</table>';
    echo '<input type="submit" value="' . ($editing ? 'Update' : 'Add New') . ' Notulen" class="button-primary">';
    echo '</form>';
}