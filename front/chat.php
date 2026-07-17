<?php

include('../../../inc/includes.php');
Session::checkLoginUser();

// ==================================================================
// UTILITAIRES
// ==================================================================
function cleanText($raw) {
    $t = html_entity_decode(strip_tags($raw));
    $t = preg_replace('/\s+/', ' ', $t);
    return mb_substr(trim($t), 0, 400);
}

function callOllama($prompt) {
    $payload = json_encode([
        'model'  => 'llama3.2:1b',
        'prompt' => $prompt,
        'stream' => false
    ]);
    $ch = curl_init('http://localhost:11434/api/generate');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 90);
    $result = curl_exec($ch);
    $answer = null;
    if (!curl_errno($ch) && $result) {
        $data = json_decode($result, true);
        $answer = $data['response'] ?? null;
    }
    curl_close($ch);
    return $answer;
}

// ==================================================================
// DÉTECTION D'INTENTION (fiable, en PHP, PAS par l'IA)
// ==================================================================
function detectStatsEntity($ql) {
    $map = [
        ['keywords' => ['ticket ouvert', 'tickets ouverts', 'ticket en cours', 'tickets en cours'],
         'table' => 'glpi_tickets', 'where' => 'status NOT IN (5,6)', 'label' => 'tickets ouverts'],
        ['keywords' => ['ticket résolu', 'tickets résolus', 'ticket fermé', 'tickets fermés', 'ticket clos'],
         'table' => 'glpi_tickets', 'where' => 'status IN (5,6)', 'label' => 'tickets résolus ou fermés'],
        ['keywords' => ['ticket', 'tickets'],
         'table' => 'glpi_tickets', 'where' => null, 'label' => 'tickets au total'],
        ['keywords' => ['ordinateur', 'ordinateurs', 'pc', 'computer'],
         'table' => 'glpi_computers', 'where' => null, 'label' => 'ordinateurs'],
        ['keywords' => ['utilisateur', 'utilisateurs', 'user'],
         'table' => 'glpi_users', 'where' => null, 'label' => 'utilisateurs'],
        ['keywords' => ['imprimante', 'imprimantes', 'printer'],
         'table' => 'glpi_printers', 'where' => null, 'label' => 'imprimantes'],
        ['keywords' => ['logiciel', 'logiciels', 'software'],
         'table' => 'glpi_softwares', 'where' => null, 'label' => 'logiciels'],
    ];
    foreach ($map as $entry) {
        foreach ($entry['keywords'] as $kw) {
            if (mb_strpos($ql, $kw) !== false) return $entry;
        }
    }
    return null;
}

function detectIntent($question) {
    $ql = mb_strtolower($question);

    $ticketTriggers = ['créer un ticket', 'créer ticket', 'ouvrir un ticket',
                        'nouveau ticket', 'faire un ticket', 'soumettre un ticket'];
    foreach ($ticketTriggers as $t) {
        if (mb_strpos($ql, $t) !== false) return ['type' => 'ticket_draft'];
    }

    $statsTriggers = ['combien', 'nombre de', 'nombre d\'', 'total de', 'total d\''];
    foreach ($statsTriggers as $t) {
        if (mb_strpos($ql, $t) !== false) {
            $entity = detectStatsEntity($ql);
            if ($entity) return ['type' => 'stats', 'entity' => $entity];
        }
    }

    return ['type' => 'search'];
}

