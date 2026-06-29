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
            <a href="estadisticas.php" class="btn btn-admin">📊 Estadístiques</a>
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

            <div id="zona-avalua" class="evaluate-zone hidden" style="display: block !important; width: 100% !important; clear: both !important; float: none !important; margin-top: 25px !important;">
                
                <div style="display: block !important; width: 100% !important; margin-bottom: 15px !important; text-align: left !important;">
                    <label for="eval-pregunta" style="display: block !important; font-weight: bold !important; font-size: 0.85rem !important; margin-bottom: 6px !important; color: #1e293b !important;">
                        📋 PREGUNTA REALITZADA:
                    </label>
                    <textarea id="eval-pregunta" rows="3" placeholder="Introduce aquí la pregunta conceptual o l'exercici..." 
                              style="display: block !important; width: 100% !important; min-width: 100% !important; max-width: 100% !important; padding: 12px !important; border: 1px solid #cbd5e1 !important; border-radius: 6px !important; background-color: #f8fafc !important; font-family: inherit !important; box-sizing: border-box !important; resize: vertical !important;"></textarea>
                </div>

                <div style="display: block !important; width: 100% !important; margin-bottom: 20px !important; text-align: left !important;">
                    <label for="eval-respuesta" style="display: block !important; font-weight: bold !important; font-size: 0.85rem !important; margin-bottom: 6px !important; color: #1e293b !important;">
                        💬 RESPOSTA DE L'ALUMNE:
                    </label>
                    <textarea id="eval-respuesta" rows="3" placeholder="Afegeix els detalls de la resposta de l'alumne o anotacions..." 
                              style="display: block !important; width: 100% !important; min-width: 100% !important; max-width: 100% !important; padding: 12px !important; border: 1px solid #cbd5e1 !important; border-radius: 6px !important; background-color: #f8fafc !important; font-family: inherit !important; box-sizing: border-box !important; resize: vertical !important;"></textarea>
                </div>

                <div style="display: flex !important; flex-direction: row !important; gap: 15px !important; width: 100% !important; margin-top: 20px !important; clear: both !important;">
                    <button id="btn-apte" class="btn btn-apte" style="flex: 1 !important; padding: 14px !important; font-size: 0.95rem !important; font-weight: bold !important; color: white !important; background-color: #059669 !important; border-radius: 8px !important; border: none !important; cursor: pointer !important; min-height: auto !important; height: auto !important;">
                        ✅ APTE
                    </button>
                    <button id="btn-no-apte" class="btn btn-no-apte" style="flex: 1 !important; padding: 14px !important; font-size: 0.95rem !important; font-weight: bold !important; color: white !important; background-color: #dc2626 !important; border-radius: 8px !important; border: none !important; cursor: pointer !important; min-height: auto !important; height: auto !important;">
                        ❌ NO APTE
                    </button>
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