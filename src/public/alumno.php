<?php
session_start();

// Guardrail de seguretat: Si no hi ha sessió de Google activa, es redirigeix al login
if (!isset($_SESSION['alumno_email'])) {
    header("Location: login.php");
    exit;
}

// ==========================================
// 🌐 INTERNACIONALITZACIÓ (IDIOMES)
// ==========================================
$allowed_langs = ['ca', 'es', 'en']; 
$lang = $_SESSION['lang'] ?? 'ca';   

if (isset($_GET['lang']) && in_array($_GET['lang'], $allowed_langs)) {
    $lang = $_GET['lang'];
    $_SESSION['lang'] = $lang;
}

// Carreguem el diccionari de traduccions corresponent
$lang_file = __DIR__ . "/lang/{$lang}.json";
$translations = [];
if (file_exists($lang_file)) {
    $translations = json_decode(file_get_contents($lang_file), true);
}

/**
 * Retorna la traducció d'una clau o un text alternatiu si no existeix
 */
function __($key, $fallback = '') {
    global $translations;
    return $translations[$key] ?? $fallback ?: $key;
}

// Assignatura dinàmica (en un futur vindrà heretada de la teva base de dades)
$asignatura_nombre = "C037_RA1"; 
?>

<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('page_title', "Cua d'Alumnes - El meu Torn") ?></title>
    <link href="css/alumno.css" rel="stylesheet">
    <script src="js/alumno.js" defer></script>
</head>

<body>

<div class="container">
    
    <div class="lang-selector" style="text-align: right; margin-bottom: 10px;">
        <form method="GET" action="" style="display: inline-block;">
            <select name="lang" onchange="this.form.submit()" style="padding: 5px; border-radius: 4px; cursor: pointer;">
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
        
        <form id="form-demanar-torn" class="form-torn" style="margin-top: 20px; text-align: left;">
            
            <div class="form-group" style="margin-bottom: 15px;">
                <label for="alum-modulo" style="display: block; font-weight: bold; margin-bottom: 5px;"><?= __('label_modulo', 'Mòdul:') ?></label>
                <select id="alum-modulo" required style="width: 100%;">
                    </select>
            </div>

            <div class="form-group" style="margin-bottom: 15px;">
                <label for="alum-ra" style="display: block; font-weight: bold; margin-bottom: 5px;"><?= __('label_ra', "Resultat d'Aprenentatge (RA):") ?></label>
                <select id="alum-ra" disabled required style="width: 100%;">
                    <option value=""><?= __('select_modulo_first', 'Primer tria un mòdul...') ?></option>
                </select>
            </div>

            <div class="form-group" style="margin-bottom: 15px;">
                <label for="alum-activitat" style="display: block; font-weight: bold; margin-bottom: 5px;"><?= __('label_activitat', 'Activitat:') ?></label>
                <select id="alum-activitat" disabled required style="width: 100%;">
                    <option value=""><?= __('select_ra_first', 'Primer tria un RA...') ?></option>
                </select>
            </div>

            <div class="form-group" style="margin-bottom: 20px;">
                <label for="alum-check" style="display: block; font-weight: bold; margin-bottom: 5px;"><?= __('label_check', 'Criteri / Check a avaluar:') ?></label>
                <select id="alum-check" name="id_check_evaluacio" disabled required style="width: 100%;">
                    <option value=""><?= __('select_activity_first', 'Primer tria una activitat...') ?></option>
                </select>
            </div>

            <button type="submit" id="apuntarse-btn" class="btn btn-primary" style="width: 100%;">
                <?= __('btn_join') ?>
            </button>
        </form>
    </div>

    <div id="seccio-espera" class="hidden">
        <div class="status-box">
            <p id="text-estat-torn"><?= __('your_turn_is') ?></p>
            <div class="big-number" id="el-meu-torn">--</div>
        </div>

        <button id="desapuntarse-btn" class="btn btn-danger" style="width: 100%; margin-top: 15px;">
            <?= __('btn_leave') ?>
        </button>
    </div>
</div>

</body>
</html>