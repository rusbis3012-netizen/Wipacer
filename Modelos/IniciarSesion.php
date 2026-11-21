<?php

session_start();

header("Content-Type: application/json; charset=utf-8");

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'mensaje' => 'Método HTTP no permitido.']);
        exit;
    }

    $datos = json_decode(file_get_contents('php://input'), true);

    if (!is_array($datos)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'mensaje' => 'Datos inválidos.']);
        exit;
    }

    $correo = $datos['Correo'] ?? '';
    $contrasena = $datos['Contrasena'] ?? '';

    if (!is_string($correo) || !is_string($contrasena) ||
        trim($correo) === '' || $contrasena === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'mensaje' => 'Correo o contraseña inválidos.']);
        exit;
    }

    require_once __DIR__ . '/../Config/Conexion.php';
    $conexion = (new Conexion())->conectar();

    $consulta = $conexion->prepare(
        'SELECT * FROM usuario WHERE Correo = :Correo'
    );
    $consulta->execute([':Correo' => trim($correo)]);
    $usuario = $consulta->fetch();

    if (!$usuario ||
        empty($usuario['Contrasena']) ||
        !password_verify($contrasena, $usuario['Contrasena'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'mensaje' => 'Correo o contraseña incorrectos.']);
        exit;
    }

    $rol = $usuario['Rol'] ?? $usuario['rol'] ?? '';

    $_SESSION['rol'] = strtolower((string) $rol);

    echo json_encode([
        'success' => true,
        'rol' => $_SESSION['rol'],
        'nombre' => trim(
            ($usuario['Nombre'] ?? $usuario['nombres'] ?? '') . ' ' .
            ($usuario['Apellido'] ?? $usuario['apellidos'] ?? '')
        )
    ]);
} catch (PDOException $error) {
    error_log($error->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'mensaje' => 'Error interno al iniciar sesión.']);
} catch (Exception $error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'mensaje' => 'Error interno al iniciar sesión.']);
}
