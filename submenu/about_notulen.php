<?php
function notulenmu_page()
{
    $user_id = get_current_user_id();
    $user = get_userdata($user_id);
    if (!empty($user) && is_array($user->roles)) {
        $role = $user->roles[0];
    }
    if ($role != 'contributor' && $role != 'administrator') {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    global $wpdb;

    $table_name = $wpdb->prefix . 'salammu_notulenmu';
    $results = $wpdb->get_results("SELECT topik_rapat FROM $table_name", ARRAY_A);

    $text = "";
    foreach ($results as $row) {
        $text .= " " . $row['topik_rapat'];
    }

    $text = strtolower(strip_tags($text));

    $words = str_word_count($text, 1);
    $word_counts = array_count_values($words);

    $word_data = [];
    foreach ($word_counts as $word => $count) {
        $word_data[] = ['text' => $word, 'size' => $count * 10]; 
    }
?>
    <div class="relative p-6 bg-[#2d3476] shadow-lg rounded-lg m-4 ml-0 text-white overflow-hidden">
        <img src="<?php echo plugin_dir_url(__FILE__) . '../assets/img/image.png'; ?>"
            alt="Notulenmu"
            class="absolute top-0 right-5 w-60">

        <p class="text-white font-bold relative z-10">Tentang NotulenMu</p>
        <p class="mt-2 text-justify relative z-10">
            NotulenMu adalah plugin yang digunakan untuk mencatat notulen rapat di wilayah, daerah, cabang, dan ranting. <br>
            Plugin ini dikembangkan oleh
            <a href="https://mandatech.co.id" class="text-yellow-300 text-inherit">
                Arwan Ahmad Khoiruddin
            </a>
        </p>
        <p class="mt-2 relative z-10">Persembahan dari LPCRPM Pimpinan Pusat Muhammadiyah.</p>

        <!-- <?php
                global $wpdb;
                $table_name = $wpdb->prefix . 'salammu_notulenmu';
                $results = $wpdb->get_results("SELECT id_tingkat FROM $table_name");

                if (!empty($results)) {
                    echo '<h2 class="mt-4 text-xl font-semibold text-white relative z-10">Data ID Tingkat</h2>';
                    echo '<ul class="mt-2 list-disc list-inside text-white relative z-10">';
                    foreach ($results as $row) {
                        echo '<li class="py-1 px-3 bg-gray-100 text-gray-800 rounded-md shadow-sm">ID Tingkat: ' . esc_html($row->id_tingkat) . '</li>';
                    }
                    echo '</ul>';
                } else {
                    echo '<p class="mt-4 text-red-400 relative z-10">No data found.</p>';
                }
                ?> -->
    </div>

    <div class="pr-4">
        <h2 class="mt-4 text-xl font-semibold text-white relative z-10">Topik Rapat yang Sering Dibahas</h2>
        <div id="wordcloud" class="flex items-center justify-center text-center bg-white w-auto h-auto rounded-lg shadow-md"></div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/d3/5.16.0/d3.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/d3-cloud/build/d3.layout.cloud.min.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            var words = <?php echo json_encode($word_data); ?>;

            var width = 400,
                height = 300;
            var svg = d3.select("#wordcloud").append("svg")
                .attr("width", width)
                .attr("height", height)
                .append("g")
                .attr("transform", "translate(" + width / 2 + "," + height / 2 + ")");

            var layout = d3.layout.cloud()
                .size([width, height])
                .words(words)
                .padding(5)
                .rotate(() => (~~(Math.random() * 2) * 90))
                .fontSize(d => d.size)
                .on("end", draw);

            layout.start();

            function draw(words) {
                svg.selectAll("text")
                    .data(words)
                    .enter().append("text")
                    .style("font-size", d => d.size + "px")
                    .style("fill", "#2d3476")
                    .attr("text-anchor", "middle")
                    .attr("transform", d => "translate(" + [d.x, d.y] + ")rotate(" + d.rotate + ")")
                    .text(d => d.text);
            }
        });
    </script>
<?php
}

?>