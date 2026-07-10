<?php

include('../../../inc/includes.php');

Session::checkLoginUser();

header('Content-Type: application/json; charset=utf-8');

$q = trim($_GET['q'] ?? '');

// Sécurité : on n'interroge la base que si la recherche fait au moins 3 caractères
if (mb_strlen($q) < 3) {
    echo json_encode(['kb' => [], 'tickets' => []]);
    exit();
}

global $DB;
$like = '%' . $q . '%';

// ==========================================================
// 1. Recherche dans la Base de Connaissances
// ==========================================================
$kbResults = [];
$res = $DB->request([
    'SELECT' => ['id', 'name', 'answer'],
    'FROM'   => 'glpi_knowbaseitems',
    'WHERE'  => [
        'OR' => [
            ['name'   => ['LIKE', $like]],
            ['answer' => ['LIKE', $like]],
        ]
    ],
    'LIMIT' => 5
]);

foreach ($res as $row) {
    $kbResults[] = [
        'id'      => $row['id'],
        'title'   => $row['name'],
        'excerpt' => mb_substr(strip_tags($row['answer']), 0, 160) . '...'
    ];
}

// ==========================================================
// 2. Recherche dans les tickets déjà résolus (avec leur solution)
// ==========================================================
$ticketResults = [];

$safeLike = $DB->escape($like);

$query = "SELECT t.id, t.name, s.content AS solution
          FROM glpi_tickets t
          INNER JOIN glpi_itilsolutions s
                  ON s.items_id = t.id AND s.itemtype = 'Ticket'
          WHERE t.status IN (5, 6)
            AND (t.name LIKE '{$safeLike}' OR t.content LIKE '{$safeLike}')
          ORDER BY t.id DESC
          LIMIT 5";

$result = $DB->doQuery($query);  

if ($result) {
    while ($row = $DB->fetchAssoc($result)) {
        $ticketResults[] = [
            'id'      => $row['id'],
            'title'   => $row['name'],
            'excerpt' => mb_substr(
    trim(preg_replace('/\s+/', ' ', strip_tags(html_entity_decode($row['solution'])))),
    0, 160
) . '...'
        ];
    }
}

echo json_encode(['kb' => $kbResults, 'tickets' => $ticketResults]);
exit();