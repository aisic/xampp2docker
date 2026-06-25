<?php
// api_gestion.php
session_start();

// Forcem a PHP a escriure els errors a la pantalla en lloc de callar-se
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'seguridad_profesor.php'; // Si un alumne crida l'API per AJAX, també queda registrat i bloquejat
header('Content-Type: application/json');

require_once __DIR__ . '/config/db.php'; // Assegura't que aquest fitxer defineix $dsn, $user, $password

try {
     $pdo = new PDO($dsn, $user, $password, [
         PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
         PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
         PDO::ATTR_EMULATE_PREPARES   => false, // 🛡️ Seguretat nativa extra contra SQLi
     ]);
} catch (\PDOException $e) {
     echo json_encode(['error' => 'Error de conexión: ' . $e->getMessage()]);
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

    // 2. Alumne actualment sota atenció (🟢 RETORNA TAMBÉ L'ID DEL TORN PEL JS)
    $stmt = $pdo->prepare("SELECT id, turno_numero, nombre_alumno FROM turnos WHERE asignatura_id = ? AND estado = 'atendiendo' LIMIT 1");
    $stmt->execute([$asignatura_id]);
    $atendiendo = $stmt->fetch() ?: ['id' => null, 'turno_numero' => '--', 'nombre_alumno' => 'Ningú'];

    // 3. Quants alumnes queden esperant a la cua
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM turnos WHERE asignatura_id = ? AND estado = 'esperando'");
    $stmt->execute([$asignatura_id]);
    $en_espera = $stmt->fetchColumn();

    // 4. Llista de la cua actual
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
if ($accio === 'siguiente' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Si s'ha esgotat el temps o el professor salta sense avaluar, marquem com a 'no_apte' i estat 'finalizado'
    $stmt = $pdo->prepare("
        UPDATE turnos 
        SET estado = 'finalizado', resultado_evaluacion = 'no_apte', hora_fin_atencion = NOW(), posicion_cola = 0 
        WHERE asignatura_id = ? AND estado = 'atendiendo' AND resultado_evaluacion IS NULL
    ");
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

// --- 🟢 NOVA ACCIÓ 4: FINALITZAR I DESAR EXAMEN COMPLET (SUBSTITUEIX MARCAR_RESULTAT) ---
if ($accio === 'finalitzar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $turno_id = intval($input['id'] ?? 0);
    $resultado = $input['resultado'] ?? ''; // 'apte' o 'no_apte'
    $pregunta = $input['pregunta'] ?? '';
    $respuesta = $input['respuesta'] ?? '';

    // Validació de seguretat estricta del format del resultat
    if (!in_array($resultado, ['apte', 'no_apte'])) {
        echo json_encode(['success' => false, 'error' => 'Resultat d\'avaluació no vàlid.']);
        exit;
    }

    if ($turno_id <= 0) {
        echo json_encode(['success' => false, 'error' => 'Identificador de torn incorrecte.']);
        exit;
    }

    try {
        // 🔒 CONSULTA PREPARADA TOTALMENT BLINDADA:
        // Desem els textos de la pregunta/resposta, el resultat, i passem l'estat a 'finalizado'
        $stmt = $pdo->prepare("
            UPDATE turnos 
            SET estado = 'atendido', 
                resultado_evaluacion = ?, 
                pregunta = ?, 
                respuesta = ?, 
                hora_fin_atencion = NOW(),
                posicion_cola = 0
            WHERE id = ? AND asignatura_id = ?
        ");
        $stmt->execute([$resultado, $pregunta, $respuesta, $turno_id, $asignatura_id]);

        echo json_encode(['success' => true]);
        exit;
        
    } catch (\PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Error a la BD: ' . $e->getMessage()]);
        exit;
    }
}