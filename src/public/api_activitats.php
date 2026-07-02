<?php
// api_activitats.php
session_start();
require_once 'seguridad_profesor.php';
header('Content-Type: application/json');
require_once __DIR__ . '/config/db.php';

try {
    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (\PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Error de BD']); exit;
}

$accio = $_GET['accio'] ?? '';

if ($accio === 'llistar_moduls') {
    $stmt = $pdo->query("SELECT id_modul, nom_modul, cicle_formatiu FROM moduls ORDER BY nom_modul ASC");
    echo json_encode(['success' => true, 'moduls' => $stmt->fetchAll()]);
    exit;
}

if ($accio === 'llistar_ras') {
    $id_modul = intval($_GET['id_modul'] ?? 0);
    $stmt = $pdo->prepare("SELECT id, CodiModul_RA, nom_ra FROM RAs WHERE id_modul = ?");
    $stmt->execute([$id_modul]);
    echo json_encode(['success' => true, 'ras' => $stmt->fetchAll()]);
    exit;
}

// Dins de api_activitats.php

// 1. MODIFICAT: Afegim un COUNT per saber quants checks té l'activitat
if ($accio === 'llistar_activitats') {
    $id_ra = intval($_GET['id_ra'] ?? 0);
    $stmt = $pdo->prepare("
        SELECT a.id_activitat_conceptual, a.nom_activitat, COUNT(c.id_check) as total_checks
        FROM activitats_ra a
        LEFT JOIN checks_activitat c ON a.id_activitat_conceptual = c.id_activitat_conceptual
        WHERE a.id_ra = ?
        GROUP BY a.id_activitat_conceptual
    ");
    $stmt->execute([$id_ra]);
    echo json_encode(['success' => true, 'activitats' => $stmt->fetchAll()]);
    exit;
}

// 2. NOU: Llistar els checks concrets d'una activitat des d'admin
if ($accio === 'llistar_checks_admin') {
    $id_act = intval($_GET['id_act'] ?? 0);
    $stmt = $pdo->prepare("SELECT id_check, titol_check FROM checks_activitat WHERE id_activitat_conceptual = ? ORDER BY id_check ASC");
    $stmt->execute([$id_act]);
    echo json_encode(['success' => true, 'checks' => $stmt->fetchAll()]);
    exit;
}

// 3. NOU: Guardar un nou criteri/check a una activitat
if ($accio === 'crear_check_admin' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $id_activitat = intval($input['id_activitat'] ?? 0);
    $titol_check = $input['titol_check'] ?? '';

    if($id_activitat <= 0 || empty($titol_check)) {
        echo json_encode(['success' => false, 'error' => 'Dades de criteri incompletes.']); exit;
    }

    $stmt = $pdo->prepare("INSERT INTO checks_activitat (id_activitat_conceptual, titol_check) VALUES (?, ?)");
    $stmt->execute([$id_activitat, $titol_check]);
    echo json_encode(['success' => true]);
    exit;
}

// 4. NOU: Eliminar un criteri des de la llista d'administració
if ($accio === 'eliminar_check_admin' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_check = intval($_GET['id'] ?? 0);
    $stmt = $pdo->prepare("DELETE FROM checks_activitat WHERE id_check = ?");
    $stmt->execute([$id_check]);
    echo json_encode(['success' => true]);
    exit;
}

if ($accio === 'crear_activitat' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $id_ra = intval($input['id_ra'] ?? 0);
    $nom_activitat = $input['nom_activitat'] ?? '';

    if($id_ra <= 0 || empty($nom_activitat)) {
        echo json_encode(['success' => false, 'error' => 'Dades incompletes.']); exit;
    }

    $stmt = $pdo->prepare("INSERT INTO activitats_ra (id_ra, nom_activitat) VALUES (?, ?)");
    $stmt->execute([$id_ra, $nom_activitat]);
    echo json_encode(['success' => true]);
    exit;
}

if ($accio === 'eliminar_activitat' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_act = intval($_GET['id'] ?? 0);
    $stmt = $pdo->prepare("DELETE FROM activitats_ra WHERE id_activitat_conceptual = ?");
    $stmt->execute([$id_act]);
    echo json_encode(['success' => true]);
    exit;
}

// Dins de api_activitats.php (o on gestionis les accions de llistat)

if ($accio === 'llistar_checks_alumne') {
    $id_act = intval($_GET['id_act'] ?? 0);
    
    // Retornem només l'ID i el títol del check d'aquella activitat
    $stmt = $pdo->prepare("SELECT id_check, titol_check FROM checks_activitat WHERE id_activitat_conceptual = ? ORDER BY id_check ASC");
    $stmt->execute([$id_act]);
    
    echo json_encode([
        'success' => true, 
        'checks' => $stmt->fetchAll(PDO::FETCH_ASSOC)
    ]);
    exit;
}