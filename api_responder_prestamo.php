<?php
error_reporting(E_ALL); ini_set('display_errors', 0);
header('Access-Control-Allow-Origin: *'); header('Content-Type: application/json; charset=utf-8');

try {
    require 'config.php';
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data || !isset($data['id_prestamo'])) throw new Exception('Datos incompletos.');

    $id_p = (int)$data['id_prestamo'];
    $accion = $data['accion'];

    // Obtenemos los detalles del préstamo
    $ch_p = curl_init($supabase_url . "/rest/v1/prestamos?id_prestamo=eq.{$id_p}");
    curl_setopt($ch_p, CURLOPT_HTTPHEADER, ['apikey: '.$api_key, 'Authorization: Bearer '.$api_key]);
    curl_setopt($ch_p, CURLOPT_RETURNTRANSFER, true); curl_setopt($ch_p, CURLOPT_SSL_VERIFYPEER, false);
    $res_p = json_decode(curl_exec($ch_p), true); curl_close($ch_p);
    
    if (empty($res_p)) throw new Exception("Préstamo no encontrado.");
    $p = $res_p[0];

    // Si estás aceptando o marcando activo, te DESCONTAMOS de inventario
    if (in_array($accion, ['ACEPTADO', 'ACTIVO'])) {
        $ch_stk = curl_init($supabase_url . "/rest/v1/inventario?id_sucursal=eq.{$id_sucursal}&id_producto=eq.{$p['id_producto']}&select=id_inventario,stock");
        curl_setopt($ch_stk, CURLOPT_HTTPHEADER, ['apikey: '.$api_key, 'Authorization: Bearer '.$api_key]);
        curl_setopt($ch_stk, CURLOPT_RETURNTRANSFER, true); curl_setopt($ch_stk, CURLOPT_SSL_VERIFYPEER, false);
        $res_stk = json_decode(curl_exec($ch_stk), true); curl_close($ch_stk);

        $stk = (!empty($res_stk)) ? (int)$res_stk[0]['stock'] : 0;
        if ($stk < $p['cantidad_prestada']) throw new Exception("Stock insuficiente en piso para prestar.");
        
        $nuevo_stock = $stk - $p['cantidad_prestada'];

        // Restamos tu inventario
        $ch_upd = curl_init($supabase_url . "/rest/v1/inventario?id_inventario=eq." . $res_stk[0]['id_inventario']);
        curl_setopt($ch_upd, CURLOPT_CUSTOMREQUEST, 'PATCH');
        curl_setopt($ch_upd, CURLOPT_POSTFIELDS, json_encode(['stock' => $nuevo_stock]));
        curl_setopt($ch_upd, CURLOPT_HTTPHEADER, ['apikey: '.$api_key, 'Authorization: Bearer '.$api_key, 'Content-Type: application/json']);
        curl_setopt($ch_upd, CURLOPT_RETURNTRANSFER, true); curl_setopt($ch_upd, CURLOPT_SSL_VERIFYPEER, false);
        curl_exec($ch_upd); curl_close($ch_upd);
    }

    // Actualizar el estado del préstamo
    $ch_est = curl_init($supabase_url . "/rest/v1/prestamos?id_prestamo=eq.{$id_p}");
    curl_setopt($ch_est, CURLOPT_CUSTOMREQUEST, 'PATCH');
    curl_setopt($ch_est, CURLOPT_POSTFIELDS, json_encode(['estado' => $accion]));
    curl_setopt($ch_est, CURLOPT_HTTPHEADER, ['apikey: '.$api_key, 'Authorization: Bearer '.$api_key, 'Content-Type: application/json']);
    curl_setopt($ch_est, CURLOPT_RETURNTRANSFER, true); curl_setopt($ch_est, CURLOPT_SSL_VERIFYPEER, false);
    curl_exec($ch_est); curl_close($ch_est);

    echo json_encode(['error' => false]);
} catch (Throwable $e) {
    echo json_encode(['error' => true, 'mensaje' => $e->getMessage()]);
}
?>