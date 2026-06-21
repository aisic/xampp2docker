<!DOCTYPE html>
<html lang="ca">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accés Docent - Gestió de Cues</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #0f172a; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .login-card { background: white; padding: 40px; border-radius: 12px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.3); text-align: center; max-width: 400px; width: 100%; }
        h1 { color: #1e3a8a; font-size: 1.8rem; margin-bottom: 10px; }
        p { color: #475569; margin-bottom: 30px; font-size: 0.95rem; }
        .danger-msg { color: #b91c1c; background: #fee2e2; padding: 12px; border-radius: 6px; margin-bottom: 20px; font-size: 0.9rem; font-weight: bold; border: 1px solid #fca5a5; }
    </style>
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

