<?php
// 1. 🔐 BLINDATGE DE SEGURETAT: Només el professor pot carregar aquesta pàgina
require_once 'seguridad_profesor.php';

// 3. Connectem a la base de dades de manera segura
require_once __DIR__ . '/config/db.php';

try {
    $pdo_seguridad = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false, // Seguretat extra contra SQLi
    ]);
} catch (\PDOException $e) {
    echo json_encode(['error' => 'Error de connexió de seguretat']);
    exit;
}

try {
    $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
} catch (\PDOException $e) {
    die("Error de connexió a la BD: " . $e->getMessage());
}

// 2. 🟢 CORREGIT: OBTENIR LLISTA D'ALUMNES FENT JOIN AMB LA TAULA ALUMNES
$stmt_alumnos = $pdo->query("
    SELECT DISTINCT a.email as email_alumno, CONCAT(a.nom_alumne, ' ', a.cognoms_alumne) as nombre_alumno 
    FROM turnos t
    INNER JOIN alumnes a ON t.id_alumne = a.id_alumne
    ORDER BY nombre_alumno ASC
");
$alumnos = $stmt_alumnos->fetchAll();

// 3. RECOLLIR REQUISITS DELS FILTRES (SI S'HAN SELECCIONAT)
$filtro_alumno = $_GET['filtro_alumno'] ?? '';
$filtro_fecha = $_GET['filtro_fecha'] ?? '';

// Variables on guardarem els resultats dels indicadors
$stats = [
    'vegades_ates' => 0,
    'temps_espera_mig' => 0,
    'temps_atencion_mig' => 0,
    'aptes' => 0,
    'no_aptes' => 0,
    'pct_apte' => 0,
    'pct_no_apte' => 0
];

// 4. LÒGICA SQL: NOMÉS CALCULEM SI S'HA SELECCIONAT UN ALUMNE O UN DIA
if ($filtro_alumno || $filtro_fecha) {
    
    // Construïm la consulta dinàmica segons els filtres aplicats
    $where_clauses = ["t.estado = 'atendido'"];
    $params = [];
    
    if ($filtro_alumno) {
        $where_clauses[] = "a.email = ?";
        $params[] = $filtro_alumno;
    }
    if ($filtro_fecha) {
        $where_clauses[] = "DATE(t.fecha_registro) = ?";
        $params[] = $filtro_fecha;
    }
    
    $where_str = implode(" AND ", $where_clauses);
    
    // SQL complexa utilitzant funcions de temps (TIMESTAMPDIFF en segons)
    $sql = "SELECT 
                COUNT(*) as total_ates,
                AVG(TIMESTAMPDIFF(SECOND, t.fecha_registro, t.hora_inicio_atencion)) as espera_mitjana,
                AVG(TIMESTAMPDIFF(SECOND, t.hora_inicio_atencion, t.hora_fin_atencion)) as atencio_mitjana,
                SUM(CASE WHEN t.resultat_prova = 'apte' THEN 1 ELSE 0 END) as total_aptes,
                SUM(CASE WHEN t.resultat_prova = 'no_apte' THEN 1 ELSE 0 END) as total_no_aptes
            FROM turnos t
            INNER JOIN alumnes a ON t.id_alumne = a.id_alumne
            WHERE $where_str";
            
    $stmt_calc = $pdo->prepare($sql);
    $stmt_calc->execute($params);
    $res = $stmt_calc->fetch();

    $tornis_detall = [];
    if ($res && $res['total_ates'] > 0) {
        // 🔍 NOVA CONSULTA: Obtenir el llistat individual d'aquests torns
        $sql_detall = "SELECT 
                        t.turno_numero, 
                        t.estado, 
                        t.resultat_prova,
                        t.fecha_registro,
                        TIMESTAMPDIFF(SECOND, t.fecha_registro, t.hora_inicio_atencion) as segons_espera,
                        TIMESTAMPDIFF(SECOND, t.hora_inicio_atencion, t.hora_fin_atencion) as segons_atencio
                       FROM turnos t
                       INNER JOIN alumnes a ON t.id_alumne = a.id_alumne
                       WHERE $where_str 
                       ORDER BY t.fecha_registro DESC";
                       
        $stmt_detall = $pdo->prepare($sql_detall);
        $stmt_detall->execute($params);
        $tornis_detall = $stmt_detall->fetchAll();

        $stats['vegades_ates'] = $res['total_ates'];
        $total_avaluats = $res['total_aptes'] + $res['total_no_aptes'];
        
        // Passem els segons mitjans a minuts arrodonits a 1 decimal
        $stats['temps_espera_mig'] = round(($res['espera_mitjana'] ?? 0) / 60, 1);
        $stats['temps_atencion_mig'] = round(($res['atencio_mitjana'] ?? 0) / 60, 1);
        $stats['aptes'] = $res['total_aptes'];
        $stats['no_aptes'] = $res['total_no_aptes'];
        
        if ($total_avaluats > 0) {
            $stats['pct_apte'] = round(($stats['aptes'] / $total_avaluats) * 100, 1);
            $stats['pct_no_apte'] = round(($stats['no_aptes'] / $total_avaluats) * 100, 1);
        } else {
            $stats['pct_apte'] = 0;
            $stats['pct_no_apte'] = 0;
        }
    }
}

// 5. CONSULTA D'AUDITORIA: HISTORIAL D'INCIDÈNCIES (ALUMNES COL·LATS)
$sql_incidencias = "SELECT email_infractor, nombre_infractor, fecha_incidencia, ip_origen 
                    FROM incidencias_acceso 
                    ORDER BY fecha_incidencia DESC";
$incidencias = $pdo->query($sql_incidencias)->fetchAll();
?>
<!DOCTYPE html>
<html lang="ca">
<head>
    <meta charset="UTF-8">
    <title>Mòdul d'Estadístiques i Seguretat</title>
    <link href="css/gestion.css" rel="stylesheet">
</head>
<body>

<div class="container">
    <header>
        <div>
            <h1>📊 Panell d'Estadístiques Docents</h1>
            <p style="font-size: 0.85rem; color:#64748b;">Mètriques de rendiment de les cues i auditoria de seguretat</p>
        </div>
        <a href="gestion.php" class="back-btn">⬅️ Tornar a Gestió</a>
    </header>

    <form method="GET" action="estadisticas.php" class="filter-card">
        <div class="form-group">
            <label for="filtro_alumno">Selecciona Alumne</label>
            <select name="filtro_alumno" id="filtro_alumno">
                <option value="">-- Tots els Alumnes --</option>
                <?php foreach($alumnos as $al): ?>
                    <option value="<?= htmlspecialchars($al['email_alumno']) ?>" <?= $filtro_alumno === $al['email_alumno'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($al['nombre_alumno']) ?> (<?= htmlspecialchars($al['email_alumno']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="filtro_fecha">Selecciona Dia / Data</label>
            <input type="date" name="filtro_fecha" id="filtro_fecha" value="<?= htmlspecialchars($filtro_fecha) ?>">
        </div>

        <button type="submit" class="submit-btn">🔍 Filtrar</button>
    </form>

    <?php if (!$filtro_alumno && !$filtro_fecha): ?>
        <p style="text-align: center; color: #64748b; padding: 30px; background: white; border-radius: 12px; margin-bottom: 40px; box-shadow: 0 2px 4px rgba(0,0,0,0.01);">
            💡 Tria un alumne o una data al formulari superior per processar i visualitzar les dades de rendiment.
        </p>
    <?php else: ?>
        <div class="grid-kpis">
            <div class="kpi">
                <div class="kpi-title">Vegades atès</div>
                <div class="kpi-value"><?= $stats['vegades_ates'] ?></div>
            </div>
            <div class="kpi" style="border-top-color: #f59e0b;">
                <div class="kpi-title">Temps d'espera mig</div>
                <div class="kpi-value"><?= $stats['temps_espera_mig'] ?> <span style="font-size:1rem;color:#64748b;">min</span></div>
            </div>
            <div class="kpi" style="border-top-color: #10b981;">
                <div class="kpi-title">Temps d'atenció mig</div>
                <div class="kpi-value"><?= $stats['temps_atencion_mig'] ?> <span style="font-size:1rem;color:#64748b;">min</span></div>
            </div>
            <div class="kpi" style="border-top-color: #6366f1;">
                <div class="kpi-title">Total Aptes / Test</div>
                <div class="kpi-value" style="color: #10b981;"><?= $stats['aptes'] ?> <span style="font-size:1rem;color:#64748b;">de <?= $stats['vegades_ates'] ?></span></div>
            </div>
        </div>

        <div class="test-box" style="display: flex; align-items: center; background: white; padding: 20px; border-radius: 12px; margin-bottom: 30px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
            <div style="flex: 0 0 220px;">
                <h3 style="font-size:1rem; color:#334155; margin: 0 0 5px 0;">Resultat dels Tests</h3>
                <p style="font-size:0.85rem; color:#64748b; margin: 0;">Distribució de les avaluacions fetes.</p>
            </div>
            <div class="test-progress" style="flex-grow: 1; display: flex; height: 24px; background: #e2e8f0; border-radius: 6px; overflow: hidden; align-items: center; font-size: 0.8rem; font-weight: bold; color: white;">
                <div class="progress-apte" style="width: <?= $stats['pct_apte'] ?>%; background: #10b981; height: 100%; display: flex; align-items: center; padding-left: 10px;">
                    <?php if($stats['aptes'] > 0): ?> APTE (<?= $stats['pct_apte'] ?>%) <?php endif; ?>
                </div>
                <?php if($stats['no_aptes'] > 0): ?> 
                    <div class="progress-no-apte" style="width: <?= $stats['pct_no_apte'] ?>%; background: #ef4444; height: 100%; display: flex; align-items: center; justify-content: flex-end; padding-right: 10px; margin-left: auto;">
                        NO APTE (<?= $stats['pct_no_apte'] ?>%)
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="detall-section" style="background: white; padding: 24px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); margin-bottom: 40px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <div>
                    <h2 class="detall-title" style="font-size: 1.25rem; color: #1e293b; margin: 0;">📋 Registre de Torns Detallat</h2>
                    <p style="font-size: 0.85rem; color: #64748b; margin: 5px 0 0 0;">
                        Aquests són els torns individuals utilitzats per generar els indicadors superiors:
                    </p>
                </div>
                <?php if (count($tornis_detall) > 0): ?>
                    <button onclick="descarregarCSV()" style="background-color: #10b981; color: white; border: none; padding: 8px 15px; border-radius: 6px; font-weight: bold; cursor: pointer; font-size: 0.85rem; display: flex; align-items: center; gap: 5px;">
                        📥 Descarregar CSV
                    </button>
                <?php endif; ?>
            </div>

            <table id="taula-detall" style="width: 100%; border-collapse: collapse; margin-top: 10px;">
                <thead>
                    <tr style="background: #f8fafc; color: #475569; border-bottom: 2px solid #e2e8f0; text-align: left;">
                        <th style="padding: 12px;">Torn</th>
                        <th style="padding: 12px;">Data i Hora Sol·licitud</th>
                        <th style="padding: 12px;">Temps Espera</th>
                        <th style="padding: 12px;">Temps Atenció</th>
                        <th style="padding: 12px;">Estat Final</th>
                        <th style="padding: 12px;">Resultat Test</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($tornis_detall) === 0): ?>
                        <tr>
                            <td colspan="6" style="padding: 20px; text-align: center; color: #64748b;">No s'han trobat torns registrats per aquests filtres.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($tornis_detall as $torn): 
                            $min_espera = $torn['segons_espera'] ? round($torn['segons_espera'] / 60, 1) . " min" : "--";
                            $min_atencio = $torn['segons_atencio'] ? round($torn['segons_atencio'] / 60, 1) . " min" : "--";
                            
                            $classe_resultat = 'color: #64748b; font-weight: bold;';
                            if ($torn['resultat_prova'] === 'apte') $classe_resultat = 'color: #10b981; font-weight: bold;';
                            if ($torn['resultat_prova'] === 'no_apte') $classe_resultat = 'color: #ef4444; font-weight: bold;';
                        ?>
                            <tr style="border-bottom: 1px solid #f1f5f9;">
                                <td style="padding: 12px; font-weight: bold; color: #2563eb;">#<?= $torn['turno_numero'] ?></td>
                                <td style="padding: 12px; color: #475569;"><?= date('d/m/Y H:i:s', strtotime($torn['fecha_registro'])) ?></td>
                                <td style="padding: 12px;"><?= $min_espera ?></td>
                                <td style="padding: 12px;"><?= $min_atencio ?></td>
                                <td style="padding: 12px;">
                                    <span style="font-size: 0.9rem; font-weight: 500; color: <?= $torn['estado'] === 'atendido' ? '#10b981' : '#dc2626' ?>;">
                                        <?= $torn['estado'] === 'atendido' ? 'Atès' : 'Cancel·lat' ?>
                                    </span>
                                </td>
                                <td style="padding: 12px; text-transform: uppercase;">
                                    <span style="<?= $classe_resultat ?>">
                                        <?= htmlspecialchars($torn['resultat_prova'] ?: 'Pendent') ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <div class="incidencias-section" style="background: white; padding: 24px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
        <h2 class="incidencias-title" style="font-size: 1.25rem; color: #991b1b; margin: 0 0 5px 0;">🚨 Registre d'Incidències: Intents d'Accés No Autoritzat</h2>
        <p style="font-size:0.85rem; color:#64748b; margin-bottom: 15px;">
            Alumnes detectats intentant forçar l'entrada a la pantalla de gestió del professor (`gestion.php`):
        </p>
        
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: #fff5f5; color: #991b1b; border-bottom: 2px