// ==================================================================
// APPEL AJAX : question posée
// ==================================================================
if (isset($_GET['ask'])) {
    header('Content-Type: application/json; charset=utf-8');

    $question = trim($_GET['question'] ?? '');
    if ($question === '') { echo json_encode(['type'=>'search','answer'=>'Question vide.','sources'=>[]]); exit(); }

    global $DB;
    $intent = detectIntent($question);

    // --------------------------------------------------------
    // INTENTION : STATISTIQUES (100% fiable, pas d'IA nécessaire)
    // --------------------------------------------------------
    if ($intent['type'] === 'stats') {
        $entity = $intent['entity'];
        $query = "SELECT COUNT(*) AS total FROM {$entity['table']}";
        if ($entity['where']) $query .= " WHERE {$entity['where']}";

        $res = $DB->doQuery($query);
        $count = 0;
        if ($res) { $row = $DB->fetchAssoc($res); $count = (int)$row['total']; }

        echo json_encode([
            'type'   => 'stats',
            'answer' => "Il y a actuellement <strong>{$count}</strong> {$entity['label']} enregistré(s) dans GLPI.",
            'sources'=> []
        ]);
        exit();
    }

    // --------------------------------------------------------
    // INTENTION : BROUILLON DE TICKET (préparation, PAS de création automatique)
    // --------------------------------------------------------
    if ($intent['type'] === 'ticket_draft') {
        $prompt = "Rédige un brouillon de ticket de support informatique à partir du message ci-dessous. "
                . "Réponds EXACTEMENT sous cette forme, sans rien ajouter avant ni après :\n"
                . "Titre: <titre court, moins de 10 mots>\n"
                . "Description: <description claire du problème, 2 à 3 phrases>\n\n"
                . "Message de l'utilisateur : {$question}";

        $raw = callOllama($prompt);
        $title = mb_substr($question, 0, 60);
        $description = $question;

        if ($raw) {
            if (preg_match('/Titre\s*:\s*(.+)/i', $raw, $m)) $title = trim($m[1]);
            if (preg_match('/Description\s*:\s*(.+)/is', $raw, $m)) $description = trim($m[1]);
        }

        echo json_encode([
            'type'  => 'ticket_draft',
            'draft' => ['title' => $title, 'description' => $description]
        ]);
        exit();
    }

    // --------------------------------------------------------
    // INTENTION : RECHERCHE DE SOLUTION (RAG, comme avant)
    // --------------------------------------------------------
    $stopwords = ['le','la','les','de','des','du','un','une','et','est','pour',
                  'avec','dans','sur','mon','ma','mes','je','j','ai','que','qui',
                  'ne','pas','plus','en','au','aux','ce','se','sa','son','ses',
                  'a','à','il','elle','on','me','te','nous','vous'];

    $words = preg_split('/\s+/', mb_strtolower($question));
    $keywords = [];
    foreach ($words as $w) {
        $w = trim($w, ".,!?;:'\"");
        if (mb_strlen($w) >= 3 && !in_array($w, $stopwords)) $keywords[] = $w;
    }
    $keywords = array_slice(array_unique($keywords), 0, 6);

    $kbResults = [];
    $ticketResults = [];

    if (!empty($keywords)) {
        $kbConditions = [];
        foreach ($keywords as $kw) {
            $safe = $DB->escape('%' . $kw . '%');
            $kbConditions[] = "(name LIKE '{$safe}' OR answer LIKE '{$safe}')";
        }
        $res = $DB->doQuery("SELECT id, name, answer FROM glpi_knowbaseitems WHERE "
                . implode(' OR ', $kbConditions) . " LIMIT 4");
        if ($res) while ($row = $DB->fetchAssoc($res)) {
            $kbResults[] = ['id'=>$row['id'], 'title'=>$row['name'], 'text'=>cleanText($row['answer'])];
        }

        $tConditions = [];
        foreach ($keywords as $kw) {
            $safe = $DB->escape('%' . $kw . '%');
            $tConditions[] = "(t.name LIKE '{$safe}' OR t.content LIKE '{$safe}')";
        }
        $res = $DB->doQuery("SELECT t.id, t.name, s.content AS solution
                              FROM glpi_tickets t
                              INNER JOIN glpi_itilsolutions s ON s.items_id = t.id AND s.itemtype = 'Ticket'
                              WHERE t.status IN (5,6) AND (" . implode(' OR ', $tConditions) . ")
                              ORDER BY t.id DESC LIMIT 4");
        if ($res) while ($row = $DB->fetchAssoc($res)) {
            $ticketResults[] = ['id'=>$row['id'], 'title'=>$row['name'], 'text'=>cleanText($row['solution'])];
        }
    }

   if (empty($kbResults) && empty($ticketResults)) {
    // Pas de contexte GLPI trouvé → on laisse l'IA répondre normalement,
    // en la prévenant qu'elle n'a pas d'info spécifique sur ce sujet
    $prompt = "Tu es un assistant pour les employés utilisant GLPI. "
            . "Aucune information spécifique n'a été trouvée dans la base de connaissances GLPI pour cette question. "
            . "Réponds du mieux que tu peux avec tes connaissances générales, dans la même langue que la question, "
            . "et précise à la fin que cette réponse n'est pas basée sur les données internes de l'entreprise.\n\n"
            . "Question : {$question}";

    $answer = callOllama($prompt) ?? "Erreur : impossible de contacter le modèle IA.";

    echo json_encode(['type' => 'search', 'answer' => trim($answer), 'sources' => []]);
    exit();
}

    $context = "";
    foreach ($kbResults as $r) $context .= "[Article KB #{$r['id']}] {$r['title']}\n{$r['text']}\n\n";
    foreach ($ticketResults as $r) $context .= "[Ticket résolu #{$r['id']}] {$r['title']}\nSolution : {$r['text']}\n\n";

    $prompt = "Tu es un assistant technique pour le support informatique GLPI. "
            . "Réponds à la question en te basant UNIQUEMENT sur les informations ci-dessous. "
            . "Si elles ne permettent pas de répondre, dis-le clairement. Réponds dans la même langue que la question posée, 5 lignes maximum.\n\n"
            . "=== Informations disponibles ===\n{$context}\n=== Question ===\n{$question}\n\nRéponse :";

    $answer = callOllama($prompt) ?? "Erreur : impossible de contacter le modèle IA.";

    echo json_encode([
        'type'    => 'search',
        'answer'  => trim($answer),
        'sources' => array_merge(
            array_map(function($r){ return ['type'=>'kb','id'=>$r['id'],'title'=>$r['title']]; }, $kbResults),
            array_map(function($r){ return ['type'=>'ticket','id'=>$r['id'],'title'=>$r['title']]; }, $ticketResults)
        )
    ]);
    exit();
}

// ==================================================================
// CRÉATION RÉELLE DU TICKET (après confirmation humaine uniquement)
// ==================================================================
if (isset($_POST['create_ticket'])) {
    header('Content-Type: application/json; charset=utf-8');

    // Sécurité : on vérifie simplement qu'on est bien dans une requête AJAX
    // envoyée depuis notre propre page (déjà protégée par Session::checkLoginUser())
    if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) && !isset($_POST['_glpi_csrf_token'])) {
        echo json_encode(['success' => false, 'error' => 'Invalid request']);
        exit();
    }

    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if ($title === '' || $description === '') {
        echo json_encode(['success' => false, 'error' => 'Missing title or description']);
        exit();
    }

    $ticket = new Ticket();
    $id = $ticket->add([
        'name'                 => $title,
        'content'              => $description,
        'entities_id'          => $_SESSION['glpiactive_entity'] ?? 0,
        '_users_id_requester'  => Session::getLoginUserID(),
        'status'               => 1, // New
    ]);

    if ($id) {
        echo json_encode(['success' => true, 'id' => $id]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Ticket creation failed']);
    }
    exit();
}

