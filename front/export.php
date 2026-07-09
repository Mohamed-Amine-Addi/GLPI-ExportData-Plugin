<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include('../../../inc/includes.php');


// Vérifier que l'utilisateur est connecté
Session::checkLoginUser();

// ==================================================================
// CONFIGURATION : table, colonnes et en-têtes pour chaque type de données
// ==================================================================
$datatypeConfig = [
    'Computer' => [
        'table'   => 'glpi_computers',
        'columns' => ['id', 'name', 'serial', 'otherserial'],
        'headers' => ['ID', 'Name', 'Serial', 'Other Serial']
    ],
    'Software' => [
        'table'   => 'glpi_softwares',
        'columns' => ['id', 'name', 'comment'],
        'headers' => ['ID', 'Name', 'Comment']
    ],
    'User' => [
        'table'   => 'glpi_users',
        'columns' => ['id', 'name', 'realname', 'firstname'],
        'headers' => ['ID', 'Login', 'Last Name', 'First Name']
    ],
    'Ticket' => [
        'table'   => 'glpi_tickets',
        'columns' => ['id', 'name', 'status', 'date'],
        'headers' => ['ID', 'Title', 'Status', 'Date']
    ],
    'Printer' => [
        'table'   => 'glpi_printers',
        'columns' => ['id', 'name', 'serial'],
        'headers' => ['ID', 'Name', 'Serial']
    ],
    'Monitor' => [
        'table'   => 'glpi_monitors',
        'columns' => ['id', 'name', 'serial'],
        'headers' => ['ID', 'Name', 'Serial']
    ],
    'NetworkEquipment' => [
        'table'   => 'glpi_networkequipments',
        'columns' => ['id', 'name', 'serial'],
        'headers' => ['ID', 'Name', 'Serial']
    ],
    'Peripheral' => [
        'table'   => 'glpi_peripherals',
        'columns' => ['id', 'name', 'serial'],
        'headers' => ['ID', 'Name', 'Serial']
    ],
    'Phone' => [
        'table'   => 'glpi_phones',
        'columns' => ['id', 'name', 'serial'],
        'headers' => ['ID', 'Name', 'Serial']
    ],
    'Supplier' => [
        'table'   => 'glpi_suppliers',
        'columns' => ['id', 'name', 'phonenumber'],
        'headers' => ['ID', 'Name', 'Phone']
    ],
    'Manufacturer' => [
        'table'   => 'glpi_manufacturers',
        'columns' => ['id', 'name'],
        'headers' => ['ID', 'Name']
    ],
];

