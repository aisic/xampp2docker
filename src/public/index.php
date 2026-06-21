<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pantalla de Cola - Proyector</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Arial', sans-serif;
            background-color: #111827; /* Fondo oscuro para que no moleste a los ojos en el proyector */
            color: #f3f4f6;
            height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 40px;
            overflow: hidden;
        }
        header {
            text-align: center;
            border-bottom: 2px solid #374151;
            padding-bottom: 20px;
        }
        h1 { font-size: 3rem; color: #3b82f6; text-transform: uppercase; }
        
        main {
            display: flex;
            flex: 1;
            justify-content: space-around;
            align-items: center;
            gap: 20px;
        }
        .card {
            background-color: #1f2937;
            border-radius: 20px;
            padding: 40px;
            text-align: center;
            flex: 1;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.5);
        }
        .card-title { font-size: 2rem; color: #9ca3af; margin-bottom: 20px; text-transform: uppercase;}
        .card-value { font-size: 8rem; font-weight: bold; color: #10b981; }
        
        /* Resaltamos de forma diferente el próximo y el tiempo */
        .next-card .card-value { color: #f59e0b; font-size: 6rem; }
        .time-card .card-value { color: #6366f1; font-size: 4.5rem; }

        footer {
            text-align: center;
            color: #4b5563;
            font-size: 1.2rem;
        }
    </style>
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