// ==================================================================
// AFFICHAGE DE LA PAGE
// ==================================================================
Html::header("AI Assistant", $_SERVER['PHP_SELF'], "tools", "pluginexportdata");
?>

<div class="center" style="max-width:750px;margin:auto;">
<h1><i class="fas fa-robot" style="color:#f8b83e;margin-right:10px;"></i>Assistant IA (local, hors ligne)</h1>
<p style="color:#888;font-size:13px;">
Je peux : rechercher une solution existante, donner des statistiques (ex: "combien de tickets ouverts ?"),
ou préparer un brouillon de ticket (ex: "créer un ticket pour mon imprimante").
</p>

<div id="chatBox" style="border:1px solid #ddd;border-radius:8px;height:420px;overflow-y:auto;padding:15px;background:#fafafa;margin-bottom:12px;"></div>

<div style="display:flex;gap:8px;">
    <input type="text" id="questionInput" placeholder="Pose ta question..." style="flex:1;padding:10px;border:1px solid #ccc;border-radius:6px;">
    <button type="button" id="sendBtn" class="btn btn-primary">Envoyer</button>
</div>
</div>

<?php echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken(false), 'id' => 'csrfTokenValue']); ?>

<script>
const chatBox = document.getElementById('chatBox');
const input = document.getElementById('questionInput');
const sendBtn = document.getElementById('sendBtn');
const csrfToken = document.getElementById('csrfTokenValue').value;

