<?php
// api_alumno.php
session_start();

$lang = $_SESSION['lang'] ?? 'ca';
$lang_file = __DIR__ . "/lang/{$lang}.json";
$translations = file_exists($lang_file) ? json_decode(file_get_contents($lang_file), true) : [];

function __api($key, $fallback) {
    global $translations;
    return $translations[$key] ?? $fallback;
}

header('Content-Type: application/json');

if (!isset($_SESSION['alumno_email'])) {
    echo json_encode(['error' => 'No autoritzat']);
    exit;
}

require_once __DIR__ . '/config/db.php'; // Assegura't que aquest fitxer defineix $dsn, $user, $password

try {
     $pdo = new PDO($dsn, $user, $password, [
         PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
         PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
     ]);
} catch (\PDOException $e) {
     echo json_encode([
        'success' => false,
        'error' => __api('connection_error', 'Error de conexión: ' . $e->getMessage())
        ]);
     exit;
}

$email = $_SESSION['alumno_email'];
$nombre = $_SESSION['alumno_nombre'];
$id_activitat = 1;
$accio = $_GET['accio'] ?? 'estat';

// --- ACCIÓ 1: OBTENIR ESTAT ---
// --- ACCIÓ 1: OBTENIR ESTAT (Corregida) ---
if ($accio === 'estat') {
    // 1. Primer de tot mirem l'estat general de la cua (Independent de l'alumne)
    $stmt_cua = $pdo->prepare("SELECT cola_abierta FROM RAs WHERE id = ?");
    $stmt_cua->execute([$id_activitat]);
    $cola_abierta = $stmt_cua->fetchColumn();

    // 2. Després mirem si l'alumne té un torn actiu
    $stmt = $pdo->prepare("SELECT * FROM turnos WHERE email_alumno = ? AND id_activitat = ? AND estado IN ('esperando', 'atendiendo') LIMIT 1");
    $stmt->execute([$email, $id_activitat]);
    $turno_actual = $stmt->fetch();

    // 3. Cas A: Si l'alumne NO està a la cua, enviem l'estat de 'cola_abierta' igualment!
    if (!$turno_actual) {
        echo json_encode([
            'success' => true,
            'en_cua' => false,
            'lang' => $_SESSION['lang'] ?? 'ca',
            'cola_abierta' => (int)$cola_abierta // 👈 Ara el JS sí que rebrà la dada per habilitar el botó!
        ]);
        exit;
    }

    // 4. Cas B: Si l'alumne SÍ que està a la cua, enviem tota la informació dels torns
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM turnos WHERE id_activitat = ? AND estado = 'esperando' AND posicion_cola < ?");
    $stmt->execute([$id_activitat, $turno_actual['posicion_cola']]);
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

// --- ACCIÓ 2: APUNTAR-SE (VERSIÓ COMODÍ DINÀMIC) ---
if ($accio === 'apuntarse' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 🟢 1. BRÚIXOLA: Busquem quin ID real té el teu primer registre a la taula RAs
    $stmt_id_real = $pdo->query("SELECT id FROM RAs LIMIT 1");
    $id_real_ra = $stmt_id_real->fetchColumn();

    // Si la taula RAs està completament buida, avisem de seguida
    if (!$id_real_ra) {
        echo json_encode([
            'success' => false,
             'error' => __api('empty_module_table', 'La taula RAs està buida a la base de dades. Insereix un mòdul abans.')
        ]);
        exit;
    }

    // A partir d'aquí utilitzem l'ID real que hem trobat a la BD, sigui el que sigui (1, 2, o un text)
    $activitat_id = $id_real_ra;

    // 2. Validar que la cua estigui oberta
    $stmt = $pdo->prepare("SELECT cola_abierta FROM RAs WHERE id = ?");
    $stmt->execute([$activitat_id]);
    if ($stmt->fetchColumn() == 0) {
        echo json_encode([
            'success' => false,
             'error' => __api('queue_closed', 'La cua està tancada pel professor')
        ]);
        exit;
    }

    // 3. Evitar duplicats actius
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM turnos WHERE email_alumno = ? AND id_activitat = ? AND estado IN ('esperando', 'atendiendo')");
    $stmt->execute([$email, $activitat_id]);
    if ($stmt->fetchColumn() > 0) {
         echo json_encode([
            'success' => false, 
            'error' => __api('already_in_queue', 'Ja estàs a la cua')
        ]);
         exit;
    }

    // 4. Generar el següent número de torn i posició
    $stmt = $pdo->prepare("SELECT MAX(turno_numero) FROM turnos WHERE id_activitat = ? AND DATE(fecha_registro) = CURDATE()");
    $stmt->execute([$activitat_id]);
    $ultim_torn = $stmt->fetchColumn() ?: 0;
    $nou_torn = $ultim_torn + 1;

    $stmt = $pdo->prepare("SELECT MAX(posicion_cola) FROM turnos WHERE id_activitat = ? AND estado = 'esperando'");
    $stmt->execute([$id_activitat]);
    $ultima_posicion = $stmt->fetchColumn() ?: 0;
    $nova_posicio = $ultima_posicion + 1;

    try {
        // 5. L'INSERT DINÀMIC SEGUR (Té 5 interrogants, tornant a posar el ? a id_activitat)
        $stmt = $pdo->prepare("
            INSERT INTO turnos (id_activitat, nombre_alumno, codigo_alumno, email_alumno, turno_numero, posicion_cola, estado, fecha_registro) 
            VALUES (?, ?, 'ALUMNE', ?, ?, ?, 'esperando', NOW())
        ");
        
        // Passem el valor real i exacte que hem llegit directament de la teva base de dades
        $stmt->execute([
            $id_activitat,  // 1r '?' -> El valor real detectat automàticament
            $nombre,         // 2n '?'
            $email,          // 3r '?'
            $nou_torn,       // 4t '?'
            $nova_posicio    // 5è '?'
        ]);

        echo json_encode(['success' => true]);
        exit;
        
    } catch (\PDOException $e) {
        echo json_encode([
            'success' => false,
            'error' => __api('db_error', 'Error a la BD: ' . $e->getMessage())
        ]);
        exit;
    }
}

// --- ACCIÓ 3: DESAPUNTAR-SE (CANCEL·LAR) ---
if ($accio === 'desapuntarse' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $pdo->prepare("UPDATE turnos SET estado = 'cancelado' WHERE email_alumno = ? AND id_activitat = ? AND estado = 'esperando'");
    $stmt->execute([$email, $id_activitat]);
    echo json_encode(['success' => true]);
}

