<?php
/**
 * BUSCAR VEHÍCULO POR PLACA
 * Archivo: buscar_vehiculo.php
 * Descripción: Busca un vehículo activo en el estacionamiento por su placa
 */

require_once('entidades.php');
require_once('conexion.php');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$placa = isset($_POST['placa']) ? strtoupper(trim($_POST['placa'])) : '';

if (empty($placa)) {
    echo json_encode(['success' => false, 'message' => 'Placa requerida']);
    exit;
}

// Validar formato de placa peruana
$regexPlaca = '/^[A-Z0-9]{3,4}-[0-9]{3}$/';
if (!preg_match($regexPlaca, $placa)) {
    echo json_encode(['success' => false, 'message' => 'Formato de placa inválido']);
    exit;
}

try {
    // Consulta para buscar vehículo activo
    $sql = "SELECT 
                r.ID_REGISTRO,
                v.PLACA, v.MARCA, v.MODELO, v.COLOR, v.TIPO,
                e.NUMERO_ESPACIO,
                r.FECHA_INGRESO,
                TIMESTAMPDIFF(HOUR, r.FECHA_INGRESO, NOW()) as HORAS_TRANSCURRIDAS,
                t.TARIFA_HORA, t.TARIFA_DIA
            FROM REGISTRO_ESTACIONAMIENTO r
            INNER JOIN VEHICULOS v ON r.ID_VEHICULO = v.ID_VEHICULO
            INNER JOIN ESPACIOS_ESTACIONAMIENTO e ON r.ID_ESPACIO = e.ID_ESPACIO
            INNER JOIN TARIFAS t ON r.ID_TARIFA = t.ID_TARIFA
            WHERE v.PLACA = ? AND r.FECHA_SALIDA IS NULL";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $placa);
    $stmt->execute();
    $resultado = $stmt->get_result();
    
    if ($resultado->num_rows == 0) {
        echo json_encode(['success' => false, 'message' => 'Vehículo no encontrado o ya fue retirado']);
        exit;
    }

    $vehiculoEncontrado = $resultado->fetch_assoc();

    // Calcular monto usando las tarifas
    $horasTranscurridas = max(1, intval($vehiculoEncontrado['HORAS_TRANSCURRIDAS']));
    $tarifaHora = floatval($vehiculoEncontrado['TARIFA_HORA']);
    $tarifaDia = floatval($vehiculoEncontrado['TARIFA_DIA']);
    
    if ($horasTranscurridas >= 12) {
        $dias = ceil($horasTranscurridas / 24);
        $montoCalculado = $dias * $tarifaDia;
    } else {
        $montoCalculado = $horasTranscurridas * $tarifaHora;
    }

    // Formatear fecha de ingreso
    $fechaIngreso = new DateTime($vehiculoEncontrado['FECHA_INGRESO']);
    $fechaIngresoFormateada = $fechaIngreso->format('d/m/Y H:i');

    $response = [
        'success' => true,
        'vehiculo' => [
            'id_registro' => $vehiculoEncontrado['ID_REGISTRO'],
            'placa' => $vehiculoEncontrado['PLACA'],
            'marca' => $vehiculoEncontrado['MARCA'],
            'modelo' => $vehiculoEncontrado['MODELO'],
            'color' => $vehiculoEncontrado['COLOR'],
            'tipo' => $vehiculoEncontrado['TIPO'],
            'numero_espacio' => $vehiculoEncontrado['NUMERO_ESPACIO'],
            'fecha_ingreso' => $fechaIngresoFormateada,
            'tiempo_estimado' => $horasTranscurridas,
            'monto_calculado' => number_format($montoCalculado, 2)
        ]
    ];

    echo json_encode($response);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error interno: ' . $e->getMessage()]);
}
?>