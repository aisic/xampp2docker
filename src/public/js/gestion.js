// ==========================================
// 📊 ESTAT GLOBAL I CONTROL DE PANELS
// ==========================================
let cuaObertaActual = true;      // Estat d'obertura/tancament de la cua d'aula
let temporitzador;               // Guardar la referència del setInterval del compte enrere
let tempsRestant = 20;           // Segons de cortesia per a l'arribada de l'alumne
let idDelTurnoActual = null;     // ID del torn actiu a la taula 'turnos'
let idCheckDelTornActual = null; // ID del check individual que l'alumne demana avaluar
let estatTriat = null;           // Guarda de manera temporal la selecció 'apte' o 'no_apte'

// ==========================================
// ⏳ CICLE DE VIDA I DISPARADORS DE BOTONS
// ==========================================
document.addEventListener("DOMContentLoaded", () => {
    console.log("Panell de gestió centralitzada inicialitzat.");

    // 1. Commutador global de la cua d'aula (Obrir / Tancar)
    const btnLock = document.getElementById('btn-lock');
    if (btnLock) btnLock.addEventListener("click", toggleCua);

    // 2. Control de flux de la cua (Cridar següent en llista)
    const btnSiguiente = document.getElementById('btn-siguiente');
    if (btnSiguiente) btnSiguiente.addEventListener("click", cridarSiguiente);

    // 3. Confirmació de presència a l'aula
    const btnPresentat = document.getElementById('btn-presentat');
    if (btnPresentat) btnPresentat.addEventListener("click", alumneSHePresentat);

    // 4. Selectors d'estat (Guarden la decisió sense tancar el torn encara)
    const btnApte = document.getElementById('btn-apte');
    if (btnApte) btnApte.addEventListener("click", () => avaluaAlumne('apte'));

    const btnNoApte = document.getElementById('btn-no-apte');
    if (btnNoApte) btnNoApte.addEventListener("click", () => avaluaAlumne('no_apte'));

    // 5. Botó final d'enviament de tota l'avaluació reunida
    const btnDesarAval = document.getElementById('btn-desar-aval'); 
    if (btnDesarAval) btnDesarAval.addEventListener("click", finalitzarAval_CheckIndividual);
    
    // Engegada del Polling d'actualització dinàmica cada 4 segons
    carregarDadesPanell();
    setInterval(carregarDadesPanell, 4000);
});

// ==========================================
// 🔄 SINCRONITZACIÓ I RENDERITZACIÓ DE DADES (API)
// ==========================================

/**
 * Consulta l'estat en segon pla per pintar la cua i l'alumne cridat a la taula
 */
async function carregarDadesPanell() {
    try {
        const resposta = await fetch('api_gestion.php?accio=estat');
        const dades = await resposta.json(); 

        if (!dades.success) {
            console.error("L'API ha retornat un error de control:", dades.error);
            return;
        }

        // 1. Actualització de capçaleres i comptadors textuals
        document.getElementById('nom-asignatura').textContent = `${dades.nom_modul} (${dades.asignatura})`;
        document.getElementById('total-espera').textContent = dades.en_espera;
        
        const alumneActiu = dades.atendiendo;
        document.getElementById('num-actual').textContent = alumneActiu.turno_numero;
        document.getElementById('nom-actual').textContent = alumneActiu.nombre_alumno;
        
        // Sincronitzem l'ID de torn global actiu
        idDelTurnoActual = alumneActiu.id_turno ?? null;

        // 2. Commutació de visibilitat de la caixa segons presència/estat del cronòmetre
        const zonaAvalua = document.getElementById('zona-avalua');
        const zonaTemps = document.getElementById('zona-temps');
        
        if (alumneActiu.id_turno !== null) {
            // Si el temporitzador ja s'ha amagat perquè l'alumne s'ha presentat, garantim visibilitat de la fitxa
            if (zonaTemps.classList.contains('hidden')) {
                zonaAvalua.classList.remove('hidden');
                document.getElementById("bloc-avaluacio-checks").classList.remove("hidden");
            }
            omplirFitxaAvaluacioTorn(alumneActiu);
        } else {
            zonaAvalua.classList.add('hidden');
            if (!zonaTemps.classList.contains('hidden')) {
                aturarTemporitzador();
            }
            idCheckDelTornActual = null;
        }

        // 3. Gestió visual de l'estat del panell de tancament de l'aula
        cuaObertaActual = (dades.cola_abierta == 1);
        const btnLock = document.getElementById('btn-lock');
        if (btnLock) {
            if (cuaObertaActual) {
                btnLock.textContent = "🔒 Tancar Cua Alumnes";
                btnLock.style.backgroundColor = "#dc2626";
            } else {
                btnLock.textContent = "🔓 Obrir Cua Alumnes";
                btnLock.style.backgroundColor = "#16a34a";
            }
        }

        // 4. Renderitzat dinàmic dels propers en cua
        const contenidorLlista = document.getElementById('llista-alumnes');
        if (contenidorLlista) {
            contenidorLlista.innerHTML = "";

            if (dades.cua_llista.length === 0) {
                contenidorLlista.innerHTML = "<p class='empty-list-text'>No hi ha ningú esperant ara mateix.</p>";
            } else {
                dades.cua_llista.forEach((alumne, index) => {
                    const item = document.createElement('div');
                    item.className = 'list-item';
                    item.innerHTML = `
                        <span><strong>${index + 1}.</strong> ${alumne.nombre_alumno}</span>
                        <span class="badge" style="font-size:0.8rem; background-color:#f1f5f9; padding:2px 8px; border-radius:4px;">
                            ${alumne.titol_check ? alumne.titol_check : 'Torn General'} (T. ${alumne.turno_numero})
                        </span>
                    `;
                    contenidorLlista.appendChild(item);
                });
            }
        }

    } catch (error) {
        console.error("Error crític al carregar les dades de gestió:", error);
    }
}

