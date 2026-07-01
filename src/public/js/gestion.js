// js/gestion.js

let cuaObertaActual = true;
let temporitzador;
let tempsRestant = 20;
let idDelTurnoActual = null; // 🟢 VARIABLE GLOBAL PER CONTROLAR EL TORN ACTUAL BLINDAT

document.addEventListener("DOMContentLoaded", () => {
    console.log("Panell de gestió inicialitzat.");

    // Associem els event listeners als botons
    const btnLock = document.getElementById('btn-lock');
    if (btnLock) btnLock.addEventListener("click", toggleCua);

    const btnSiguiente = document.getElementById('btn-siguiente');
    if (btnSiguiente) btnSiguiente.addEventListener("click", cridarSiguiente);

    // 🟢 ADAPTACIÓ: Els botons ara llegeixen el formulari abans d'enviar les dades
    const btnApte = document.getElementById('btn-apte');
    if (btnApte) btnApte.addEventListener("click", () => avaluaAlumne('apte'));

    const btnNoApte = document.getElementById('btn-no-apte');
    if (btnNoApte) btnNoApte.addEventListener("click", () => avaluaAlumne('no_apte'));

    const btnPresentat = document.getElementById('btn-presentat');
    if (btnPresentat) {
        btnPresentat.addEventListener("click", alumneSHePresentat);
    }
    
    // Càrrega inicial de dades i configuració del bucle de refresc (4 segons)
    carregarDadesPanell();
    carregarLlistaActivitatsEval(); // 🟢 Afegir aquesta línia aquí
    setInterval(carregarDadesPanell, 4000);
});

