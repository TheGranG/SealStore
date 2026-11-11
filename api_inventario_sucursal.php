<?php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');

try {
    // Si tienes config.php, puedes hacer "require 'config.php';" y borrar estas dos líneas
    $supabase_url = 'https://exifxvgbwuqdlnjtmixd.supabase.co';
    $api_key = 'sb_publishable_3mTZ7NYM3JLzM4VVVTnRAw_GbLrVIhb';
    
    // Obtenemos el ID de la sucursal de la cual queremos ver el catálogo
    $id_target = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    if ($id_target === 0) {
        throw new Exception("ID de sucursal no proporcionado.");
    }

    // Consultamos la tabla inventario_nodos y cruzamos con productos
    // cantidad_actual=gt.0 asegura que solo te regrese lo que SÍ hay en piso
    $endpoint = $supabase_url . "/rest/v1/inventario_nodos?id_sucursal=eq.{$id_target}&cantidad_actual=gt.0&select=cantidad_actual,sku_id,productos(nombre,nombre_producto,nombre_pieza)";
    
    $ch = curl_init($endpoint);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'apikey: ' . $api_key, 
        'Authorization: Bearer ' . $api_key
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $respuesta = curl_exec($ch);
    curl_close($ch);

    $datos = json_decode($respuesta, true);
    $formateado = [];
    
    if (is_array($datos) && !isset($datos['message'])) {
        foreach ($datos as $row) {
            // Buscamos el nombre correcto previniendo cambios en la BD
            $nombre = $row['productos']['nombre_producto'] ?? $row['productos']['nombre_pieza'] ?? $row['productos']['nombre'] ?? 'Producto General';
            
            $formateado[] = [
                'sku' => $row['sku_id'],
                'nombre' => $nombre,
                'stock' => $row['cantidad_actual']
            ];
        }
    }

    ob_end_clean();
    echo json_encode($formateado);

} catch (Throwable $e) {
    ob_end_clean();
    echo json_encode(['error' => true, 'mensaje' => 'Error BD: ' . $e->getMessage()]);
}
?>