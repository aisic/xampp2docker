// ==========================================
// 🌍 ESTAT GLOBAL DE L'APLICACIÓ
// ==========================================
let idRaSeleccionat = null;           // Reté l'ID del Resultat d'Aprenentatge actiu al filtre
let idActivitatSeleccionada = null;   // Reté l'ID de l'activitat en procés de gestió de checks

// ==========================================
// 🚀 INICIALITZACIÓ I DISPARADORS D'EVENTS
// ==========================================
document.addEventListener("DOMContentLoaded", () => {
    // Càrrega dels mòduls inicials als selectors dropdown
    carregarModulsInicials();

    // Sincronització: Canvi de mòdul al formulari de creació d'activitats
    document.getElementById("select-modulo").addEventListener("change", (e) => {
        vincularRAsAlDropdown(e.target.value, "select-ra");
    });

    // Sincronització: Canvi de mòdul al filtre del llistat general
    document.getElementById("select-modulo").addEventListener("change", (e) => {
        vincularRAsAlDropdown(e.target.value, "filtre-ra");
    });

    // Filtre principal: Carregar la taula d'activitats en seleccionar un RA
    document.getElementById("filtre-ra").addEventListener("change", (e) => {
        idRaSeleccionat = e.target.value;
        carregarActivitatsDelRA(idRaSeleccionat);
    });

    // Intercepció de formularis
    document.getElementById("form-activitat").addEventListener("submit", crearActivitat);
    document.getElementById("form-check").addEventListener("submit", crearCheckCriteri);
});

// ==========================================
// 📂 GESTió DE MÒDULS I RAs (SELECTORS)
// ==========================================

/**
 * Sol·licita a l'API els mòduls docents disponibles i omple el selector principal.
 */
async function carregarModulsInicials() {
    const res = await fetch('api_activitats.php?accio=llistar_moduls');
    const dades = await res.json();
    if (dades.success) {
        const selectMod = document.getElementById("select-modulo");
        let html = '<option value="">-- Selecciona un Mòdul --</option>';
        dades.moduls.forEach(m => {
            html += `<option value="${m.id_modul}">[${m.cicle_formatiu}] ${m.nom_modul}</option>`;
        });
        selectMod.innerHTML = html;
    }
}

/**
 * Filtra i pobla dinàmicament els RAs associats al mòdul escollit en el selector indicat.
 */
async function vincularRAsAlDropdown(id_modul, elementId) {
    const selectTarget = document.getElementById(elementId);
    if (!id_modul) {
        selectTarget.innerHTML = '<option value="">Primer tria un mòdul...</option>';
        selectTarget.disabled = true;
        return;
    }

    const res = await fetch(`api_activitats.php?accio=llistar_ras&id_modul=${id_modul}`);
    const dades = await res.json();
    if (dades.success) {
        let html = '<option value="">-- Selecciona el RA --</option>';
        dades.ras.forEach(r => {
            html += `<option value="${r.id}">${r.CodiModul_RA} - ${r.nom_ra}</option>`;
        });
        selectTarget.innerHTML = html;
        selectTarget.disabled = false;
    }
}

// ==========================================
// 📝 GESTIÓ D'ACTIVITATS (PANELL SUPERIOR)
// ==========================================

/**
 * Carrega les activitats vinculades a un RA i en calcula el pes equitatiu (1/N)
 * fent ús d'estructures de memòria del DOM per evitar trencaments de text per apòstrofs.
 */