// ==================================================================
// TRAITEMENT DE L'EXPORT — AVANT TOUT AFFICHAGE (obligatoire pour les headers HTTP)
// ==================================================================
if (isset($_POST['export'])) {

    $datatype = $_POST['datatype'];
    $format   = $_POST['format'];

    // Sécurité : on vérifie que le datatype demandé existe bien dans notre config
    if (isset($datatypeConfig[$datatype])) {

        global $DB;

        $config  = $datatypeConfig[$datatype];
        $headers = $config['headers'];

        // 1. Récupérer les données depuis la bonne table
        $result = $DB->request([
            'SELECT' => $config['columns'],
            'FROM'   => $config['table']
        ]);

        // On transforme le résultat en simple tableau PHP (plus facile à réutiliser)
        $rows = [];
        foreach ($result as $row) {
            $rows[] = array_values($row); // garde juste les valeurs, dans l'ordre des colonnes
        }

        $filenameBase = 'export_' . strtolower($datatype);

        // ==============================================================
        // 2. Génération selon le format demandé
        // ==============================================================
        switch ($format) {

            // ---------- CSV ----------
            case 'csv':
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename=' . $filenameBase . '.csv');

                $output = fopen('php://output', 'w');
                fputcsv($output, $headers);
                foreach ($rows as $row) {
                    fputcsv($output, $row);
                }
                fclose($output);
                exit();

            // ---------- JSON ----------
            case 'json':
                header('Content-Type: application/json; charset=utf-8');
                header('Content-Disposition: attachment; filename=' . $filenameBase . '.json');

                $jsonData = [];
                foreach ($rows as $row) {
                    // On associe chaque valeur à son en-tête (clé => valeur)
                    $jsonData[] = array_combine($headers, $row);
                }

                echo json_encode($jsonData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                exit();

            // ---------- XML ----------
            case 'xml':
                header('Content-Type: application/xml; charset=utf-8');
                header('Content-Disposition: attachment; filename=' . $filenameBase . '.xml');

                $xml = new SimpleXMLElement('<export/>');
                foreach ($rows as $row) {
                    $item = $xml->addChild('item');
                    foreach ($headers as $i => $headerName) {
                        // On nettoie le nom pour qu'il soit valide comme balise XML (pas d'espace)
                        $tagName = str_replace(' ', '_', $headerName);
                        $item->addChild($tagName, htmlspecialchars($row[$i] ?? ''));
                    }
                }

                echo $xml->asXML();
                exit();

            // ---------- XLSX (Excel) ----------
            case 'xlsx':
                header('Content-Type: application/vnd.ms-excel; charset=utf-8');
                header('Content-Disposition: attachment; filename=' . $filenameBase . '.xls');

                echo '<table border="1">';

                // Ligne d'en-tête
                echo '<tr>';
                foreach ($headers as $headerName) {
                    echo '<th>' . htmlspecialchars($headerName) . '</th>';
                }
                echo '</tr>';

                // Lignes de données
                foreach ($rows as $row) {
                    echo '<tr>';
                    foreach ($row as $value) {
                        echo '<td>' . htmlspecialchars($value ?? '') . '</td>';
                    }
                    echo '</tr>';
                }

                echo '</table>';
                exit();

            // ---------- PDF ----------
            case 'pdf':
                // GLPI embarque TCPDF
                require_once GLPI_ROOT . '/vendor/tecnickcom/tcpdf/tcpdf.php';

                $pdf = new TCPDF();
                $pdf->AddPage();
                $pdf->SetFont('helvetica', '', 10);

                // Titre
                $pdf->Cell(0, 10, 'Export : ' . $datatype, 0, 1, 'C');
                $pdf->Ln(5);

                // En-têtes de colonnes
                $colWidth = 190 / count($headers);
                foreach ($headers as $headerName) {
                    $pdf->Cell($colWidth, 8, $headerName, 1, 0, 'C');
                }
                $pdf->Ln();

                // Lignes de données
                foreach ($rows as $row) {
                    foreach ($row as $value) {
                        $pdf->Cell($colWidth, 8, (string)$value, 1, 0, 'C');
                    }
                    $pdf->Ln();
                }

                $pdf->Output($filenameBase . '.pdf', 'D'); // 'D' = force le téléchargement
                exit();

            default:
                // Format non reconnu, on continue vers l'affichage normal de la page
                break;
        }
    }
}

// ==================================================================
// AFFICHAGE NORMAL DE LA PAGE (formulaire)
// ==================================================================

Html::header("Export Data", $_SERVER['PHP_SELF'], "tools", "pluginexportdata");

?>

<div class="center">
    <h1>Export Data</h1>
    <p>Export GLPI data into different file formats.</p>

    <form method="post" action="">

        <table class="tab_cadre_fixe">
            <tr>
                <th colspan="2">Export Settings</th>
            </tr>

            <tr>
                <td><strong>Data to export</strong></td>
                <td>
                    <select name="datatype">
                        <option value="Computer">Computers</option>
                        <option value="Software">Software</option>
                        <option value="User">Users</option>
                        <option value="Ticket">Tickets</option>
                        <option value="Printer">Printers</option>
                        <option value="Monitor">Monitors</option>
                        <option value="NetworkEquipment">Network Equipments</option>
                        <option value="Peripheral">Peripherals</option>
                        <option value="Phone">Phones</option>
                        <option value="Supplier">Suppliers</option>
                        <option value="Manufacturer">Manufacturers</option>
                    </select>
                </td>
            </tr>

            <tr>
                <td><strong>Export format</strong></td>
                <td>
                    <select name="format">
                        <option value="csv">CSV</option>
                        <option value="xlsx">Excel (.xlsx)</option>
                        <option value="pdf">PDF</option>
                        <option value="json">JSON</option>
                        <option value="xml">XML</option>
                    </select>
                </td>
            </tr>

            <tr>
                <td colspan="2" class="center">
                    <input type="submit" name="export" value="Export" class="btn btn-primary">
                </td>
            </tr>

        </table>

       <?php echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken(false)]); ?>
    </form>
</div>

<?php

Html::footer();