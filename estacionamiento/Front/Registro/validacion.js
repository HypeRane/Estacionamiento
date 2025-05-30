function validarFormulario() {
  const placa = document.getElementById("placa").value.trim();
  const regexPlaca = /^[A-Z0-9]{3,4}-[0-9]{3}$/;

  if (!regexPlaca.test(placa)) {
    alert("La placa debe tener un formato válido peruano. Ej: ABC-123 o ABCD-123");
    return false;
  }

  const marcaSelect = document.getElementById("marca");
  const otraMarca = document.getElementById("otraMarca").value.trim();
  const marcaFinal = marcaSelect.value === "Otro" ? otraMarca : marcaSelect.value;

  const colorSelect = document.getElementById("color");
  const otroColor = document.getElementById("otroColor").value.trim();
  const colorFinal = colorSelect.value === "Otro" ? otroColor : colorSelect.value;

  const modelo = document.getElementById("modelo").value.trim();
  const tipo = document.getElementById("tipo").value;

  if (marcaFinal === "" || colorFinal === "" || modelo === "" || tipo === "") {
    alert("Todos los campos son obligatorios");
    return false;
  }

}

function mostrarOtraMarca() {
  const marca = document.getElementById("marca").value;
  document.getElementById("otraMarca").style.display = marca === "Otro" ? "block" : "none";
}

function mostrarOtroColor() {
  const color = document.getElementById("color").value;
  document.getElementById("otroColor").style.display = color === "Otro" ? "block" : "none";
}

