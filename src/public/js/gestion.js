let cuaObertaActual = true;
let temporitzador;
let tempsRestant = 20;

document.addEventListener("DOMContentLoaded", () => {
    console.log("Panell de gestió inicialitzat.");

    // Associem els event listeners als botons
    const btnLock = document.getElementById('btn-lock');
    if (btnLock) btnLock.addEventListener("click", toggleCua);

    const btnSiguiente = document.getElementById('btn-siguiente');
    if (btnSiguiente) btnSiguiente.addEventListener("click", cridarSiguiente);

    const btnApte = document.getElementById('btn-apte');
    if (btnApte) btnApte.addEventListener("click", () => avaluaAlumne('apte'));

    const btnNoApte = document.getElementById('btn-no-apte');
    if (btnNoApte) btnNoApte.addEventListener("click", () => avaluaAlumne('no_apte'));

    // Càrrega inicial de dades i configuració del bucle de refresc (4 segons)
    carregarDadesPanell();
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

        // 2. Mostrar o amagar zona d'avaluació de test de manera modular
        const zonaAvalua = document.getElementById('zona-avalua');
        if (numActual !== '--') {
            zonaAvalua.classList.remove('hidden');
        } else {
            zonaAvalua.classList.add('hidden');
            aturarTemporitzador();
        }

        // 3. Actualitzar botó de bloqueig/obertura de cua
        cuaObertaActual = (dades.cola_abierta == 1);
        const btnLock = document.getElementById('btn-lock');
        
        if (btnLock) {
            if (cuaObertaActual) {
                btnLock.textContent = "🔒 Tancar Cua Alumnes";
                btnLock.style.backgroundColor = "#dc2626"; // Mantenim el canvi d'estat de color dinàmic
            } else {
                btnLock.textContent = "🔓 Obrir Cua Alumnes";
                btnLock.style.backgroundColor = "#16a34a";
            }
        }

        // 4. Actualitzar la llista visual de la cua
        const contenidorLlista = document.getElementById('llista-alumnes');
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
    zonaTemps.classList.remove('hidden');
    
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

async function avaluaAlumne(resultatTest) {
    try {
        aturarTemporitzador(); 

        const resposta = await fetch('api_gestion.php?accio=marcar_resultat', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ resultat: resultatTest })
        });

        const dades = await resposta.json();
        if (dades.success) {
            alert(`Alumne desat com a: ${resultatTest.toUpperCase()}`);
            carregarDadesPanell();
        }
    } catch (error) {
        console.error("Error a l'avaluar l'alumne:", error);
    }
}