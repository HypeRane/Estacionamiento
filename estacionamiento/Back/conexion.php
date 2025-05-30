<?php
$host = "localhost";
$usuario = "root";
$contrasena = "";
$bd = "estacionamiento";

$conn = new mysqli($host, $usuario, $contrasena, $bd);

// Verificar conexión
if ($conn->connect_error) {
    die("❌ Error de conexión: " . $conn->connect_error);
}

// Establecer zona horaria
date_default_timezone_set('America/Lima');
?>
