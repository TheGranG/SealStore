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
    $raw_input = file_get_contents('php://input');
    $data = json_decode($raw_input, true);
    
    if (!$data || empty($data['items'])) {
        throw new Exception("El pedido esta vacio.");
    }
    
    $ch_ped = curl_init($supabase_url . "/rest/v1/pedidos_abastecimiento");
    curl_setopt($ch_ped, CURLOPT_POST, true);
    curl_setopt($ch_ped, CURLOPT_POSTFIELDS, json_encode([
        'id_sucursal' => $id_sucursal,
        'estado' => 'PENDIENTE',
        'fecha_pedido' => date('c')
    ]));
    curl_setopt($ch_ped, CURLOPT_HTTPHEADER, ['apikey: ' . $api_key, 'Authorization: Bearer ' . $api_key, 'Content-Type: application/json', 'Prefer: return=representation']);
    curl_setopt($ch_ped, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch_ped, CURLOPT_SSL_VERIFYPEER, false);
    $res_ped = json_decode(curl_exec($ch_ped), true);
    curl_close($ch_ped);
    
    if (empty($res_ped)) throw new Exception("Error al crear el pedido.");
    $id_pedido = $res_ped[0]['id_pedido'];
    
    foreach ($data['items'] as $item) {
        $sku = $item['sku'];
        $cantidad = (int)$item['cantidad'];
        
        $ch_prod = curl_init($supabase_url . "/rest/v1/productos?codigo_barras=eq." . rawurlencode($sku) . "&select=id_producto");
        curl_setopt($ch_prod, CURLOPT_HTTPHEADER, ['apikey: ' . $api_key, 'Authorization: Bearer ' . $api_key]);
        curl_setopt($ch_prod, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch_prod, CURLOPT_SSL_VERIFYPEER, false);
        $res_prod = json_decode(curl_exec($ch_prod), true);
        curl_close($ch_prod);
        
        if (!empty($res_prod)) {
            $id_prod = $res_prod[0]['id_producto'];
            
            $ch_det = curl_init($supabase_url . "/rest/v1/detalle_pedidos");
            curl_setopt($ch_det, CURLOPT_POST, true);
            curl_setopt($ch_det, CURLOPT_POSTFIELDS, json_encode([
                'id_pedido' => $id_pedido,
                'id_producto' => $id_prod,
                'cantidad' => $cantidad
            ]));
            curl_setopt($ch_det, CURLOPT_HTTPHEADER, ['apikey: ' . $api_key, 'Authorization: Bearer ' . $api_key, 'Content-Type: application/json']);
            curl_setopt($ch_det, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch_det, CURLOPT_SSL_VERIFYPEER, false);
            curl_exec($ch_det);
            curl_close($ch_det);
        }
    }
    
    ob_end_clean();
    echo json_encode(['error' => false, 'mensaje' => 'Pedido enviado a Matriz exitosamente.']);
    
} catch (Throwable $e) {
    ob_end_clean();
    echo json_encode(['error' => true, 'mensaje' => $e->getMessage()]);
}
?>