<?php
// api_alumno.php
session_start();

// ==========================================
// 🌐 CONFIGURACIÓ D'IDIOMA I CAPÇALERES
// ==========================================
$lang = $_SESSION['lang'] ?? 'ca';
$lang_file = __DIR__ . "/lang/{$lang}.json";
$translations = file_exists($lang_file) ? json_decode(file_get_contents($lang_file), true) : [];

/**
 * Tradueix una clau en l'entorn de l'API o retorna un text alternatiu
 */
function __api($key, $fallback) {
    global $translations;
    return $translations[$key] ?? $fallback;
}

// Forcem que tota sortida d'aquest fitxer sigui interpretada com a JSON
header('Content-Type: application/json');

// Control d'accés primari
if (!isset($_SESSION['alumno_email'])) {
    echo json_encode(['success' => false, 'error' => 'No autoritzat']);
    exit;
}

// ==========================================
// 🔌 CONNEXIÓ A LA BASE DE DADES
// ==========================================
require_once __DIR__ . '/config/db.php'; 

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

// ==========================================
// 🧑‍🎓 IDENTIFICACIÓ HISTÒRICA DE L'ALUMNE
// ==========================================
$email = $_SESSION['alumno_email'];
$accio = $_GET['accio'] ?? 'estat';

// Busquem el lligam de l'ID intern de l'alumne mitjançant el correu de la sessió
$stmt_alumne = $pdo->prepare("SELECT id_alumne FROM alumnes WHERE email = ? LIMIT 1");
$stmt_alumne->execute([$email]);
$id_alumne = $stmt_alumne->fetchColumn();

if (!$id_alumne) {
    echo json_encode([
        'success' => false,
        'error' => __api('student_not_found', 'L\'alumne no està registrat a la base de dades.')
    ]);
    exit;
}

// ==========================================
// 🔄 LECTURA DELS COSSOS DE PETICIÓ JSON (POST)
// ==========================================
// Llegim el flux d'entrada per si el JS ens envia paràmetres en el Body (com id_check_evaluacio)
$input = json_decode(file_get_contents('php://input'), true);

