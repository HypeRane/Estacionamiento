<?php


class Vehiculo {
    var $id_vehiculo;
    var $placa;
    var $marca;
    var $modelo;
    var $color;
    var $tipo;

    function Vehiculo($placa, $marca, $modelo, $color, $tipo) {
        $this->placa = $placa;
        $this->marca = $marca;
        $this->modelo = $modelo;
        $this->color = $color;
        $this->tipo = $tipo;
    }
}
class Espacio {
    var $id_espacio;
    var $numero_espacio;
    var $tipo_espacio;
    var $estado;

    function Espacio($numero_espacio, $tipo_espacio, $estado) {
        $this->numero_espacio = $numero_espacio;
        $this->tipo_espacio = $tipo_espacio;
        $this->estado = $estado;
    }
}
class Tarifa {
    var $id_tarifa;
    var $tipo_vehiculo;
    var $tarifa_hora;
    var $tarifa_dia;

    function Tarifa($tipo_vehiculo, $tarifa_hora, $tarifa_dia) {
        $this->tipo_vehiculo = $tipo_vehiculo;
        $this->tarifa_hora = $tarifa_hora;
        $this->tarifa_dia = $tarifa_dia;
    }
}

class RegistroVehiculo {
    var $placa;
    var $marca;
    var $modelo;
    var $color;
    var $tipo;
    var $hora_ingreso;       // string dd/mm/yyyy hh:mm
    var $tiempo_estimado;    // en horas (float)
    var $monto_inicial;      // calculado

    function __construct($placa, $marca, $modelo, $color, $tipo, $hora_ingreso, $tiempo_estimado) {
        $this->placa = $placa;
        $this->marca = $marca;
        $this->modelo = $modelo;
        $this->color = $color;
        $this->tipo = $tipo;
        $this->hora_ingreso = $hora_ingreso;
        $this->tiempo_estimado = $tiempo_estimado;

        $this->monto_inicial = $this->calculoMontoInicial();
    }

    public function calculoMontoInicial() {
        // Ejemplo simple de tarifas por tipo (puedes cambiar a consulta en BD)
        $tarifas = [
            'Sedan' => 5.00,
            'Camioneta' => 7.50,
            'Minivan' => 6.50,
            'Van' => 6.00,
            'Moto' => 3.00,
            'auto' => 5.00, // valor genérico
        ];

        $tarifa_hora = isset($tarifas[$this->tipo]) ? $tarifas[$this->tipo] : 5.00;

        return round($tarifa_hora * $this->tiempo_estimado, 2);
    }
}
?>

