<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');
require 'config.php';

// Usamos $id_sucursal tal como lo definiste en config.php
// Modificar la línea de $endpoint:
$endpoint = $supabase_url . "/rest/v1/inventario?id_sucursal=eq.{$id_sucursal}&select=id_producto,stock,stock_minimo,productos(codigo_barras,nombre,precio_sugerido,categorias(nombre))";
$ch = curl_init($endpoint);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'apikey: ' . $api_key, 
    'Authorization: Bearer ' . $api_key
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$respuesta = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code != 200) {
    echo json_encode(['error' => true, 'mensaje' => 'Error de conexión a la Matriz. Código: ' . $http_code]);
    exit;
}

$datos_supabase = json_decode($respuesta, true);
$datos_formateados = [];
if (is_array($datos_supabase) && !isset($datos_supabase['message'])) {
    foreach ($datos_supabase as $row) {
        $datos_formateados[] = [
            'id_producto' => (int)$row['id_producto'], // Extraemos el ID numérico real
            'sku_id' => $row['productos']['codigo_barras'],
            'cantidad_actual' => $row['stock'],
            'productos' => [
                'nombre_producto' => $row['productos']['nombre'],
                'precio_lista_sugerido' => $row['productos']['precio_sugerido'],
                'stock_minimo_alerta' => $row['stock_minimo'],
            ]
        ];
    }
}

echo json_encode($datos_formateados);
?>