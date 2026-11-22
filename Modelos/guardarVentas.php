<?php
require_once "../Config/Conexion.php";

$conexion = new Conexion();
$pdo = $conexion->conectar();

header("Content-Type: application/json"); //la respuesta sera en formato json

$datos = json_decode(file_get_contents("php://input"), true);
// file_get_contents("php://input" lee la peticion de http

if(!$datos){
    echo json_encode([
        "success" => false,
        "mensaje" => "No se recibieron los datos correctamente"
    ]);
    exit;
}

if (
    empty($datos["clientes"]) ||
    empty($datos["producto"]) ||
    empty($datos["cantidad"]) ||
    empty($datos["precio"])  ||
    empty($datos["metodoPago"])
)

?>