<?php
require_once 'seguridad_profesor.php'; // Protegeix la vista HTML
?>
<!DOCTYPE html>
<html lang="ca">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panell de Gestió - Professor</title>
    <link href="css/gestion.css" rel="stylesheet">
</head>
<body>

<div class="wrapper">
    <header>
        <div>
            <h1 id="nom-asignatura">Cargando asignatura...</h1>
            <p style="font-size:0.9rem; color:#64748b;">Gestió de l'aula en temps real</p>
        </div>
        <div style="display: flex; gap: 10px;">
            <button id="btn-lock" class="btn btn-toggle" onclick="toggleCua()">Carregant estat...</button>
	    <a href="estadisticas.php" class="btn btn-admin">📊 Estadístiques</a>
	    <a href="logout.php" class="btn" style="background-color: #334155; color: white; border: 1px solid #475569;" onmouseover="this.style.backgroundColor='#1e293b'" onmouseout="this.style.backgroundColor='#334155'">
        🚪 Tancar Sessió
    </a>
        </div>
    </header>

    <div style="margin-bottom: 25px;">
        <button class="btn btn-success" onclick="cridarSiguiente()">🔔 CRIDAR SEGÜENT ALUMNE</button>
    </div>

    <div class="grid">
<div class="card">
    <div class="card-title">Atenent ara mateix</div>
    <div class="big-info" id="num-actual" style="color: #10b981;">--</div>
    <p id="nom-actual" style="font-size: 1.2rem; font-weight: 500; color: #475569; margin-bottom: 15px;">Buscant...</p>
    
    <div id="zona-temps" style="margin-bottom: 20px; display: none;">
        <p style="font-size: 0.9rem; color: #b91c1c; font-weight: bold;">Temps restant per presentar-se: <span id="comptador-enrere">20</span>s</p>
        <div style="background: #e2e8f0; border-radius: 10px; height: 10px; width: 100%; overflow: hidden; margin-top: 5px;">
            <div id="barra-progres" style="background: #dc2626; height: 100%; width: 100%; transition: width 1s linear;"></div>
        </div>
    </div>

    <div id="zona-avalua" style="display: flex; gap: 10px; justify-content: center; display: none;">
        <button class="btn" style="background-color: #059669; color: white; flex: 1;" onclick="avaluaAlumne('apte')">✅ APTE</button>
        <button class="btn" style="background-color: #ea580c; color: white; flex: 1;" onclick="avaluaAlumne('no_apte')">❌ NO APTE</button>
    </div>
</div>
        <div class="card">
            <div class="card-title">Alumnes en espera</div>
            <div class="big-info" id="total-espera" style="color: #f59e0b;">0</div>
            <p style="color: #64748b;">alumnes a la cua d'espera</p>
        </div>
    </div>

    <div class="card list-card">
        <div class="card-title" style="margin-bottom: 10px;">Proxims alumnes ordenats a la cua</div>
        <div id="llista-alumnes">
            </div>
    </div>
</div>

<script>
    let cuaObertaActual = true;
    let temporitzador;
    let tempsRestant = 20;
    let alumneActualId = null;

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
            
            if (resultat.success && !resultat.quedaven_alumnes) {
                alert("La cua està buida. No hi ha més alumnes per atendre!");
            }
            carregarDadesPanell();
        } catch (error) {
            console.error("Error al cridar al següent alumne:", error);
        }
    }

    // Execució inicial i bucle de refresc automàtic (cada 4 segons)

