<?php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');

try {
    require 'config.php';

    $ch_ped = curl_init($supabase_url . "/rest/v1/pedidos_abastecimiento?id_sucursal=eq.{$id_sucursal}");
    curl_setopt($ch_ped, CURLOPT_HTTPHEADER, ['apikey: ' . $api_key, 'Authorization: Bearer ' . $api_key]);
    curl_setopt($ch_ped, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch_ped, CURLOPT_SSL_VERIFYPEER, false);
    $pedidos = json_decode(curl_exec($ch_ped), true);
    curl_close($ch_ped);

    $rastreo = [];
    if (is_array($pedidos) && !isset($pedidos['message'])) {
        foreach ($pedidos as $pedido) {
            $id_pedido = $pedido['id_pedido'];
            $estado = $pedido['estado'];
            $fecha = $pedido['fecha_pedido'];

            $ch_det = curl_init($supabase_url . "/rest/v1/detalle_pedidos?id_pedido=eq.{$id_pedido}&select=cantidad,id_producto");
            curl_setopt($ch_det, CURLOPT_HTTPHEADER, ['apikey: ' . $api_key, 'Authorization: Bearer ' . $api_key]);
            curl_setopt($ch_det, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch_det, CURLOPT_SSL_VERIFYPEER, false);
            $detalles = json_decode(curl_exec($ch_det), true);
            curl_close($ch_det);

            if (is_array($detalles) && !isset($detalles['message'])) {
                foreach ($detalles as $det) {
                    $id_prod = $det['id_producto'];
                    $cant = $det['cantidad'];

                    $ch_prod = curl_init($supabase_url . "/rest/v1/productos?id_producto=eq.{$id_prod}&select=codigo_barras");
                    curl_setopt($ch_prod, CURLOPT_HTTPHEADER, ['apikey: ' . $api_key, 'Authorization: Bearer ' . $api_key]);
                    curl_setopt($ch_prod, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch_prod, CURLOPT_SSL_VERIFYPEER, false);
                    $prod_res = json_decode(curl_exec($ch_prod), true);
                    curl_close($ch_prod);

                    $sku = (!empty($prod_res) && !isset($prod_res['message'])) ? $prod_res[0]['codigo_barras'] : 'Desc';

                    $rastreo[] = [
                        'id_movimiento' => $id_pedido,
                        'folio_referencia' => 'ORD-MATRIZ-' . $id_pedido,
                        'sku_id' => $sku,
                        'cantidad' => $cant,
                        'fecha_movimiento' => $fecha,
                        'tipo_movimiento' => $estado
                    ];
                }
            }
        }
    }
    ob_end_clean();
    echo json_encode($rastreo);
} catch (Throwable $e) {
    ob_end_clean();
    echo json_encode([]);
}
?>