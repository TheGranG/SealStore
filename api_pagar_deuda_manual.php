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

    // Checar tu stock local para pagar
    $ch_stk = curl_init($supabase_url . "/rest/v1/inventario?id_sucursal=eq.{$id_sucursal}&id_producto=eq.{$p['id_producto']}&select=id_inventario,stock");
    curl_setopt($ch_stk, CURLOPT_HTTPHEADER, ['apikey: '.$api_key, 'Authorization: Bearer '.$api_key]);
    curl_setopt($ch_stk, CURLOPT_RETURNTRANSFER, true); curl_setopt($ch_stk, CURLOPT_SSL_VERIFYPEER, false);
    $stock_res = json_decode(curl_exec($ch_stk), true); curl_close($ch_stk);

    $stock_ant = (!empty($stock_res)) ? (int)$stock_res[0]['stock'] : 0;
    if ($stock_ant < $p['cantidad_prestada']) throw new Exception('No tienes stock suficiente para devolver.');

    // DESCONTAR de tu inventario
    $nuevo_stock = $stock_ant - $p['cantidad_prestada'];
    $ch_upd = curl_init($supabase_url . "/rest/v1/inventario?id_inventario=eq." . $stock_res[0]['id_inventario']);
    curl_setopt($ch_upd, CURLOPT_CUSTOMREQUEST, 'PATCH');
    curl_setopt($ch_upd, CURLOPT_POSTFIELDS, json_encode(['stock' => $nuevo_stock]));
    curl_setopt($ch_upd, CURLOPT_HTTPHEADER, ['apikey: '.$api_key, 'Authorization: Bearer '.$api_key, 'Content-Type: application/json']);
    curl_setopt($ch_upd, CURLOPT_RETURNTRANSFER, true); curl_setopt($ch_upd, CURLOPT_SSL_VERIFYPEER, false);
    curl_exec($ch_upd); curl_close($ch_upd);

    // Cambiar estado para notificar al otro que ya va en camino
    // Cambiar estado para notificar al otro que ya va en camino
$ch_est = curl_init($supabase_url . "/rest/v1/prestamos?id_prestamo=eq.{$id_p}");
curl_setopt($ch_est, CURLOPT_CUSTOMREQUEST, 'PATCH');
curl_setopt($ch_est, CURLOPT_POSTFIELDS, json_encode([
    'estado'             => 'PAGADO',
    'cantidad_devuelta'  => (int)$p['cantidad_prestada'],
    'fecha_liquidacion'  => date('c') // opcional, pero recomendado
]));
    curl_setopt($ch_est, CURLOPT_HTTPHEADER, ['apikey: '.$api_key, 'Authorization: Bearer '.$api_key, 'Content-Type: application/json']);
    curl_setopt($ch_est, CURLOPT_RETURNTRANSFER, true); curl_setopt($ch_est, CURLOPT_SSL_VERIFYPEER, false);
    curl_exec($ch_est); curl_close($ch_est);

    echo json_encode(['error' => false]);
} catch (Throwable $e) {
    echo json_encode(['error' => true, 'mensaje' => $e->getMessage()]);
}
?>