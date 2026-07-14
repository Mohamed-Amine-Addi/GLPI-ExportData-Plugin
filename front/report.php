<?php

include('../../../inc/includes.php');
Session::checkLoginUser();
include(__DIR__ . '/../inc/reportfields.php');

$fieldsConfig = plugin_exportdata_getReportFields();
$relations    = plugin_exportdata_getReportRelations();

// Trouve la relation entre deux types, quel que soit l'ordre choisi
function findRelation($typeA, $typeB, $relations) {
    foreach ($relations as $r) {
        if (($r['a'] === $typeA && $r['b'] === $typeB) ||
            ($r['a'] === $typeB && $r['b'] === $typeA)) {
            return $r['on'];
        }
    }
    return null;
}

// Construit la liste SELECT + en-têtes à partir des champs cochés (préfixés p_ ou s_)
function buildSelection($fieldsConfig, $primaryType, $secondaryType, $requested) {
    $primaryTable   = $fieldsConfig[$primaryType]['table'];
    $secondaryTable = $secondaryType ? $fieldsConfig[$secondaryType]['table'] : null;

    $selectExprs = [];
    $headers = [];
    $colKeys = [];

    foreach ($requested as $raw) {
        if (strpos($raw, 'p_') === 0) {
            $key = substr($raw, 2);
            if (isset($fieldsConfig[$primaryType]['fields'][$key])) {
                $alias = 'p_' . $key;
                $selectExprs[] = "{$primaryTable}.{$key} AS {$alias}";
                $headers[] = $primaryType . ': ' . $fieldsConfig[$primaryType]['fields'][$key];
                $colKeys[] = $alias;
            }
        } elseif (strpos($raw, 's_') === 0 && $secondaryType) {
            $key = substr($raw, 2);
            if (isset($fieldsConfig[$secondaryType]['fields'][$key])) {
                $alias = 's_' . $key;
                $selectExprs[] = "{$secondaryTable}.{$key} AS {$alias}";
                $headers[] = $secondaryType . ': ' . $fieldsConfig[$secondaryType]['fields'][$key];
                $colKeys[] = $alias;
            }
        }
    }

    return [$selectExprs, $headers, $colKeys];
}

function runReportQuery($fieldsConfig, $relations, $primaryType, $secondaryType, $requested, $limit = null) {
    global $DB;

    if (!isset($fieldsConfig[$primaryType])) return [[], []];

    [$selectExprs, $headers, $colKeys] = buildSelection($fieldsConfig, $primaryType, $secondaryType, $requested);
    if (empty($selectExprs)) return [[], []];

    $primaryTable = $fieldsConfig[$primaryType]['table'];
    $query = "SELECT " . implode(', ', $selectExprs) . " FROM {$primaryTable}";

    if ($secondaryType && isset($fieldsConfig[$secondaryType])) {
        $onClause = findRelation($primaryType, $secondaryType, $relations);
        if ($onClause) {
            $secondaryTable = $fieldsConfig[$secondaryType]['table'];
            $query .= " LEFT JOIN {$secondaryTable} ON {$onClause}";
        }
    }

    if ($limit) {
        $query .= " LIMIT " . (int)$limit;
    }

    $result = $DB->doQuery($query);
    $rows = [];
    if ($result) {
        while ($row = $DB->fetchAssoc($result)) {
            $rows[] = array_map(function($k) use ($row) { return $row[$k]; }, $colKeys);
        }
    }

    return [$headers, $rows];
}

// ==================================================================
// EXPORT
// ==================================================================
if (isset($_POST['do_export'])) {

    $primaryType   = $_POST['datatype'] ?? '';
    $secondaryType = $_POST['datatype2'] ?? '';
    $format        = $_POST['format'] ?? 'csv';
    $requested     = $_POST['fields'] ?? [];

    [$headers, $rows] = runReportQuery($fieldsConfig, $relations, $primaryType, $secondaryType, $requested);

    if (empty($headers)) exit('No valid fields selected');

    $filenameBase = 'report_' . strtolower($primaryType) . ($secondaryType ? '_' . strtolower($secondaryType) : '');

    switch ($format) {

        case 'csv':
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=' . $filenameBase . '.csv');
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers);
            foreach ($rows as $r) fputcsv($out, $r);
            fclose($out);
            exit();

        case 'json':
            header('Content-Type: application/json; charset=utf-8');
            header('Content-Disposition: attachment; filename=' . $filenameBase . '.json');
            $json = [];
            foreach ($rows as $r) $json[] = array_combine($headers, $r);
            echo json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            exit();

        case 'xls':
            header('Content-Type: application/vnd.ms-excel; charset=utf-8');
            header('Content-Disposition: attachment; filename=' . $filenameBase . '.xls');
            echo '<table border="1"><tr>';
            foreach ($headers as $h) echo '<th>' . htmlspecialchars($h) . '</th>';
            echo '</tr>';
            foreach ($rows as $r) {
                echo '<tr>';
                foreach ($r as $v) echo '<td>' . htmlspecialchars($v ?? '') . '</td>';
                echo '</tr>';
            }
            echo '</table>';
            exit();

        case 'pdf':
            require_once GLPI_ROOT . '/vendor/tecnickcom/tcpdf/tcpdf.php';

            $pdf = new TCPDF('L'); // Paysage, plus large pour plusieurs colonnes
            $pdf->AddPage();
            $pdf->SetFont('helvetica', '', 9);

            $pdf->SetFont('helvetica', 'B', 13);
            $pdf->Cell(0, 10, 'Report: ' . $primaryType . ($secondaryType ? ' + ' . $secondaryType : ''), 0, 1, 'C');
            $pdf->SetFont('helvetica', '', 9);
            $pdf->Ln(3);

            $colWidth = 270 / count($headers);

            $pdf->SetFillColor(230, 230, 230);
            foreach ($headers as $h) {
                $pdf->Cell($colWidth, 8, $h, 1, 0, 'C', true);
            }
            $pdf->Ln();

            foreach ($rows as $r) {
                foreach ($r as $v) {
                    $pdf->Cell($colWidth, 7, (string)($v ?? ''), 1, 0, 'C');
                }
                $pdf->Ln();
            }

            $pdf->Output($filenameBase . '.pdf', 'D');
            exit();
    }
}

