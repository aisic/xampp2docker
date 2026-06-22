<!DOCTYPE html>
<html lang="ca">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pantalla de Cua - Projector</title>
    <link href="css/index.css" rel="stylesheet">
    <script src="js/pantalla.js" defer></script>
</head>
<body>

    <div id="banner-estat-projector" class="banner-projector">
        <span id="text-estat-projector">Comprovant estat de la sessió...</span>
    </div>

    <header>
        <h1 id="nombre-asignatura">Carregant assignatura...</h1>
    </header>

    <main>
        <div class="card">
            <div class="card-title">Torn Actual</div>
            <div class="card-value" id="turno-actual">--</div>
        </div>

        <div class="sidebar-cards">
            <div class="card next-card flex-center">
                <div class="card-title">Pròxim</div>
                <div class="card-value" id="turno-proximo">--</div>
            </div>
            <div class="card time-card flex-center">
                <div class="card-title">Temps Mitjà d'Espera</div>
                <div class="card-value" id="tiempo-espera">--</div>
            </div>
        </div>
    </main>

    <footer>
        Sistema de Gestió de Cues © 2026 - Actualització automàtica en temps real
    </footer>

</body>
</html>