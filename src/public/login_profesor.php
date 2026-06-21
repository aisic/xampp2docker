<!DOCTYPE html>
<html lang="ca">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accés Docent - Gestió de Cues</title>
    <link href="css/login.css" rel="stylesheet">
    <script src="https://accounts.google.com/gsi/client" async defer></script>
</head>
<body>

<div class="login-card">
    <h1>Accés Professors</h1>
    <p>Inicia sessió amb el teu compte docent per gestionar la cua d'alumnes.</p>
    
    <?php if (isset($_GET['error']) && $_GET['error'] === 'no_autorizado'): ?>
        <div class="danger-msg">
            ⚠️ ACCÉS DENEGAT.<br>Aquest panell és només per a professors. L'intent d'accés ha estat registrat.
        </div>
    <?php endif; ?>

    <div id="g_id_onload"
         data-client_id="EL_TEU_CLIENT_ID_DE_GOOGLE.apps.googleusercontent.com"
         data-callback="handleCredentialResponse"
         data-auto_prompt="false">
    </div>
    <div class="g_id_signin" data-type="standard" data-size="large" data-theme="filled_blue"></div>
</div>

<script>
    async function handleCredentialResponse(response) {
        try {
            // Reutilitzem l'api_oauth.php que ja valida el token i desa la sessió
            const resposta = await fetch('api_oauth.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ token: response.credential })
            });
            const resultat = await resposta.json();
            
            if (resultat.success) {
                // Si el login és correcte, anem a gestió (on 'seguridad_profesor.php' farà el segon filtre)
                window.location.href = 'gestion.php';
            } else {
                alert("Error en la connexió amb Google");
            }
        } catch (e) {
            console.error(e);
        }
    }
</script>
</body>
</html>