// ==================================================================
// APERÇU (AJAX)
// ==================================================================
if (isset($_GET['preview'])) {
    header('Content-Type: application/json; charset=utf-8');

    $primaryType   = $_GET['datatype'] ?? '';
    $secondaryType = $_GET['datatype2'] ?? '';
    $requested     = isset($_GET['fields']) ? explode(',', $_GET['fields']) : [];

    [$headers, $rows] = runReportQuery($fieldsConfig, $relations, $primaryType, $secondaryType, $requested, 10);

    echo json_encode(['headers' => $headers, 'rows' => $rows]);
    exit();
}

// ==================================================================
// AFFICHAGE DE LA PAGE
// ==================================================================
Html::header("Report Builder", $_SERVER['PHP_SELF'], "tools", "pluginexportdata");
?>

<div class="center" style="max-width:950px;margin:auto;">
<h1>Report Builder</h1>
<p>Choisissez un type de données principal, éventuellement un type à combiner, les champs à inclure et leur ordre.</p>

<table class="tab_cadre_fixe" style="width:100%;">
<tr>
  <td><strong>Primary data type</strong></td>
  <td>
    <select id="datatypeSelect">
      <?php foreach ($fieldsConfig as $key => $conf): ?>
      <option value="<?php echo $key; ?>"><?php echo $key; ?></option>
      <?php endforeach; ?>
    </select>
  </td>
</tr>
<tr>
  <td><strong>Combine with (optional)</strong></td>
  <td><select id="datatype2Select"><option value="">None</option></select></td>
</tr>
</table>

<div style="display:flex;gap:20px;margin-top:15px;">
  <div style="flex:1;">
    <h3>Available fields</h3>
    <div id="availableFields"></div>
  </div>
  <div style="flex:1;">
    <h3>Selected fields (export order)</h3>
    <ul id="selectedFields" style="list-style:none;padding:0;"></ul>
  </div>
</div>

<div style="margin-top:15px;">
  <button type="button" id="previewBtn" class="btn btn-secondary">Aperçu</button>
  <select id="formatSelect">
    <option value="csv">CSV</option>
    <option value="xls">Excel</option>
    <option value="json">JSON</option>
    <option value="pdf">PDF</option>
  </select>
  <button type="button" id="exportBtn" class="btn btn-primary">Exporter</button>
</div>

<div id="previewArea" style="margin-top:20px;overflow-x:auto;"></div>
</div>

<form id="exportForm" method="post" action="" style="display:none;">
  <?php echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken(false)]); ?>
  <input type="hidden" name="do_export" value="1">
  <input type="hidden" name="datatype" id="hiddenDatatype">
  <input type="hidden" name="datatype2" id="hiddenDatatype2">
  <input type="hidden" name="format" id="hiddenFormat">
  <div id="hiddenFieldsContainer"></div>
</form>

<script>
const FIELDS_CONFIG = <?php echo json_encode($fieldsConfig); ?>;
const RELATIONS     = <?php echo json_encode($relations); ?>;
let selected = [];

const datatypeSelect  = document.getElementById('datatypeSelect');
const datatype2Select = document.getElementById('datatype2Select');
const availableDiv    = document.getElementById('availableFields');
const selectedList    = document.getElementById('selectedFields');

function compatibleTypes(primary) {
  const out = [];
  RELATIONS.forEach(function(r){
    if (r.a === primary) out.push({type:r.b, label:r.label});
    if (r.b === primary) out.push({type:r.a, label:r.label});
  });
  return out;
}

function refreshDatatype2Options() {
  const primary = datatypeSelect.value;
  const compat = compatibleTypes(primary);
  let html = '<option value="">None</option>';
  compat.forEach(function(c){
    html += '<option value="' + c.type + '">' + c.type + ' — ' + c.label + '</option>';
  });
  datatype2Select.innerHTML = html;
}

