<?php
/**
 * EASY CAR LUXURY - API de Publicaciones
 * MODIFICADO: Incluye nuevos campos de identificación, serie y datos legales.
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

// Autenticación
$headers = getallheaders();
$auth_header = $headers['Authorization'] ?? '';
$token = str_replace('Bearer ', '', $auth_header);

if (empty($token)) {
    jsonError('Token no proporcionado', 401);
}

try {
    $decoded = JWT::decode($token, new Key(JWT_SECRET, 'HS256'));
    $user_id = $decoded->user_id;
} catch (Exception $e) {
    jsonError('Token inválido', 401);
}

$method = $_SERVER['REQUEST_METHOD'];
$db = Database::getInstance();

switch ($method) {
    case 'GET':
        $id = $_GET['id'] ?? null;
        if ($id) {
            // Obtener una publicación
            $publicacion = $db->getOne("
                SELECT p.*, c.nombre as categoria_nombre,
                       (SELECT COUNT(*) FROM comentarios WHERE publicacion_id = p.id AND visible = 1) as comentarios_count,
                       (SELECT image_path FROM imagenes_publicaciones WHERE publicacion_id = p.id AND is_primary = 1 LIMIT 1) as imagen_principal
                FROM publicaciones p
                JOIN categorias c ON p.categoria_id = c.id
                WHERE p.id = ? AND p.usuario_id = ?",
                [$id, $user_id]
            );
            
            if ($publicacion) {
                $imagenes = $db->getAll("SELECT * FROM imagenes_publicaciones WHERE publicacion_id = ? ORDER BY sort_order", [$id]);
                $documentos = $db->getAll("SELECT * FROM documentacion_articulos WHERE publicacion_id = ?", [$id]);
                
                jsonResponse([
                    'publicacion' => $publicacion,
                    'imagenes' => $imagenes,
                    'documentos' => $documentos
                ]);
            } else {
                jsonError('Publicación no encontrada', 404);
            }
        } else {
            // Listar publicaciones del usuario
            $page = $_GET['page'] ?? 1;
            $per_page = 10;
            $offset = ($page - 1) * $per_page;
            
            $publicaciones = $db->getAll("
                SELECT p.*, 
                       (SELECT image_path FROM imagenes_publicaciones WHERE publicacion_id = p.id AND is_primary = 1 LIMIT 1) as imagen_principal
                FROM publicaciones p
                WHERE p.usuario_id = ?
                ORDER BY p.created_at DESC
                LIMIT ? OFFSET ?",
                [$user_id, $per_page, $offset]
            );
            
            jsonResponse($publicaciones);
        }
        break;
        
    case 'POST':
        // Crear publicación
        $input = json_decode(file_get_contents('php://input'), true);
        
        $slug = slugify($input['titulo']);
        $slug_original = $slug;
        $counter = 1;
        while ($db->getOne("SELECT id FROM publicaciones WHERE slug = ?", [$slug])) {
            $slug = $slug_original . '-' . $counter;
            $counter++;
        }
        
        $id = $db->insert('publicaciones', [
            'usuario_id' => $user_id,
            'categoria_id' => $input['categoria_id'],
            'titulo' => $input['titulo'],
            'slug' => $slug,
            'descripcion' => $input['descripcion'],
            'precio' => $input['precio'],
            'negociable' => $input['negociable'] ?? 1,
            'estado_articulo' => $input['estado_articulo'] ?? 'usado',
            'year_fabricacion' => $input['year_fabricacion'] ?? null,
            'kilometraje' => $input['kilometraje'] ?? null,
            'color' => $input['color'] ?? '',
            'ubicacion' => $input['ubicacion'] ?? '',
            'solo_premium_elite' => $input['solo_premium_elite'] ?? 0,
            'status' => 'active',
            // Nuevos campos
            'brand' => $input['brand'] ?? '',
            'linea_modelo_comercial' => $input['linea_modelo_comercial'] ?? '',
            'clase_vehiculo' => $input['clase_vehiculo'] ?? '',
            'tipo_carroceria' => $input['tipo_carroceria'] ?? '',
            'cilindrada' => $input['cilindrada'] ?? 0,
            'potencia_hp' => $input['potencia_hp'] ?? 0,
            'fuel_type' => $input['fuel_type'] ?? '',
            'capacidad' => $input['capacidad'] ?? '',
            'blindaje' => $input['blindaje'] ?? 'No',
            'numero_motor' => $input['numero_motor'] ?? '',
            'numero_chasis' => $input['numero_chasis'] ?? '',
            'numero_vin' => $input['numero_vin'] ?? '',
            'servicio' => $input['servicio'] ?? '',
            'origen' => $input['origen'] ?? '',
            'propietario_nombre' => $input['propietario_nombre'] ?? '',
            'propietario_tipo_documento' => $input['propietario_tipo_documento'] ?? '',
            'propietario_numero_documento' => $input['propietario_numero_documento'] ?? '',
            'empresa_vinculadora_nombre' => $input['empresa_vinculadora_nombre'] ?? '',
            'empresa_vinculadora_nit' => $input['empresa_vinculadora_nit'] ?? ''
        ]);
        
        if ($id) {
            logAudit($user_id, 'CREATE', 'publicaciones', $id, null, $input);
            jsonResponse(['id' => $id, 'slug' => $slug], 'Publicación creada', 201);
        } else {
            jsonError('Error al crear publicación', 500);
        }
        break;
        
    case 'PUT':
        // Actualizar publicación
        $input = json_decode(file_get_contents('php://input'), true);
        $id = $input['id'] ?? null;
        
        if (!$id) {
            jsonError('ID requerido', 400);
        }
        
        // Verificar propiedad
        $pub = $db->getOne("SELECT * FROM publicaciones WHERE id = ? AND usuario_id = ?", [$id, $user_id]);
        if (!$pub) {
            jsonError('No autorizado', 403);
        }
        
        $update_data = [];
        $allowed_fields = [
            'titulo', 'descripcion', 'precio', 'negociable', 'estado_articulo',
            'year_fabricacion', 'kilometraje', 'color', 'ubicacion', 'solo_premium_elite', 'status',
            // Nuevos campos
            'brand', 'linea_modelo_comercial', 'clase_vehiculo', 'tipo_carroceria',
            'cilindrada', 'potencia_hp', 'fuel_type', 'capacidad', 'blindaje',
            'numero_motor', 'numero_chasis', 'numero_vin',
            'servicio', 'origen', 'propietario_nombre', 'propietario_tipo_documento',
            'propietario_numero_documento', 'empresa_vinculadora_nombre', 'empresa_vinculadora_nit'
        ];
        
        foreach ($allowed_fields as $field) {
            if (isset($input[$field])) {
                $update_data[$field] = $input[$field];
            }
        }
        
        if (!empty($update_data)) {
            $db->update('publicaciones', $update_data, 'id = ?', [$id]);
            logAudit($user_id, 'UPDATE', 'publicaciones', $id, $pub, $update_data);
        }
        
        jsonResponse(null, 'Publicación actualizada');
        break;
        
    case 'DELETE':
        // Eliminar publicación
        $input = json_decode(file_get_contents('php://input'), true);
        $id = $input['id'] ?? null;
        
        if (!$id) {
            jsonError('ID requerido', 400);
        }
        
        $pub = $db->getOne("SELECT * FROM publicaciones WHERE id = ? AND usuario_id = ?", [$id, $user_id]);
        if (!$pub) {
            jsonError('No autorizado', 403);
        }
        
        // Eliminar imágenes físicas
        $imagenes = $db->getAll("SELECT * FROM imagenes_publicaciones WHERE publicacion_id = ?", [$id]);
        foreach ($imagenes as $img) {
            $file_path = $_SERVER['DOCUMENT_ROOT'] . $img['image_path'];
            if (file_exists($file_path)) {
                unlink($file_path);
            }
        }
        
        // Eliminar documentos físicos
        $documentos = $db->getAll("SELECT * FROM documentacion_articulos WHERE publicacion_id = ?", [$id]);
        foreach ($documentos as $doc) {
            $file_path = $_SERVER['DOCUMENT_ROOT'] . $doc['url_documento'];
            if (file_exists($file_path)) {
                unlink($file_path);
            }
        }
        
        // Eliminar de BD
        $db->delete('publicaciones', 'id = ?', [$id]);
        logAudit($user_id, 'DELETE', 'publicaciones', $id, $pub, null);
        
        jsonResponse(null, 'Publicación eliminada');
        break;
        
    default:
        jsonError('Método no permitido', 405);
        break;
}