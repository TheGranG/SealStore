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
        throw new Exception("El ticket esta vacio.");
    }
    
    $id_ticket = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x', mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000, mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff));
    $folio = 'VTA-' . time();
    $subtotal_global = 0;
    $renglon = 1;
    
    foreach ($data['items'] as $item) {
        $sku = $item['sku'];
        $cantidad = (int)$item['cantidad'];
        
        $ch_prod = curl_init($supabase_url . "/rest/v1/productos?codigo_barras=eq." . rawurlencode($sku) . "&select=id_producto,nombre,precio_sugerido");
        curl_setopt($ch_prod, CURLOPT_HTTPHEADER, ['apikey: ' . $api_key, 'Authorization: Bearer ' . $api_key]);
        curl_setopt($ch_prod, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch_prod, CURLOPT_SSL_VERIFYPEER, false);
        $res_prod = json_decode(curl_exec($ch_prod), true);
        curl_close($ch_prod);
        
        if (empty($res_prod)) throw new Exception("Producto no encontrado.");
        $id_prod = $res_prod[0]['id_producto'];
        $precio = $res_prod[0]['precio_sugerido'];
        $nombre = $res_prod[0]['nombre'];
        $subtotal = $cantidad * $precio;
        $subtotal_global += $subtotal;
        
        $ch_inv = curl_init($supabase_url . "/rest/v1/inventario?id_sucursal=eq.{$id_sucursal}&id_producto=eq.{$id_prod}&select=id_inventario,stock");
        curl_setopt($ch_inv, CURLOPT_HTTPHEADER, ['apikey: ' . $api_key, 'Authorization: Bearer ' . $api_key]);
        curl_setopt($ch_inv, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch_inv, CURLOPT_SSL_VERIFYPEER, false);
        $res_inv = json_decode(curl_exec($ch_inv), true);
        curl_close($ch_inv);
        
        if (empty($res_inv) || $res_inv[0]['stock'] < $cantidad) {
            throw new Exception("Stock insuficiente.");
        }
        
        $nuevo_stock = $res_inv[0]['stock'] - $cantidad;
        
        $ch_upd = curl_init($supabase_url . "/rest/v1/inventario?id_inventario=eq." . $res_inv[0]['id_inventario']);
        curl_setopt($ch_upd, CURLOPT_CUSTOMREQUEST, 'PATCH');
        curl_setopt($ch_upd, CURLOPT_POSTFIELDS, json_encode(['stock' => $nuevo_stock]));
        curl_setopt($ch_upd, CURLOPT_HTTPHEADER, ['apikey: ' . $api_key, 'Authorization: Bearer ' . $api_key, 'Content-Type: application/json']);
        curl_setopt($ch_upd, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch_upd, CURLOPT_SSL_VERIFYPEER, false);
        curl_exec($ch_upd);
        curl_close($ch_upd);
        
        $id_detalle = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x', mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000, mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff));
        
        $ch_det = curl_init($supabase_url . "/rest/v1/ticket_detalles");
        curl_setopt($ch_det, CURLOPT_POST, true);
        curl_setopt($ch_det, CURLOPT_POSTFIELDS, json_encode([
            'id_detalle' => $id_detalle,
            'id_ticket' => $id_ticket,
            'renglon' => $renglon,
            'id_producto' => $id_prod,
            'nombre_producto' => $nombre,
            'cantidad' => $cantidad,
            'precio_unitario' => $precio,
            'subtotal' => $subtotal
        ]));
        curl_setopt($ch_det, CURLOPT_HTTPHEADER, ['apikey: ' . $api_key, 'Authorization: Bearer ' . $api_key, 'Content-Type: application/json']);
        curl_setopt($ch_det, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch_det, CURLOPT_SSL_VERIFYPEER, false);
        curl_exec($ch_det);
        curl_close($ch_det);
        
        $ch_mov = curl_init($supabase_url . "/rest/v1/movimientos_kardex");
        curl_setopt($ch_mov, CURLOPT_POST, true);
        curl_setopt($ch_mov, CURLOPT_POSTFIELDS, json_encode([
            'folio' => $folio,
            'tipo' => 'VENTA',
            'origen_destino' => 'Cliente',
            'producto_nombre' => $nombre,
            'cantidad' => $cantidad,
            'monto_total' => $subtotal,
            'fecha' => date('c')
        ]));
        curl_setopt($ch_mov, CURLOPT_HTTPHEADER, ['apikey: ' . $api_key, 'Authorization: Bearer ' . $api_key, 'Content-Type: application/json']);
        curl_setopt($ch_mov, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch_mov, CURLOPT_SSL_VERIFYPEER, false);
        curl_exec($ch_mov);
        curl_close($ch_mov);
        
        $renglon++;
    }
    
    $ch_tick = curl_init($supabase_url . "/rest/v1/tickets");
    curl_setopt($ch_tick, CURLOPT_POST, true);
    curl_setopt($ch_tick, CURLOPT_POSTFIELDS, json_encode([
        'id_ticket' => $id_ticket,
        'id_sucursal' => $id_sucursal,
        'fecha' => date('c'),
        'moneda' => 'MXN',
        'metodo_pago' => 'Efectivo',
        'subtotal' => $subtotal_global,
        'impuesto' => 0,
        'total' => $subtotal_global,
        'estado' => 'COMPLETADO',
        'origen_demo' => true,
        'inventario_descontado' => true
    ]));
    curl_setopt($ch_tick, CURLOPT_HTTPHEADER, ['apikey: ' . $api_key, 'Authorization: Bearer ' . $api_key, 'Content-Type: application/json']);
    curl_setopt($ch_tick, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch_tick, CURLOPT_SSL_VERIFYPEER, false);
    curl_exec($ch_tick);
    curl_close($ch_tick);
    
    ob_end_clean();
    echo json_encode(['error' => false, 'mensaje' => 'Cobro exitoso', 'folio' => $folio]);
    
} catch (Throwable $e) {
    ob_end_clean();
    echo json_encode(['error' => true, 'mensaje' => $e->getMessage()]);
}
?>