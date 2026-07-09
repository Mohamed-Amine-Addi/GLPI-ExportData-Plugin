<?php

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

class PluginExportdataMenu extends CommonGLPI {

    static function getMenuName() {
        return __('Export Data');
    }
    
    static function getMenuContent() {
        $menu = [
            'title' => self::getMenuName(),
            'page'  => '/plugins/exportdata/front/export.php',
            'icon'  => 'fas fa-file-export',
        ];
        return $menu;
    }
}
