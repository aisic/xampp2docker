<?php
// logout.php
session_start();

// Detectem quin tipus d'usuari era abans d'esborrar (per saber on redirigir)
$era_profesor = false;
$profesores_autorizados = ['tu_correo_profesor@institut.cat', 'coordinador@institut.cat']; // Mateixa llista que seguridad_profesor.php

if (isset($_SESSION['alumno_email']) && in_array($_SESSION['alumno_email'], $profesores_autorizados)) {
    $era_profesor = true;
}

// Netegem i destruïm la sessió completament
session_unset();
session_destroy();

// Redirigim a la seva pàgina de login correcta
if ($era_profesor) {
    header("Location: login_profesor.php");
} else {
    header("Location: login.php");
}
exit;

