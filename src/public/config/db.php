<?php
// conexion.php

// 1. Funció senzilla per llegir el fitxer .env sense necessitat de llibreries externes (Composer)
function carregarEnv($ruta) {
    if (!file_exists($ruta)) {
        return;
    }
    $linies = file($ruta, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($linies as $linia) {
        if (strpos(trim($linia), '#') === 0) continue; // Salta comentaris
        list($nom, $valor) = explode('=', $linia, 2);
        $_ENV[trim($nom)] = trim($valor);
    }
}

// Carreguem les variables d'entorn
carregarEnv(__DIR__ . '/.env');

// 2. Intentem establir la connexió PDO
try {
    $host     = $_ENV['DB_HOST'] ?? 'localhost';
    $dbname   = $_ENV['DB_NAME'] ?? '';
    $user     = $_ENV['DB_USER'] ?? '';
    $password = $_ENV['DB_PASSWORD'] ?? '';
    $charset  = $_ENV['DB_CHARSET'] ?? 'utf8mb4';

    // Creem l'objecte de connexió $pdo global

    $dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $pdo = new PDO($dsn, $user, $password, [
        // 1. Activa les excepcions per a errors de SQL (així pots fer un try-catch)
         PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
         // 2. Retorna els registres com a arrays associatius per defecte
         PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
         // 3. DESACTIVA L'EMULACIÓ (Crucial per a la seguretat real de MySQL)
         PDO::ATTR_EMULATE_PREPARES   => false,
     ]);
     } catch (\PDOException $e) {
    // Per seguretat extrema, mai mostrem l'$e->getMessage() als alumnes perque revelaria rutes internes
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Error de connexió intern de la base de dades.']);
    exit;
}