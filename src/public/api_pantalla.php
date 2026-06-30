<?php
// api_pantalla.php
header('Content-Type: application/json');

require_once __DIR__ . '/config/db.php'; // Assegura't que aquest fitxer defineix $dsn, $user, $password

try {
     $pdo = new PDO($dsn, $user, $password, [
         PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
         PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
     ]);
} catch (\PDOException $e) {
     echo json_encode(['error' => 'Error de conexión: ' . $e->getMessage()]);
     exit;
}

// Para este ejemplo, asumimos la asignatura ID = 1 (puedes dinamizarlo luego)
$id_activitat = 1;

// 2. Obtener nombre de la asignatura
$stmt = $pdo->prepare("SELECT CodiModul_RA FROM RAs WHERE id = ?");
$stmt->bindValue(1, $id_activitat, PDO::PARAM_INT);
$stmt->execute();
$asignatura = $stmt->fetchColumn() ?: "Sin Asignatura";

// 3. Turno actual (sirviendo)
$stmt = $pdo->prepare("SELECT turno_numero FROM turnos WHERE id_activitat = ? AND estado = 'atendiendo' LIMIT 1");
$stmt->execute([$id_activitat]);
$sirviendo = $stmt->fetchColumn() ?: "--";

// 4. Próximo turno (en espera, el primero de la cola según su posición)
$stmt = $pdo->prepare("SELECT turno_numero FROM turnos WHERE id_activitat = ? AND estado = 'esperando' ORDER BY posicion_cola ASC LIMIT 1");
$stmt->execute([$id_activitat]);
$proximo = $stmt->fetchColumn() ?: "--";

// 5. Calcular tiempo medio de espera hoy (en minutos)
// Promedio de la diferencia entre cuando se registró y cuando empezó a ser atendido
$stmt = $pdo->prepare("
    SELECT AVG(TIMESTAMPDIFF(MINUTE, fecha_registro, hora_inicio_atencion)) as t_medio 
    FROM turnos 
    WHERE id_activitat = ? 
      AND estado IN ('atendido', 'atendiendo') 
      AND DATE(fecha_registro) = CURDATE()
");
$stmt->execute([$id_activitat]);
$tiempo_medio_res = $stmt->fetch();
$tiempo_medio = isset($tiempo_medio_res['t_medio']) ? round($tiempo_medio_res['t_medio']) . " min" : "0 min";

// 6. Consultem si la cua està oberta o tancada
$stmt_cua = $pdo->prepare("SELECT cola_abierta FROM RAs WHERE id = ?");
$stmt_cua->execute([$id_activitat]);
$cola_abierta = $stmt_cua->fetchColumn();

// 7. Enviar respuesta como JSON
echo json_encode([
    'asignatura' => $asignatura,
    'sirviendo' => $sirviendo,
    'proximo' => $proximo,
    'tiempo_medio' => $tiempo_medio,
    'success' => true,
    'cola_abierta' => (int)$cola_abierta
]);

