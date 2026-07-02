// ==========================================
// 🌍 ESTAT GLOBAL I CONFIGURACIÓ DE TRADUCCIONS
// ==========================================
let jaNotificat = false; // Controla que la notificació push del torn només s'enviï una vegada

window.I18n = {
    translations: {},
    translate: function(key) {
        return this.translations[key] || key;
    }
};

/**
 * Carrega asíncronament les traduccions des de la carpeta lang/ basant-se en la sessió de PHP
 */
async function inicialitzarIdioma() {
    try {
        // 1. Obtenim l'estat actual de la sessió de l'alumne (inclou l'idioma elegit)
        const respostaEstat = await fetch('api_alumno.php?accio=estat');
        const dadesEstat = await respostaEstat.json();
        const idiomaSessio = dadesEstat.lang || 'ca'; 
        
        // 2. Descarreguem el diccionari JSON actiu
        const respostaLang = await fetch(`lang/${idiomaSessio}.json`);
        window.I18n.translations = await respostaLang.json();
        
    } catch (error) {
        console.error("No s'han pogut carregar les traduccions, utilitzant sistema d'emergència:", error);
        // Fallback per evitar que la interfície es quedi en blanc si falla la xarxa
        window.I18n.translations = { "minutes": "min", "your_turn_is": "Torn:" };
    }
}

// ==========================================
// ⏳ CICLE DE VIDA I DISPARADORS D'EVENTS (DOM)
// ==========================================
document.addEventListener("DOMContentLoaded", async () => {
    // 1. Inicialització de l'idioma i primers selectors elementals
    await inicialitzarIdioma(); 
    carregarModulsAlumne();

    // 2. Sol·licitud preventiva de permisos per a notificacions d'escriptori
    if ("Notification" in window && Notification.permission === "default") {
        Notification.requestPermission();
    }

    // 3. Controladors d'esdeveniments per als Dropdowns Encadenats
    document.getElementById("alum-modulo").addEventListener("change", (e) => {
        vincularDropdownsAlumne(e.target.value, "alum-ra", "llistar_ras&id_modul=", "Primer tria un mòdul...");
        resetearSelectorAlumne("alum-activitat", "Primer tria un RA...");
        resetearSelectorAlumne("alum-check", "Primer tria una activitat...");
    });

    document.getElementById("alum-ra").addEventListener("change", (e) => {
        vincularDropdownsAlumne(e.target.value, "alum-activitat", "llistar_activitats&id_ra=", "Primer tria un RA...");
        resetearSelectorAlumne("alum-check", "Primer tria una activitat...");
    });

    document.getElementById("alum-activitat").addEventListener("change", (e) => {
        vincularDropdownsAlumne(e.target.value, "alum-check", "llistar_checks_alumne&id_act=", "Primer tria una activitat...");
    });
    
    // 4. Gestor de l'enviament del formulari complet amb el check elegit
    document.getElementById("form-demanar-torn").addEventListener("submit", enviarSollicitudTorn);

    // 5. Gestor per a desapuntar-se de la cua de manera immediata
    const btnDesapuntar = document.getElementById("desapuntarse-btn");
    if (btnDesapuntar) {
        btnDesapuntar.addEventListener("click", async () => {
            await accionarCua("desapuntarse");
        });
    }

    // 6. Engegada del Polling / Sincronització en calent d'estats cada 3 segons
    await comprovarEstatCua();
    setInterval(comprovarEstatCua, 3000);
});

// ==========================================
// 📊 CONTROL DE FLUX I SCRIPTOR DE LA CUA
// ==========================================

/**
 * Revisa en bucle l'estat actual de la cua i commuta les pantalles de sol·licitud/espera
 */