function renderAvailable() {
  const dt  = datatypeSelect.value;
  const dt2 = datatype2Select.value;

  let html = '<div style="font-weight:600;margin-bottom:4px;">' + dt + '</div>';
  const fields = FIELDS_CONFIG[dt].fields;
  for (const key in fields) {
    const val = 'p_' + key;
    const checked = selected.includes(val) ? 'checked' : '';
    html += '<label style="display:block;margin:3px 0;">'
          + '<input type="checkbox" value="' + val + '" ' + checked + ' class="fieldCheck"> ' + fields[key]
          + '</label>';
  }

  if (dt2) {
    html += '<div style="font-weight:600;margin:12px 0 4px;">' + dt2 + '</div>';
    const fields2 = FIELDS_CONFIG[dt2].fields;
    for (const key in fields2) {
      const val = 's_' + key;
      const checked = selected.includes(val) ? 'checked' : '';
      html += '<label style="display:block;margin:3px 0;">'
            + '<input type="checkbox" value="' + val + '" ' + checked + ' class="fieldCheck"> ' + fields2[key]
            + '</label>';
    }
  }

  availableDiv.innerHTML = html;
  availableDiv.querySelectorAll('.fieldCheck').forEach(function(cb){
    cb.addEventListener('change', function(){
      if (this.checked) {
        if (!selected.includes(this.value)) selected.push(this.value);
      } else {
        selected = selected.filter(function(f){ return f !== cb.value; });
      }
      renderSelected();
    });
  });
}

function fieldLabel(raw) {
  const dt  = datatypeSelect.value;
  const dt2 = datatype2Select.value;
  if (raw.startsWith('p_')) return FIELDS_CONFIG[dt].fields[raw.substring(2)];
  if (raw.startsWith('s_') && dt2) return FIELDS_CONFIG[dt2].fields[raw.substring(2)];
  return raw;
}

function renderSelected() {
  let html = '';
  selected.forEach(function(key, idx){
    html += '<li style="display:flex;align-items:center;gap:6px;padding:4px;border:1px solid #ddd;margin-bottom:4px;border-radius:4px;">'
          + '<span style="flex:1;">' + fieldLabel(key) + '</span>'
          + '<button type="button" data-idx="' + idx + '" data-dir="-1" class="moveBtn">↑</button>'
          + '<button type="button" data-idx="' + idx + '" data-dir="1" class="moveBtn">↓</button>'
          + '</li>';
  });
  selectedList.innerHTML = html;
  selectedList.querySelectorAll('.moveBtn').forEach(function(btn){
    btn.addEventListener('click', function(){
      const idx = parseInt(this.getAttribute('data-idx'));
      const dir = parseInt(this.getAttribute('data-dir'));
      const newIdx = idx + dir;
      if (newIdx < 0 || newIdx >= selected.length) return;
      const tmp = selected[idx];
      selected[idx] = selected[newIdx];
      selected[newIdx] = tmp;
      renderSelected();
    });
  });
}

datatypeSelect.addEventListener('change', function(){
  selected = [];
  refreshDatatype2Options();
  renderAvailable();
  renderSelected();
  document.getElementById('previewArea').innerHTML = '';
});

datatype2Select.addEventListener('change', function(){
  selected = selected.filter(function(f){ return f.startsWith('p_'); });
  renderAvailable();
  renderSelected();
  document.getElementById('previewArea').innerHTML = '';
});

document.getElementById('previewBtn').addEventListener('click', function(){
  if (selected.length === 0) { alert('Sélectionnez au moins un champ.'); return; }
  const dt  = datatypeSelect.value;
  const dt2 = datatype2Select.value;
  fetch('report.php?preview=1&datatype=' + encodeURIComponent(dt)
      + '&datatype2=' + encodeURIComponent(dt2)
      + '&fields=' + encodeURIComponent(selected.join(',')))
    .then(function(r){ return r.json(); })
    .then(function(data){
      let html = '<table class="tab_cadre_fixe" style="width:100%;"><tr>';
      data.headers.forEach(function(h){ html += '<th>' + h + '</th>'; });
      html += '</tr>';
      data.rows.forEach(function(row){
        html += '<tr>';
        row.forEach(function(v){ html += '<td>' + (v ?? '') + '</td>'; });
        html += '</tr>';
      });
      html += '</table>';
      document.getElementById('previewArea').innerHTML = html;
    });
});

document.getElementById('exportBtn').addEventListener('click', function(){
  if (selected.length === 0) { alert('Sélectionnez au moins un champ.'); return; }
  document.getElementById('hiddenDatatype').value  = datatypeSelect.value;
  document.getElementById('hiddenDatatype2').value = datatype2Select.value;
  document.getElementById('hiddenFormat').value    = document.getElementById('formatSelect').value;
  const container = document.getElementById('hiddenFieldsContainer');
  container.innerHTML = '';
  selected.forEach(function(f){
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'fields[]';
    input.value = f;
    container.appendChild(input);
  });
  document.getElementById('exportForm').submit();
});

refreshDatatype2Options();
renderAvailable();
renderSelected();
</script>

<?php
Html::footer();
?>