/**
 * Ordena al servidor invertir el permís d'entrada d'alumnes a la cua
 */
async function toggleCua() {
    try {
        const nouEstat = !cuaObertaActual;
        await fetch('api_gestion.php?accio=toggle_cua', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ estat: nouEstat })
        });
        carregarDadesPanell();
    } catch (error) {
        console.error("Error al commutar la cua:", error);
    }
}

// ==========================================
// ⏰ LOGÍSTICA DEL COMPTE ENRERE (PRESÈNCIA)
// ==========================================

async function cridarSiguiente() {
    try {
        const resposta = await fetch('api_gestion.php?accio=siguiente', { method: 'POST' });
        const resultat = await resposta.json();

        if (resultat.success) {
            if (resultat.quedaven_alumnes) {
                // Neteja total de formularis per rebre el nou candidat
                if (document.getElementById('eval-pregunta')) document.getElementById('eval-pregunta').value = '';
                if (document.getElementById('eval-respuesta')) document.getElementById('eval-respuesta').value = '';
                estatTriat = null;
                marcarBotonsEstatVisual(null);

                iniciarTemporitzador(); 
            } else {
                alert("La cua està buida. No hi ha més alumnes per atendre!");
                aturarTemporitzador();
            }
        }
        carregarDadesPanell();
    } catch (error) {
        console.error("Error al cridar al següent alumne:", error);
    }
}

function iniciarTemporitzador() {
    aturarTemporitzador(); 
    tempsRestant = 20;
    
    const zonaTemps = document.getElementById('zona-temps');
    if (zonaTemps) zonaTemps.classList.remove('hidden');
    
    const zonaAvalua = document.getElementById('zona-avalua');
    if (zonaAvalua) zonaAvalua.classList.add('hidden'); 
    
    document.getElementById('comptador-enrere').textContent = tempsRestant;
    document.getElementById('barra-progres').style.width = '100%';

    temporitzador = setInterval(() => {
        tempsRestant--;
        document.getElementById('comptador-enrere').textContent = tempsRestant;
        document.getElementById('barra-progres').style.width = `${(tempsRestant / 20) * 100}%`;

        if (tempsRestant <= 0) {
            aturarTemporitzador();
            console.log("Temps de cortesia esgotat. Saltant d'alumne...");
            cridarSiguiente(); 
        }
    }, 1000);
}

function aturarTemporitzador() {
    clearInterval(temporitzador);
    const zonaTemps = document.getElementById('zona-temps');
    if (zonaTemps) zonaTemps.classList.add('hidden');
}

/**
 * Flux corregit: Exposa la caixa d'avaluació i les zones de text a l'instant
 */
function alumneSHePresentat() {
    aturarTemporitzador();
    
    const zonaAvalua = document.getElementById('zona-avalua');
    if (zonaAvalua) zonaAvalua.classList.remove('hidden');
    
    const blocPreguntes = document.getElementById("bloc-avaluacio-checks");
    if (blocPreguntes) blocPreguntes.classList.remove('hidden');
    
    estatTriat = null;
    marcarBotonsEstatVisual(null);

    const txtEstat = document.getElementById('text-estat-torn');
    if (txtEstat) {
        txtEstat.innerHTML = "🟢 <span style='color:#16a34a; font-weight:bold;'>Alumne present a la taula (Avaluació oberta)</span>";
    }
}

