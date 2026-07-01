<?php
require_once 'seguridad_profesor.php'; // Protegeix la vista HTML
?>
<!DOCTYPE html>
<html lang="ca">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panell de Gestió - Professor</title>
    <link href="css/gestion.css" rel="stylesheet">
    <script src="js/gestion.js" defer></script>
</head>
<body>

<div class="wrapper">
    <header>
        <div>
            <h1 id="nom-asignatura">Carregant assignatura...</h1>
            <p class="header-subtitle">Gestió de l'aula en temps real</p>
        </div>
        <div class="header-actions">
            <button id="btn-lock" class="btn btn-toggle">Carregant estat...</button>
            <a href="estadisticas.php" class="btn btn-stats">📊 Estadístiques</a>
            <a href="gestio_academica.php" class="btn btn-gestio">⚙️ Configuració Acadèmica</a>
            <a href="logout.php" class="btn btn-logout">🚪 Tancar Sessió</a>
        </div>
    </header>

    <div class="main-action">
        <button id="btn-siguiente" class="btn btn-success">🔔 CRIDAR SEGÜENT ALUMNE</button>
    </div>

    <div class="grid">
        <div class="card">
            <div class="card-title">Atenent ara mateix</div>
            <div class="big-info info-atendiendo" id="num-actual">--</div>
            <p id="nom-actual" class="student-name">Buscant...</p>
            
            <div id="zona-temps" class="timer-zone hidden">
                <p class="timer-text">Temps restant per presentar-se: <span id="comptador-enrere">20</span>s</p>
                <div class="progress-container">
                    <div id="barra-progres" class="progress-bar"></div>
                </div>
                <div style="margin-top: 15px;">
                    <button id="btn-presentat" class="btn" style="background-color: #2563eb; color: white; width: 100%; padding: 10px;">
                        🙋‍♂️ L'alumne s'ha presentat
                     </button>
                </div>
            </div>

            <div id="zona-avalua" class="hidden" style="background: white; padding: 20px; border-radius: 12px; margin-top: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.05);">
                <h3 style="margin-top: 0; color: #1e293b;">📋 Avaluació de l'Alumne Actual</h3>
                
                <div style="margin-bottom: 15px;">
                    <label for="eval-activitat" style="display:block; font-weight:bold; font-size:0.85rem; margin-bottom:5px; color:#475569;">Selecciona l'activitat que està defensant:</label>
                    <select id="eval-activitat" style="width:100%; padding:8px; border-radius:6px; border:1px solid #cbd5e1;" onchange="activarBotonsAvaluacio(this.value)">
                        </select>
                </div>

                <div id="bloc-decisio-inicial" class="hidden" style="display: flex; gap: 10px; margin-bottom: 15px;">
                    <button onclick="avaluarTornNoApte()" style="flex: 1; background-color: #dc2626; color: white; padding: 12px; font-weight: bold; border-radius: 6px; border: none; cursor: pointer;">❌ No Apte</button>
                    <button onclick="mostrarBlocChecks()" style="flex: 1; background-color: #2563eb; color: white; padding: 12px; font-weight: bold; border-radius: 6px; border: none; cursor: pointer;">✅ Apte (Avaluar Checks)</button>
                </div>

                <div id="bloc-avaluacio-checks" class="hidden">
                    <div id="contenidor-checks-dinamics" style="margin-bottom: 20px; background: #f8fafc; padding: 15px; border-radius: 8px; border: 1px solid #e2e8f0;">
                        </div>

                    <div style="margin-bottom: 15px;">
                        <label style="display:block; font-weight:bold; font-size:0.85rem; margin-bottom:5px;">Pregunta realitzada:</label>
                        <textarea id="eval-pregunta" style="width:100%; border-radius:6px; border:1px solid #cbd5e1; height:50px;"></textarea>
                    </div>
                    <div style="margin-bottom: 15px;">
                        <label style="display:block; font-weight:bold; font-size:0.85rem; margin-bottom:5px;">Resposta / Observacions:</label>
                        <textarea id="eval-respuesta" style="width:100%; border-radius:6px; border:1px solid #cbd5e1; height:50px;"></textarea>
                    </div>

                    <button id="btn-desar-avaluacio-checks" class="btn" style="background-color: #16a34a; color: white; width: 100%; padding: 12px; font-weight: bold; border-radius: 6px; border: none; cursor: pointer;" onclick="finalitzarAval_Checks()">💾 Desar Avaluació i Tancar Torn</button>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-title">Alumnes en espera</div>
            <div class="big-info info-espera" id="total-espera">0</div>
            <p class="text-muted">alumnes a la cua d'espera</p>
        </div>
    </div>

    <div class="card list-card">
        <div class="card-title list-title">Pròxims alumnes ordenats a la cua</div>
        <div id="llista-alumnes"></div>
    </div>
</div>

</body>
</html>