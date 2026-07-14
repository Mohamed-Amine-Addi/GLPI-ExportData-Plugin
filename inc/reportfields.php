<?php

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

// Liste blanche des champs autorisés par type de donnée
// (sécurité : on n'acceptera JAMAIS un champ qui n'est pas dans cette liste)
function plugin_exportdata_getReportFields() {
    return [
        'Computer' => [
            'table'  => 'glpi_computers',
            'fields' => [
                'id'            => 'ID',
                'name'          => 'Name',
                'serial'        => 'Serial',
                'otherserial'   => 'Other Serial',
                'comment'       => 'Comment',
                'date_creation' => 'Creation Date',
                'date_mod'      => 'Last Modified',
            ]
        ],
        'Software' => [
            'table'  => 'glpi_softwares',
            'fields' => [
                'id'            => 'ID',
                'name'          => 'Name',
                'comment'       => 'Comment',
                'date_creation' => 'Creation Date',
            ]
        ],
        'User' => [
            'table'  => 'glpi_users',
            'fields' => [
                'id'        => 'ID',
                'name'      => 'Login',
                'realname'  => 'Last Name',
                'firstname' => 'First Name',
                'phone'     => 'Phone',
            ]
        ],
        'Ticket' => [
            'table'  => 'glpi_tickets',
            'fields' => [
                'id'       => 'ID',
                'name'     => 'Title',
                'status'   => 'Status',
                'date'     => 'Date',
                'urgency'  => 'Urgency',
                'priority' => 'Priority',
            ]
        ],
        'Printer' => [
            'table'  => 'glpi_printers',
            'fields' => [
                'id'      => 'ID',
                'name'    => 'Name',
                'serial'  => 'Serial',
                'comment' => 'Comment',
            ]
        ],
        'Manufacturer' => [
            'table'  => 'glpi_manufacturers',
            'fields' => [
                'id'      => 'ID',
                'name'    => 'Name',
                'comment' => 'Comment',
            ]
        ],
    ];
}

function plugin_exportdata_getReportRelations() {
    return [
        ['a' => 'Computer', 'b' => 'User',
         'on' => 'glpi_computers.users_id = glpi_users.id',
         'label' => 'User assigned to the computer'],

        ['a' => 'Computer', 'b' => 'Manufacturer',
         'on' => 'glpi_computers.manufacturers_id = glpi_manufacturers.id',
         'label' => 'Computer manufacturer'],

        ['a' => 'Printer', 'b' => 'Manufacturer',
         'on' => 'glpi_printers.manufacturers_id = glpi_manufacturers.id',
         'label' => 'Printer manufacturer'],
    ];
}