function addBubble(html, who) {
    const wrap = document.createElement('div');
    wrap.style.margin = '10px 0';
    wrap.style.display = 'flex';
    wrap.style.justifyContent = who === 'user' ? 'flex-end' : 'flex-start';

    const bubble = document.createElement('div');
    bubble.style.maxWidth = '80%';
    bubble.style.padding = '10px 14px';
    bubble.style.borderRadius = '10px';
    bubble.style.background = who === 'user' ? '#f8b83e' : '#fff';
    bubble.style.border = who === 'user' ? 'none' : '1px solid #ddd';
    bubble.style.whiteSpace = 'pre-wrap';
    bubble.innerHTML = html;

    wrap.appendChild(bubble);
    chatBox.appendChild(wrap);
    chatBox.scrollTop = chatBox.scrollHeight;
    return bubble;
}

function renderTicketDraft(bubble, draft) {
    bubble.innerHTML =
        '<div style="font-weight:600;margin-bottom:8px;">📝 Brouillon de ticket proposé :</div>'
      + '<label style="font-size:12px;color:#666;">Titre</label>'
      + '<input type="text" class="draftTitle" value="' + draft.title.replace(/"/g,'&quot;') + '" style="width:100%;padding:6px;margin-bottom:8px;border:1px solid #ccc;border-radius:4px;">'
      + '<label style="font-size:12px;color:#666;">Description</label>'
      + '<textarea class="draftDesc" rows="4" style="width:100%;padding:6px;margin-bottom:10px;border:1px solid #ccc;border-radius:4px;">' + draft.description + '</textarea>'
      + '<div>'
      +   '<button type="button" class="confirmBtn" style="background:#4caf50;color:#fff;border:none;padding:7px 14px;border-radius:5px;cursor:pointer;">✓ Confirmer et créer</button> '
      +   '<button type="button" class="cancelBtn" style="background:#ccc;border:none;padding:7px 14px;border-radius:5px;cursor:pointer;">Annuler</button>'
      + '</div>'
      + '<div class="ticketStatus" style="margin-top:8px;font-size:12px;"></div>';

    bubble.querySelector('.cancelBtn').addEventListener('click', function(){
        bubble.innerHTML = '<em>Création annulée.</em>';
    });

    bubble.querySelector('.confirmBtn').addEventListener('click', function(){
        const titleVal = bubble.querySelector('.draftTitle').value;
        const descVal = bubble.querySelector('.draftDesc').value;
        const statusEl = bubble.querySelector('.ticketStatus');
        statusEl.textContent = 'Création en cours...';

        const body = new URLSearchParams();
        body.append('create_ticket', '1');
        body.append('title', titleVal);
        body.append('description', descVal);
        body.append('_glpi_csrf_token', csrfToken);

        fetch('chat.php', { method: 'POST', body: body })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    statusEl.innerHTML = '✅ Ticket #' + data.id + ' créé avec succès.';
                    bubble.querySelector('.confirmBtn').disabled = true;
                    bubble.querySelector('.cancelBtn').style.display = 'none';
                } else {
                    statusEl.innerHTML = '❌ Erreur : ' + (data.error || 'inconnue');
                }
            });
    });
}

function sendQuestion() {
    const q = input.value.trim();
    if (!q) return;

    addBubble(q, 'user');
    input.value = '';
    const loadingBubble = addBubble('⏳ Réflexion en cours...', 'ai');

    fetch('chat.php?ask=1&question=' + encodeURIComponent(q))
        .then(r => r.json())
        .then(data => {
            if (data.type === 'ticket_draft') {
                renderTicketDraft(loadingBubble, data.draft);
                return;
            }
            let sourcesHtml = '';
            if (data.sources && data.sources.length > 0) {
                sourcesHtml = '<div style="margin-top:8px;font-size:11px;color:#888;border-top:1px solid #eee;padding-top:6px;">Sources : ';
                sourcesHtml += data.sources.map(s => (s.type === 'kb' ? '📘 KB' : '🎫 Ticket #' + s.id) + ' — ' + s.title).join(' · ');
                sourcesHtml += '</div>';
            }
            loadingBubble.innerHTML = data.answer.replace(/\n/g, '<br>') + sourcesHtml;
        })
        .catch(() => { loadingBubble.textContent = "Erreur de communication."; });
}

sendBtn.addEventListener('click', sendQuestion);
input.addEventListener('keypress', e => { if (e.key === 'Enter') sendQuestion(); });
</script>

<?php
Html::footer();
?>