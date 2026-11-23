<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/Conexion.php';

$db = 'wipacer';

try {
    $conexionObj = new Conexion();
    $pdo = $conexionObj->conectar();
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Error de conexión a la base de datos']);
    exit;
}

$idEmpresa = (int) ($_SESSION['IdEmpresa'] ?? $_GET['id_empresa'] ?? $_POST['id_empresa'] ?? 1);
$idUsuario = isset($_SESSION['IdUsuario']) ? (int) $_SESSION['IdUsuario'] : null;

$metodo = $_SERVER['REQUEST_METHOD'];
$accion = $_GET['accion'] ?? $_POST['accion'] ?? '';

$stmtTabla = $pdo->prepare("
    SELECT COUNT(*)
    FROM information_schema.tables
    WHERE table_schema = :db AND table_name = 'movimientobodega'
");
$stmtTabla->execute([':db' => $db]);
$tieneHistorialMov = ((int) $stmtTabla->fetchColumn()) > 0;

if ($metodo === 'GET' && $accion === 'listar') {
    try {
        $stmtBodegas = $pdo->prepare("
            SELECT IdBodega, Nombre, Direccion, Responsable, Estado
            FROM bodega
            WHERE IdEmpresa = :empresa AND Estado = 'Activo'
            ORDER BY Nombre ASC
        ");
        $stmtBodegas->execute([':empresa' => $idEmpresa]);
        $bodegas = $stmtBodegas->fetchAll();

        $stmtInv = $pdo->prepare("
            SELECT 
                i.IdInventario,
                i.IdProducto,
                i.IdBodega,
                p.Codigo,
                p.Nombre AS producto,
                b.Nombre AS bodega,
                i.CantidadDisponible AS cantidad,
                i.CantidadMinima,
                i.FechaActualizacion
            FROM inventario i
            INNER JOIN producto p ON i.IdProducto = p.IdProducto
            INNER JOIN bodega b ON i.IdBodega = b.IdBodega
            WHERE p.IdEmpresa = :empresa AND b.IdEmpresa = :empresa
            ORDER BY i.FechaActualizacion DESC
            LIMIT 50
        ");
        $stmtInv->execute([':empresa' => $idEmpresa]);
        $inventario = $stmtInv->fetchAll();

        $movimientos = [];
        if ($tieneHistorialMov) {
            $stmtMov = $pdo->prepare("
                SELECT 
                    m.IdMovimiento,
                    m.TipoMovimiento AS tipo,
                    p.Nombre AS producto,
                    b.Nombre AS bodega,
                    m.Cantidad AS cantidad,
                    COALESCE(m.VehiculoGuia, 'N/A') AS vehiculo,
                    m.Estado,
                    m.FechaMovimiento
                FROM movimientobodega m
                INNER JOIN producto p ON m.IdProducto = p.IdProducto
                INNER JOIN bodega b ON m.IdBodega = b.IdBodega
                WHERE m.IdEmpresa = :empresa
                ORDER BY m.IdMovimiento DESC
                LIMIT 50
            ");
            $stmtMov->execute([':empresa' => $idEmpresa]);
            $movimientos = $stmtMov->fetchAll();
        } else {
            foreach ($inventario as $fila) {
                $movimientos[] = [
                    'tipo'     => 'STOCK',
                    'producto' => $fila['producto'],
                    'bodega'   => $fila['bodega'],
                    'cantidad' => (int) $fila['cantidad'],
                    'vehiculo' => $fila['Codigo'],
                    'estado'   => 'En inventario'
                ];
            }
        }

        $stmtStock = $pdo->prepare("
            SELECT COALESCE(SUM(i.CantidadDisponible), 0)
            FROM inventario i
            INNER JOIN producto p ON i.IdProducto = p.IdProducto
            WHERE p.IdEmpresa = :empresa
        ");
        $stmtStock->execute([':empresa' => $idEmpresa]);
        $stockTotal = (int) $stmtStock->fetchColumn();

        $carguesHoy = 0;
        $descarguesHoy = 0;
        if ($tieneHistorialMov) {
            $stmtCar = $pdo->prepare("
                SELECT COUNT(*) FROM movimientobodega
                WHERE IdEmpresa = :empresa AND TipoMovimiento = 'CARGUE' AND DATE(FechaMovimiento) = CURDATE()
            ");
            $stmtCar->execute([':empresa' => $idEmpresa]);
            $carguesHoy = (int) $stmtCar->fetchColumn();

            $stmtDes = $pdo->prepare("
                SELECT COUNT(*) FROM movimientobodega
                WHERE IdEmpresa = :empresa AND TipoMovimiento = 'DESCARGUE' AND DATE(FechaMovimiento) = CURDATE()
            ");
            $stmtDes->execute([':empresa' => $idEmpresa]);
            $descarguesHoy = (int) $stmtDes->fetchColumn();
        }

        echo json_encode([
            'ok' => true,
            'bodegas' => $bodegas,
            'inventario' => $inventario,
            'movimientos' => $movimientos,
            'kpis' => [
                'bodegas'    => count($bodegas),
                'stock'      => $stockTotal,
                'cargues'    => $carguesHoy,
                'descargues' => $descarguesHoy
            ]
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Error al consultar datos de bodega']);
    }
    exit;
}

if ($metodo === 'POST' && $accion === 'registrar_movimiento') {
    $tipo       = strtoupper(trim($_POST['tipo_movimiento'] ?? ''));
    $idBodega   = (int) ($_POST['bodega_id'] ?? 0);
    $nombreProd = trim($_POST['producto'] ?? '');
    $cantidad   = (int) ($_POST['cantidad'] ?? 0);
    $vehiculo   = trim($_POST['vehiculo'] ?? '');

    $tiposValidos = ['CARGUE', 'DESCARGUE', 'TRASLADO'];

    if (!in_array($tipo, $tiposValidos, true) || $idBodega <= 0 || $nombreProd === '' || $cantidad <= 0) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Verifica el tipo, bodega, producto y cantidad']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        $stmtBod = $pdo->prepare("SELECT IdBodega FROM bodega WHERE IdBodega = :bodega AND IdEmpresa = :empresa LIMIT 1");
        $stmtBod->execute([':bodega' => $idBodega, ':empresa' => $idEmpresa]);
        if (!$stmtBod->fetch()) {
            throw new RuntimeException('La bodega seleccionada no existe para esta empresa');
        }

        $stmtProd = $pdo->prepare("
            SELECT IdProducto
            FROM producto
            WHERE IdEmpresa = :empresa AND (LOWER(Nombre) = LOWER(:nombre) OR LOWER(Codigo) = LOWER(:nombre))
            LIMIT 1
        ");
        $stmtProd->execute([':empresa' => $idEmpresa, ':nombre' => $nombreProd]);
        $prod = $stmtProd->fetch();

        if (!$prod) {
            $stmtCat = $pdo->prepare("SELECT IdCategoria FROM categoria WHERE IdEmpresa = :empresa ORDER BY IdCategoria ASC LIMIT 1");
            $stmtCat->execute([':empresa' => $idEmpresa]);
            $idCategoria = (int) $stmtCat->fetchColumn();

            if ($idCategoria <= 0) {
                $stmtInsCat = $pdo->prepare("INSERT INTO categoria (IdEmpresa, Nombre, Descripcion, Estado) VALUES (:empresa, 'General', 'Categoría general', 'Activo')");
                $stmtInsCat->execute([':empresa' => $idEmpresa]);
                $idCategoria = (int) $pdo->lastInsertId();
            }

            $codigoGenerado = 'PRD-' . strtoupper(substr(uniqid(), -6));
            $stmtInsProd = $pdo->prepare("
                INSERT INTO producto (IdEmpresa, IdCategoria, Codigo, Nombre, PrecioCompra, PrecioVenta, StockMinimo, Estado)
                VALUES (:empresa, :categoria, :codigo, :nombre, 0.00, 0.00, 0, 'Activo')
            ");
            $stmtInsProd->execute([
                ':empresa'   => $idEmpresa,
                ':categoria' => $idCategoria,
                ':codigo'    => $codigoGenerado,
                ':nombre'    => $nombreProd
            ]);
            $idProducto = (int) $pdo->lastInsertId();
        } else {
            $idProducto = (int) $prod['IdProducto'];
        }

        $delta = ($tipo === 'CARGUE') ? $cantidad : -$cantidad;

        $stmtInvCheck = $pdo->prepare("
            SELECT IdInventario, CantidadDisponible
            FROM inventario
            WHERE IdProducto = :producto AND IdBodega = :bodega
            LIMIT 1
        ");
        $stmtInvCheck->execute([':producto' => $idProducto, ':bodega' => $idBodega]);
        $invRow = $stmtInvCheck->fetch();

        if ($invRow) {
            $nuevoStock = max(0, (int) $invRow['CantidadDisponible'] + $delta);
            $stmtInvUpd = $pdo->prepare("
                UPDATE inventario
                SET CantidadDisponible = :cantidad
                WHERE IdInventario = :id
            ");
            $stmtInvUpd->execute([
                ':cantidad' => $nuevoStock,
                ':id'       => (int) $invRow['IdInventario']
            ]);
        } else {
            $stockInicial = max(0, $delta);
            $stmtInvIns = $pdo->prepare("
                INSERT INTO inventario (IdProducto, IdBodega, CantidadDisponible, CantidadMinima, CantidadMaxima)
                VALUES (:producto, :bodega, :cantidad, 0, 0)
            ");
            $stmtInvIns->execute([
                ':producto' => $idProducto,
                ':bodega'   => $idBodega,
                ':cantidad' => $stockInicial
            ]);
        }

        $estado = ($tipo === 'DESCARGUE' || $tipo === 'TRASLADO') ? 'En ruta' : 'Completado';
        if ($tieneHistorialMov) {
            $stmtMovIns = $pdo->prepare("
                INSERT INTO movimientobodega (IdEmpresa, IdBodega, IdProducto, IdUsuario, TipoMovimiento, Cantidad, VehiculoGuia, Estado)
                VALUES (:empresa, :bodega, :producto, :usuario, :tipo, :cantidad, :vehiculo, :estado)
            ");
            $stmtMovIns->execute([
                ':empresa'  => $idEmpresa,
                ':bodega'   => $idBodega,
                ':producto' => $idProducto,
                ':usuario'  => $idUsuario,
                ':tipo'     => $tipo,
                ':cantidad' => $cantidad,
                ':vehiculo' => $vehiculo !== '' ? $vehiculo : 'N/A',
                ':estado'   => $estado
            ]);
        }

        $pdo->commit();
        echo json_encode(['ok' => true, 'mensaje' => 'Movimiento e inventario actualizados en wipacer']);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if ($metodo === 'POST' && $accion === 'crear_bodega') {
    $nombre      = trim($_POST['nombre'] ?? '');
    $direccion   = trim($_POST['direccion'] ?? '');
    $responsable = trim($_POST['responsable'] ?? '');

    if ($nombre === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'El nombre de la bodega es obligatorio']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO bodega (IdEmpresa, Nombre, Direccion, Responsable, Estado)
            VALUES (:empresa, :nombre, :direccion, :responsable, 'Activo')
        ");
        $stmt->execute([
            ':empresa'     => $idEmpresa,
            ':nombre'      => $nombre,
            ':direccion'   => $direccion !== '' ? $direccion : null,
            ':responsable' => $responsable !== '' ? $responsable : null
        ]);

        echo json_encode(['ok' => true, 'mensaje' => 'Bodega creada correctamente']);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Error al guardar la bodega en MySQL']);
    }
    exit;
}

http_response_code(400);
echo json_encode(['ok' => false, 'error' => 'Acción no válida']);
