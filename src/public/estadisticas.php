<?php
// 1. 🔐 BLINDATGE DE SEGURETAT: Només el professor pot carregar aquesta pàgina
require_once 'seguridad_profesor.php';

$host = 'db';
$db   = 'gestion_colas';
$user = 'root';
$pass = 'root'; // La teva contrasenya de MariaDB

try {
    $dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
} catch (\PDOException $e) {
    die("Error de connexió a la BD: " . $e->getMessage());
}

// 2. OBTENIR LLISTA D'ALUMNES UNIQUES PER AL DESPLEGABLE DEL FILTRE
$stmt_alumnos = $pdo->query("SELECT DISTINCT email_alumno, nombre_alumno FROM turnos ORDER BY nombre_alumno ASC");
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
    $where_clauses = ["estado = 'atendido'"];
    $params = [];
    
    if ($filtro_alumno) {
        $where_clauses[] = "email_alumno = ?";
        $params[] = $filtro_alumno;
    }
    if ($filtro_fecha) {
        $where_clauses[] = "DATE(fecha_registro) = ?";
        $params[] = $filtro_fecha;
    }
    
    $where_str = implode(" AND ", $where_clauses);
    
    // SQL complexa utilitzant funcions de temps (TIMESTAMPDIFF en segons)
    $sql = "SELECT 
                COUNT(*) as total_ates,
                AVG(TIMESTAMPDIFF(SECOND, fecha_registro, hora_inicio_atencion)) as espera_mitjana,
                AVG(TIMESTAMPDIFF(SECOND, hora_inicio_atencion, hora_fin_atencion)) as atencio_mitjana,
                SUM(CASE WHEN resultat_prova = 'apte' THEN 1 ELSE 0 END) as total_aptes,
                SUM(CASE WHEN resultat_prova = 'no_apte' THEN 1 ELSE 0 END) as total_no_aptes
            FROM turnos 
            WHERE $where_str";
            
    $stmt_calc = $pdo->prepare($sql);
    $stmt_calc->execute($params);
    $res = $stmt_calc->fetch();

