// js/alumno.js
let jaNotificat = false;

window.I18n = {
    translations: {},
    translate: function(key) {
        return this.translations[key] || key;
    }
};

// Funció asíncrona per descarregar les traduccions des de la carpeta lang/
async function inicialitzarIdioma() {
    try {
        // 1. Demanem a l'API l'idioma que té guardat PHP a la sessió actual
        const respostaEstat = await fetch('api_alumno.php?accio=estat');
        const dadesEstat = await respostaEstat.json();
        
        // Suposem que api_alumno.php ens torna una clau 'lang' (ex: 'ca', 'es', 'en').
        // Si no la troba, forcem 'ca' per defecte.
        const idiomaSessio = dadesEstat.lang || 'ca'; 
        
        // 2. Anem a buscar el fitxer JSON correcte a la carpeta lang/
        const respostaLang = await fetch(`lang/${idiomaSessio}.json`);
        
        // 3. Guardem les traduccions a l'objecte global
        window.I18n.translations = await respostaLang.json();
        
    } catch (error) {
        console.error("No s'han pogut carregar les traduccions, utilitzant sistema d'emergència:", error);
        // Fallback en cas d'error de xarxa perquè l'app no es congeli
        window.I18n.translations = { "minutes": "min", "your_turn_is": "Torn:" };
    }
}

// Demanar permís per a les notificacions només entrar
if (Notification.permission === "default") {
    Notification.requestPermission();
}

async function comprovarEstatCua() {
    try {
        const resposta = await fetch('api_alumno.php?accio=estat');
        const dades = await resposta.json();
        
        const contenidorEstat = document.getElementById('estat-cua-contenidor');
        const textEstat = document.getElementById('estat-cua-text');
        const botoApuntar = document.getElementById('apuntarse-btn'); 

        // 1. CONTROL DE VISIBILITAT DE PANTALLES
        if (dades.en_cua) {
            document.getElementById('seccio-apuntarse').classList.add('hidden');
            document.getElementById('seccio-espera').classList.remove('hidden');
            
            document.getElementById('el-meu-torn').textContent = dades.el_meu_torn;
            document.getElementById('alumnes-davant').textContent = dades.alumnes_davant;
            // Traducció dinàmica del sufix "min"
            document.getElementById('temps-estimat').textContent = dades.temps_estimat + " " + window.I18n.translate('minutes');

            if (dades.estat_actual === 'atendiendo') {
                // Text en cas que sigui el torn de l'alumne
                document.getElementById('text-estat-torn').innerHTML = `<span style='color:#15803d; font-weight:bold;'>${window.I18n.translate('its_your_turn')}</span>`;
                llencarNotificacio();
            } else {
                document.getElementById('text-estat-torn').textContent = window.I18n.translate('your_turn_is');
                jaNotificat = false; 
            }
        } else {
            document.getElementById('seccio-apuntarse').classList.remove('hidden');
            document.getElementById('seccio-espera').classList.add('hidden');
            jaNotificat = false;
        }

        // 2. CONTROL UNIFICAT DE L'ESTAT DE LA CUA (Amb traduccions aplicades)
        if (contenidorEstat && textEstat) {
            if (dades.cola_abierta == 1) {
                textEstat.textContent = window.I18n.translate('queue_is_open');
                contenidorEstat.style.backgroundColor = "#e6f4ea";
                contenidorEstat.style.color = "#137333";

                if (botoApuntar) {
                    botoApuntar.removeAttribute('disabled');
                    botoApuntar.disabled = false;
                    botoApuntar.textContent = window.I18n.translate('btn_join'); 
                    botoApuntar.style.opacity = "1";
                    botoApuntar.style.cursor = "pointer";
                    botoApuntar.style.pointerEvents = "auto";
                }
            } else {
                textEstat.textContent = window.I18n.translate('queue_is_closed');
                contenidorEstat.style.backgroundColor = "#fce8e6";
                contenidorEstat.style.color = "#c5221f";

                if (botoApuntar) {
                    botoApuntar.setAttribute('disabled', 'true');
                    botoApuntar.disabled = true;
                    botoApuntar.textContent = window.I18n.translate('queue_closed_temporarily');
                    botoApuntar.style.opacity = "0.5";
                    botoApuntar.style.cursor = "not-allowed";
                    botoApuntar.style.pointerEvents = "none";
                }
            }
        }

    } catch (error) {
        console.error("Error en la connexió al comprovar estat:", error);
    }
}

// 🟢 FUNCIÓ SECURE: Gestiona l'acció actuant en l'idioma seleccionat
async function accionarCua(accio) {
    try {
        const resposta = await fetch(`api_alumno.php?accio=${accio}`, { method: 'POST' });
        
        // Llegim el text de resposta directament per avaluar si està buit o malformat
        const textResposta = await resposta.text();
        
        let dades;
        try {
            dades = JSON.parse(textResposta);
        } catch (e) {
            console.error("El servidor ha retornat un format no JSON:", textResposta);
            alert(window.I18n.translate('invalid_json_error'));
            return;
        }

        if (dades && dades.success) {
            // Si la base de dades s'ha guardat correctament, actualitzem la vista de seguida
            await comprovarEstatCua(); 
        } else {
            // Mostrem l'error directament traduït si prové del PHP, o l'afegim al prefix d'atenció
            alert(window.I18n.translate('warning_prefix') + (dades.error || "No s'ha pogut processar la petició."));
        }
    } catch (error) {
        console.error("Error de xarxa en processar acció:", error);
    }
}

document.addEventListener("DOMContentLoaded", async () => { 

    const btnApuntar = document.getElementById("apuntarse-btn");
    if (btnApuntar) {
        btnApuntar.addEventListener("click", async () => {
            if ("Notification" in window && Notification.permission === "default") {
                await Notification.requestPermission();
            }
            await accionarCua("apuntarse");
        });
    }

    const btnDesapuntar = document.getElementById("desapuntarse-btn");
    if (btnDesapuntar) {
        btnDesapuntar.addEventListener("click", async () => {
            await accionarCua("desapuntarse");
        });
    }
    // Inicialització del Polling actiu
    await inicialitzarIdioma(); // Assegurem que les traduccions estan carregades abans de continuar
    await comprovarEstatCua();
    setInterval(comprovarEstatCua, 3000);
});

function llencarNotificacio() {
    if (!jaNotificat && Notification.permission === "granted") {
        new Notification(window.I18n.translate('its_your_turn'), {
            body: window.I18n.translate('notification_body'),
            icon: "https://cdn-icons-png.flaticon.com/512/179/179133.png"
        });
        jaNotificat = true;
    }
}

