<?php
// api_alumno.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['alumno_email'])) {
    echo json_encode(['error' => 'No autoritzat']);
    exit;
}

// TODO: Requereix el teu fitxer de connexió PDO $pdo aquí.
// Per la prova ràpida re-aprofito la teva connexió corregida:
$host = 'db';
$db   = 'gestion_colas';
$user = 'root';
$pass = 'root';
#$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db";#;charset=$charset";

try {
     $pdo = new PDO($dsn, $user, $pass, [
         PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
         PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
     ]);
} catch (\PDOException $e) {
     echo json_encode(['error' => 'Error de conexión: ' . $e->getMessage()]);
     exit;
}


$email = $_SESSION['alumno_email'];
$nombre = $_SESSION['alumno_nombre'];
$asignatura_id = 1; 
$accio = $_GET['accio'] ?? 'estat';

// --- ACCIÓ 1: OBTENIR ESTAT ---
if ($accio === 'estat') {
    // Mirem si l'alumne té un torn actiu ('esperando' o 'atendiendo')
    $stmt = $pdo->prepare("SELECT * FROM turnos WHERE email_alumno = ? AND asignatura_id = ? AND estado IN ('esperando', 'atendiendo') LIMIT 1");
    $stmt->execute([$email, $asignatura_id]);
    $turno_actual = $stmt->fetch();
    $stmt_cua = $pdo->prepare("SELECT cola_abierta FROM asignaturas WHERE id = ?");
    $stmt_cua->execute([$asignatura_id]);
    $cola_abierta = $stmt_cua->fetchColumn();

    if (!$turno_actual) {
        echo json_encode(['en_cua' => false]);
        exit;
    }

    // Saber quants té al davant (basat en posicion_cola)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM turnos WHERE asignatura_id = ? AND estado = 'esperando' AND posicion_cola < ?");
    $stmt->execute([$asignatura_id, $turno_actual['posicion_cola']]);
    $alumnes_davant = $stmt->fetchColumn();

    // Calcular temps estimat d'espera (p.ex: Temps de la teva consulta de proyector multiplicat per alumnes_davant)
    // Suposem un de defecte de 7 minuts per alumne si no hi ha prou dades històriques.
    $temps_mig_unitari = 7; 
    $temps_estimat = $alumnes_davant * $temps_mig_unitari;

    echo json_encode([
        'en_cua' => true,
        'el_meu_torn' => $turno_actual['turno_numero'],
        'estat_actual' => $turno_actual['estado'],
        'alumnes_davant' => $alumnes_davant,
	'temps_estimat' => $temps_estimat,
	'success' => true,
        'cola_abierta' => (int)$cola_abierta, // 1 si està oberta, 0 si està tancada
    // ... la resta de dades que ja enviaves (turno_actual, el_meu_torn, posicion, etc.) ...
    ]);
}

// --- ACCIÓ 2: APUNTAR-SE ---
if ($accio === 'apuntarse' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Validar que la cua estigui oberta
    $stmt = $pdo->prepare("SELECT cola_abierta FROM asignaturas WHERE id = ?");
    $stmt->execute([$asignatura_id]);
    if ($stmt->fetchColumn() == 0) {
        echo json_encode(['success' => false, 'error' => 'La cua està tancada pel professor']);
        exit;
    }

    // 2. Evitar duplicats actius
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM turnos WHERE email_alumno = ? AND asignatura_id = ? AND estado IN ('esperando', 'atendiendo')");
    $stmt->execute([$email, $asignatura_id]);
    if ($stmt->fetchColumn() > 0) {
         echo json_encode(['success' => false, 'error' => 'Ja estàs a la cua']);
         exit;
    }

    // 3. Generar el següent número de torn i posició
    $stmt = $pdo->prepare("SELECT MAX(turno_numero) FROM turnos WHERE asignatura_id = ? AND DATE(fecha_registro) = CURDATE()");
    $stmt->execute([$asignatura_id]);
    $ultim_torn = $stmt->fetchColumn() ?: 0;
    $nou_torn = $ultim_torn + 1;

    $stmt = $pdo->prepare("SELECT MAX(posicion_cola) FROM turnos WHERE asignatura_id = ? AND estado = 'esperando'");
    $stmt->execute([$asignatura_id]);
    $ultima_posicion = $stmt->fetchColumn() ?: 0;
    $nova_posicio = $ultima_posicion + 1;

    // 4. Insertar a la base de dades
    $stmt = $pdo->prepare("INSERT INTO turnos (asignatura_id, nombre_alumno, codigo_alumno, email_alumno, turno_numero, posicion_cola, estado) VALUES (?, ?, 'ALUMNE', ?, ?, ?, 'esperando')");
    $stmt->execute([$asignatura_id, $nombre, $email, $nou_torn, $nova_posicio]);

    echo json_encode(['success' => true]);
}

// --- ACCIÓ 3: DESAPUNTAR-SE (CANCEL·LAR) ---
if ($accio === 'desapuntarse' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $pdo->prepare("UPDATE turnos SET estado = 'cancelado' WHERE email_alumno = ? AND asignatura_id = ? AND estado = 'esperando'");
    $stmt->execute([$email, $asignatura_id]);
    echo json_encode(['success' => true]);
}