async function comprovarEstatCua() {
    try {
        const resposta = await fetch('api_alumno.php?accio=estat');
        const dades = await resposta.json();
        
        const contenidorEstat = document.getElementById('estat-cua-contenidor');
        const textEstat = document.getElementById('estat-cua-text');
        const botoApuntar = document.getElementById('apuntarse-btn'); 

        // 1. Commutació dinàmica de visualitzacions segons estat de l'alumne
        if (dades.en_cua) {
            document.getElementById('seccio-apuntarse').classList.add('hidden');
            document.getElementById('seccio-espera').classList.remove('hidden');
            
            document.getElementById('el-meu-torn').textContent = dades.el_meu_torn;
            document.getElementById('alumnes-davant').textContent = dades.alumnes_davant;
            document.getElementById('temps-estimat').textContent = dades.temps_estimat + " " + window.I18n.translate('minutes');

            if (dades.estat_actual === 'atendiendo') {
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

        // 2. Control de tancament temporitzat de la cua per part del docent
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

/**
 * 🟢 VERSIÓ MILLORADA: Executa transaccions POST contra l'API de l'alumne
 * acceptant paràmetres addicionals (ID de checks, formularis, etc.) en format JSON.
 */
async function accionarCua(accio, cosDades = null) {
    try {
        const opcionsFetch = { method: 'POST' };
        
        // Si estem enviant objectes (com el check), configurem la capçalera JSON
        if (cosDades) {
            opcionsFetch.headers = { 'Content-Type': 'application/json' };
            opcionsFetch.body = JSON.stringify(cosDades);
        }

        const resposta = await fetch(`api_alumno.php?accio=${accio}`, opcionsFetch);
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
            await comprovarEstatCua(); 
        } else {
            alert(window.I18n.translate('warning_prefix') + (dades.error || "Error indeterminat."));
        }
    } catch (error) {
        console.error("Error de xarxa en processar acció:", error);
    }
}

// ==========================================
// 📂 LOGÍSTICA DE DROPDOWNS ASÍNCRONS (API)
// ==========================================

/**
 * Omple el dropdown de mòduls disponibles un cop el DOM està a punt
 */
async function carregarModulsAlumne() {
    const res = await fetch('api_activitats.php?accio=llistar_moduls');
    const dades = await res.json();
    if (dades.success) {
        const select = document.getElementById("alum-modulo");
        let html = '<option value="">-- Selecciona un Mòdul --</option>';
        dades.moduls.forEach(m => {
            html += `<option value="${m.id_modul}">[${m.cicle_formatiu}] ${m.nom_modul}</option>`;
        });
        select.innerHTML = html;
    }
}

/**
 * Genera el flux en cadena carregant els sub-elements basant-se en l'ID pare passat
 */
async function vincularDropdownsAlumne(idPare, elementIdTarget, rutaAccio, textDefecte) {
    const selectTarget = document.getElementById(elementIdTarget);
    if (!idPare) {
        resetearSelectorAlumne(elementIdTarget, textDefecte);
        return;
    }

    const res = await fetch(`api_activitats.php?accio=${rutaAccio}${idPare}`);
    const dades = await res.json();
    
    if (dades.success) {
        const llista = dades.ras || dades.activitats || dades.checks || [];
        
        if (llista.length === 0) {
            selectTarget.innerHTML = '<option value="">⚠️ No hi ha elements disponibles</option>';
            selectTarget.disabled = true;
            return;
        }

        let html = `<option value="">-- Selecciona --</option>`;
        llista.forEach(item => {
            const id = item.id || item.id_activitat_conceptual || item.id_check;
            const text = item.CodiModul_RA ? `${item.CodiModul_RA} - ${item.nom_ra}` : (item.nom_activitat || item.titol_check);
            
            html += `<option value="${id}">${text}</option>`;
        });
        
        selectTarget.innerHTML = html;
        selectTarget.disabled = false;
    }
}

/**
 * Neteja i bloca un selector inferior de la línia temporal si es reseteja el pare
 */
function resetearSelectorAlumne(elementId, text) {
    const el = document.getElementById(elementId);
    if (el) {
        el.innerHTML = `<option value="">${text}</option>`;
        el.disabled = true;
    }
}

/**
 * Intercepta el formulari de sol·licitud i envia l'alumne a la cua amb el seu check triat
 */
async function enviarSollicitudTorn(e) {
    e.preventDefault();
    
    const idCheck = document.getElementById("alum-check").value;
    if (!idCheck) { 
        alert("Siusplau, tria el criteri concret que vols avaluar."); 
        return; 
    }

    // Demanem el torn canalitzant les dades dinàmiques cap a la funció unificada
    await accionarCua("demanar_turno", { id_check_evaluacio: idCheck });
}

// ==========================================
// 🔔 SISTEMA DE NOTIFICACIONS PUSH
// ==========================================

/**
 * Dispara una alerta emergent en l'escriptori de l'usuari si l'app es troba en segon pla
 */
function llencarNotificacio() {
    if (!jaNotificat && Notification.permission === "granted") {
        new Notification(window.I18n.translate('its_your_turn'), {
            body: window.I18n.translate('notification_body'),
            icon: "https://cdn-icons-png.flaticon.com/512/179/179133.png"
        });
        jaNotificat = true; // Evitem spam bloquejant el llançador fins al següent torn
    }
}