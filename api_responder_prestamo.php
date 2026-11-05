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
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data || empty($data['id_prestamo']) || empty($data['accion'])) {
        throw new Exception("Datos incompletos.");
    }
    
    $id_prestamo = (int)$data['id_prestamo'];
    $accion = $data['accion'];
    
    $ch_upd = curl_init($supabase_url . "/rest/v1/prestamos?id_prestamo=eq.{$id_prestamo}");
    curl_setopt($ch_upd, CURLOPT_CUSTOMREQUEST, 'PATCH');
    curl_setopt($ch_upd, CURLOPT_POSTFIELDS, json_encode(['estado' => $accion]));
    curl_setopt($ch_upd, CURLOPT_HTTPHEADER, ['apikey: ' . $api_key, 'Authorization: Bearer ' . $api_key, 'Content-Type: application/json']);
    curl_setopt($ch_upd, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch_upd, CURLOPT_SSL_VERIFYPEER, false);
    $res = curl_exec($ch_upd);
    $http_code = curl_getinfo($ch_upd, CURLINFO_HTTP_CODE);
    curl_close($ch_upd);
    
    if ($http_code >= 400) throw new Exception("Error al procesar la peticion.");
    
    ob_end_clean();
    echo json_encode(['error' => false, 'mensaje' => 'Exito']);
    
} catch (Throwable $e) {
    ob_end_clean();
    echo json_encode(['error' => true, 'mensaje' => $e->getMessage()]);
}
?>