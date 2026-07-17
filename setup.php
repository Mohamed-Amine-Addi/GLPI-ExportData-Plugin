<?php

if(!defined('GLPI_ROOT')){
    die("Sorry. You can't access directly to this file");
}

function plugin_version_exportdata(){
    return [
        'name'       => "Export Data",
        'version'    => '1.0.0',
        'author'     => 'Amine',
        'license'    => 'GPLv3+',
        'homepage'   => '',
        'requirements'=> [
            'glpi' => [
                'min' => '10.0.0',
            ]
        ]
    ];
}

function plugin_exportdata_check_prerequisites(){
    return true;
}

function plugin_exportdata_check_config(){
    return true;
}

function plugin_exportdata_install(){
    return true;
}

function plugin_exportdata_uninstall(){
    return true;
}

function plugin_init_exportdata() {
    global $PLUGIN_HOOKS;

    $PLUGIN_HOOKS['csrf_compliant']['exportdata'] = true;

    $PLUGIN_HOOKS['menu_toadd']['exportdata'] = [
    'tools'    => 'PluginExportdataMenu',
    'helpdesk' => 'PluginExportdataStatsMenu',
    'management' => 'PluginExportdataReportMenu',
    'plugins'    => 'PluginExportdataChatMenu',
    ];

    $PLUGIN_HOOKS['post_item_form']['exportdata'] = 'plugin_exportdata_post_item_form';

}

function plugin_exportdata_central(){
    echo '<div class="card">';
    echo '<h3>Export Data Plugin</h3>';
    echo '<p>That is my first Plugin GLPI, and it is works.</p>';
    echo '</div>';

}

function plugin_exportdata_post_item_form($params) {
    $item = $params['item'];

    // On n'affiche notre encart que sur le formulaire des Tickets
    if (!($item instanceof Ticket)) {
        return;
    }
    ?>
    <div id="exportdata-suggest" style="margin:15px 0;padding:12px;border:1px solid #ddd;border-radius:6px;background:#fafafa;">
        <label style="font-weight:bold;display:block;margin-bottom:6px;">
            🔎 Rechercher une solution déjà existante
        </label>
        <input type="text" id="exportdata-suggest-input"
               placeholder="Ex : imprimante, mot de passe, réseau..."
               style="width:100%;padding:6px;border:1px solid #ccc;border-radius:4px;">
        <div id="exportdata-suggest-results" style="margin-top:10px;"></div>
    </div>

    <script>
    (function() {
        let timer = null;
        const input = document.getElementById('exportdata-suggest-input');
        const results = document.getElementById('exportdata-suggest-results');

        input.addEventListener('input', function() {
            clearTimeout(timer);
            const q = input.value.trim();
            if (q.length < 3) {
                results.innerHTML = '';
                return;
            }
            // On attend 400ms après la dernière frappe avant de chercher
            // (évite d'envoyer une requête à chaque lettre tapée)
            timer = setTimeout(function() {
                fetch('/glpi/glpi/plugins/exportdata/ajax/suggest.php?q=' + encodeURIComponent(q))
                    .then(r => r.json())
                    .then(data => {
                        let html = '';
                        if (data.kb.length === 0 && data.tickets.length === 0) {
                            html = '<div style="color:#888;font-size:13px;">Aucun résultat trouvé.</div>';
                        }
                        data.kb.forEach(item => {
                            html += '<div style="padding:8px;border-bottom:1px solid #eee;">'
                                + '<div style="font-size:12px;color:#0a6;font-weight:bold;">📘 Base de connaissances</div>'
                                + '<div style="font-weight:600;">' + item.title + '</div>'
                                + '<div style="font-size:12px;color:#666;">' + item.excerpt + '</div>'
                                + '</div>';
                        });
                        data.tickets.forEach(item => {
                            html += '<div style="padding:8px;border-bottom:1px solid #eee;">'
                                + '<div style="font-size:12px;color:#06c;font-weight:bold;">🎫 Ticket résolu #' + item.id + '</div>'
                                + '<div style="font-weight:600;">' + item.title + '</div>'
                                + '<div style="font-size:12px;color:#666;">' + item.excerpt + '</div>'
                                + '</div>';
                        });
                        results.innerHTML = html;
                    });
            }, 400);
        });
    })();
    </script>
    <?php
}
?>