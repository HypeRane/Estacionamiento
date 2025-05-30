<?php
/**
 * LIBERAR PLAZA - PROCESAR SALIDA DE VEHÍCULO
 * Archivo: liberar.php
 * Descripción: Procesa la salida de un vehículo y libera la plaza
 */

require_once('entidades.php');
require_once('conexion.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Error: Acceso no permitido.");
}

$id_registro = isset($_POST['id_registro']) ? intval($_POST['id_registro']) : 0;
$fecha_salida_real = isset($_POST['fecha_salida_real']) ? trim($_POST['fecha_salida_real']) : '';

// Validar datos
if ($id_registro <= 0) {
    die("Error: ID de registro inválido.");
}

if (empty($fecha_salida_real)) {
    die("Error: Fecha de salida requerida.");
}

// Validar formato de fecha
$fechaSalida = DateTime::createFromFormat('Y-m-d\TH:i', $fecha_salida_real);
if (!$fechaSalida) {
    die("Error: Formato de fecha de salida inválido.");
}

try {
    // Iniciar transacción
    $conn->autocommit(FALSE);

    // 1. Verificar que el registro existe y no ha sido liberado
    $sqlVerificar = "SELECT r.ID_REGISTRO, r.ID_ESPACIO, r.FECHA_INGRESO, v.PLACA, v.TIPO, t.TARIFA_HORA, t.TARIFA_DIA
                     FROM REGISTRO_ESTACIONAMIENTO r
                     INNER JOIN VEHICULOS v ON r.ID_VEHICULO = v.ID_VEHICULO
                     INNER JOIN TARIFAS t ON r.ID_TARIFA = t.ID_TARIFA
                     WHERE r.ID_REGISTRO = ? AND r.FECHA_SALIDA IS NULL";
    
    $stmtVerificar = $conn->prepare($sqlVerificar);
    $stmtVerificar->bind_param("i", $id_registro);
    $stmtVerificar->execute();
    $resultado = $stmtVerificar->get_result();
    
    if ($resultado->num_rows == 0) {
        throw new Exception("El vehículo no se encuentra en el estacionamiento o ya fue retirado.");
    }

    $registro = $resultado->fetch_assoc();

    // Calcular tiempo real transcurrido
    $fechaIngreso = new DateTime($registro['FECHA_INGRESO']);
    $fechaSalidaObj = DateTime::createFromFormat('Y-m-d\TH:i', $fecha_salida_real);
    $diferencia = $fechaIngreso->diff($fechaSalidaObj);
    $horasReales = ($diferencia->days * 24) + $diferencia->h + ($diferencia->i / 60);
    $horasReales = max(1, ceil($horasReales)); // Mínimo 1 hora

    // Calcular monto final
    $tarifaHora = floatval($registro['TARIFA_HORA']);
    $tarifaDia = floatval($registro['TARIFA_DIA']);
    
    if ($horasReales >= 12) {
        $dias = ceil($horasReales / 24);
        $montoFinal = $dias * $tarifaDia;
    } else {
        $montoFinal = $horasReales * $tarifaHora;
    }

    // 2. Actualizar registro con fecha de salida
    $sqlActualizarRegistro = "UPDATE REGISTRO_ESTACIONAMIENTO 
                              SET FECHA_SALIDA_REAL = ?, FECHA_SALIDA = NOW()
                              WHERE ID_REGISTRO = ?";
    
    $stmtActualizar = $conn->prepare($sqlActualizarRegistro);
    $fechaSalidaStr = $fechaSalidaObj->format('Y-m-d H:i:s');
    $stmtActualizar->bind_param("si", $fechaSalidaStr, $id_registro);
    
    if (!$stmtActualizar->execute()) {
        throw new Exception("Error al actualizar el registro de salida: " . $stmtActualizar->error);
    }

    // 3. Liberar el espacio
    $sqlLiberarEspacio = "UPDATE ESPACIOS_ESTACIONAMIENTO 
                          SET ESTADO = 'libre' 
                          WHERE ID_ESPACIO = ?";
    
    $stmtLiberar = $conn->prepare($sqlLiberarEspacio);
    $stmtLiberar->bind_param("i", $registro['ID_ESPACIO']);
    
    if (!$stmtLiberar->execute()) {
        throw new Exception("Error al liberar el espacio: " . $stmtLiberar->error);
    }

    // Confirmar transacción
    $conn->commit();

    // Variables para mostrar en la página de éxito
    $placa = $registro['PLACA'];
    $tipo = $registro['TIPO'];
    $fechaIngresoFormateada = date('d/m/Y H:i', strtotime($registro['FECHA_INGRESO']));
    $fechaSalidaFormateada = $fechaSalida->format('d/m/Y H:i');
    $montoFormateado = number_format($montoFinal, 2);
    $horasFormateadas = number_format($horasReales, 1);

} catch (Exception $e) {
    // Rollback en caso de error
    $conn->rollback();
    die("Error: " . $e->getMessage());
} finally {
    $conn->autocommit(TRUE);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Plaza Liberada - Estacionamiento</title>
    <link rel="stylesheet" href="../Front/Liberar/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Oswald&display=swap" rel="stylesheet">
</head>
<body>
    <div class="sidebar">
        <h2>ESTACIONAMIENTO</h2>
        <a href="../Front/menu.html">Menú Principal</a>
        <a href="../Front/Registro/registro.html">Registrar Vehículo</a>
        <a href="../Front/Liberar/liberar.html">Liberar Plaza</a>
    </div>

    <div class="main-content">
        <div class="formulario" style="max-width: 500px;">
            <h2 style="color: #28a745;">✅ Plaza Liberada</h2>
            
            <div style="background-color: #d4edda; border: 1px solid #c3e6cb; border-radius: 8px; padding: 20px; margin: 20px 0;">
                <h3 style="color: #155724; margin-top: 0;">Información de Salida:</h3>
                
                <p><strong>Placa:</strong> <?php echo htmlspecialchars($placa); ?></p>
                <p><strong>Tipo:</strong> <?php echo htmlspecialchars($tipo); ?></p>
                <p><strong>Hora de Ingreso:</strong> <?php echo $fechaIngresoFormateada; ?></p>
                <p><strong>Hora de Salida:</strong> <?php echo $fechaSalidaFormateada; ?></p>
                <p><strong>Tiempo Total:</strong> <?php echo $horasFormateadas; ?> horas</p>
                
                <div style="background-color: #fff; padding: 15px; border-radius: 5px; margin: 15px 0; text-align: center;">
                    <h4 style="color: #155724; margin: 0;">MONTO A COBRAR</h4>
                    <h2 style="color: #155724; margin: 10px 0; font-size: 2.5em;">S/. <?php echo $montoFormateado; ?></h2>
                </div>
                
                <p style="font-size: 0.9em; color: #155724; margin: 15px 0;">
                    <strong>Tarifa aplicada:</strong> 
                    <?php if ($horasReales >= 12): ?>
                        S/. <?php echo number_format($tarifaDia, 2); ?> por día
                    <?php else: ?>
                        S/. <?php echo number_format($tarifaHora, 2); ?> por hora
                    <?php endif; ?>
                </p>
            </div>

            <div style="text-align: center; margin-top: 30px;">
                <a href="../Front/Liberar/liberar.html" style="display: inline-block; background-color: #007bff; color: white; padding: 12px 25px; text-decoration: none; border-radius: 8px; margin: 5px;">
                    Liberar Otra Plaza
                </a>
                <a href="../Front/menu.html" style="display: inline-block; background-color: #6c757d; color: white; padding: 12px 25px; text-decoration: none; border-radius: 8px; margin: 5px;">
                    Menú Principal
                </a>
            </div>

            <div style="margin-top: 20px; padding: 15px; background-color: #f8f9fa; border-radius: 8px; font-size: 0.9em; color: #6c757d;">
                <p><strong>Nota:</strong> El espacio ha sido liberado automáticamente y está disponible para nuevos vehículos.</p>
                <p><strong>Fecha de procesamiento:</strong> <?php echo date('d/m/Y H:i:s'); ?></p>
            </div>
        </div>
    </div>

    <script>
        // Auto-redirect después de 30 segundos al menú principal
        setTimeout(function() {
            if (confirm('¿Desea regresar al menú principal?')) {
                window.location.href = '../Front/menu.html';
            }
        }, 30000);
    </script>
</body>
</html>