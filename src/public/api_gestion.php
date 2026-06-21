<?php
// api_gestion.php
session_start();
require_once 'seguridad_profesor.php'; // Si un alumne crida l'API per AJAX, també queda registrat i bloquejat
header('Content-Type: application/json');

// NOTA: Per producció aquí hauries de validar si l'usuari és el professor
// p.ex. if ($_SESSION['alumno_email'] !== 'professor@centre.cat') { exit('No autoritzat'); }

$host = 'db';
$port = '3306'; // El teu port de MariaDB
$db   = 'gestion_colas';
$user = 'root';
$pass = 'root'; // La teva contrasenya

try {
    $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
} catch (\PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}

$asignatura_id = 1; // ID de l'assignatura per defecte
$accio = $_GET['accio'] ?? '';

// --- ACCIÓ 1: OBTENIR ESTAT ACTUAL DEL PANNELL ---
if ($accio === 'estat') {
    // 1. Estat de la cua (Oberta/Tancada)
    $stmt = $pdo->prepare("SELECT nombre, cola_abierta FROM asignaturas WHERE id = ?");
    $stmt->execute([$asignatura_id]);
    $asignatura = $stmt->fetch();

    // 2. Alumne actualment sota atenció
    $stmt = $pdo->prepare("SELECT turno_numero, nombre_alumno FROM turnos WHERE asignatura_id = ? AND estado = 'atendiendo' LIMIT 1");
    $stmt->execute([$asignatura_id]);
    $atendiendo = $stmt->fetch() ?: ['turno_numero' => '--', 'nombre_alumno' => 'Ningú'];

    // 3. Quants alumnes queden esperant a la cua
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM turnos WHERE asignatura_id = ? AND estado = 'esperando'");
    $stmt->execute([$asignatura_id]);
    $en_espera = $stmt->fetchColumn();

    // 4. Llista de la cua actual (per si es vol veure qui ve a continuació)
    $stmt = $pdo->prepare("SELECT id, turno_numero, nombre_alumno FROM turnos WHERE asignatura_id = ? AND estado = 'esperando' ORDER BY posicion_cola ASC");
    $stmt->execute([$asignatura_id]);
    $cua_llista = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'asignatura' => $asignatura['nombre'],
        'cola_abierta' => $asignatura['cola_abierta'],
        'atendiendo' => $atendiendo,
        'en_espera' => $en_espera,
        'cua_llista' => $cua_llista
    ]);
}

// --- ACCIÓ 2: COMMUTAR CUA (OBRIR / TANCAR) ---
if ($accio === 'toggle_cua' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $nou_estat = $input['estat'] ? 1 : 0;

    $stmt = $pdo->prepare("UPDATE asignaturas SET cola_abierta = ? WHERE id = ?");
    $stmt->execute([$nou_estat, $asignatura_id]);
    echo json_encode(['success' => true]);
}

// --- ACCIÓ 3: CRIDAR SEGÜENT ALUMNE ---
// --- MODIFICACIÓ DE L'ACCIÓ 'siguiente' ---
if ($accio === 'siguiente' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Si hi havia un alumne "atendiendo" i el professor prem següent sense avaluar-lo, 
    // significa que s'ha esgotat el temps de 20 segons o no ha vingut.
    $stmt = $pdo->prepare("
        UPDATE turnos 
        SET estado = 'cancelado', resultat_prova = 'no_apte', hora_fin_atencion = NOW() 
        WHERE asignatura_id = ? AND estado = 'atendiendo' AND resultat_prova = 'pendent'
    ");
    $stmt->execute([$asignatura_id]);

    // També tanquem correctament els que sí s'havien avaluat
    $stmt = $pdo->prepare("UPDATE turnos SET estado = 'atendido', hora_fin_atencion = NOW() WHERE asignatura_id = ? AND estado = 'atendiendo'");
    $stmt->execute([$asignatura_id]);

    // Cridem al següent de la cua
    $stmt = $pdo->prepare("SELECT id FROM turnos WHERE asignatura_id = ? AND estado = 'esperando' ORDER BY posicion_cola ASC LIMIT 1");
    $stmt->execute([$asignatura_id]);
    $proxim_id = $stmt->fetchColumn();

    if ($proxim_id) {
        $stmt = $pdo->prepare("UPDATE turnos SET estado = 'atendiendo', hora_inicio_atencion = NOW() WHERE id = ?");
        $stmt->execute([$proxim_id]);
        echo json_encode(['success' => true, 'quedaven_alumnes' => true, 'hora_inici' => date('Y-m-d H:i:s')]);
    } else {
        echo json_encode(['success' => true, 'quedaven_alumnes' => false]);
    }
    exit;
}

// --- NOVA ACCIÓ 4: MARCAR RESULTAT TEST ---
if ($accio === 'marcar_resultat' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $resultat = $input['resultat'] ?? ''; // 'apte' o 'no_apte'

    if (!in_array($resultat, ['apte', 'no_apte'])) {
        echo json_encode(['success' => false, 'error' => 'Resultat no vàlid']);
        exit;
    }

    // Guardem el resultat i donem el torn per finalitzat
    $stmt = $pdo->prepare("
        UPDATE turnos 
        SET resultat_prova = ?, estado = 'atendido', hora_fin_atencion = NOW() 
        WHERE asignatura_id = ? AND estado = 'atendiendo'
    ");
    $stmt->execute([$resultat, $asignatura_id]);

    echo json_encode(['success' => true]);
    exit;
}