async function carregarActivitatsDelRA(id_ra) {
    const tbody = document.getElementById("taula-activitats-body");
    document.getElementById("bloc-crear-check").classList.add("hidden");

    if (!id_ra) {
        tbody.innerHTML = `<tr><td colspan="4" class="text-center">Selecciona un RA per verure les seves activitats estructurades.</td></tr>`;
        return;
    }

    const res = await fetch(`api_activitats.php?accio=llistar_activitats&id_ra=${id_ra}`);
    const dades = await res.json();
    if (dades.success) {
        tbody.innerHTML = "";
        const totalActivitats = dades.activitats.length;

        if (totalActivitats === 0) {
            tbody.innerHTML = `<tr><td colspan="4" class="text-center">Aquest RA no té cap activitat.</td></tr>`;
            return;
        }

        const pesPercentatge = (100 / totalActivitats).toFixed(1);

        dades.activitats.forEach(act => {
            const tr = document.createElement("tr");
            
            // Si estem re-renderitzant la taula i aquesta era l'activitat que s'editava, preservem l'estat visual actiu
            if (idActivitatSeleccionada === act.id_activitat_conceptual) {
                tr.className = "fila-activa";
            }

            tr.innerHTML = `
                <td><strong>${act.nom_activitat}</strong></td>
                <td><span class="badge-proporcio">1/${totalActivitats} (${pesPercentatge}%)</span></td>
                <td><span class="badge-comptador-checks">${act.total_checks} checks</span></td>
                <td class="zona-accions"></td>
            `;

            // Botó de gestió de Checks (Protegit nativament contra cometes/apòstrofs)
            const btnInfo = document.createElement("button");
            btnInfo.className = "btn btn-info";
            btnInfo.textContent = "⚙️ Checks";
            btnInfo.dataset.idAct = act.id_activitat_conceptual; // Guardem l'ID com a metada per a referències locals
            btnInfo.addEventListener("click", () => {
                seleccionarActivitatPerA_Checks(act.id_activitat_conceptual, act.nom_activitat);
            });

            // Botó d'eliminació d'activitats
            const btnDanger = document.createElement("button");
            btnDanger.className = "btn btn-danger";
            btnDanger.textContent = "🗑️";
            btnDanger.addEventListener("click", () => {
                eliminarActivitat(act.id_activitat_conceptual);
            });

            // Injecció de components
            const celdaAccions = tr.querySelector(".zona-accions");
            celdaAccions.appendChild(btnInfo);
            celdaAccions.appendChild(btnDanger);

            tbody.appendChild(tr);
        });
    }
}

/**
 * Envia les dades del formulari per registrar una nova activitat.
 */
async function crearActivitat(e) {
    e.preventDefault();
    const id_ra = document.getElementById("select-ra").value;
    const dades = {
        id_ra: id_ra,
        nom_activitat: document.getElementById("input-nom-activitat").value
    };

    const res = await fetch('api_activitats.php?accio=crear_activitat', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(dades)
    });

    const result = await res.json();
    if (result.success) {
        alert("Activitat creada i assignada correctament!");
        document.getElementById("input-nom-activitat").value = "";

        // Sincronitzem el llistat i forcem refresc de la taula de pesos de fons
        document.getElementById("filtre-ra").value = id_ra;
        idRaSeleccionat = id_ra;
        carregarActivitatsDelRA(id_ra);
    }
}

/**
 * Elimina de manera permanent una activitat acadèmica sol·licitant confirmació prèvia.
 */
async function eliminarActivitat(id_act) {
    if (!confirm("Estàs segur que vols eliminar aquesta activitat? Això reajustarà els pesos de les restants.")) return;

    const res = await fetch(`api_activitats.php?accio=eliminar_activitat&id=${id_act}`, { method: 'POST' });
    const result = await res.json();
    if (result.success) {
        // Si l'activitat eliminada era la que s'estava editant a baix, tanquem el formulari secundari
        if(idActivitatSeleccionada === id_act) {
            idActivitatSeleccionada = null;
            document.getElementById("bloc-crear-check").classList.add("hidden");
        }
        carregarActivitatsDelRA(idRaSeleccionat);
    }
}

// ==========================================
// ⚙️ GESTIÓ DE CHECKS (PANELL INFERIOR)
// ==========================================

/**
 * Activa l'entorn de treball de fons per a una activitat específica mantenint-la fixa en pantalla.
 */
async function seleccionarActivitatPerA_Checks(id_activitat, nom_activitat) {
    idActivitatSeleccionada = id_activitat; // Bloquegem l'ID a l'estat global
    
    document.getElementById("id-activitat-per-check").value = id_activitat;
    document.getElementById("nom-activitat-seleccionada").textContent = nom_activitat;
    document.getElementById("bloc-crear-check").classList.remove("hidden");
    
    marcarFilaActivaA_LaTaula(id_activitat);
    carregarChecksDeLActivitatAdmin(id_activitat);
}

/**
 * Obté i renderitza nativament mitjançant nodes els criteris individuals de validació.
 */
