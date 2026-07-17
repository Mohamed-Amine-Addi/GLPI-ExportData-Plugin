<?php

include('../../../inc/includes.php');
Session::checkLoginUser();

Html::header("AI Test", $_SERVER['PHP_SELF'], "tools", "pluginexportdata");

$response = null;
$error = null;

if (isset($_POST['ask'])) {
    $question = $_POST['question'];

    $payload = json_encode([
        'model'  => 'llama3.2:1b',
        'prompt' => $question,
        'stream' => false
    ]);

    $ch = curl_init('http://localhost:11434/api/generate');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);

    $result = curl_exec($ch);

    if (curl_errno($ch)) {
        $error = curl_error($ch);
    } else {
        $data = json_decode($result, true);
        $response = $data['response'] ?? 'No response field found';
    }

    curl_close($ch);
}
?>

<div class="center" style="max-width:700px;margin:auto;">
    <h1>Test de connexion à Ollama</h1>

    <form method="post" action="">
        <textarea name="question" rows="3" style="width:100%;" placeholder="Pose une question..."><?php echo htmlspecialchars($_POST['question'] ?? ''); ?></textarea>
        <br><br>
        <button type="submit" name="ask" class="btn btn-primary">Demander à l'IA</button>
        <?php echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken(false)]); ?>
    </form>

    <?php if ($error): ?>
        <div style="margin-top:20px;padding:12px;background:#fee;border:1px solid #c33;border-radius:6px;">
            <strong>Erreur :</strong> <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <?php if ($response): ?>
        <div style="margin-top:20px;padding:12px;background:#eef;border:1px solid #33c;border-radius:6px;">
            <strong>Réponse de l'IA :</strong><br>
            <?php echo nl2br(htmlspecialchars($response)); ?>
        </div>
    <?php endif; ?>
</div>

<?php
Html::footer();
?>