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