<!DOCTYPE html>
<html lang="ca">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Cua d'Alumnes</title>
    <link href="css/login.css" rel="stylesheet">
    <script src="https://accounts.google.com/gsi/client" async defer></script>
</head>
<body>

<div class="login-card">
    <h1>Accés alumne</h1>
    <p>Identifica't amb el teu correu del centre per demanar el teu torn.</p>
    
    <div id="error" class="error-msg"></div>

    <div id="g_id_onload"
         data-client_id="569428212376-8bnfus0c5tal7q4d45j9c9sl8t8064oj.apps.googleusercontent.com"
         data-callback="handleCredentialResponse"
         data-auto_prompt="false">
    </div>
    <div class="g_id_signin" data-type="standard" data-size="large" data-theme="filled_blue"></div>

    <!--<div class="g_id_signin"
         data-type="standard"
         data-size="large"
         data-theme="outline"
         data-text="sign_in_with"
         data-shape="semibold"
         data-logo_alignment="center">
    </div>-->
</div>

<script>
    // Aquesta funció s'executa automàticament quan l'alumne fa login correctament a Google
    async function handleCredentialResponse(response) {
        try {
            // Enviem el token rebut de Google al nostre backend de PHP per validar-lo
            const resposta = await fetch('api_oauth.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ token: response.credential })
            });
            
            const resultat = await resposta.json();
            
            if (resultat.success) {
                // Si el PHP dona el vistiplau, redirigim a la pantalla de la cua
                window.location.href = 'alumno.php';
            } else {
                const errorDiv = document.getElementById('error');
                errorDiv.textContent = resultat.error || 'Error en l\'autenticació';
                errorDiv.style.display = 'block';
            }
        } catch (e) {
            console.error("Error en el procés de login:", e);
        }
    }
</script>
</body>
</html>