$tornis_detall = [];
if ($res && $res['total_ates'] > 0) {
    // (Mantenir els càlculs de $stats que ja tenies de la resposta anterior)
    
    // 🔍 NOVA CONSULTA: Obtenir el llistat individual d'aquests torns
    $sql_detall = "SELECT 
                    turno_numero, 
                    estado, 
                    resultat_prova,
                    fecha_registro,
                    TIMESTAMPDIFF(SECOND, fecha_registro, hora_inicio_atencion) as segons_espera,
                    TIMESTAMPDIFF(SECOND, hora_inicio_atencion, hora_fin_atencion) as segons_atencio
                   FROM turnos 
                   WHERE $where_str 
                   ORDER BY fecha_registro DESC";
                   
    $stmt_detall = $pdo->prepare($sql_detall);
    $stmt_detall->execute($params);
    $tornis_detall = $stmt_detall->fetchAll();
}

    if ($res && $res['total_ates'] > 0) {
	$stats['vegades_ates'] = $res['total_ates'];
	$total_avaluats = $res['total_aptes'] + $res['total_no_aptes'];
        // Passem els segons mitjans a minuts arrodonits a 1 decimal
        $stats['temps_espera_mig'] = round(($res['espera_mitjana'] ?? 0) / 60, 1);
        $stats['temps_atencion_mig'] = round(($res['atencio_mitjana'] ?? 0) / 60, 1);
        $stats['aptes'] = $res['total_aptes'];
        $stats['no_aptes'] = $res['total_no_aptes'];
        
        // Càlcul de percentatges matemàtics
        // $stats['pct_apte'] = round(($stats['aptes'] / $stats['vegades_ates']) * 100, 1);
	// $stats['pct_no_apte'] = round(($stats['no_aptes'] / $stats['vegades_ates']) * 100, 1);
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

        <div class="test-box">
            <div style="flex: 0 0 220px;">
                <h3 style="font-size:1rem; color:#334155;">Resultat dels Tests</h3>
                <p style="font-size:0.85rem; color:#64748b;">Distribució de les avaluacions fetes.</p>
            </div>
            <div class="test-progress">
                <div class="progress-apte" style="width: <?= $stats['pct_apte'] ?>%;">
                    <?php if($stats['aptes'] > 0): ?> APTE (<?= $stats['pct_apte'] ?>%) <?php endif; ?>
                </div>
                <?php if($stats['no_aptes'] > 0): ?> 
                    <span style="margin-left: auto; padding-right: 15px;">NO APTE (<?= $stats['pct_no_apte'] ?>%)</span> 
                <?php endif; ?>
	    </div>


</div>

        <div class="detall-section">
            <h2 class="detall-title">📋 Registre de Torns Detallat</h2>
            <p style="font-size: 0.85rem; color: #64748b; margin-bottom: 15px;">
                Aquests són els torns individuals utilitzats per generar els indicadors superiors:
            </p>
<?php if (count($tornis_detall) > 0): ?>
            <button onclick="descarregarCSV()" style="background-color: #10b981; color: white; border: none; padding: 8px 15px; border-radius: 6px; font-weight: bold; cursor: pointer; font-size: 0.85rem; display: flex; align-items: center; gap: 5px;">
                📥 Descarregar CSV
            </button>
        <?php endif; ?>
            <table id="taula-detall" style="width: 100%; border-collapse: collapse; border: 1px solid #e2e8f0;">
                <thead>
                    <tr style="background: #f8fafc; color: #475569;">
                        <th>Torn</th>
                        <th>Data i Hora Sol·licitud</th>
                        <th>Temps Espera</th>
                        <th>Temps Atenció</th>
                        <th>Estat Final</th>
                        <th>Resultat Test</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($tornis_detall) === 0): ?>
                        <tr>
                            <td colspan="6" class="text-empty">No s'han trobat torns registrats per aquests filtres.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($tornis_detall as $torn): 
                            // Convertim els segons calculats a minuts/segons llegibles
                            $min_espera = $torn['segons_espera'] ? round($torn['segons_espera'] / 60, 1) . " min" : "--";
                            $min_atencio = $torn['segons_atencio'] ? round($torn['segons_atencio'] / 60, 1) . " min" : "--";
                            
                            // Color del resultat
                            $classe_resultat = 'badge-pendent';
                            if ($torn['resultat_prova'] === 'apte') $classe_resultat = 'badge-apte';
                            if ($torn['resultat_prova'] === 'no_apte') $classe_resultat = 'badge-no-apte';
                        ?>
                            <tr>
                                <td style="font-weight: bold; color: #2563eb;">#<?= $torn['turno_numero'] ?></td>
                                <td style="color: #475569;"><?= date('d/m/Y H:i:s', strtotime($torn['fecha_registro'])) ?></td>
                                <td><?= $min_espera ?></td>
                                <td><?= $min_atencio ?></td>
                                <td>
                                    <span style="font-size: 0.9rem; font-weight: 500; color: <?= $torn['estado'] === 'atendido' ? '#10b981' : '#dc2626' ?>;">
                                        <?= $torn['estado'] === 'atendido' ? 'Atès' : 'Cancel·lat (No presentat)' ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge-result <?= $classe_resultat ?>">
                                        <?= $torn['resultat_prova'] ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        </div>
    <?php endif; ?>

    <div class="incidencias-section">
        <h2 class="incidencias-title">🚨 Registre d'Incidències: Intents d'Accés No Autoritzat</h2>
        <p style="font-size:0.85rem; color:#991b1b; margin-bottom: 15px;">
            Alumnes detectats intentant forçar l'entrada a la pantalla de gestió del professor (`gestion.php`):
        </p>
        
        <table>
            <thead>
                <tr>
                    <th>Nom de l'Alumne</th>
                    <th>Correu Electrònic</th>
                    <th>Data i Hora Exacta</th>
                    <th>Adreça IP d'Origen</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($incidencias) === 0): ?>
                    <tr>
                        <td colspan="4" class="text-empty">Excellent. No s'ha registrat cap incidència de seguretat fins ara.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($incidencias as $inc): ?>
                        <tr style="background-color: #fffbfa;">
                            <td style="font-weight: bold; color: #b91c1c;"><?= htmlspecialchars($inc['nombre_infractor']) ?></td>
                            <td><code><?= htmlspecialchars($inc['email_infractor']) ?></code></td>
                            <td style="color: #475569;"><?= date('d/m/Y H:i:s', strtotime($inc['fecha_incidencia'])) ?></td>
                            <td style="font-size: 0.85rem; color: #64748b;"><?= htmlspecialchars($inc['ip_origen']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
<script>
function descarregarCSV() {
    const taula = document.getElementById('taula-detall');
    if (!taula) return;

    let filesCSV = [];
    const files = taula.querySelectorAll('tr');

    files.forEach(fila => {
        const cel_les = fila.querySelectorAll('th, td');
        let filaText = [];

        cel_les.forEach(cel_la => {
            // Netegem el text d'espais en blanc, salts de línia i eliminem caràcters estranys
            let text = cel_la.innerText.trim().replace(/\n/g, " ");

            // Si el text conté punt i coma, el posem entre cometes perquè no trenqui el CSV
            if (text.includes(';')) {
                text = `"${text}"`;
            }
            filaText.push(text);
        });

        // Unim les cel·les d'aquesta fila amb un punt i coma (estàndard d'Excel en català/espanyol)
        filesCSV.push(filaText.join(';'));
    });

    // Unim totes les files amb un salt de línia i afegim el codi BOM per als accents en Excel
    const contingutCSV = "\uFEFF" + filesCSV.join('\n');

    // Creem el fitxer virtual en memòria
    const blob = new Blob([contingutCSV], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);

    // Generem un nom de fitxer dinàmic amb la data d'avui
    const dataAvui = new Date().toISOString().slice(0, 10);
    const nomFitxer = `estadisticas_cues_${dataAvui}.csv`;

    // Creem un enllaç invisible, el cliquem per iniciar la descàrrega i el destruïm
    const enllac = document.createElement("a");
    enllac.setAttribute("href", url);
    enllac.setAttribute("download", nomFitxer);
    enllac.style.visibility = 'hidden';
    document.body.appendChild(enllac);
    enllac.click();
    document.body.removeChild(enllac);
}
</script>
</html>

