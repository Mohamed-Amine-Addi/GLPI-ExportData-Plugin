<?php

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

class PluginExportdataReportMenu extends CommonGLPI {

    static function getMenuName() {
        return __('Report Builder');
    }

    static function getMenuContent() {
        $menu = [
            'title' => self::getMenuName(),
            'page'  => '/plugins/exportdata/front/report.php',
            'icon'  => 'fas fa-layer-group',
        ];
        return $menu;
    }
}