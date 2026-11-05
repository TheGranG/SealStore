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

    if (!$data || !isset($data['id_prestamo'])) {
        throw new Exception('Faltan datos.');
    }
    $id_prestamo = (int)$data['id_prestamo'];

    $ch_p = curl_init($supabase_url . "/rest/v1/prestamos_sucursales?id_prestamo=eq.{$id_prestamo}");
    curl_setopt($ch_p, CURLOPT_HTTPHEADER, ['apikey: '.$api_key, 'Authorization: Bearer '.$api_key]);
    curl_setopt($ch_p, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch_p, CURLOPT_SSL_VERIFYPEER, false);
    $prestamo = json_decode(curl_exec($ch_p), true);
    curl_close($ch_p);

    if (empty($prestamo) || isset($prestamo['message'])) {
        throw new Exception('Préstamo no encontrado.');
    }
    $p = $prestamo[0];
    $sku = $p['sku_id'];
    $sku_enc = rawurlencode($sku);
    $cantidad = (int)$p['cantidad'];

    $ch_stk = curl_init($supabase_url . "/rest/v1/inventario_nodos?id_sucursal=eq.{$id_sucursal}&sku_id=eq.{$sku_enc}");
    curl_setopt($ch_stk, CURLOPT_HTTPHEADER, ['apikey: '.$api_key, 'Authorization: Bearer '.$api_key]);
    curl_setopt($ch_stk, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch_stk, CURLOPT_SSL_VERIFYPEER, false);
    $stock_res = json_decode(curl_exec($ch_stk), true);
    curl_close($ch_stk);

    $stock_ant = (is_array($stock_res) && !empty($stock_res) && isset($stock_res[0]['cantidad_actual'])) ? (int)$stock_res[0]['cantidad_actual'] : 0;
    $nuevo_stock = $stock_ant + $cantidad;

    $ch_upd = curl_init($supabase_url . "/rest/v1/inventario_nodos?id_sucursal=eq.{$id_sucursal}&sku_id=eq.{$sku_enc}");
    curl_setopt($ch_upd, CURLOPT_CUSTOMREQUEST, 'PATCH');
    curl_setopt($ch_upd, CURLOPT_POSTFIELDS, json_encode(['cantidad_actual' => $nuevo_stock]));
    curl_setopt($ch_upd, CURLOPT_HTTPHEADER, ['apikey: '.$api_key, 'Authorization: Bearer '.$api_key, 'Content-Type: application/json']);
    curl_setopt($ch_upd, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch_upd, CURLOPT_SSL_VERIFYPEER, false);
    curl_exec($ch_upd);
    curl_close($ch_upd);

    $ch_est = curl_init($supabase_url . "/rest/v1/prestamos_sucursales?id_prestamo=eq.{$id_prestamo}");
    curl_setopt($ch_est, CURLOPT_CUSTOMREQUEST, 'PATCH');
    curl_setopt($ch_est, CURLOPT_POSTFIELDS, json_encode([
        'estado' => 'EN_DEUDA',
        'fecha_actualizacion' => date('c')
    ]));
    curl_setopt($ch_est, CURLOPT_HTTPHEADER, ['apikey: '.$api_key, 'Authorization: Bearer '.$api_key, 'Content-Type: application/json']);
    curl_setopt($ch_est, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch_est, CURLOPT_SSL_VERIFYPEER, false);
    curl_exec($ch_est);
    curl_close($ch_est);

    $ch_mov = curl_init($supabase_url . "/rest/v1/movimientos");
    curl_setopt($ch_mov, CURLOPT_POST, true);
    curl_setopt($ch_mov, CURLOPT_POSTFIELDS, json_encode([
        'sku_id' => $sku,
        'tipo_movimiento' => 'ENTRADA',
        'cantidad' => $cantidad,
        'descripcion' => "Ingreso de Préstamo. Estado: EN_DEUDA.",
        'fecha_movimiento' => date('c'),
        'id_sucursal' => $id_sucursal,
        'folio_referencia' => $p['folio']
    ]));
    curl_setopt($ch_mov, CURLOPT_HTTPHEADER, ['apikey: '.$api_key, 'Authorization: Bearer '.$api_key, 'Content-Type: application/json']);
    curl_setopt($ch_mov, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch_mov, CURLOPT_SSL_VERIFYPEER, false);
    curl_exec($ch_mov);
    curl_close($ch_mov);

    ob_end_clean();
    echo json_encode(['error' => false, 'mensaje' => 'Stock sumado exitosamente. Se ha marcado en deuda.']);
} catch (Throwable $e) {
    ob_end_clean();
    echo json_encode(['error' => true, 'mensaje' => $e->getMessage()]);
}
?>