// ==========================================
// 🎯 FITXA D'AVALUACIÓ UNITÀRIA PER CRITERI (CHECK)
// ==========================================

/**
 * Injecta el criteri concret que l'alumne ha triat
 */
function omplirFitxaAvaluacioTorn(dadesTorn) {
    const nouCheckId = dadesTorn.id_check_evaluacio;
    
    // Evitem parpellejos o sobreescriptures constants durant el polling si és el mateix check
    if (idCheckDelTornActual === nouCheckId) return;
    idCheckDelTornActual = nouCheckId;

    if (!idCheckDelTornActual) {
        document.getElementById("eval-titol-activitat").textContent = "⚠️ Torn d'antiga estructura";
        document.getElementById("eval-titol-check").textContent = "L'alumne no té cap check vàlid assignat.";
        document.getElementById("bloc-decisio-inicial").classList.add("hidden");
        return;
    }

    document.getElementById("eval-titol-activitat").textContent = dadesTorn.nom_activitat;
    document.getElementById("eval-titol-check").textContent = `Criteri a defensar: ${dadesTorn.titol_check}`;
    document.getElementById("bloc-decisio-inicial").classList.remove("hidden");
}

/**
 * Commuta l'estat local triat i canvia l'estat visual dels botons
 */
function avaluaAlumne(estat) {
    estatTriat = estat; 
    marcarBotonsEstatVisual(estat);
}

/**
 * Modifica els contorns de color dels selectors segons la decisió temporal
 */
function marcarBotonsEstatVisual(estat) {
    const btnApte = document.getElementById('btn-apte');
    const btnNoApte = document.getElementById('btn-no-apte');
    
    if (!btnApte || !btnNoApte) return;

    if (estat === 'apte') {
        btnApte.style.border = "3px solid #10b981"; 
        btnApte.style.opacity = "1";
        btnNoApte.style.opacity = "0.4";
        btnNoApte.style.border = "none";
    } else if (estat === 'no_apte') {
        btnNoApte.style.border = "3px solid #ef4444"; 
        btnNoApte.style.opacity = "1";
        btnApte.style.opacity = "0.4";
        btnApte.style.border = "none";
    } else {
        btnApte.style.border = "none";
        btnNoApte.style.border = "none";
        btnApte.style.opacity = "1";
        btnNoApte.style.opacity = "1";
    }
}

/**
 * 💾 BOTÓ ÚNIC FINAL: Recull l'estat, els textareas i ho envia al backend unificat
 */
async function finalitzarAval_CheckIndividual() {
    if (!idDelTurnoActual || !idCheckDelTornActual) { 
        alert("Dades invàlides de sessió. No es troba el check o el torn actiu."); 
        return; 
    }

    if (!estatTriat) {
        alert("Siusplau, marca primer si el resultat de la defensa ha estat 'Apte' o 'No Apte' abans de desar.");
        return;
    }

    const dadesEnv = {
        id_turno: idDelTurnoActual,
        id_check: idCheckDelTornActual,
        resultat_prova: estatTriat, 
        pregunta: document.getElementById('eval-pregunta').value.trim(),
        respuesta: document.getElementById('eval-respuesta').value.trim()
    };

    try {
        aturarTemporitzador();

        const res = await fetch('api_gestion.php?accio=finalitzar_apte_individual', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(dadesEnv)
        });
        
        const result = await res.json();
        if (result.success) {
            alert(`S'ha desat el check i s'ha tancat el torn com a [${estatTriat.toUpperCase()}] correctament.`);
            netejarPanellAvaluacioNatiu();
            carregarDadesPanell();
        } else {
            alert("Error del servidor: " + result.error);
        }
    } catch (e) { 
        console.error("Error al processar el tancament de l'avaluació unificada:", e); 
    }
}

/**
 * Restableix les variables de control d'estat netejant la memòria cau del formulari
 */
function netejarPanellAvaluacioNatiu() {
    idCheckDelTornActual = null;
    idDelTurnoActual = null;
    estatTriat = null;
    
    if (document.getElementById('eval-pregunta')) document.getElementById('eval-pregunta').value = '';
    if (document.getElementById('eval-respuesta')) document.getElementById('eval-respuesta').value = '';
    
    marcarBotonsEstatVisual(null);
    
    const txtEstat = document.getElementById('text-estat-torn');
    if (txtEstat) txtEstat.innerHTML = "";

    document.getElementById("bloc-avaluacio-checks").classList.add("hidden");
    document.getElementById("zona-avalua").classList.add("hidden"); 
}