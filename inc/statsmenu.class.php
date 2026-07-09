<?php

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

class PluginExportdataStatsMenu extends CommonGLPI {

    static function getMenuName() {
        return __('Advanced Statistics');
    }

    static function getMenuContent() {
        $menu = [
            'title' => self::getMenuName(),
            'page'  => '/plugins/exportdata/front/stats.php',
            'icon'  => 'fas fa-chart-pie',
        ];
        return $menu;
    }
}