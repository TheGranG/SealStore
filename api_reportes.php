<?php
date_default_timezone_set('America/Monterrey');
error_reporting(E_ALL);
ini_set('display_errors', 0);

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');

// Llamamos a las credenciales
require 'config.php';

$id_sucursal = 2; // Tu sucursal

function peticion_sb($endpoint) {
    global $supabase_url, $api_key;
    $ch = curl_init($supabase_url . $endpoint);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'apikey: ' . $api_key,
        'Authorization: Bearer ' . $api_key
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $res = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    // Si la columna no existe o hay error, devolvemos un arreglo vacío en lugar de romper el código
    if ($http_code >= 400) return [];
    
    $data = json_decode($res, true);
    return (is_array($data) && !isset($data['message'])) ? $data : [];
}

try {
    $reportes_unificados = [];

    // 1. Extraer Ventas desde Kardex (SIN filtro de id_sucursal porque la tabla no lo tiene)
    $movimientos = peticion_sb("/rest/v1/movimientos_kardex?order=fecha.desc&limit=100");
    foreach ($movimientos as $m) {
        $tipo = strtoupper($m['tipo'] ?? '');
        // Filtramos para asegurar que capture las ventas registradas
        if (strpos($tipo, 'VENTA') !== false) {
            $reportes_unificados[] = [
                'id_movimiento'   => $m['id_movimiento'] ?? '',
                'folio'           => $m['folio'] ?? 'MOV-VTA',
                'tipo'            => 'VENTA',
                'producto_nombre' => $m['producto_nombre'] ?? 'Producto Mostrador',
                'cantidad'        => (int)($m['cantidad'] ?? 1),
                'origen_destino'  => $m['origen_destino'] ?? 'Cliente',
                'monto_total'     => (float)($m['monto_total'] ?? 0),
                'fecha'           => $m['fecha'] ?? date('Y-m-d H:i:s')
            ];
        }
    }

    // 2. Extraer Entrada de Stock por pedido a la Matriz (ID 1)
    $pedidos = peticion_sb("/rest/v1/pedidos_abastecimiento?id_sucursal=eq.{$id_sucursal}&order=fecha_pedido.desc&limit=50");
    foreach ($pedidos as $p) {
        $estado = strtoupper($p['estado'] ?? '');
        if (in_array($estado, ['ENTREGADO', 'COMPLETADO', 'RECIBIDO'])) {
            $reportes_unificados[] = [
                'id_movimiento'   => $p['id_pedido'] ?? '',
                'folio'           => 'PED-' . ($p['id_pedido'] ?? 'MAT'),
                'tipo'            => 'ENTRADA MATRIZ',
                'producto_nombre' => 'Abastecimiento Consolidado',
                'cantidad'        => 1, 
                'origen_destino'  => 'Matriz Central (ID: 1)',
                'monto_total'     => 0,
                'fecha'           => $p['fecha_recibida'] ?? $p['fecha_pedido'] ?? date('Y-m-d H:i:s')
            ];
        }
    }

    // 3. Extraer Entrada de Stock por Devolución de Préstamo (Donde tú eres el prestamista)
    $prestamos = peticion_sb("/rest/v1/prestamos?id_sucursal_prestamista=eq.{$id_sucursal}&order=fecha_prestamo.desc&limit=50");
    foreach ($prestamos as $pr) {
        $estado = strtoupper($pr['estado'] ?? '');
        if (in_array($estado, ['RECIBIDO', 'DEVUELTO', 'COMPLETADO_RECIBIDO'])) {
            $reportes_unificados[] = [
                'id_movimiento'   => $pr['id_prestamo'] ?? '',
                'folio'           => 'DEV-' . ($pr['id_prestamo'] ?? 'PR'),
                'tipo'            => 'REPOSICION PRESTAMO',
                'producto_nombre' => 'Producto (ID: ' . ($pr['id_producto'] ?? 'N/A') . ')',
                'cantidad'        => (int)($pr['cantidad_devuelta'] ?? $pr['cantidad_prestada'] ?? 0),
                'origen_destino'  => 'Devolución de Sucursal ' . ($pr['id_sucursal_solicitante'] ?? 'N/A'),
                'monto_total'     => 0,
                'fecha'           => $pr['fecha_liquidacion'] ?? $pr['fecha_prestamo'] ?? date('Y-m-d H:i:s')
            ];
        }
    }

    // Ordenar toda la bitácora cronológicamente
    usort($reportes_unificados, function($a, $b) {
        return strtotime($b['fecha']) - strtotime($a['fecha']);
    });

    echo json_encode($reportes_unificados);

} catch (Exception $e) {
    echo json_encode([]);
}
?>