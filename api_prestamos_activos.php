<?php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');

try {
    require 'config.php'; 

    function fetchFromSB($url) {
        global $api_key;
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['apikey: ' . $api_key, 'Authorization: Bearer ' . $api_key]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $res = json_decode(curl_exec($ch), true);
        curl_close($ch);
        return (is_array($res) && !isset($res['message'])) ? $res : [];
    }

    // Traemos deudas y favores. Si 'productos(*)' genera error relacional en Supabase, el servidor usará el código fallback a la derecha.
    $endpoint_join = "/rest/v1/prestamos?estado=neq.DEVUELTO&select=*,productos(*)";
    
    $mis_deudas = fetchFromSB($supabase_url . $endpoint_join . "&id_sucursal_solicitante=eq." . $id_sucursal);
    $me_deben = fetchFromSB($supabase_url . $endpoint_join . "&id_sucursal_prestamista=eq." . $id_sucursal);

    // Fallback de seguridad (sin JOIN a productos) en caso de que las llaves foráneas no estén configuradas correctamente en Supabase.
    if (empty($mis_deudas) && empty($me_deben)) {
        $endpoint_safe = "/rest/v1/prestamos?estado=neq.DEVUELTO&select=*";
        $mis_deudas = fetchFromSB($supabase_url . $endpoint_safe . "&id_sucursal_solicitante=eq." . $id_sucursal);
        $me_deben = fetchFromSB($supabase_url . $endpoint_safe . "&id_sucursal_prestamista=eq." . $id_sucursal);
    }

    $formatear = function($item) {
        return [
            'id_prestamo' => $item['id_prestamo'],
            'folio' => 'PR-' . str_pad($item['id_prestamo'], 4, "0", STR_PAD_LEFT),
            'sku_id' => isset($item['productos']) ? $item['productos']['codigo_barras'] : 'ID: ' . $item['id_producto'],
            'cantidad' => $item['cantidad_prestada'],
            'estado' => $item['estado'],
            'fecha_solicitud' => $item['fecha_prestamo'],
            'sucursal_prestadora' => $item['id_sucursal_prestamista'],
            'sucursal_solicitante' => $item['id_sucursal_solicitante'],
            'productos' => isset($item['productos']) ? [
                'nombre_pieza' => $item['productos']['nombre'],
                'nombre_producto' => $item['productos']['nombre']
            ] : null
        ];
    };

    $mis_deudas_formato = array_map($formatear, $mis_deudas);
    $me_deben_formato = array_map($formatear, $me_deben);

    ob_end_clean();
    echo json_encode([
        'error' => false,
        'mis_deudas' => $mis_deudas_formato,
        'me_deben' => $me_deben_formato
    ]);
} catch (Throwable $e) {
    ob_end_clean();
    echo json_encode(['error' => true, 'mis_deudas' => [], 'me_deben' => [], 'mensaje' => $e->getMessage()]);
}
?>