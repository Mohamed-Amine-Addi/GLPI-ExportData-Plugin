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
];
}

function plugin_exportdata_central(){
    echo '<div class="card">';
    echo '<h3>Export Data Plugin</h3>';
    echo '<p>That is my first Plugin GLPI, and it is works.</p>';
    echo '</div>';

}
?>