// ==========================================
// 🔍 ACCIÓ 1: OBTENIR ESTAT ACTUAL
// ==========================================
if ($accio === 'estat') {
    // 1. Mirem si la cua global es troba oberta o tancada (agafem el primer registre de RAs com a control de cua)
    $stmt_cua = $pdo->query("SELECT id, cola_abierta FROM RAs LIMIT 1");
    $ra_actiu = $stmt_cua->fetch();
    
    $id_activitat = $ra_actiu['id'] ?? 0;
    $cola_abierta = $ra_actiu['cola_abierta'] ?? 0;

    // 2. Busquem si aquest alumne té algun torn pendent o actiu
    $stmt = $pdo->prepare("
        SELECT t.* FROM turnos t
        WHERE t.id_alumne = ? AND t.estado IN ('esperando', 'atendiendo') 
        LIMIT 1
    ");
    $stmt->execute([$id_alumne]);
    $turno_actual = $stmt->fetch();

    // Cas A: L'alumne està lliure i no ha demanat tanda
    if (!$turno_actual) {
        echo json_encode([
            'success' => true,
            'en_cua' => false,
            'lang' => $lang,
            'cola_abierta' => (int)$cola_abierta
        ]);
        exit;
    }

    // Cas B: L'alumne ja està esperant a la cua. Calculem la seva posició i temps estimat
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM turnos 
        WHERE estado = 'esperando' AND turno_numero < ?
    ");
    $stmt->execute([$turno_actual['turno_numero']]);
    $alumnes_davant = $stmt->fetchColumn();

    $temps_mig_unitari = 7; // Temps estimat de correcció per check (en minuts)
    $temps_estimat = $alumnes_davant * $temps_mig_unitari;

    echo json_encode([
        'success' => true,
        'en_cua' => true,
        'lang' => $lang,
        'el_meu_torn' => $turno_actual['turno_numero'],
        'estat_actual' => $turno_actual['estado'],
        'alumnes_davant' => $alumnes_davant,
        'temps_estimat' => $temps_estimat,
        'cola_abierta' => (int)$cola_abierta
    ]);
    exit;
}

// ==========================================
// 📥 ACCIÓ 2: SOL·LICITAR TORN (DEMANAR_TURNO)
// ==========================================
// S'ha modificat per reaccionar a 'demanar_turno' tal com ho fa el teu nou alumno.js
if (($accio === 'apuntarse' || $accio === 'demanar_turno') && $_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. Validem si la cua general de l'aula està oberta
    $stmt_cua = $pdo->query("SELECT id, cola_abierta FROM RAs LIMIT 1");
    $ra_actiu = $stmt_cua->fetch();
    $id_activitat = $ra_actiu['id'] ?? 0;
    
    if (($ra_actiu['cola_abierta'] ?? 0) == 0) {
        echo json_encode([
            'success' => false,
            'error' => __api('queue_closed', 'La cua està tancada pel professor.')
        ]);
        exit;
    }

    // 2. Extraiem el check seleccionat de l'entrada JSON enviada pel JS
    $id_check_evaluacio = isset($input['id_check_evaluacio']) ? intval($input['id_check_evaluacio']) : 0;
    if (!$id_check_evaluacio) {
        echo json_encode([
            'success' => false,
            'error' => __api('no_check_selected', 'Siusplau, selecciona un criteri vàlid per avaluar.')
        ]);
        exit;
    }

    // 3. Evitem duplicats: Mirem si l'alumne ja té un torn obert
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM turnos WHERE id_alumne = ? AND estado IN ('esperando', 'atendiendo')");
    $stmt->execute([$id_alumne]);
    if ($stmt->fetchColumn() > 0) {
         echo json_encode([
            'success' => false, 
            'error' => __api('already_in_queue', 'Ja estàs a la cua d\'espera.')
        ]);
         exit;
    }

    // 4. Auto-incrementem el número de torn del dia d'avui
    $stmt = $pdo->query("SELECT MAX(turno_numero) FROM turnos WHERE DATE(fecha_registro) = CURDATE()");
    $ultim_torn = $stmt->fetchColumn() ?: 0;
    $nou_torn = $ultim_torn + 1;

    // 5. Determinem la darrera posició física real dins de la cua activa
    $stmt = $pdo->query("SELECT MAX(posicion_cola) FROM turnos WHERE estado = 'esperando'");
    $ultima_posicion = $stmt->fetchColumn() ?: 0;
    $nova_posicio = $ultima_posicion + 1;

try {
        // 5.5 Obtenim l'id_activitat automàticament a partir del check triat
        $stmt_act = $pdo->prepare("SELECT id_activitat_conceptual FROM checks_activitat WHERE id_check = ? LIMIT 1");
        $stmt_act->execute([$id_check_evaluacio]);
        $id_activitat = $stmt_act->fetchColumn();

        if (!$id_activitat) {
            echo json_encode([
                'success' => false,
                'error' => __api('activity_not_found', 'No s\'ha trobat l\'activitat associada a aquest criteri.')
            ]);
            exit;
        }

        // 6. Inserim el registre passant tant l'id_check com l'id_activitat trobat
        $stmt = $pdo->prepare("
            INSERT INTO turnos (id_alumne, id_activitat, id_check_evaluacio, turno_numero, posicion_cola, estado, fecha_registro) 
            VALUES (?, ?, ?, ?, ?, 'esperando', NOW())
        ");
        
        $stmt->execute([
            $id_alumne,     
            $id_activitat,  // Envia el camp que la teva BD demana obligatòriament
            $id_check_evaluacio,  
            $nou_torn,       
            $nova_posicio    
        ]);

        // Retornem l'èxit en un JSON perfecte cap al frontend (alumno.js)
        echo json_encode([
            'success' => true,
            'en_cua' => true,
            'el_meu_torn' => $nou_torn,
            'estat_actual' => 'esperando'
        ]);
        exit;
        
    } catch (\PDOException $e) {
        echo json_encode([
            'success' => false,
            'error' => __api('db_error', 'Error a la BD: ' . $e->getMessage())
        ]);
        exit;
    }
}

// ==========================================
// 🚪 ACCIÓ 3: DESAPUNTAR-SE DE LA CUA
// ==========================================
if ($accio === 'desapuntarse' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Passem el torn a estat 'cancelado' per alliberar la cua immediatament
    $stmt = $pdo->prepare("UPDATE turnos SET estado = 'cancelado' WHERE id_alumne = ? AND estado = 'esperando'");
    $stmt->execute([$id_alumne]);
    
    echo json_encode(['success' => true]);
    exit;
}

// Si es demana una acció no contemplada, retornem un avís controlat en lloc de deixar la pantalla en blanc
echo json_encode(['success' => false, 'error' => 'Acció no reconeguda']);
exit;