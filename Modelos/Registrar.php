<?php
require_once __DIR__ . '/../Config/Conexion.php';

header('Content-Type: application/json; charset=utf-8');

function responder($estado, array $respuesta)
{
    http_response_code($estado);
    echo json_encode($respuesta, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
}

$metodo = $_SERVER['REQUEST_METHOD'] ?? '';

try {
    $conexion = (new Conexion())->conectar();

    if ($metodo === 'GET' && isset($_GET['empresas'])) {
        $consulta = $conexion->query(
            "SELECT IdEmpresa, NombreEmpresa
             FROM empresa
             WHERE Estado = 'Activo'
             ORDER BY NombreEmpresa"
        );

        responder(200, ['success' => true, 'empresas' => $consulta->fetchAll()]);
        exit;
    }

    if ($metodo !== 'POST') {
        responder(405, ['success' => false, 'mensaje' => 'Método no permitido.']);
        exit;
    }

    try {
        $datos = json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $error) {
        responder(400, ['success' => false, 'mensaje' => 'El contenido enviado no es JSON válido.']);
        exit;
    }

    if (!is_array($datos)) {
        responder(400, ['success' => false, 'mensaje' => 'Los datos enviados no son válidos.']);
        exit;
    }

    foreach (['Nombre', 'Apellido', 'Correo', 'Contrasena'] as $campo) {
        if (!isset($datos[$campo]) || !is_string($datos[$campo]) || trim($datos[$campo]) === '') {
            responder(400, ['success' => false, 'mensaje' => 'Completa los campos obligatorios.']);
            exit;
        }
    }

    if (strlen($datos['Contrasena']) < 8) {
        responder(400, ['success' => false, 'mensaje' => 'La contraseña debe tener al menos 8 caracteres.']);
        exit;
    }

    if (isset($datos['Direccion']) && !is_string($datos['Direccion'])) {
        responder(400, ['success' => false, 'mensaje' => 'La dirección no es válida.']);
        exit;
    }

    if (isset($datos['Telefono']) && !is_string($datos['Telefono'])) {
        responder(400, ['success' => false, 'mensaje' => 'El teléfono no es válido.']);
        exit;
    }

    if (!filter_var($datos['Correo'], FILTER_VALIDATE_EMAIL)) {
        responder(400, ['success' => false, 'mensaje' => 'Ingresa un correo válido.']);
        exit;
    }

    $idEmpresa = filter_var($datos['IdEmpresa'] ?? null, FILTER_VALIDATE_INT);
    if ($idEmpresa === false || $idEmpresa === null || $idEmpresa < 1) {
        responder(400, ['success' => false, 'mensaje' => 'Selecciona una empresa válida.']);
        exit;
    }

    $consulta = $conexion->prepare(
        "SELECT 1 FROM empresa WHERE IdEmpresa = :IdEmpresa AND Estado = 'Activo'"
    );
    $consulta->execute([':IdEmpresa' => $idEmpresa]);
    if (!$consulta->fetchColumn()) {
        responder(400, ['success' => false, 'mensaje' => 'La empresa seleccionada no existe o está inactiva.']);
        exit;
    }

    $correo = trim($datos['Correo']);
    $consulta = $conexion->prepare('SELECT 1 FROM usuario WHERE Correo = :Correo');
    $consulta->execute([':Correo' => $correo]);
    if ($consulta->fetchColumn()) {
        responder(409, ['success' => false, 'mensaje' => 'Ya existe una cuenta con ese correo.']);
        exit;
    }

    $telefono = isset($datos['Telefono']) && trim($datos['Telefono']) !== '' ? trim($datos['Telefono']) : null;
    $direccion = isset($datos['Direccion']) && trim($datos['Direccion']) !== '' ? trim($datos['Direccion']) : null;
    if ($direccion !== null && strlen($direccion) > 50) {
        responder(400, ['success' => false, 'mensaje' => 'La dirección no puede superar 50 caracteres.']);
        exit;
    }

    $contrasenaHash = password_hash($datos['Contrasena'], PASSWORD_DEFAULT);

    $consulta = $conexion->prepare(
        'INSERT INTO usuario (IdEmpresa, Nombre, Apellido, Correo, Contrasena, Telefono, Direccion)
         VALUES (:IdEmpresa, :Nombre, :Apellido, :Correo, :Contrasena, :Telefono, :Direccion)'
    );
    $consulta->execute([
        ':IdEmpresa' => $idEmpresa,
        ':Nombre' => trim($datos['Nombre']),
        ':Apellido' => trim($datos['Apellido']),
        ':Correo' => $correo,
        ':Contrasena' => $contrasenaHash,
        ':Telefono' => $telefono,
        ':Direccion' => $direccion
    ]);

    responder(201, ['success' => true, 'mensaje' => 'La cuenta se registró correctamente.']);
} catch (PDOException $error) {
    error_log($error->getMessage());
    if ($error->getCode() === '23000') {
        responder(409, ['success' => false, 'mensaje' => 'Ya existe una cuenta con ese correo.']);
        exit;
    }

    responder(500, ['success' => false, 'mensaje' => 'Error interno al registrar la cuenta.']);
}