async function carregarChecksDeLActivitatAdmin(id_activitat) {
    const llista = document.getElementById("llista-checks-actuals");
    llista.innerHTML = "Carregant criteris...";

    const res = await fetch(`api_activitats.php?accio=llistar_checks_admin&id_act=${id_activitat}`);
    const dades = await res.json();

    if (dades.success) {
        llista.innerHTML = "";
        if (dades.checks.length === 0) {
            llista.innerHTML = "<li style='color:#64748b; font-style:italic;'>Encara no s'ha definit cap check per aquesta activitat. L'alumne rebrà un 10 automàtic si és Apte.</li>";
            return;
        }

        dades.checks.forEach(chk => {
            const li = document.createElement("li");
            li.style.marginBottom = "8px";
            li.style.display = "flex";
            li.style.justifyContent = "space-between";
            li.style.alignItems = "center";

            li.innerHTML = `<span>📌 ${chk.titol_check}</span>`;

            // Botó per esborrar criteris solts (Aïllament segur del DOM)
            const btnEliminarCriteri = document.createElement("button");
            btnEliminarCriteri.style.background = "none";
            btnEliminarCriteri.style.border = "none";
            btnEliminarCriteri.style.color = "#dc2626";
            btnEliminarCriteri.style.cursor = "pointer";
            btnEliminarCriteri.style.fontSize = "0.85rem";
            btnEliminarCriteri.textContent = "⚠️ Eliminar Criteri";

            btnEliminarCriteri.addEventListener("click", () => {
                eliminarCheck(chk.id_check, id_activitat);
            });

            li.appendChild(btnEliminarCriteri);
            llista.appendChild(li);
        });
    }
}

/**
 * Afegeix un nou check executant un refresc i aïllament parcial (evita parpellejos o tancament de flux).
 */
async function crearCheckCriteri(e) {
    e.preventDefault();
    if (!idActivitatSeleccionada) return; 
    
    const titol_check = document.getElementById("input-titol-check").value;

    const res = await fetch('api_activitats.php?accio=crear_check_admin', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ id_activitat: idActivitatSeleccionada, titol_check: titol_check })
    });

    const result = await res.json();
    if (result.success) {
        document.getElementById("input-titol-check").value = "";
        
        // Refresquem exclusivament la llista de checks conservant el focus a l'activitat actual
        await carregarChecksDeLActivitatAdmin(idActivitatSeleccionada);
        
        // Modificació directa en calent del comptador de la taula
        actualitzarComptadorChecksTaula(idActivitatSeleccionada, 1);
    }
}

/**
 * Esborra un criteri de validació sense alterar ni comprometre l'estat d'edició o obertura.
 */
async function eliminarCheck(id_check, id_activitat) {
    if (!confirm("Vols eliminar aquest criteri d'avaluació?")) return;

    const res = await fetch(`api_activitats.php?accio=eliminar_check_admin&id=${id_check}`, { method: 'POST' });
    const result = await res.json();
    if (result.success) {
        await carregarChecksDeLActivitatAdmin(id_activitat);
        actualitzarComptadorChecksTaula(id_activitat, -1);
    }
}

// ==========================================
// 🎨 UTILS VISUALS I DINÀMICA DEL DOM LOCAL
// ==========================================

/**
 * Assigna la classe de realçat de color CSS únicament a la fila de la taula que s'està editant.
 */
function marcarFilaActivaA_LaTaula(id_activitat) {
    const files = document.querySelectorAll("#taula-activitats-body tr");
    files.forEach(f => f.classList.remove("fila-activa"));
    
    const botons = document.querySelectorAll("#taula-activitats-body .btn-info");
    botons.forEach(b => {
        if (b.dataset.idAct == id_activitat) {
            const filaPare = b.closest("tr");
            if (filaPare) filaPare.classList.add("fila-activa");
        }
    });
}

/**
 * Modifica dinàmicament el comptador numèric de checks a la vista HTML de fons sense necessitat de fer crides API pesades.
 */
function actualitzarComptadorChecksTaula(id_activitat, canvi) {
    const botons = document.querySelectorAll("#taula-activitats-body .btn-info");
    botons.forEach(b => {
        if (b.dataset.idAct == id_activitat) {
            const filaPare = b.closest("tr");
            const badge = filaPare.querySelector(".badge-comptador-checks");
            if (badge) {
                let numeroActual = parseInt(badge.textContent) || 0;
                let nouNumero = numeroActual + canvi;
                if (nouNumero < 0) nouNumero = 0;
                badge.textContent = `${nouNumero} checks`;
            }
        }
    });
}