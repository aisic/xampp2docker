<?php
// api_gestion.php
session_start();

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'seguridad_profesor.php'; 
header('Content-Type: application/json');

require_once __DIR__ . '/config/db.php'; 

try {
     $pdo = new PDO($dsn, $user, $password, [
         PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
         PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
         PDO::ATTR_EMULATE_PREPARES   => false, 
     ]);
} catch (\PDOException $e) {
     echo json_encode(['error' => 'Error de conexión: ' . $e->getMessage()]);
     exit;
}

$id_activitat = 1; 
$accio = $_GET['accio'] ?? '';

// --- ACCIÓ 1: OBTENIR ESTAT ACTUAL DEL PANELL ---
if ($accio === 'estat') {
    $stmt = $pdo->prepare("
        SELECT 
            r.CodiModul_RA, 
            r.cola_abierta, 
            m.nom_modul 
        FROM RAs r
        INNER JOIN moduls m ON r.id_modul = m.id_modul
        WHERE r.id = ?
    ");
    $stmt->execute([$id_activitat]);
    $asignatura = $stmt->fetch();

    // 2. 🟢 CORREGIT: Alumne actual amb JOIN per obtenir el nom i cognoms directament
    $stmt = $pdo->prepare("
        SELECT t.id, t.turno_numero, t.id_alumne, CONCAT(a.nom_alumne, ' ', a.cognoms_alumne) as nombre_alumno 
        FROM turnos t
        INNER JOIN alumnes a ON t.id_alumne = a.id_alumne
        WHERE t.id_activitat = ? AND t.estado = 'atendiendo' 
        LIMIT 1
    ");
    $stmt->execute([$id_activitat]);
    $atendiendo = $stmt->fetch() ?: ['id' => null, 'turno_numero' => '--', 'id_alumne' => null, 'nombre_alumno' => 'Buscant...'];

    // 3. Quants alumnes queden esperant a la cua
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM turnos WHERE id_activitat = ? AND estado = 'esperando'");
    $stmt->execute([$id_activitat]);
    $en_espera = $stmt->fetchColumn();

    // 4. 🟢 CORREGIT: Llista de la cua actual amb JOIN per evitar l'undefined al JS
    $stmt = $pdo->prepare("
        SELECT t.id, t.turno_numero, CONCAT(a.nom_alumne, ' ', a.cognoms_alumne) as nombre_alumno 
        FROM turnos t
        INNER JOIN alumnes a ON t.id_alumne = a.id_alumne
        WHERE t.id_activitat = ? AND t.estado = 'esperando' 
        ORDER BY t.posicion_cola ASC
    ");
    $stmt->execute([$id_activitat]);
    $cua_llista = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'asignatura' => $asignatura['CodiModul_RA'] ?? '',
        'nom_modul' => $asignatura['nom_modul'] ?? '',
        'cola_abierta' => $asignatura['cola_abierta'] ?? 0,
        'atendiendo' => $atendiendo,
        'en_espera' => $en_espera,
        'cua_llista' => $cua_llista
    ]);
    exit;
}

// --- ACCIÓ 2: COMMUTAR CUA (OBRIR / TANCAR) ---
if ($accio === 'toggle_cua' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $nou_estat = $input['estat'] ? 1 : 0;

    $stmt = $pdo->prepare("UPDATE RAs SET cola_abierta = ? WHERE id = ?");
    $stmt->execute([$nou_estat, $id_activitat]);
    echo json_encode(['success' => true]);
    exit;
}

// --- ACCIÓ 3: CRIDAR SEGÜENT ALUMNE ---
if ($accio === 'siguiente' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // 🟢 CORREGIT: Canviat 'finalizado' per 'atendido' per lligar amb les estadístiques
    $stmt = $pdo->prepare("
        UPDATE turnos 
        SET estado = 'atendido', resultat_prova = 'no_apte', hora_fin_atencion = NOW(), posicion_cola = 0 
        WHERE id_activitat = ? AND estado = 'atendiendo'
    ");
    $stmt->execute([$id_activitat]);

    // Cridem al següent de la cua
    $stmt = $pdo->prepare("SELECT id FROM turnos WHERE id_activitat = ? AND estado = 'esperando' ORDER BY posicion_cola ASC LIMIT 1");
    $stmt->execute([$id_activitat]);
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

// --- ACCIÓ 4: FINALITZAR I DESAR EXAMEN COMPLET ---
if ($accio === 'finalitzar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $turno_id = intval($input['id'] ?? 0);
    $resultado = $input['resultado'] ?? ''; 
    $pregunta = $input['pregunta'] ?? '';
    $respuesta = $input['respuesta'] ?? '';

    if (!in_array($resultado, ['apte', 'no_apte'])) {
        echo json_encode(['success' => false, 'error' => 'Resultat d\'avaluació no vàlid.']);
        exit;
    }

    if ($turno_id <= 0) {
        echo json_encode(['success' => false, 'error' => 'Identificador de torn incorrecte.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("
            UPDATE turnos 
            SET estado = 'atendido', 
                resultat_prova = ?, 
                pregunta = ?, 
                respuesta = ?, 
                hora_fin_atencion = NOW(),
                posicion_cola = 0
            WHERE id = ? AND id_activitat = ?
        ");
        $stmt->execute([$resultado, $pregunta, $respuesta, $turno_id, $id_activitat]);

        echo json_encode(['success' => true]);
        exit;
        
    } catch (\PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Error a la BD: ' . $e->getMessage()]);
        exit;
    }
}

// Dins de api_gestion.php, afegeix aquestes accions al teu switch/if de $_GET['accio']

if ($accio === 'obtenir_activitats_eval') {
    // Retorna les activitats conceptuals apuntant al seu RA respectiu
    $stmt = $pdo->query("SELECT a.id_activitat_conceptual, a.nom_activitat, r.CodiModul_RA FROM activitats_ra a INNER JOIN RAs r ON a.id_ra = r.id");
    echo json_encode(['success' => true, 'activitats' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    exit;
}

if ($accio === 'obtenir_checks') {
    $id_act = intval($_GET['id_act'] ?? 0);
    $stmt = $pdo->prepare("SELECT id_check, titol_check FROM checks_activitat WHERE id_activitat_conceptual = ?");
    $stmt->execute([$id_act]);
    echo json_encode(['success' => true, 'checks' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    exit;
}

if ($accio === 'finalitzar_amb_checks' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $id_turno = intval($input['id_turno'] ?? 0);
    $id_activitat = intval($input['id_activitat'] ?? 0);
    $pregunta = $input['pregunta'] ?? '';
    $respuesta = $input['respuesta'] ?? '';
    $checks = $input['checks'] ?? [];

    try {
        $pdo->beginTransaction();

        // 1. Obtenir l'id del alumne d'aquest torn concret per poder guardar-li la seva nota
        $stmt_t = $pdo->prepare("SELECT id_alumne FROM turnos WHERE id = ?");
        $stmt_t->execute([$id_turno]);
        $id_alumne = $stmt_t->fetchColumn();

        if(!$id_alumne) { throw new Exception("No s'ha trobat l'alumne associat al torn."); }

        // 2. Processar cada un dels checks enviats pel formulari
        foreach ($checks as $chk) {
            $id_check = intval($chk['id_check']);
            $completat = intval($chk['completat']);
            
            // 📊 Càlcul del Pes de Degradació segons el nombre d'alumnes que s'han avaluat d'aquest check ABANS
            $stmt_count = $pdo->prepare("SELECT COUNT(*) FROM notes_checks_alumne WHERE id_check = ? AND completat = 1");
            $stmt_count->execute([$id_check]);
            $alumnes_avaluats_abans = intval($stmt_count->fetchColumn());

            // Executem la regla dels grups de 5 (100%, 90%, 80%, 70%, 60%, 50%)
            if ($alumnes_avaluats_abans < 5)        $pct = 100;
            else if ($alumnes_avaluats_abans < 10)  $pct = 90;
            else if ($alumnes_avaluats_abans < 15)  $pct = 80;
            else if ($alumnes_avaluats_abans < 20)  $pct = 70;
            else if ($alumnes_avaluats_abans < 25)  $pct = 60;
            else                                    $pct = 50;

            // Inserim o actualitzem la nota del check de l'alumne
            $stmt_ins = $pdo->prepare("
                INSERT INTO notes_checks_alumne (id_alumne, id_check, completat, percentatge_aplicat) 
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE completat = VALUES(completat), percentatge_aplicat = VALUES(percentatge_aplicat)
            ");
            $stmt_ins->execute([$id_alumne, $id_check, $completat, $pct]);
        }

        // 3. Finalitzem el torn com a atès de manera tradicional i salvem les observacions
        $stmt_f = $pdo->prepare("UPDATE turnos SET estado = 'atendido', evaluacion = 'atendido' WHERE id = ?");
        $stmt_f->execute([$id_turno]);

        $pdo->commit();
        echo json_encode(['success' => true]);

    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if ($accio === 'finalitzar_no_apte' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $id_turno = intval($input['id_turno'] ?? 0);

    // Passem el torn a "atendido" però marquem a l'avaluació interna del registre que ha estat un "no_apte"
    $stmt = $pdo->prepare("UPDATE turnos SET estado = 'atendido', evaluacion = 'no_apte' WHERE id = ?");
    $stmt->execute([$id_turno]);

    echo json_encode(['success' => true]);
    exit;
}