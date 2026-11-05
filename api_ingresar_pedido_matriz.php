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

    if (!$data || empty($data['id_movimiento'])) {
        throw new Exception("Datos incompletos.");
    }
    $id_pedido = (int)$data['id_movimiento'];

    $ch_det = curl_init($supabase_url . "/rest/v1/detalle_pedidos?id_pedido=eq.{$id_pedido}&select=cantidad,id_producto");
    curl_setopt($ch_det, CURLOPT_HTTPHEADER, ['apikey: ' . $api_key, 'Authorization: Bearer ' . $api_key]);
    curl_setopt($ch_det, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch_det, CURLOPT_SSL_VERIFYPEER, false);
    $detalles = json_decode(curl_exec($ch_det), true);
    curl_close($ch_det);

    if (empty($detalles)) throw new Exception("Pedido vacio o no existe.");

    foreach ($detalles as $det) {
        $id_prod = $det['id_producto'];
        $cant = $det['cantidad'];

        $ch_inv = curl_init($supabase_url . "/rest/v1/inventario?id_sucursal=eq.{$id_sucursal}&id_producto=eq.{$id_prod}");
        curl_setopt($ch_inv, CURLOPT_HTTPHEADER, ['apikey: ' . $api_key, 'Authorization: Bearer ' . $api_key]);
        curl_setopt($ch_inv, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch_inv, CURLOPT_SSL_VERIFYPEER, false);
        $inv = json_decode(curl_exec($ch_inv), true);
        curl_close($ch_inv);

        if (!empty($inv)) {
            $nuevo_stock = $inv[0]['stock'] + $cant;
            $ch_upd = curl_init($supabase_url . "/rest/v1/inventario?id_inventario=eq." . $inv[0]['id_inventario']);
            curl_setopt($ch_upd, CURLOPT_CUSTOMREQUEST, 'PATCH');
            curl_setopt($ch_upd, CURLOPT_POSTFIELDS, json_encode(['stock' => $nuevo_stock]));
            curl_setopt($ch_upd, CURLOPT_HTTPHEADER, ['apikey: ' . $api_key, 'Authorization: Bearer ' . $api_key, 'Content-Type: application/json']);
            curl_setopt($ch_upd, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch_upd, CURLOPT_SSL_VERIFYPEER, false);
            curl_exec($ch_upd);
            curl_close($ch_upd);
        } else {
            $ch_ins = curl_init($supabase_url . "/rest/v1/inventario");
            curl_setopt($ch_ins, CURLOPT_POST, true);
            curl_setopt($ch_ins, CURLOPT_POSTFIELDS, json_encode([
                'id_sucursal' => $id_sucursal,
                'id_producto' => $id_prod,
                'stock' => $cant,
                'stock_minimo' => 5
            ]));
            curl_setopt($ch_ins, CURLOPT_HTTPHEADER, ['apikey: ' . $api_key, 'Authorization: Bearer ' . $api_key, 'Content-Type: application/json']);
            curl_setopt($ch_ins, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch_ins, CURLOPT_SSL_VERIFYPEER, false);
            curl_exec($ch_ins);
            curl_close($ch_ins);
        }
    }

    $ch_upd_ped = curl_init($supabase_url . "/rest/v1/pedidos_abastecimiento?id_pedido=eq.{$id_pedido}");
    curl_setopt($ch_upd_ped, CURLOPT_CUSTOMREQUEST, 'PATCH');
    curl_setopt($ch_upd_ped, CURLOPT_POSTFIELDS, json_encode(['estado' => 'COMPLETADO']));
    curl_setopt($ch_upd_ped, CURLOPT_HTTPHEADER, ['apikey: ' . $api_key, 'Authorization: Bearer ' . $api_key, 'Content-Type: application/json']);
    curl_setopt($ch_upd_ped, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch_upd_ped, CURLOPT_SSL_VERIFYPEER, false);
    curl_exec($ch_upd_ped);
    curl_close($ch_upd_ped);

    ob_end_clean();
    echo json_encode(['error' => false, 'mensaje' => 'Inventario recibido exitosamente.']);
} catch (Throwable $e) {
    ob_end_clean();
    echo json_encode(['error' => true, 'mensaje' => $e->getMessage()]);
}
?>