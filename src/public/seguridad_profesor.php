<?php
// seguridad_profesor.php

// 1. Assegurem que la sessió estigui iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Si ni tan sols està loguejat amb Google, directament al login
if (!isset($_SESSION['alumno_email'])) {
    header("Location: login_profesor.php");
    exit;
}

// 3. Connectem a la base de dades de manera segura
require_once __DIR__ . '/config/db.php';

try {
    $pdo_seguridad = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false, // Seguretat extra contra SQLi
    ]);
} catch (\PDOException $e) {
    echo json_encode(['error' => 'Error de connexió de seguretat']);
    exit;
}

// 4. Busquem el correu de la sessió a la taula de professors autoritzats
$email_a_comprovar = $_SESSION['alumno_email'];

$stmt_seg = $pdo_seguridad->prepare("SELECT COUNT(*) FROM profesores WHERE email = ?");
$stmt_seg->execute([$email_a_comprovar]);
$es_profesor = $stmt_seg->fetchColumn();

// 5. Si el recompte és 0, significa que no és un professor autoritzat
if ($es_profesor == 0) {
    session_unset();
    session_destroy();
    // Redirigim a la pantalla de l'alumne o mostrem un error d'accés denegat
    header("Location: login_profesor.php?error=no_autoritzat");
    exit;
}

// Si passa d'aquí, el professor està validat correctament per la BD i el codi de la pàgina continua...


// 3. 🚨 COMPROVACIÓ CRÍTICA: És un professor autoritzat?
//if (!in_array($email_actual, $profesores_autorizados)) {
 //   
    // És un alumne intentant entrar! Registrem la incidència a la Base de Dades
//    $host = 'db';
 //   $db   = 'gestion_colas';
 //   $user = 'root';
 //   $pass = 'root'; // La teva contrasenya de MariaDB
    
//    try {
 //       $dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
 //       $pdo_seg = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        
 //       $ip = $_SERVER['REMOTE_ADDR'] ?? 'Desconeguda';
        
 //       $stmt = $pdo_seg->prepare("
 //           INSERT INTO incidencias_acceso (email_infractor, nombre_infractor, ip_origen, pagina_intentada) 
  //          VALUES (?, ?, ?, 'gestion.php')
   //     ");
    //    $stmt->execute([$email_actual, $nombre_actual, $ip]);
        
 //   } catch (\PDOException $e) {
  //      // Fallada silenciosa de registre per no bloquejar la resposta, o loguejar-ho en un fitxer
   // }

    // Destruïm la sessió d'aquest alumne per seguretat i el fem fora
   // session_unset();
   // session_destroy();
    
    // El redirigim a una pàgina d'error d'accés denegat
   // header("Location: login_profesor.php?error=no_autorizado");
//    exit;
//}

