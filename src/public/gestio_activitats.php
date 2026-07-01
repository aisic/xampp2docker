<?php
require_once 'seguridad_profesor.php';
?>
<!DOCTYPE html>
<html lang="ca">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestió d'Activitats per RA</title>
    <link rel="stylesheet" href="css/activitats.css">
    <script src="js/activitats.js" defer></script>
</head>
<body>

<div class="act-wrapper">
    <header class="act-header">
        <div>
            <h1>📝 Gestió d'Activitats de l'Aula</h1>
            <p>Defineix tasques o exàmens per a cada un dels RAs configurats</p>
        </div>
        <div>
            <a href="gestio_academica.php" class="btn btn-back">↩️ Tornar a l'Administració</a>
        </div>
    </header>

    <div class="act-grid">
        <div class="act-card">
            <h2>Crear Nova Activitat</h2>
            <form id="form-activitat" class="act-form">
                <div class="form-group">
                    <label for="select-modulo">1. Selecciona el Mòdul:</label>
                    <select id="select-modulo" required>
                        <option value="">Carregant mòduls...</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="select-ra">2. Selecciona el RA destí:</label>
                    <select id="select-ra" required disabled>
                        <option value="">Primer tria un mòdul...</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="input-nom-activitat">3. Nom de l'activitat:</label>
                    <input type="text" id="input-nom-activitat" placeholder="Ex: Pràctica Docker, Examen Final..." required>
                </div>
                <button type="submit" class="btn btn-submit">Crear i Assignar Activitat</button>
            </form>
        </div>

        <div class="act-card table-card">
            <h2>Llistat d'Activitats i Gestió de Checks</h2>
            <div class="filter-box">
                <label for="filtre-ra">Filtrar per veure distribucions del RA:</label>
                <select id="filtre-ra" disabled>
                    <option value="">Selecciona un mòdul primer...</option>
                </select>
            </div>

            <table class="act-table">
                <thead>
                    <tr>
                        <th>Nom de l'Activitat</th>
                        <th>Pes ($1/N$)</th>
                        <th>Checks definits</th> <th>Accions</th>
                    </tr>
                </thead>
                <tbody id="taula-activitats-body">
                    <tr>
                        <td colspan="4" class="text-center">Selecciona un RA per veure les seves activitats estructurades.</td>
                    </tr>
                </tbody>
            </table>

            <div id="bloc-crear-check" class="hidden" style="margin-top: 30px; border-top: 2px dashed #e2e8f0; padding-top: 20px;">
                <h3 style="margin-top: 0; font-size: 1.1rem; color: #1e293b;">➕ Afegir Check a: <span id="nom-activitat-seleccionada" style="color: #2563eb;">-</span></h3>
                <form id="form-check" class="act-form" style="display: flex; gap: 10px; align-items: flex-end;">
                    <input type="hidden" id="id-activitat-per-check">
                    <div class="form-group" style="flex: 1; margin-bottom: 0;">
                        <label for="input-titol-check">Descripció o criteri del check:</label>
                        <input type="text" id="input-titol-check" placeholder="Ex: Funciona la connexió a la BD, Codi sagnat correctament..." required>
                    </div>
                    <button type="submit" class="btn" style="background-color: #2563eb; color: white; height: 42px; padding: 0 20px;">Afegir Criteri</button>
                </form>
                
                <ul id="llista-checks-actuals" style="margin-top: 15px; padding-left: 20px; color: #475569;"></ul>
            </div>
        </div>
    <h3 style="margin-top: 0; color: #1e293b;">🛠️ Checks per a l'activitat: <span id="nom-activitat-checks-titol" style="color:#2563eb;">-</span></h3>
    
    <div style="display: flex; gap: 10px; margin-bottom: 20px;">
        <input type="text" id="input-nou-check" placeholder="Ex: El disseny de la BD està en 3ra Forma Normal" style="flex: 1; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px;">
        <button onclick="crearNouCheck()" class="btn" style="background-color: #2563eb; color: white; width: auto; padding: 10px 20px;">➕ Afegir Check</button>
    </div>

    <ul id="llista-checks-actuals" style="list-style: none; padding: 0; margin: 0;">
        </ul>
</div>
        </div>
    </div>
</div>

</body>
</html>