const formulario = document.getElementById("formRegistro");

async function cargarEmpresas() {
    const empresaSelect = formulario.elements.IdEmpresa;
    const mensaje = document.getElementById("mensajeRegistro");

    try {
        const url = new URL(formulario.action, window.location.href);
        url.searchParams.set("empresas", "1");

        const respuesta = await fetch(url);
        const resultado = await respuesta.json();
        if (!respuesta.ok || !resultado.success) {
            throw new Error(resultado.mensaje || "No se pudieron cargar las empresas.");
        }

        empresaSelect.replaceChildren(new Option("Selecciona una empresa", ""));
        resultado.empresas.forEach(empresa => {
            empresaSelect.add(new Option(empresa.NombreEmpresa, empresa.IdEmpresa));
        });

        if (resultado.empresas.length === 0) {
            mensaje.textContent = "No hay empresas activas disponibles.";
        }
    } catch (error) {
        console.error("Error al cargar empresas:", error);
        empresaSelect.replaceChildren(new Option("No se pudieron cargar las empresas", ""));
        mensaje.textContent = "No se pudieron cargar las empresas. Inténtalo más tarde.";
    }
}

formulario.addEventListener("submit", async function (event) {
    //Evitar recargar la página al enviar el formulario
    event.preventDefault();

    const botonGuardar = formulario.querySelector('button[type="submit"]');

    //Lee los campos del formulario  y los convierte en un objeto
    const usuario = Object.fromEntries(new FormData(formulario).entries());

    botonGuardar.disabled = true; //Desabilitar el bóton mientras se procesa la solicitud.
    try {
        const respuesta = await fetch(formulario.action, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(usuario)
        })

        //pasar la respuesta json a un objeto de JavaScript 
        const resultado = await respuesta.json();
        document.getElementById("mensajeRegistro").textContent = resultado.mensaje;

        if (respuesta.ok && resultado.success) {
            formulario.reset(); //limpiar el formulario si la solicitud fue exitosa.
        }
    } catch (error) {
        console.error("Error al enviar el formulario:", error);
        document.getElementById("mensajeRegistro").textContent = " Ocurrio un error al enviar el formulario. Por favor, inténtelo de nuevo.";
    } finally {
        botonGuardar.disabled = false; //Habilitar el botón después de procesar la solicitud.
    }
});            

cargarEmpresas();
