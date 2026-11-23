document.addEventListener("DOMContentLoaded", () => {
    const API_URL = "../Modelos/bodegas.php";

    const btnToggle = document.getElementById("btnToggleSidebar");
    const sidebar = document.getElementById("sidebar");
    if (btnToggle && sidebar) {
        btnToggle.addEventListener("click", () => sidebar.classList.toggle("open"));
    }

    let bodegasLista = [
        { IdBodega: 1, Nombre: "Bodega Central Duitama", Direccion: "Duitama", Responsable: "Coordinador Central", coords: [5.8267, -73.0333] },
        { IdBodega: 2, Nombre: "Bodega Norte Tunja", Direccion: "Tunja", Responsable: "Coordinador Norte", coords: [5.5353, -73.3678] },
        { IdBodega: 3, Nombre: "Centro Acopio Sogamoso", Direccion: "Sogamoso", Responsable: "Coordinador Oriente", coords: [5.7144, -72.9339] }
    ];

    let movimientos = [
        { tipo: "CARGUE", producto: "Cajas Archivo Empresarial", bodega: "Bodega Central Duitama", cantidad: 120, vehiculo: "WPK-309", estado: "Completado" },
        { tipo: "DESCARGUE", producto: "Insumos Contables Serie A", bodega: "Bodega Norte Tunja", cantidad: 45, vehiculo: "JTR-814", estado: "En ruta" }
    ];

    let inventario = [];

    const ciudadesCoords = {
        duitama: [5.8267, -73.0333],
        tunja: [5.5353, -73.3678],
        sogamoso: [5.7144, -72.9339],
        paipa: [5.7801, -73.1171],
        bogota: [4.7110, -74.0721]
    };

    function resolverCoordenadas(bodega, index) {
        if (bodega.coords) return bodega.coords;
        const texto = `${bodega.Nombre || ""} ${bodega.Direccion || ""}`.toLowerCase();
        for (const [ciudad, coords] of Object.entries(ciudadesCoords)) {
            if (texto.includes(ciudad)) return coords;
        }
        return [5.75 + (index * 0.04), -73.08 - (index * 0.04)];
    }

    const map = L.map("mapaBodegas").setView([5.69, -73.12], 10);

    L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
        attribution: "&copy; OpenStreetMap",
        maxZoom: 19
    }).addTo(map);

    const marcadores = [];
    let rutaActiva = L.polyline([
        [5.8267, -73.0333],
        [5.6750, -73.1900],
        [5.5353, -73.3678]
    ], {
        color: "#3b82f6",
        weight: 4,
        dashArray: "8, 8"
    }).addTo(map);

    function renderMapa(filtro = "todos") {
        marcadores.forEach(m => map.removeLayer(m));
        marcadores.length = 0;

        const puntos = [];

        if (filtro === "todos" || filtro === "bodega") {
            bodegasLista.forEach((b, idx) => {
                const coords = resolverCoordenadas(b, idx);
                puntos.push(coords);
                const marker = L.circleMarker(coords, {
                    radius: 10,
                    fillColor: "#3b82f6",
                    color: "#ffffff",
                    weight: 2.5,
                    fillOpacity: 0.95
                }).addTo(map);

                marker.bindPopup(`
                    <strong>${b.Nombre}</strong><br>
                    <small>${b.Direccion || "Sede registrada"}</small><br>
                    <span style="color:#2563eb;font-weight:600;">Resp: ${b.Responsable || "Asignado"}</span>
                `);
                marcadores.push(marker);
            });
        }

        if (filtro === "todos" || filtro === "ruta") {
            const unidadCoord = [5.6750, -73.1900];
            puntos.push(unidadCoord);
            const markerRuta = L.circleMarker(unidadCoord, {
                radius: 8,
                fillColor: "#10b981",
                color: "#ffffff",
                weight: 2.5,
                fillOpacity: 0.95
            }).addTo(map);

            markerRuta.bindPopup(`<strong>Unidad en Ruta · JTR-814</strong><br><small>Corredor Duitama → Tunja</small>`);
            marcadores.push(markerRuta);
        }

        rutaActiva.setStyle({ opacity: filtro === "bodega" ? 0.15 : 0.85 });
    }

    renderMapa();

    document.getElementById("btnCentrarMapa")?.addEventListener("click", () => {
        map.setView([5.69, -73.12], 10);
    });

    document.querySelectorAll(".btn-chip").forEach(btn => {
        btn.addEventListener("click", () => {
            document.querySelectorAll(".btn-chip").forEach(b => b.classList.remove("active"));
            btn.classList.add("active");
            renderMapa(btn.dataset.filter);
        });
    });

    function actualizarSelectsBodegas() {
        const selOrigen = document.getElementById("bodegaOrigen");
        const selDestino = document.getElementById("bodegaDestino");
        if (!selOrigen || !selDestino) return;

        const opciones = bodegasLista.map(b => `<option value="${b.IdBodega}">${b.Nombre}</option>`).join("");
        selOrigen.innerHTML = opciones;
        selDestino.innerHTML = opciones;
        if (bodegasLista.length > 1) selDestino.selectedIndex = 1;
    }

    actualizarSelectsBodegas();

    const tipoMovSelect = document.getElementById("tipoMovimiento");
    const grupoDestino = document.getElementById("grupoBodegaDestino");
    const labelOrigen = document.getElementById("labelBodegaOrigen");

    tipoMovSelect?.addEventListener("change", () => {
        const esTraslado = tipoMovSelect.value === "TRASLADO";
        grupoDestino.classList.toggle("d-none", !esTraslado);
        labelOrigen.textContent = esTraslado ? "Bodega Origen" : "Bodega";
    });

    document.querySelectorAll(".tab-btn").forEach(tab => {
        tab.addEventListener("click", () => {
            document.querySelectorAll(".tab-btn").forEach(t => t.classList.remove("active"));
            tab.classList.add("active");

            document.getElementById("tab-movimientos").classList.toggle("d-none", tab.dataset.tab !== "tab-movimientos");
            document.getElementById("tab-stock").classList.toggle("d-none", tab.dataset.tab !== "tab-stock");
        });
    });

    const tbodyMov = document.getElementById("tablaMovimientosBody");
    const tbodyStock = document.getElementById("tablaStockBody");
    const buscarInput = document.getElementById("buscarTabla");

    function renderTablas(filtro = "") {
        const q = filtro.trim().toLowerCase();

        const movFiltrados = movimientos.filter(m =>
            (m.producto || "").toLowerCase().includes(q) ||
            (m.bodega || "").toLowerCase().includes(q) ||
            (m.tipo || "").toLowerCase().includes(q) ||
            (m.vehiculo || "").toLowerCase().includes(q)
        );

        tbodyMov.innerHTML = movFiltrados.length
            ? movFiltrados.map(item => {
                const clase = item.tipo === "CARGUE" ? "badge-cargue"
                    : item.tipo === "DESCARGUE" ? "badge-descargue"
                    : "badge-traslado";
                return `
                    <tr>
                        <td><span class="badge-tipo ${clase}">${item.tipo}</span></td>
                        <td class="fw-semibold">${item.producto}</td>
                        <td>${item.bodega}</td>
                        <td class="fw-bold">${item.cantidad}</td>
                        <td>${item.vehiculo || "—"}</td>
                        <td><span class="text-muted small">${item.estado || "Registrado"}</span></td>
                    </tr>
                `;
            }).join("")
            : `<tr><td colspan="6" class="text-center text-muted py-4">Sin movimientos registrados.</td></tr>`;

        const stockFiltrado = inventario.filter(i =>
            (i.producto || "").toLowerCase().includes(q) ||
            (i.bodega || "").toLowerCase().includes(q) ||
            (i.Codigo || "").toLowerCase().includes(q)
        );

        tbodyStock.innerHTML = stockFiltrado.length
            ? stockFiltrado.map(item => {
                const bajo = Number(item.cantidad) <= Number(item.CantidadMinima || 0);
                return `
                    <tr>
                        <td class="text-muted small">${item.Codigo || "—"}</td>
                        <td class="fw-semibold">${item.producto}</td>
                        <td>${item.bodega}</td>
                        <td class="fw-bold">${item.cantidad}</td>
                        <td>${item.CantidadMinima ?? 0}</td>
                        <td><span class="badge ${bajo ? "bg-danger-subtle text-danger" : "bg-success-subtle text-success"}">${bajo ? "Stock bajo" : "Óptimo"}</span></td>
                    </tr>
                `;
            }).join("")
            : `<tr><td colspan="6" class="text-center text-muted py-4">Aún no hay registros en inventario.</td></tr>`;
    }

    renderTablas();
    buscarInput?.addEventListener("input", e => renderTablas(e.target.value));

    async function cargarDatosServidor() {
        try {
            const res = await fetch(`${API_URL}?accion=listar`);
            if (!res.ok) return;
            const data = await res.json();
            if (!data.ok) return;

            if (Array.isArray(data.bodegas) && data.bodegas.length > 0) {
                bodegasLista = data.bodegas;
                actualizarSelectsBodegas();
                renderMapa();
            }
            if (Array.isArray(data.movimientos)) movimientos = data.movimientos;
            if (Array.isArray(data.inventario)) {
                inventario = data.inventario;
                const datalist = document.getElementById("listaProductosSugeridos");
                if (datalist) {
                    const nombres = [...new Set(inventario.map(i => i.producto))];
                    datalist.innerHTML = nombres.map(n => `<option value="${n}">`).join("");
                }
            }
            if (data.kpis) {
                document.getElementById("kpiBodegas").textContent = data.kpis.bodegas ?? bodegasLista.length;
                document.getElementById("kpiStock").textContent = Number(data.kpis.stock ?? 0).toLocaleString();
                document.getElementById("kpiCargues").textContent = data.kpis.cargues ?? 0;
                document.getElementById("kpiDescargues").textContent = data.kpis.descargues ?? 0;
            }
            renderTablas(buscarInput?.value || "");
        } catch (err) {
            document.getElementById("kpiBodegas").textContent = bodegasLista.length;
        }
    }

    cargarDatosServidor();

    const formMov = document.getElementById("formMovimientoBodega");
    const estadoMov = document.getElementById("estadoMovimiento");

    formMov?.addEventListener("submit", async e => {
        e.preventDefault();
        const formData = new FormData(formMov);
        formData.append("accion", "registrar_movimiento");

        try {
            const resp = await fetch(API_URL, { method: "POST", body: formData });
            const json = await resp.json();

            if (resp.ok && json.ok) {
                estadoMov.innerHTML = `<span class="text-success fw-semibold">✓ ${json.mensaje}</span>`;
                formMov.reset();
                grupoDestino.classList.add("d-none");
                await cargarDatosServidor();
            } else {
                estadoMov.innerHTML = `<span class="text-danger fw-semibold">${json.error || "No se pudo registrar."}</span>`;
            }
        } catch (err) {
            estadoMov.innerHTML = `<span class="text-danger fw-semibold">Error de conexión con bodegas.php</span>`;
        }
    });

    const formNuevaBodega = document.getElementById("formNuevaBodega");
    formNuevaBodega?.addEventListener("submit", async e => {
        e.preventDefault();
        const formData = new FormData(formNuevaBodega);
        formData.append("accion", "crear_bodega");

        try {
            const resp = await fetch(API_URL, { method: "POST", body: formData });
            const json = await resp.json();
            if (resp.ok && json.ok) {
                formNuevaBodega.reset();
                bootstrap.Modal.getInstance(document.getElementById("modalNuevaBodega"))?.hide();
                await cargarDatosServidor();
            } else {
                alert(json.error || "No se pudo crear la bodega");
            }
        } catch (err) {
            alert("Error de conexión al crear la bodega");
        }
    });
});
