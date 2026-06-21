<?php
// seguridad_profesor.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Llista de correus electrònics autoritzats com a professors
$profesores_autorizados = [
    'isaac.gonzalo@itb.cat',
    'coordinacio@itb.cat' // Pots afegir-ne més
];

// 2. Si no hi ha sessió de Google Iniciada, al login de cap d'un docent
if (!isset($_SESSION['alumno_email'])) {
    header("Location: login_profesor.php");
    exit;
}

$email_actual = $_SESSION['alumno_email'];
$nombre_actual = $_SESSION['alumno_nombre'];

// 3. 🚨 COMPROVACIÓ CRÍTICA: És un professor autoritzat?
if (!in_array($email_actual, $profesores_autorizados)) {
    
    // És un alumne intentant entrar! Registrem la incidència a la Base de Dades
    $host = 'db';
    $db   = 'gestion_colas';
    $user = 'root';
    $pass = 'root'; // La teva contrasenya de MariaDB
    
    try {
        $dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
        $pdo_seg = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'Desconeguda';
        
        $stmt = $pdo_seg->prepare("
            INSERT INTO incidencias_acceso (email_infractor, nombre_infractor, ip_origen, pagina_intentada) 
            VALUES (?, ?, ?, 'gestion.php')
        ");
        $stmt->execute([$email_actual, $nombre_actual, $ip]);
        
    } catch (\PDOException $e) {
        // Fallada silenciosa de registre per no bloquejar la resposta, o loguejar-ho en un fitxer
    }

    // Destruïm la sessió d'aquest alumne per seguretat i el fem fora
    session_unset();
    session_destroy();
    
    // El redirigim a una pàgina d'error d'accés denegat
    header("Location: login_profesor.php?error=no_autorizado");
    exit;
}

