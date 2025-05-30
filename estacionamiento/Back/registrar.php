<?php
require_once('entidades.php');
require_once('conexion.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $placa = isset($_POST['placa']) ? strtoupper(trim($_POST['placa'])) : '';
    $marca = isset($_POST['marca']) ? trim($_POST['marca']) : '';
    if ($marca === 'Otro') {
        $marca = isset($_POST['otraMarca']) ? trim($_POST['otraMarca']) : $marca;
    }
    $modelo = isset($_POST['modelo']) ? trim($_POST['modelo']) : '';
    $color = isset($_POST['color']) ? trim($_POST['color']) : '';
    if ($color === 'Otro') {
        $color = isset($_POST['otroColor']) ? trim($_POST['otroColor']) : $color;
    }
    $tipo = isset($_POST['tipo']) ? trim($_POST['tipo']) : '';
    $hora_ingreso = isset($_POST['hora_ingreso']) ? trim($_POST['hora_ingreso']) : '';
    $tiempo_estimado = isset($_POST['tiempo_est']) ? floatval($_POST['tiempo_est']) : 0;

    if (empty($placa) || empty($marca) || empty($modelo) || empty($color) || empty($tipo) || $tiempo_estimado <= 0 || empty($hora_ingreso)) {
        die("Error: Complete todos los campos correctamente.");
    }

    $regexPlaca = '/^[A-Z0-9]{3,4}-[0-9]{3}$/';
    if (!preg_match($regexPlaca, $placa)) {
        die("Error: Formato de placa inválido. Use formato peruano: ABC-123");
    }

    try {
        $conn->autocommit(FALSE);

        $sqlVerificar = "SELECT ID_VEHICULO FROM VEHICULOS WHERE PLACA = ?";
        $stmtVerificar = $conn->prepare($sqlVerificar);
        $stmtVerificar->bind_param("s", $placa);
        $stmtVerificar->execute();
        $resultado = $stmtVerificar->get_result();

        if ($resultado->num_rows > 0) {
            $vehiculo = $resultado->fetch_assoc();
            $id_vehiculo = $vehiculo['ID_VEHICULO'];
        } else {
            $sqlVehiculo = "INSERT INTO VEHICULOS (PLACA, MARCA, MODELO, COLOR, TIPO) VALUES (?, ?, ?, ?, ?)";
            $stmtVehiculo = $conn->prepare($sqlVehiculo);
            $stmtVehiculo->bind_param("sssss", $placa, $marca, $modelo, $color, $tipo);

            if (!$stmtVehiculo->execute()) {
                throw new Exception("Error al registrar el vehículo: " . $stmtVehiculo->error);
            }

            $id_vehiculo = $conn->insert_id;
        }

        $sqlEspacio = "SELECT ID_ESPACIO, NUMERO_ESPACIO FROM ESPACIOS_ESTACIONAMIENTO WHERE ESTADO = 'libre' LIMIT 1";
        $resultadoEspacio = $conn->query($sqlEspacio);

        if ($resultadoEspacio->num_rows == 0) {
            throw new Exception("No hay espacios disponibles");
        }

        $espacio = $resultadoEspacio->fetch_assoc();
        $id_espacio = $espacio['ID_ESPACIO'];
        $numero_espacio = $espacio['NUMERO_ESPACIO'];

        $sqlTarifa = "SELECT ID_TARIFA, TARIFA_HORA, TARIFA_DIA FROM TARIFAS WHERE TIPO_VEHICULO = ?";
        $stmtTarifa = $conn->prepare($sqlTarifa);
        $stmtTarifa->bind_param("s", $tipo);
        $stmtTarifa->execute();
        $resultadoTarifa = $stmtTarifa->get_result();

        if ($resultadoTarifa->num_rows == 0) {
            $sqlTarifaGenerica = "SELECT ID_TARIFA, TARIFA_HORA, TARIFA_DIA FROM TARIFAS WHERE TIPO_VEHICULO = 'General' LIMIT 1";
            $resultadoTarifa = $conn->query($sqlTarifaGenerica);

            if ($resultadoTarifa->num_rows == 0) {
                throw new Exception("No se encontró tarifa para este tipo de vehículo");
            }
        }

        $tarifa = $resultadoTarifa->fetch_assoc();
        $id_tarifa = $tarifa['ID_TARIFA'];

        $fecha_ingreso = new DateTime($hora_ingreso);
        $fecha_salida_estimada = clone $fecha_ingreso;
        $fecha_salida_estimada->add(new DateInterval('PT' . intval($tiempo_estimado) . 'H'));

        $sqlRegistro = "INSERT INTO REGISTRO_ESTACIONAMIENTO (ID_VEHICULO, ID_ESPACIO, ID_TARIFA, FECHA_INGRESO, FECHA_SALIDA_ESTIMADA) VALUES (?, ?, ?, ?, ?)";
        $stmtRegistro = $conn->prepare($sqlRegistro);
        $fecha_ingreso_str = $fecha_ingreso->format('Y-m-d H:i:s');
        $fecha_salida_est_str = $fecha_salida_estimada->format('Y-m-d H:i:s');
        $stmtRegistro->bind_param("iisss", $id_vehiculo, $id_espacio, $id_tarifa, $fecha_ingreso_str, $fecha_salida_est_str);

        if (!$stmtRegistro->execute()) {
            throw new Exception("Error al registrar el estacionamiento: " . $stmtRegistro->error);
        }

        $sqlActualizarEspacio = "UPDATE ESPACIOS_ESTACIONAMIENTO SET ESTADO = 'ocupado' WHERE ID_ESPACIO = ?";
        $stmtActualizarEspacio = $conn->prepare($sqlActualizarEspacio);
        $stmtActualizarEspacio->bind_param("i", $id_espacio);

        if (!$stmtActualizarEspacio->execute()) {
            throw new Exception("Error al actualizar el espacio: " . $stmtActualizarEspacio->error);
        }

        $registro = new RegistroVehiculo($placa, $marca, $modelo, $color, $tipo, $hora_ingreso, $tiempo_estimado);
        $monto_inicial = $registro->monto_inicial;

        $conn->commit();

        mostrarPaginaExito($placa, $marca, $modelo, $color, $tipo, $numero_espacio, $fecha_ingreso_str, $fecha_salida_est_str, $monto_inicial);

    } catch (Exception $e) {
        $conn->rollback();
        die("Error: " . $e->getMessage());
    } finally {
        $conn->close();
    }
}

