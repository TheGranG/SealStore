<?php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}
header('Content-Type: application/json; charset=utf-8');

try {
    require 'config.php';
    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data || !isset($data['sku']) || !isset($data['suc_prestadora']) || !isset($data['cantidad'])) {
        throw new Exception("Datos incompletos.");
    }

    // 1. Obtener el id_producto a partir del SKU (código de barras)
    $ch_prod = curl_init($supabase_url . "/rest/v1/productos?codigo_barras=eq." . rawurlencode($data['sku']) . "&select=id_producto");
    curl_setopt($ch_prod, CURLOPT_HTTPHEADER, ['apikey: ' . $api_key, 'Authorization: Bearer ' . $api_key]);
    curl_setopt($ch_prod, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch_prod, CURLOPT_SSL_VERIFYPEER, false);
    $res_prod = json_decode(curl_exec($ch_prod), true);
    curl_close($ch_prod);

    if (empty($res_prod) || isset($res_prod['message'])) {
        throw new Exception("Producto no encontrado en la base de datos central.");
    }
    
    $id_producto_real = $res_prod[0]['id_producto'];

    // 2. Insertar en la tabla oficial 'prestamos'
    $ch_pres = curl_init($supabase_url . "/rest/v1/prestamos");
    curl_setopt($ch_pres, CURLOPT_POST, true);
    curl_setopt($ch_pres, CURLOPT_POSTFIELDS, json_encode([
        'id_sucursal_solicitante' => $id_sucursal,
        'id_sucursal_prestamista' => (int)$data['suc_prestadora'],
        'id_producto' => $id_producto_real,
        'cantidad_prestada' => (int)$data['cantidad'],
        'cantidad_devuelta' => 0,
        'estado' => 'PENDIENTE',
        'fecha_prestamo' => date('c')
    ]));
    curl_setopt($ch_pres, CURLOPT_HTTPHEADER, ['apikey: ' . $api_key, 'Authorization: Bearer ' . $api_key, 'Content-Type: application/json']);
    curl_setopt($ch_pres, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch_pres, CURLOPT_SSL_VERIFYPEER, false);
    $res = curl_exec($ch_pres);
    $http_code = curl_getinfo($ch_pres, CURLINFO_HTTP_CODE);
    curl_close($ch_pres);

    if ($http_code >= 400) {
        throw new Exception("Error al registrar el préstamo en Supabase.");
    }

    ob_end_clean();
    echo json_encode(['error' => false, 'mensaje' => 'Solicitud enviada a la sucursal ' . $data['suc_prestadora']]);

} catch (Throwable $e) {
    ob_end_clean();
    echo json_encode(['error' => true, 'mensaje' => $e->getMessage()]);
}
?>