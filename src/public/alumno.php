<?php
session_start();

// var_dump($_SESSION);
// Si no està loguejat amb Google, el podríes redirigir aquí al login
if (!isset($_SESSION['alumno_email'])) {
    header("Location: login.php");
    exit;
}

// 🌐 LÒGICA D'IDIOMA
$allowed_langs = ['ca', 'es', 'en']; // Llista d'idiomes fàcilment ampliable
$lang = $_SESSION['lang'] ?? 'ca';   // Idioma per defecte

if (isset($_GET['lang']) && in_array($_GET['lang'], $allowed_langs)) {
    $lang = $_GET['lang'];
    $_SESSION['lang'] = $lang;
}

// Carreguem la taula de llenguatge des del JSON corresponent
$lang_file = __DIR__ . "/lang/{$lang}.json";
$translations = [];
if (file_exists($lang_file)) {
    $translations = json_decode(file_get_contents($lang_file), true);
}

// Funció còmoda per traduir a la vista de PHP
function __($key, $fallback = '') {
    global $translations;
    return $translations[$key] ?? $fallback ?: $key;
}

$asignatura_nombre = "C037_RA1"; // Això vindrà de la teva BD
?>

<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cua d'Alumnes - El meu Torn</title>
    <link href="css/alumno.css" rel="stylesheet">
    <script src="js/alumno.js" defer></script>
</head>

<body>

<div class="container">
    <div class="lang-selector" style="text-align: right; margin-bottom: 10px;">
        <form method="GET" action="" style="display: inline-block;">
            <select name="lang" onchange="this.form.submit()" style="padding: 5px; border-radius: 4px;">
                <option value="ca" <?= $lang === 'ca' ? 'selected' : '' ?>>Català</option>
                <option value="es" <?= $lang === 'es' ? 'selected' : '' ?>>Castellano</option>
                <option value="en" <?= $lang === 'en' ? 'selected' : '' ?>>English</option>
            </select>
        </form>
    </div>

    <h1><?= htmlspecialchars($asignatura_nombre) ?></h1>
    
   <div class="user-info">
        <span><?= __('connected_as') ?> <strong><?= htmlspecialchars($_SESSION['alumno_nombre']) ?></strong></span>
        <a href="logout.php" class="logout-btn"><?= __('logout') ?></a>
    </div>

    <div class="status-container">
        <p class="status-label"><?= __('queue_status') ?></p>
        <div id="estat-cua-contenidor" class="estat-cua-box">
            <span id="estat-cua-text"><?= __('checking_status') ?></span>
        </div>
        <ul>
            <li><?= __('students_ahead') ?> <strong id="alumnes-davant">--</strong></li>
            <li><?= __('estimated_time') ?> <strong id="temps-estimat">-- <?= __('minutes') ?></strong></li>
        </ul>
    </div>

    <div id="seccio-apuntarse" class="hidden">
        <p class="info-text"><?= __('not_in_queue') ?></p>
        <button id="apuntarse-btn" class="btn btn-primary"><?= __('btn_join') ?></button>
    </div>

    <div id="seccio-espera" class="hidden">
        <div class="status-box">
            <p id="text-estat-torn"><?= __('your_turn_is') ?></p>
            <div class="big-number" id="el-meu-torn">--</div>
        </div>

        <button id="desapuntarse-btn" class="btn btn-danger"><?= __('btn_leave') ?></button>
    </div>
</div>

</body>
</html>