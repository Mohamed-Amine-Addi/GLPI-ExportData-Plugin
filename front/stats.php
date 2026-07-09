<?php

include('../../../inc/includes.php');
Session::checkLoginUser();

//Les statistiques qu on peut afficher
$statsConfig = [
    'computers_by_manufacturer' => [
        'label' => 'Computers by Manufacturer',
        'table' => 'glpi_computers',
        'join' => 'glpi_manufacturers',
        'joinOn' => 'glpi_computers.manufacturers_id = glpi_manufacturers.id',
        'groupByField' => 'glpi_manufacturers.name',

    ],
     'tickets_by_status' => [
        'label' => 'Tickets by Status',
        'table' => 'glpi_tickets',
        'groupByField' => 'glpi_tickets.status',
    ],
    'computers_by_os' => [
    'label' => 'Computers by Operating System',
    'table' => 'glpi_computers',
    'join'  => 'glpi_items_operatingsystems',
    'joinOn' => "glpi_computers.id = glpi_items_operatingsystems.items_id AND glpi_items_operatingsystems.itemtype = 'Computer'",
    'join2' => 'glpi_operatingsystems',
    'joinOn2' => 'glpi_items_operatingsystems.operatingsystems_id = glpi_operatingsystems.id',
    'groupByField' => 'glpi_operatingsystems.name',
],
    'users_by_profile' => [
        'label' => 'Users by Profile',
        'table' => 'glpi_profiles_users',
        'join'  => 'glpi_profiles',
        'joinOn' => 'glpi_profiles_users.profiles_id = glpi_profiles.id',
        'groupByField' => 'glpi_profiles.name',
    ],
];

//si c est une requete ajax demandant les  donnees (json)
if (isset($_GET['ajax']) && isset($_GET['stat'])){
    header('Content-Type: application/json; charset=utf-8');
    $statKey = $_GET['stat'];
    if (!isset($statsConfig[$statKey])) {
        echo json_encode(['error' => 'Unknown stat']);
        exit();
    }

    global $DB;
    $config = $statsConfig[$statKey];
    $query = "SELECT {$config['groupByField']} AS label, COUNT(*) AS total
          FROM {$config['table']}";

if (isset($config['join'])) {
    $query .= " INNER JOIN {$config['join']} ON {$config['joinOn']}";
}

if (isset($config['join2'])) {
    $query .= " INNER JOIN {$config['join2']} ON {$config['joinOn2']}";
}

$query .= " GROUP BY {$config['groupByField']}
            ORDER BY total DESC
            LIMIT 10";

    $result = $DB->doQuery($query);

    $labels = [];
    $values = [];

    while ($row = $DB->fetchAssoc($result)) {
        $labels[] = $row['label'] ?? 'Unknown';
        $values[] = (int) $row['total'];
    }

    echo json_encode(['labels' => $labels, 'values' => $values]);
    exit();
}

//affichage normale de la page
Html::header("Advanced Statistics", $_SERVER['PHP_SELF'], "assistance", "pluginexportdata");

?>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<div class="center">
    <h1>Advanced Statistics</h1>
    <p>Select a dataset and a chart type to visualize your GLPI data.</p>

    <table class="tab_cadre_fixe">
        <tr>
            <td><strong>Data to display</strong></td>
            <td>
                <select id="statSelect">
                    <?php foreach ($statsConfig as $key => $conf): ?>
                        <option value="<?php echo $key; ?>"><?php echo $conf['label']; ?></option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
        <tr>
            <td><strong>Chart type</strong></td>
            <td>
                <select id="chartTypeSelect">
                    <option value="bar">Bar</option>
                    <option value="doughnut">Donut</option>
                    <option value="radar">Radar</option>
                    <option value="line">Line</option>
                    <option value="pie">Pie</option>
                </select>
            </td>
        </tr>
        <tr>
            <td colspan="2" class="center">
                <button type="button" id="generateBtn" class="btn btn-primary">Generate Chart</button>
            </td>
        </tr>
    </table>

    <div style="max-width: 700px; margin: 30px auto;">
        <canvas id="myChart"></canvas>
    </div>
</div>

<script>
let currentChart = null;

document.getElementById('generateBtn').addEventListener('click', function () {
    const stat = document.getElementById('statSelect').value;
    const chartType = document.getElementById('chartTypeSelect').value;

    fetch('stats.php?ajax=1&stat=' + encodeURIComponent(stat))
        .then(response => response.json())
        .then(data => {
            const ctx = document.getElementById('myChart').getContext('2d');

            // Detruit l'ancien graphique s'il existe, sinon Chart.js plante
            if (currentChart) {
                currentChart.destroy();
            }

            currentChart = new Chart(ctx, {
                type: chartType,
                data: {
                    labels: data.labels,
                    datasets: [{
                        label: stat,
                        data: data.values,
                        backgroundColor: [
                            '#4e79a7', '#f28e2b', '#e15759', '#76b7b2',
                            '#59a14f', '#edc948', '#b07aa1', '#ff9da7',
                            '#9c755f', '#bab0ac'
                        ]
                    }]
                },
                options: {
                    responsive: true
                }
            });
        });
});
</script>

<?php

Html::footer();
?>