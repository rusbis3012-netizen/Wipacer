
const formulario = document.getElementById("formLogin");
const mensaje = document.getElementById("mensajeLogin");

formulario.addEventListener("submit", async function(event) {

    // Evitar que se recargue la página
    event.preventDefault();

    // Obtener los datos del formulario
    const correo = document.getElementById("Correo").value.trim();
    const contrasena = document.getElementById("Contrasena").value;

    // Enviar los datos a PHP
    try {

        const respuesta = await fetch(formulario.action, {
            method: "POST",
            headers: {
                "Content-Type": "application/json"
            },
            body: JSON.stringify({
                Correo: correo,
                Contrasena: contrasena
            })
        });

        // Convertir la respuesta de PHP a JSON
        const resultado = await respuesta.json();

        // Comprobar si el inicio de sesión fue exitoso
        if (resultado.success) {

            mensaje.textContent = "Inicio de sesión exitoso.";
            mensaje.style.color = "green";

            // Redirigir a la página de bienvenida
            window.location.href = "Welcome.html";

        } else {

            mensaje.textContent = resultado.mensaje;
            mensaje.style.color = "red";

        }

    } catch (error) {

        mensaje.textContent = "Error al conectar con el servidor.";
        mensaje.style.color = "red";

    }

});
