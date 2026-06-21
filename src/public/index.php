<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pantalla de Cola - Proyector</title>
    <link href="css/index.css" rel="stylesheet">
</head>
<body>
<div id="banner-estat-projector" style="text-align: center; padding: 15px; font-size: 1.4rem; font-weight: bold; margin-bottom: 25px; border-radius: 10px; transition: all 0.5s ease;">
    <span id="text-estat-projector">Comprovant estat de la sessió...</span>
</div>
    <header>
        <h1 id="nombre-asignatura">Cargando asignatura...</h1>
    </header>

    <main>
        <div class="card">
            <div class="card-title">Turno Actual</div>
            <div class="card-value" id="turno-actual">--</div>
        </div>

        <div style="display: flex; flex-direction: column; gap: 20px; flex: 1; height: 100%;">
            <div class="card next-card" style="flex: 1; display: flex; flex-direction: column; justify-content: center;">
                <div class="card-title">Próximo</div>
                <div class="card-value" id="turno-proximo">--</div>
            </div>
            <div class="card time-card" style="flex: 1; display: flex; flex-direction: column; justify-content: center;">
                <div class="card-title">Tiempo Medio Espera</div>
                <div class="card-value" id="tiempo-espera">--</div>
            </div>
        </div>
    </main>

    <footer>
        Sistema de Gestión de Colas © 2026 - Actualización automática en tiempo real
    </footer>

    <script>
        // Función que conecta con el backend y actualiza el HTML
        async function actualizarPantalla() {
            try {
                const respuesta = await fetch('api_pantalla.php');
                const datos = await respuesta.json();

                if(datos.error) {
                    console.error(datos.error);
                    return;
                }

                // Modificar el DOM con los nuevos datos
                document.getElementById('nombre-asignatura').textContent = datos.asignatura;
                document.getElementById('turno-actual').textContent = datos.sirviendo;
                document.getElementById('turno-proximo').textContent = datos.proximo;
                document.getElementById('tiempo-espera').textContent = datos.tiempo_medio;
// --- ACTUALITZACIÓ DINÀMICA DE L'ESTAT DE LA CUA ---
        const banner = document.getElementById('banner-estat-projector');
        const textBanner = document.getElementById('text-estat-projector');

        if (datos.cola_abierta === 1) {
            // Cua oberta: text suau o verd, sense alarmismes
            textBanner.textContent = "🟢 CUA OBERTA — Pots demanar el teu torn des del mòbil o portàtil";
            banner.style.backgroundColor = "#d1fae5";
            banner.style.color = "#065f46";
            banner.style.border = "2px solid #34d399";
        } else {
            // Cua tancada: avís vermell molt visible des de lluny
            textBanner.textContent = "🛑 CUA TANCADA — El professor ha tancat el registre de nous torns";
            banner.style.backgroundColor = "#fee2e2";
            banner.style.color = "#991b1b";
            banner.style.border = "2px solid #f87171";
            // Podem afegir un petit efecte d'atenció si vols
        }
            } catch (error) {
                console.error("Error al obtener datos de la cola:", error);
            }
        }

        // Ejecutar inmediatamente al cargar la página
        actualizarPantalla();

        // Consultar a la base de datos cada 3 segundos (3000 milisegundos)
        setInterval(actualizarPantalla, 3000);
    </script>
</body>
</html>

