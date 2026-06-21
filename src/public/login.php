<!DOCTYPE html>
<html lang="ca">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Cua d'Alumnes</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f3f4f6; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .login-card { background: white; padding: 40px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); text-align: center; max-width: 400px; width: 100%; }
        h1 { color: #2563eb; font-size: 1.8rem; margin-bottom: 10px; }
        p { color: #6b7280; margin-bottom: 30px; font-size: 0.95rem; }
        .error-msg { color: #dc2626; background: #fee2e2; padding: 10px; border-radius: 6px; margin-bottom: 20px; font-size: 0.9rem; display: none; }
    </style>
    <script src="https://accounts.google.com/gsi/client" async defer></script>
</head>
<body>

<div class="login-card">
    <h1>Gestió de Cues</h1>
    <p>Identifica't amb el teu correu del centre per demanar el teu torn.</p>
    
    <div id="error" class="error-msg"></div>

    <div id="g_id_onload"
         data-client_id="569428212376-8bnfus0c5tal7q4d45j9c9sl8t8064oj.apps.googleusercontent.com"
         data-callback="handleCredentialResponse"
         data-auto_prompt="false">
    </div>
    <div class="g_id_signin"
         data-type="standard"
         data-size="large"
         data-theme="outline"
         data-text="sign_in_with"
         data-shape="semibold"
         data-logo_alignment="left">
    </div>
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