function mostrarPaginaExito($placa, $marca, $modelo, $color, $tipo, $numero_espacio, $fecha_ingreso, $fecha_salida, $monto) {
    echo "<h2>✅ Registro exitoso</h2>";
    echo "<p><strong>Placa:</strong> " . htmlspecialchars($placa) . "</p>";
    echo "<p><strong>Marca:</strong> " . htmlspecialchars($marca) . "</p>";
    echo "<p><strong>Modelo:</strong> " . htmlspecialchars($modelo) . "</p>";
    echo "<p><strong>Color:</strong> " . htmlspecialchars($color) . "</p>";
    echo "<p><strong>Tipo:</strong> " . htmlspecialchars($tipo) . "</p>";
    echo "<p><strong>Espacio asignado:</strong> " . htmlspecialchars($numero_espacio) . "</p>";
    echo "<p><strong>Hora de ingreso:</strong> " . htmlspecialchars($fecha_ingreso) . "</p>";
    echo "<p><strong>Hora de salida estimada:</strong> " . htmlspecialchars($fecha_salida) . "</p>";
    echo "<p><strong>Monto inicial:</strong> S/ " . number_format($monto, 2) . "</p>";
    echo "<a href='formulario_registro.html'>Registrar otro vehículo</a>";
}
?>
