<?php
if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

function plugin_exportdata_ticket_post_add(Ticket $ticket) {
    global $DB;
    if (!empty($_POST['department'])) {
        $DB->insert('glpi_plugin_exportdata_departments', [
            'tickets_id' => $ticket->getID(),
            'department' => $_POST['department'],
        ]);
    }
}

function plugin_exportdata_getAddSearchOptionsNew($itemtype) {
    $options = [];

    if ($itemtype == 'Ticket') {
        // En-tête de groupe (regroupe nos champs dans le sélecteur de colonnes)
        $options[] = [
            'id'   => 'exportdata',
            'name' => 'Export Data',
        ];

        // Le champ Département, joint via tickets_id
        $options[] = [
            'id'         => 12001, // identifiant unique, à ne pas réutiliser ailleurs
            'table'      => 'glpi_plugin_exportdata_departments',
            'field'      => 'department',
            'name'       => 'Département',
            'datatype'   => 'string',
            'joinparams' => [
                'jointype' => 'child', // jointure NEWTABLE.tickets_id = glpi_tickets.id
            ],
        ];
    }

    return $options;
}