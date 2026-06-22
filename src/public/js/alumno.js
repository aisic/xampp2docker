    let jaNotificat = false;

    // Demanar permís per a les notificacions només entrar
    if (Notification.permission === "default") {
        Notification.requestPermission();
    }

    async function comprovarEstatCua() {
        try {
            const resposta = await fetch('api_alumno.php?accio=estat');
            const dades = await resposta.json();
        // --- NOVA LÒGICA PER MOSTRAR SI ESTÀ OBERTA O TANCADA ---
        const contenidorEstat = document.getElementById('estat-cua-contenidor');
        const textEstat = document.getElementById('estat-cua-text');
        const botoApuntar = document.getElementById('btn-apuntar'); // El teu botó d'agafar torn

        if (dades.cola_abierta === 1) {
            // Cua Oberta
            textEstat.textContent = "🟢 LA CUA ESTÀ OBERTA (Pots demanar torn)";
            contenidorEstat.style.backgroundColor = "#e6f4ea";
            contenidorEstat.style.color = "#137333";

            // Si el botó existeix (perquè l'alumne encara no té torn), el deixem actiu
            if (botoApuntar) {
                botoApuntar.disabled = false;
                botoApuntar.style.opacity = "1";
                botoApuntar.style.cursor = "pointer";
            }
        } else {
            // Cua Tancada
            textEstat.textContent = "🔴 LA CUA ESTÀ TANCADA PEL PROFESSOR";
            contenidorEstat.style.backgroundColor = "#fce8e6";
            contenidorEstat.style.color = "#c5221f";

            // Desactivem el botó perquè no puguin fer clic
            if (botoApuntar) {
                botoApuntar.disabled = true;
                botoApuntar.style.opacity = "0.5";
                botoApuntar.style.cursor = "not-allowed";
                botoApuntar.innerText = "🔒 Cua tancada temporalment";
            }
        }

            if (dades.en_cua) {
                document.getElementById('seccio-apuntarse').classList.add('hidden');
                document.getElementById('seccio-espera').classList.remove('hidden');
                
                document.getElementById('el-meu-torn').textContent = dades.el_meu_torn;
                document.getElementById('alumnes-davant').textContent = dades.alumnes_davant;
                document.getElementById('temps-estimat').textContent = dades.temps_estimat + " min";

                // Llençar notificació si és el seu torn (estat = 'atendiendo')
                if (dades.estat_actual === 'atendiendo') {
                    document.getElementById('text-estat-torn').innerHTML = "<span style='color:#15803d; font-weight:bold;'>¡ÉS EL TEU TORN! Passa al lloc del professor</span>";
                    llencarNotificacio();
                } else {
                    document.getElementById('text-estat-torn').textContent = "El teu número de torn és:";
                    jaNotificat = false; // Resetegem si canvia d'estat
                }

            } else {
                document.getElementById('seccio-apuntarse').classList.remove('hidden');
                document.getElementById('seccio-espera').classList.add('hidden');
                jaNotificat = false;
            }
        } catch (error) {
            console.error("Error en la connexió", error);
        }
    }

    // Dins de la funció que rep el JSON de l'api_alumno.php:
    async function accionarCua(accio) {
        try {
            const resposta = await fetch(`api_alumno.php?accio=${accio}`, { method: 'POST' });
            await resposta.json();
            comprovarEstatCua(); // Forcem refresc immediat
        } catch (error) {
            console.error("Error al processar acció", error);
        }
    }

    // Lògica per apuntarse a la cua quan es prem el botó
    document.addEventListener("DOMContentLoaded", () => { 
       const btnApuntar = document.getElementById("apuntarse-btn");

        if (btnApuntar) {
            btnApuntar.addEventListener("click", async () => {
                
                // 🔔 SOLUCIÓ: Demanem el permís AQUÍ, aprofitant el clic de l'usuari
                if ("Notification" in window && Notification.permission === "default") {
                    await Notification.requestPermission();
                }

                // A continuació, la teva lògica de demanar torn (el fetch a l'API)
                //apuntarAlumneALaCua(); 
                await accionarCua("apuntarse");

            });
        }
        const btnDesapuntar = document.getElementById("desapuntarse-btn");
    if (btnDesapuntar) {
        btnDesapuntar.addEventListener("click", async () => {
            //if (confirm("Estàs segur que vols sortir de la cua d'espera?")) {
                await accionarCua("desapuntarse");
            //}
        });
    }
    });

    //document.addEventListener("DOMContentLoaded", () => {
    //    const boto = document.getElementById("apuntarse-btn");
    //    if (boto) {
    //        boto.addEventListener("click", accionarCua.bind(null, "apuntarse"));
    //    }
    //});

function descarregarCSV() {
    // La teva lògica de descàrrega aquí...
    console.log("Descarregant...");
}
    function llencarNotificacio() {
        if (!jaNotificat && Notification.permission === "granted") {
            new Notification("¡És el teu torn!", {
                body: "El professor et crida per a la revisió.",
                icon: "https://cdn-icons-png.flaticon.com/512/179/179133.png" // Opcional
            });
            jaNotificat = true; // Evita que soni/surti cada 3 segons de bucle
        }
    }

    // Polling cada 3 segons
    comprovarEstatCua();
    setInterval(comprovarEstatCua, 3000);