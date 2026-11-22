const modal = document.getElementById("modalVenta");
const botonAbrir = document.getElementById("abrirFormulario");
const botonCerrar = document.getElementById("cerrarFormulario");
const botonCancelar = document.getElementById("cancelarVenta");
const formularioVenta = document.getElementById("formNuevaVenta");
const tablaVentas = document.querySelector("#tablaVentas tbody");
const clienteVenta = document.getElementById("clienteVenta");
const productoVenta = document.getElementById("productoVenta");
const cantidadVenta = document.getElementById("cantidadVenta");
const precioVenta = document.getElementById("precioVenta");
const metodoVenta = document.getElementById("metodoVenta");

//registrar una nueva venta 
formularioVenta.addEventListener("submit", (event) => { //submit es para detectar cuando se envia el formulario 
    event.preventDefault(); //evita que la pagina se recargue

    const cliente = clienteVenta.value; //el.value obtiene e, valor escrito en un campo
    const producto = productoVenta.value;
    const cantidad = parseInt(cantidadVenta.value); //parseInt pasa de valor a numero entero
    const precio = parseFloat(precioVenta.value); // parseFloat pasa de valor a numero decimal
    const metodo = metodoVenta.value;

    const total = cantidad * precio; //calcula el valor total de la venta

    const numeroVenta = tablaVentas.rows.length + 1; //cuenta las filas que existen en la tabla
    const factura = "VTA-" + String(numeroVenta).padStart(3,"0"); //padStart completa el numero de la factura con 0

    const fecha = new Date().toLocaleDateString("es-CO"); //formatea la fecha para colombia 

     const totalFormateado = total.toLocaleString("es-CO", { // convierte el total a moneda colombiana
        style: "currency",
        currency: "COP",
        maximumFractionDigits: 0
    });

    const nuevaFila = document.createElement("tr"); //crea una fila

    nuevaFila.innerHTML = `
        <td>${factura}</td>
        <td>${cliente}</td>
        <td>${fecha}</td>
        <td>${totalFormateado}</td>
        <td><span class="estado pagada">Pagada</span></td>
        <td><button type="button" class="boton-ver">Ver</button></td>
    `;

     tablaVentas.prepend(nuevaFila); // agrega la fila al iniio de la tabla

    alert("Venta registrada correctamente. Método de pago: " + metodo); //mensaje

    formularioVenta.reset(); //reset limpia los cambios del formulario
    modal.style.display = "none";
});



//const es una variable que no se reasigna

botonAbrir.addEventListener("click", () => { //() => { despues de que 
    modal.style.display = "flex"; 
    //el display controla como se muestra el elemento
    //flex hace que el elemento se muestre utilizando flexbox
    //cuando presione registrar la ventana aparece

})
botonCerrar.addEventListener("click", () => {
    modal.style.display = "none";
    // none oculta la ventana
    //cierra con la x
})
botonCancelar.addEventListener("click", () => {
    modal.style.display = "none";
    //cierra pero con cancelar
})
// Cerrar al hacer clic fuera del formulario
modal.addEventListener("click", (event) => {
    if (event.target === modal) { //event recibe la informacion de ese click
        //event.target identifica donde se presiono
        modal.style.display = "none";
    }
});

const buscadorVentas = document.getElementById("buscarventa");


buscadorVentas.addEventListener("input", () => { //  input Detecta cuando el usuario escribe en el buscador

   
    const textoBusqueda = buscadorVentas.value.toLowerCase().trim(); //toLowerCase obtiene el texto escrito y lo convierte a minusculas

    
    const filas = tablaVentas.querySelectorAll("tr"); // Coge todas las filas que existen la tabla

    // Recorre cada fila de la tabla
    filas.forEach((fila) => {

        // convierte a minusculas
        const contenidoFila = fila.textContent.toLowerCase();

        // Comprueba si el contenido coincide con lo que se esta buscando
        const coincide = contenidoFila.includes(textoBusqueda);

        // Muestra la fila si coincide o la oculta si no coincide
        fila.style.display = coincide ? "" : "none";
    });
});
