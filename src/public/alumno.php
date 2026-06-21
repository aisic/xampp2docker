<?php
session_start();

// var_dump($_SESSION);
// Si no està loguejat amb Google, el podríes redirigir aquí al login
if (!isset($_SESSION['alumno_email'])) {
    header("Location: login.php");
    exit;
}

$asignatura_nombre = "C037"; // Això vindrà de la teva BD
?>
<!DOCTYPE html>
<html lang="ca">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cua d'Alumnes - El meu Torn</title>
    <link href="css/alumno.css" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Roboto, sans-serif; background-color: #f3f4f6; color: #1f2937; padding: 20px; }
        .container { max-width: 500px; margin: 30px auto; background: white; padding: 30px; border-radius: 15px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); text-align: center; }
        h1 { color: #2563eb; font-size: 1.8rem; margin-bottom: 5px; }
        h2 { color: #4b5563; font-size: 1.1rem; margin-bottom: 25px; }
        .user-info { font-size: 0.9rem; color: #6b7280; margin-bottom: 20px; }
        .btn { display: inline-block; width: 100%; padding: 15px; border: none; border-radius: 10px; font-size: 1.1rem; font-weight: bold; cursor: pointer; transition: background 0.2s; margin-top: 15px; }
        .btn-primary { background-color: #2563eb; color: white; }
        .btn-primary:hover { background-color: #1d4ed8; }
        .btn-danger { background-color: #dc2626; color: white; }
        .btn-danger:hover { background-color: #b91c1c; }
        .status-box { background: #eff6ff; border: 2px solid #bfdbfe; padding: 20px; border-radius: 12px; margin-bottom: 20px; }
        .big-number { font-size: 3.5rem; font-weight: bold; color: #1e3a8a; margin: 10px 0; }
        .hidden { display: none; }
    </style>
</head>
<body>

<div class="container">
    <h1><?= htmlspecialchars($asignatura_nombre) ?></h1>
<div class="user-info" style="display: flex; justify-content: space-between; align-items: center; background: #f8fafc; padding: 10px 15px; border-radius: 8px; margin-bottom: 20px;">
    <span>Connectat com: <strong><?= htmlspecialchars($_SESSION['alumno_nombre']) ?></strong></span>
    <a href="logout.php" style="color: #dc2626; text-decoration: none; font-weight: bold; font-size: 0.85rem; border: 1px solid #fca5a5; padding: 4px 8px; border-radius: 6px; background: #fee2e2; transition: background 0.2s;" onmouseover="this.style.background='#fecaca'" onmouseout="this.style.background='#fee2e2'">
        🚪 Sortir
    </a>
</div>

    <div id="seccio-apuntarse" class="hidden">
        <p style="margin-bottom: 20px; color: #4b5563;">Actualment no estàs a la cua d'espera d'aquesta assignatura.</p>
        <button class="btn btn-primary" onclick="accionarCua('apuntarse')">Apuntar-me a la Cua</button>
    </div>

    <div id="seccio-espera" class="hidden">
        <div class="status-box">
            <p id="text-estat-torn">El teu número de torn és:</p>
            <div class="big-number" id="el-meu-torn">--</div>
        </div>
        
        <div style="margin: 20px 0; text-align: left; background: #f9fafb; padding: 15px; border-radius: 8px;">
	    <p style="margin-bottom: 8px;">Status de la cua:</p>
<div id="estat-cua-contenidor" style="text-align: center; padding: 12px; border-radius: 8px; margin-bottom: 20px; font-weight: bold; font-size: 1.1rem; background-color: #f1f5f9; color: #475569;">
    <span id="estat-cua-text">Comprovant estat de la cua...</span>
</div>
            <ul>
                <li>Alumnes per davant teu: <strong id="alumnes-davant">--</strong></li>
                <li>Temps estimat d'espera: <strong id="temps-estimat">-- min</strong></li>
            </ul>
        </div>

        <button class="btn btn-danger" onclick="accionarCua('desapuntarse')">Sortir de la Cua</button>
    </div>
</div>

<script>
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
</script>
</body>
</html>