// Modifiquem la funció existent carregarDadesPanell per gestionar la vista dels botons
async function carregarDadesPanell() {
    try {
        const resposta = await fetch('api_gestion.php?accio=estat');
        // REVISEMA AIXÒ: Assegurem que guardem a la variable 'dades'
        const dades = await resposta.json(); 

        if(!dades.success) {
            console.error("L'API ha retornat un error:", dades.error);
            return;
        }

        // 1. Actualitzar textos bàsics
        document.getElementById('nom-asignatura').textContent = dades.asignatura;
        
        const numActual = dades.atendiendo.turno_numero;
        document.getElementById('num-actual').textContent = numActual;
        document.getElementById('nom-actual').textContent = dades.atendiendo.nombre_alumno;
        document.getElementById('total-espera').textContent = dades.en_espera;

        // 2. Mostrar o amagar zona d'avaluació de test
        if (numActual !== '--') {
            document.getElementById('zona-avalua').style.display = 'flex';
        } else {
            document.getElementById('zona-avalua').style.display = 'none';
            aturarTemporitzador();
        }

        // 3. ACTUALITZAR EL BOTÓ DE BLOCATGE (AQUÍ ES QUEDAVA ENCALLAT)
        cuaObertaActual = (dades.cola_abierta == 1);
        const btnLock = document.getElementById('btn-lock');
        
        if (cuaObertaActual) {
            btnLock.textContent = "🔒 Tancar Cua Alumnes";
            btnLock.style.backgroundColor = "#dc2626";
        } else {
            btnLock.textContent = "🔓 Obrir Cua Alumnes";
            btnLock.style.backgroundColor = "#16a34a";
        }

        // 4. Actualitzar la llista visual de la cua
        const contenidorLlista = document.getElementById('llista-alumnes');
        contenidorLlista.innerHTML = "";

        if(dades.cua_llista.length === 0) {
            contenidorLlista.innerHTML = "<p style='color:#94a3b8; padding: 10px;'>No hi ha ningú esperant ara mateix.</p>";
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

    } catch (error) {
        console.error("Error crític al carregar les dades de gestió:", error);
    }
}
// Control del compte enrere de 20 segons
function iniciarTemporitzador() {
    aturarTemporitzador(); // Netejar si n'hi havia un corrent
    tempsRestant = 20;
    document.getElementById('zona-temps').style.style.display = 'block';
    document.getElementById('comptador-enrere').textContent = tempsRestant;
    document.getElementById('barra-progres').style.width = '100%';

    temporitzador = setInterval(() => {
        tempsRestant--;
        document.getElementById('comptador-enrere').textContent = tempsRestant;
        document.getElementById('barra-progres').style.width = `${(tempsRestant / 20) * 100}%`;

        if (tempsRestant <= 0) {
            aturarTemporitzador();
            console.log("Temps esgotat. Saltant al següent alumne...");
            cridarSiguiente(); // Automatització: salta de torn sol
        }
    }, 1000);
}

function aturarTemporitzador() {
    clearInterval(temporitzador);
    document.getElementById('zona-temps').style.display = 'none';
}

// Modificació del botó cridarSiguiente per activar el compte enrere
async function cridarSiguiente() {
    try {
        const resposta = await fetch('api_gestion.php?accio=siguiente', { method: 'POST' });
        const resultat = await resposta.json();

        if (resultat.success) {
            if (resultat.quedaven_alumnes) {
                iniciarTemporitzador(); // Iniciem els 20 segons en cridar-lo
            } else {
                alert("La cua està buida.");
                aturarTemporitzador();
            }
        }
        carregarDadesPanell();
    } catch (error) {
        console.error(error);
    }
}

// Nova funció per enviar la qualificació del test (Apte / No Apte)
async function avaluaAlumne(resultatTest) {
    try {
        aturarTemporitzador(); // L'alumne ja ha contestat, aturem el compte enrere

        const resposta = await fetch('api_gestion.php?accio=marcar_resultat', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ resultat: resultatTest })
        });

        const dades = await resposta.json();
        if(dades.success) {
            alert(`Alumne desat com a: ${resultatTest.toUpperCase()}`);
            carregarDadesPanell();
        }
    } catch (error) {
        console.error(error);
    }
}

    carregarDadesPanell();
    setInterval(carregarDadesPanell, 4000);
</script>
</body>
</html>

