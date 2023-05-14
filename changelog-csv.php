<?php
/*
Plugin Name: Changelog CSV
Plugin URI: https://500designs.com/
Description: This is a simple plugin to display the changelogs of all installed plugins.
Version: 1.0
Author: Mauro Carrera
Author URI: https://500designs.com/
License: GPL2
*/

// Hook for adding admin menus
add_action('admin_menu', 'wp_plugin_changelog_add_pages');

// Action function for above hook
function wp_plugin_changelog_add_pages() {
    // enqueue the scripts and styles in the admin pages
    add_action( 'admin_enqueue_scripts', 'enqueue_admin_scripts_and_styles' );

    add_menu_page(__('Changelog CSV','menu-test'), __('Changelog CSV','menu-test'), 'manage_options', 'changelog-report', 'wp_plugin_changelog_output' );
}



// The function to output the changelog page
function wp_plugin_changelog_output() {
    // Include the necessary files
    require_once(ABSPATH . 'wp-admin/includes/plugin-install.php');
    require_once(ABSPATH . 'wp-admin/includes/plugin.php');

    // Get all installed plugins
    $all_plugins = get_plugins();

    // Start the table output
    echo '<h2>Changelog CSV</h2>';
    echo '<div id="cooldown-container">';
    echo '<button id="download-csv" class="button-primary">Download CSV</button>';
    echo '</div>';
 
    echo '<table id="changelog-table" class="widefat fixed striped" style="margin-top: 20px; border-collapse: collapse;">';
    echo '<thead><tr><th>Name</th><th>Current Version</th><th>Latest Version</th><th>Changelog</th></tr></thead><tbody>';

    // Loop through each plugin
    foreach ($all_plugins as $plugin_file => $plugin_data) {
        $plugin_slug = dirname($plugin_file);
        $api = plugins_api('plugin_information', array(
            'slug' => $plugin_slug,
            'fields' => array(
                'sections' => true,
            ),
        ));
        $latest_version = 'Up to date.';
        if (!is_wp_error($api)) {
            $latest_version = $api->version;

            // Create a new DOMDocument instance
            $dom = new DOMDocument();
            // Load the HTML
            @$dom->loadHTML($api->sections['changelog']);

            // Find the first H4
            $h4s = $dom->getElementsByTagName('h4');
            $h4 = $h4s->item(0);
            $h4 = $dom->saveHTML($h4);

            // Find the first UL following the H4
            $ul = $dom->getElementsByTagName('ul');
            $ul = $ul->item(0);
            $ul = $dom->saveHTML($ul);

            $changelog = $ul;
            $changelog = str_replace('.', ".\n", $changelog);
            $changelog = str_replace('Â', '', $changelog);
        } else {
            $changelog = 'Changelog not available.';
        }
        echo '<tr' . ($latest_version == 'Up to date.' ? ' class="up-to-date"' : '') . '>';
        echo '<td>' . esc_html($plugin_data['Name']) . '</td>';
        echo '<td>' . esc_html($plugin_data['Version']) . '</td>';
        echo '<td>' . esc_html($latest_version) . '</td>';
                echo '<td>' . wp_kses_post($changelog) . '</td>';
        echo '</tr>';
    }

    // Close the table
    echo '</tbody></table>';

    // CSS to hide rows with 'up-to-date' class
    echo '<style>
        tr.up-to-date {
            display: none;
        }
    </style>';

        // Add DataTables initialization
    echo "<script>
        jQuery(document).ready(function() {
            jQuery('#changelog-table').DataTable({
                responsive: true,
                
            });
        });
    </script>";


     // CSS to adjust the width of the "Show X entries" dropdown
    echo '<style>
        .dataTables_length {
            margin-top: 10px !important;
            margin-bottom: 10px !important;

        }
        .dataTables_length select {
            width: 50px !important;
        }
        .dataTables_wrapper .dataTables_filter input {
    border: 1px solid #aaa;
    border-radius: 3px;
    padding: 5px;
    background-color: white;
    margin-left: 3px;
    margin-right: 10px;
}


    </style>';

    // JavaScript to convert HTML table to CSV
    echo "<script>
        document.getElementById('download-csv').addEventListener('click', function() {
            var button = this; // Reference to the button

            // Disable the button
            button.disabled = true;

            // Show cooldown timer message
            var cooldownMessage = document.createElement('span');
            cooldownMessage.textContent = 'Please wait... Cooldown: 60 seconds';
            cooldownMessage.style.color = '#000';
            cooldownMessage.style.fontSize = '14px';
            cooldownMessage.style.marginLeft = '10px';
            button.parentNode.appendChild(cooldownMessage);

            // Set cooldown timer
            var cooldownSeconds = 60;
            var cooldownInterval = setInterval(function() {
                cooldownSeconds--;
                cooldownMessage.textContent = 'Please wait... Cooldown: ' + cooldownSeconds + ' seconds';
                if (cooldownSeconds === 0) {
                    // Enable the button and remove the cooldown message
                    button.disabled = false;
                    cooldownMessage.parentNode.removeChild(cooldownMessage);
                    clearInterval(cooldownInterval);
                }
            }, 1000);

            var table = document.getElementById('changelog-table');
            var rows = Array.from(table.getElementsByTagName('tr'));
            var csvContent = '';
            rows.forEach(function(row) {
                var rowData = Array.from(row.getElementsByTagName('td'));
                var latestVersionCell = rowData[2]; // Get the 'Latest Version' cell
                if (latestVersionCell && latestVersionCell.textContent !== 'Up to date.') {
                    rowData = rowData.map(function(cell) {
                        var cellText = cell.textContent.replace('\"', '\"\"');
                        cellText = cellText.replace(/Â/g, '');  // Remove the \"Â\" character
                        cellText = cellText.replace(/\\s+/g, ' ');  // Replace all forms of whitespace with a single space
                        cellText = cellText.replace(/\\s*;\\s*/g, ';');  // Remove spaces before and after semicolon
                        return '\"' + cellText + '\"';
                    });
                    csvContent += rowData.join(',') + '\\n';
                }
            });
            csvContent = csvContent.replace(/\\n\\s*\\n/g, '\\n');  // Remove empty lines
            var blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            var filename = (new URL('".get_site_url()."')).hostname + '-' + new Date().toISOString() + '.csv';
            if (navigator.msSaveBlob) { // IE 10+
                navigator.msSaveBlob(blob, filename);
            } else {
                var link = document.createElement(\"a\");
                if (link.download !== undefined) { // feature detection
                    var url = URL.createObjectURL(blob);
                    link.setAttribute(\"href\", url);
                    link.setAttribute(\"download\", filename);
                    link.style.visibility = 'hidden';
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                }
            }
        });
    </script>";
}

echo '
<script>
window.addEventListener("DOMContentLoaded", (event) => {
    var links = document.querySelectorAll("#changelog-table a");
    for (var i = 0; i < links.length; i++) {
        links[i].target = "_blank";
    }
});
</script>
';

// Enqueue scripts and styles
function enqueue_admin_scripts_and_styles() {
    // include DataTables CSS
    wp_enqueue_style('datatables-css', 'https://cdn.datatables.net/1.10.25/css/jquery.dataTables.min.css', array(), null);

    // include DataTables Responsive CSS
    wp_enqueue_style('datatables-responsive-css', 'https://cdn.datatables.net/responsive/2.2.9/css/responsive.dataTables.min.css', array(), null);

    // include jQuery
    wp_enqueue_script('jquery');

    // include DataTables JS
    wp_enqueue_script('datatables-js', 'https://cdn.datatables.net/1.10.25/js/jquery.dataTables.min.js', array('jquery'), null, true);

    // include DataTables Responsive JS
    wp_enqueue_script('datatables-responsive-js', 'https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js', array('jquery', 'datatables-js'), null, true);
}

    // include jsPDF and html2canvas
    wp_enqueue_script('html2canvas', 'https://cdnjs.cloudflare.com/ajax/libs/html2canvas/0.5.0-beta4/html2canvas.min.js', array(), null, true);
   

?>
