<?php
error_reporting(E_ALL); ini_set('display_errors', 0);
header('Access-Control-Allow-Origin: *'); header('Content-Type: application/json; charset=utf-8');

try {
    require 'config.php';
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data || !isset($data['id_prestamo'])) throw new Exception('Faltan datos.');

    $id_p = (int)$data['id_prestamo'];

    $ch_p = curl_init($supabase_url . "/rest/v1/prestamos?id_prestamo=eq.{$id_p}");
    curl_setopt($ch_p, CURLOPT_HTTPHEADER, ['apikey: '.$api_key, 'Authorization: Bearer '.$api_key]);
    curl_setopt($ch_p, CURLOPT_RETURNTRANSFER, true); curl_setopt($ch_p, CURLOPT_SSL_VERIFYPEER, false);
    $prestamo = json_decode(curl_exec($ch_p), true); curl_close($ch_p);
    
    if (empty($prestamo)) throw new Exception('Préstamo no encontrado.');
    $p = $prestamo[0];

    // Obtenemos tu inventario
    $ch_stk = curl_init($supabase_url . "/rest/v1/inventario?id_sucursal=eq.{$id_sucursal}&id_producto=eq.{$p['id_producto']}");
    curl_setopt($ch_stk, CURLOPT_HTTPHEADER, ['apikey: '.$api_key, 'Authorization: Bearer '.$api_key]);
    curl_setopt($ch_stk, CURLOPT_RETURNTRANSFER, true); curl_setopt($ch_stk, CURLOPT_SSL_VERIFYPEER, false);
    $stock_res = json_decode(curl_exec($ch_stk), true); curl_close($ch_stk);

    if (!empty($stock_res)) {
        // SUMAMOS EL STOCK a la fila existente
        $nuevo_stock = (int)$stock_res[0]['stock'] + $p['cantidad_prestada'];
        $ch_upd = curl_init($supabase_url . "/rest/v1/inventario?id_inventario=eq." . $stock_res[0]['id_inventario']);
        curl_setopt($ch_upd, CURLOPT_CUSTOMREQUEST, 'PATCH');
        curl_setopt($ch_upd, CURLOPT_POSTFIELDS, json_encode(['stock' => $nuevo_stock]));
        curl_setopt($ch_upd, CURLOPT_HTTPHEADER, ['apikey: '.$api_key, 'Authorization: Bearer '.$api_key, 'Content-Type: application/json']);
        curl_setopt($ch_upd, CURLOPT_RETURNTRANSFER, true); curl_setopt($ch_upd, CURLOPT_SSL_VERIFYPEER, false);
        curl_exec($ch_upd); curl_close($ch_upd);
    } else {
        // SI ES PRODUCTO NUEVO, creamos el inventario
        $ch_ins = curl_init($supabase_url . "/rest/v1/inventario");
        curl_setopt($ch_ins, CURLOPT_POST, true);
        curl_setopt($ch_ins, CURLOPT_POSTFIELDS, json_encode([
            'id_sucursal' => $id_sucursal, 'id_producto' => $p['id_producto'], 'stock' => $p['cantidad_prestada'], 'stock_minimo' => 5
        ]));
        curl_setopt($ch_ins, CURLOPT_HTTPHEADER, ['apikey: '.$api_key, 'Authorization: Bearer '.$api_key, 'Content-Type: application/json']);
        curl_setopt($ch_ins, CURLOPT_RETURNTRANSFER, true); curl_setopt($ch_ins, CURLOPT_SSL_VERIFYPEER, false);
        curl_exec($ch_ins); curl_close($ch_ins);
    }

    // Marcamos la deuda como activa
    $ch_est = curl_init($supabase_url . "/rest/v1/prestamos?id_prestamo=eq.{$id_p}");
    curl_setopt($ch_est, CURLOPT_CUSTOMREQUEST, 'PATCH');
    curl_setopt($ch_est, CURLOPT_POSTFIELDS, json_encode(['estado' => 'EN_DEUDA']));
    curl_setopt($ch_est, CURLOPT_HTTPHEADER, ['apikey: '.$api_key, 'Authorization: Bearer '.$api_key, 'Content-Type: application/json']);
    curl_setopt($ch_est, CURLOPT_RETURNTRANSFER, true); curl_setopt($ch_est, CURLOPT_SSL_VERIFYPEER, false);
    curl_exec($ch_est); curl_close($ch_est);

    echo json_encode(['error' => false, 'mensaje' => 'Stock sumado exitosamente. Se ha marcado en deuda.']);
} catch (Throwable $e) {
    echo json_encode(['error' => true, 'mensaje' => $e->getMessage()]);
}
?>