async function carregarDadesPanell() {
    try {
        const resposta = await fetch('api_gestion.php?accio=estat');
        const dades = await resposta.json(); 

        if (!dades.success) {
            console.error("L'API ha retornat un error:", dades.error);
            return;
        }

        // 1. Actualitzar textos bàsics
        document.getElementById('nom-asignatura').textContent = dades.asignatura;
        
        const numActual = dades.atendiendo.turno_numero;
        document.getElementById('num-actual').textContent = numActual;
        document.getElementById('nom-actual').textContent = dades.atendiendo.nombre_alumno;
        document.getElementById('total-espera').textContent = dades.en_espera;
        document.getElementById('nom-asignatura').textContent = `${dades.nom_modul}`;
        // 🟢 DESEM L'ID DEL TORN ACTUAL PER UTILITZAR-LO EN L'AVALUACIÓ SECURE
        idDelTurnoActual = dades.atendiendo.id ?? null;

        // 2. Mostrar o amagar seccions de manera modular segons si hi ha algú a la taula
        const zonaAvalua = document.getElementById('zona-avalua');
        const zonaTemps = document.getElementById('zona-temps');
        
        if (numActual !== '--') {
            // Si el temporitzador està actiu, no forcem encara la visibilitat de la zona d'avaluació
            if (zonaTemps.classList.contains('hidden')) {
                zonaAvalua.classList.remove('hidden');
            }
        } else {
            zonaAvalua.classList.add('hidden');
            if (!zonaTemps.classList.contains('hidden')) {
                aturarTemporitzador();
            }
        }

        // 3. Actualitzar botó de bloqueig/obertura de cua
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

        // 4. Actualitzar la llista visual de la cua
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
                        <span class="badge">Torn ${alumne.turno_numero}</span>
                    `;
                    contenidorLlista.appendChild(item);
                });
            }
        }

    } catch (error) {
        console.error("Error crític al carregar les dades de gestió:", error);
    }
}

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

async function cridarSiguiente() {
    try {
        const resposta = await fetch('api_gestion.php?accio=siguiente', { method: 'POST' });
        const resultat = await resposta.json();

        if (resultat.success) {
            if (resultat.quedaven_alumnes) {
                // 🟢 NETEJEM ELS CAMPS DELS TEXTAREAS PER AL NOU ALUMNE QUE VE
                if (document.getElementById('eval-pregunta')) document.getElementById('eval-pregunta').value = '';
                if (document.getElementById('eval-respuesta')) document.getElementById('eval-respuesta').value = '';
                
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
    if (zonaAvalua) zonaAvalua.classList.add('hidden'); // Amaguem avaluació mentre corre el cronòmetre
    
    document.getElementById('comptador-enrere').textContent = tempsRestant;
    document.getElementById('barra-progres').style.width = '100%';

    temporitzador = setInterval(() => {
        tempsRestant--;
        document.getElementById('comptador-enrere').textContent = tempsRestant;
        document.getElementById('barra-progres').style.width = `${(tempsRestant / 20) * 100}%`;

        if (tempsRestant <= 0) {
            aturarTemporitzador();
            console.log("Temps esgotat. Saltant al següent alumne...");
            cridarSiguiente(); 
        }
    }, 1000);
}

function aturarTemporitzador() {
    clearInterval(temporitzador);
    const zonaTemps = document.getElementById('zona-temps');
    if (zonaTemps) zonaTemps.classList.add('hidden');
}

// 🟢 FUNCIÓ D'AVALUACIÓ ADAPTADA AMB EXTRECCIÓ DE PREGUNTA I RESPOSTA
async function avaluaAlumne(resultatTest) {
    if (!idDelTurnoActual) {
        alert("No hi ha cap alumne actiu per avaluar.");
        return;
    }

    // Extreiem els elements de text de manera segura
    const preguntaCamp = document.getElementById('eval-pregunta');
    const respuestaCamp = document.getElementById('eval-respuesta');
    
    const preguntaText = preguntaCamp ? preguntaCamp.value : '';
    const respuestaText = respuestaCamp ? respuestaCamp.value : '';

    try {
        aturarTemporitzador(); 

        // Modificat l'endpoint cap a l'acció centralitzada 'finalitzar'
        const resposta = await fetch('api_gestion.php?accio=finalitzar', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ 
                id: idDelTurnoActual,
                resultado: resultatTest,
                pregunta: preguntaText,
                respuesta: respuestaText
            })
        });

        const dades = await resposta.json();
        if (dades.success) {
            // Netegem completament les caixes de text
            if (preguntaCamp) preguntaCamp.value = '';
            if (respuestaCamp) respuestaCamp.value = '';

            alert(`Alumne desat correctament com a: ${resultatTest.toUpperCase()}`);
            carregarDadesPanell();
        } else {
            alert("Error del servidor: " + dades.error);
        }
    } catch (error) {
        console.error("Error a l'avaluar l'alumne:", error);
    }
}

function alumneSHePresentat() {
    console.log("L'alumne ha arribat a temps. Aturant compte enrere.");
    
    // 1. Aturem el setInterval immediatament perquè no segueixi restant temps
    clearInterval(temporitzador);
    
    // 2. Amaguem la caixa del compte enrere i la barra vermella
    const zonaTemps = document.getElementById('zona-temps');
    if (zonaTemps) zonaTemps.classList.add('hidden');
    
    // 3. Ens assegurem que la zona d'avaluació (Apte/No Apte) es queda visible
    const zonaAvalua = document.getElementById('zona-avalua');
    if (zonaAvalua) zonaAvalua.classList.remove('hidden');
    
    // El teu element opcional (si no existeix evitem que falli l'script)
    const txtEstat = document.getElementById('text-estat-torn');
    if (txtEstat) {
        txtEstat.innerHTML = "🟢 <span style='color:#16a34a;'>Alumne present a la taula</span>";
    }
}

// 1. Carrega les activitats del mòdul quan el panell s'actualitza
async function carregarLlistaActivitatsEval(id_modulo) {
    const selector = document.getElementById("eval-activitat");
    if (!selector || selector.options.length > 1) return; // Evitem sobreescriure si ja té opcions

    const res = await fetch(`api_gestion.php?accio=obtenir_activitats_eval`);
    const dades = await res.json();
    if(dades.success) {
        let html = '<option value="">-- Tria l\'Activitat --</option>';
        dades.activitats.forEach(act => {
            html += `<option value="${act.id_activitat_conceptual}">${act.nom_activitat} (${act.CodiModul_RA})</option>`;
        });
        selector.innerHTML = html;
    }
}

// 2. Es crida quan el professor canvia el selector d'activitat
async function carregarChecksDeLActivitat(id_activitat) {
    const contenidor = document.getElementById("contenidor-checks-dinamics");
    if(!id_activitat) {
        contenidor.innerHTML = "<p style='color:#64748b; font-size:0.9rem; text-align:center;'>Selecciona una activitat per avaluar els seus criteris.</p>";
        return;
    }

    const res = await fetch(`api_gestion.php?accio=obtenir_checks&id_act=${id_activitat}`);
    const dades = await res.json();
    if(dades.success) {
        if(dades.checks.length === 0) {
            contenidor.innerHTML = "<p style='color:#dc2626; font-size:0.9rem; text-align:center; font-weight:bold;'>⚠️ Aquesta activitat no té cap check definit. Es desarà directament amb un 10.</p>";
            return;
        }

        contenidor.innerHTML = "";
        dades.checks.forEach(chk => {
            const div = document.createElement("div");
            div.style.padding = "10px 0";
            div.style.borderBottom = "1px solid #e2e8f0";
            div.style.display = "flex";
            div.style.alignItems = "center";
            div.innerHTML = `
                <input type="checkbox" class="check-evaluacio-alum" value="${chk.id_check}" style="width:20px; height:20px; margin-right:15px; cursor:pointer;">
                <label style="cursor:pointer; font-weight:500; color:#334155;">${chk.titol_check}</label>
            `;
            contenidor.appendChild(div);
        });
    }
}

// 3. Substitut del botó d'avaluar antic. Recull checkboxes i envia a guardar amb degradació
async function finalitzarAval_Checks() {
    if (!idDelTurnoActual) { alert("No hi ha cap alumne actiu."); return; }
    
    const id_activitat = document.getElementById("eval-activitat").value;
    if(!id_activitat) { alert("Has d'escollir quina activitat estàs avaluant."); return; }

    // Recopilem quins checks han estat marcats com a passats
    const checkboxes = document.querySelectorAll(".check-evaluacio-alum");
    let llistaChecksResultats = [];
    
    checkboxes.forEach(cb => {
        llistaChecksResultats.push({
            id_check: cb.value,
            completat: cb.checked ? 1 : 0
        });
    });

    const dadesEnv = {
        id_turno: idDelTurnoActual,
        id_activitat: id_activitat,
        pregunta: document.getElementById('eval-pregunta').value,
        respuesta: document.getElementById('eval-respuesta').value,
        checks: llistaChecksResultats
    };

    try {
        aturarTemporitzador();
        const res = await fetch('api_gestion.php?accio=finalitzar_amb_checks', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(dadesEnv)
        });
        
        const result = await res.json();
        if(result.success) {
            alert(`Avaluació guardada permanentment! S'ha calculat la nota segons el pes de degradació per ordre d'entrega.`);
            document.getElementById('eval-pregunta').value = '';
            document.getElementById('eval-respuesta').value = '';
            document.getElementById("eval-activitat").value = '';
            document.getElementById("contenidor-checks-dinamics").innerHTML = "";
            carregarDadesPanell();
            netejarFormulariAvaluacio();

        } else {
            alert("Error: " + result.error);
        }
    } catch(e) { console.error(e); }
}

// js/gestion.js

// Es crida quan es canvia l'activitat al selector
function activarBotonsAvaluacio(id_activitat) {
    const blocDecisio = document.getElementById("bloc-decisio-inicial");
    const blocChecks = document.getElementById("bloc-avaluacio-checks");
    
    // Resetegem l'estat visual
    blocChecks.classList.add("hidden");
    
    if (id_activitat) {
        blocDecisio.classList.remove("hidden");
    } else {
        blocDecisio.classList.add("hidden");
    }
}

// CAS 1: L'alumne és NO APTE. Tanquem el torn immediatament sense desar cap check passat
async function avaluarTornNoApte() {
    if (!idDelTurnoActual) return;
    if (!confirm("Vols finalitzar el torn d'aquest alumne com a NO APTE?")) return;

    try {
        aturarTemporitzador();
        const res = await fetch('api_gestion.php?accio=finalitzar_no_apte', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id_turno: idDelTurnoActual })
        });
        
        const result = await res.json();
        if(result.success) {
            alert("Torn tancat com a No Apte.");
            netejarFormulariAvaluacio();
            carregarDadesPanell();
        }
    } catch(e) { console.error(e); }
}

// CAS 2: El professor prem APTE. Carregem i mostrem els checks reals.
async function mostrarBlocChecks() {
    const id_activitat = document.getElementById("eval-activitat").value;
    const contenidor = document.getElementById("contenidor-checks-dinamics");
    
    const res = await fetch(`api_gestion.php?accio=obtenir_checks&id_act=${id_activitat}`);
    const dades = await res.json();
    
    if(dades.success) {
        contenidor.innerHTML = "<h4 style='margin-top:0; color:#1e293b;'>Marqueu els checks superats:</h4>";
        
        if(dades.checks.length === 0) {
            contenidor.innerHTML += "<p style='color:#dc2626; font-size:0.9rem; font-weight:bold; text-align:center;'>⚠️ Aquesta activitat no té cap check associat. Sumarà un 10 automàtic.</p>";
        } else {
            dades.checks.forEach(chk => {
                const div = document.createElement("div");
                div.style.padding = "8px 0";
                div.style.display = "flex";
                div.style.alignItems = "center";
                div.innerHTML = `
                    <input type="checkbox" class="check-evaluacio-alum" value="${chk.id_check}" style="width:18px; height:18px; margin-right:12px; cursor:pointer;">
                    <label style='cursor:pointer;'>${chk.titol_check}</label>
                `;
                contenidor.appendChild(div);
            });
        }
        
        // Mostrem la zona de formularis/checks
        document.getElementById("bloc-avaluacio-checks").classList.remove("hidden");
    }
}

// Funció auxiliar per netejar la interfície un cop desat
function netejarFormulariAvaluacio() {
    document.getElementById('eval-pregunta').value = '';
    document.getElementById('eval-respuesta').value = '';
    document.getElementById("eval-activitat").value = '';
    document.getElementById("bloc-decisio-inicial").classList.add("hidden");
    document.getElementById("bloc-avaluacio-checks").classList.add("hidden");
    document.getElementById("contenidor-checks-dinamics").innerHTML = "";
}
