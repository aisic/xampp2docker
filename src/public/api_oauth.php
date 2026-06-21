<?php
// api_oauth.php
session_start();
header('Content-Type: application/json');

// Rebre les dades JSON del Frontend
$input = json_decode(file_get_contents('php://input'), true);
$token = $input['token'] ?? '';

if (empty($token)) {
    echo json_encode(['success' => false, 'error' => 'Manca el token de seguretat.']);
    exit;
}

// 1. Preguntar a Google si el Token és vàlid aportant la credencial
$url = "https://oauth2.googleapis.com/tokeninfo?id_token=" . urlencode($token);
$resposta_google = @file_get_contents($url);

if ($resposta_google === false) {
    echo json_encode(['success' => false, 'error' => 'Token de Google invàlid o caducat.']);
    exit;
}

$dades_usuari = json_decode($resposta_google, true);

// 2. Validar que el destí del token sigui el nostre Client ID (Seguretat crítica)
$el_meu_client_id = "569428212376-8bnfus0c5tal7q4d45j9c9sl8t8064oj.apps.googleusercontent.com";
if ($dades_usuari['aud'] !== $el_meu_client_id) {
    echo json_encode(['success' => false, 'error' => 'Petició no autoritzada.']);
    exit;
}

// 3. ⚠️ FILTRE DE DOMINI DEL CENTRE (Opcional però molt recomanat)
// Si el teu centre és 'itb.cat', només deixem passar correus que acabin així:
$domini_autoritzat = "itb.cat"; // Canvia-ho pel teu domini real
if (isset($dades_usuari['hd']) && $dades_usuari['hd'] !== $domini_autoritzat) {
    echo json_encode(['success' => false, 'error' => "Només permès per a comptes de @$domini_autoritzat"]);
    exit;
}

// 4. Tot és correcte: Guardem les dades de l'alumne a la Sessió de PHP
$_SESSION['alumno_email']  = $dades_usuari['email'];
$_SESSION['alumno_nombre'] = $dades_usuari['name']; // Nom complet (p.ex. Joan Garcia)

echo json_encode(['success' => true]);

