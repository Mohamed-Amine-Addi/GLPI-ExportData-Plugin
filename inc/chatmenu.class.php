<?php

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

class PluginExportdataChatMenu extends CommonGLPI {

    static function getMenuName() {
        return __('AI Assistant');
    }

    static function getMenuContent() {
        $menu = [
            'title' => self::getMenuName(),
            'page'  => '/plugins/exportdata/front/chat.php',
            'icon'  => 'fas fa-robot',
        ];
        return $menu;
    }
}