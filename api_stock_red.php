<?php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');

try {
    require 'config.php';
    $sku = $_GET['sku'] ?? '';
    if (!$sku) {
        throw new Exception("SKU vacio");
    }

    $ch_prod = curl_init($supabase_url . "/rest/v1/productos?codigo_barras=eq." . rawurlencode($sku) . "&select=id_producto");
    curl_setopt($ch_prod, CURLOPT_HTTPHEADER, ['apikey: ' . $api_key, 'Authorization: Bearer ' . $api_key]);
    curl_setopt($ch_prod, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch_prod, CURLOPT_SSL_VERIFYPEER, false);
    $res_prod = json_decode(curl_exec($ch_prod), true);
    curl_close($ch_prod);

    if (empty($res_prod)) throw new Exception("No encontrado");
    $id_prod = $res_prod[0]['id_producto'];

    $ch_inv = curl_init($supabase_url . "/rest/v1/inventario?id_producto=eq.{$id_prod}&id_sucursal=neq.{$id_sucursal}&stock=gt.0&select=id_sucursal,stock");
    curl_setopt($ch_inv, CURLOPT_HTTPHEADER, ['apikey: ' . $api_key, 'Authorization: Bearer ' . $api_key]);
    curl_setopt($ch_inv, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch_inv, CURLOPT_SSL_VERIFYPEER, false);
    $res_inv = json_decode(curl_exec($ch_inv), true);
    curl_close($ch_inv);

    $sucursales = [];
    if (is_array($res_inv) && !isset($res_inv['message'])) {
        foreach ($res_inv as $row) {
            $sucursales[] = [
                'id_sucursal' => $row['id_sucursal'],
                'cantidad_actual' => $row['stock']
            ];
        }
    }
    ob_end_clean();
    echo json_encode($sucursales);
} catch (Throwable $e) {
    ob_end_clean();
    echo json_encode([]);
}
?>