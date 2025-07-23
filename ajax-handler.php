<?php
// ajax-handler.php
require_once(dirname(__FILE__, 4) . '/wp-load.php');

// Validación mínima (puedes personalizar si querés más seguridad)
if (!defined('ABSPATH')) exit;

$accion = $_GET['fl_ajax'] ?? null;

// ============================
// 1. BUSCAR PRODUCTOS AJAX
// ============================
if ($accion === 'buscar') {
    $term = sanitize_text_field($_GET['term'] ?? '');
    if (!$term) {
        wp_send_json([]);
    }

    $args = [
        'post_type'      => 'product',
        'post_status'    => 'publish',
        's'              => $term,
        'posts_per_page' => 6,
    ];

    $query = new WP_Query($args);
    $results = [];

    if ($query->have_posts()) {
        foreach ($query->posts as $post) {
            $product = wc_get_product($post->ID);
            // se permite mostrar productos sin stock para evitar que queden fuera de las búsquedas
            if (!$product) continue;

            $price = $product->get_price();
            if ($price === '' || $price === null || !is_numeric($price)) {
                $price = '';
                if ($product->is_type('variable')) {
                    foreach ($product->get_children() as $child_id) {
                        $child = wc_get_product($child_id);
                        $child_price = $child ? $child->get_price() : '';
                        if ($child_price !== '' && $child_price !== null && is_numeric($child_price)) {
                            $price = $child_price;
                            break;
                        }
                    }
                }
            }

            $moq = get_post_meta($post->ID, 'min_quantity', true);
            $moq = intval($moq) ?: 1;

            // ✅ Obtener hasta 3 imágenes (destacada + 2 galería)
            $imagenes = [];

            $thumb = wp_get_attachment_url($product->get_image_id());
            if ($thumb) $imagenes[] = $thumb;

            $galeria = $product->get_gallery_image_ids();
            foreach ($galeria as $img_id) {
                if (count($imagenes) >= 3) break;
                $url = wp_get_attachment_url($img_id);
                if ($url) $imagenes[] = $url;
            }

$results[] = [
    'id'          => $product->get_id(),
    'name'        => $product->get_name(),
    'sku'         => $product->get_sku(),
    'price'       => ($price !== '' ? floatval($price) : 0),
    'moq'         => $moq,
    'image'       => $imagenes[0] ?? '',
    'imagenes'    => $imagenes,
    'type'        => $product->get_type(),
    'description' => wp_strip_all_tags($product->get_description()) // 👈 agregado
];

        }
    }

    // 👉 Agrupar por SKU/ID, priorizando precios mayores a cero
    $agrupados = [];
    foreach ($results as $r) {
        $clave = $r['sku'] ?: $r['id'];
        if (!isset($agrupados[$clave]) || ($agrupados[$clave]['price'] <= 0 && $r['price'] > 0)) {
            $agrupados[$clave] = $r;
        }
    }

    $ordenados = array_values($agrupados);
    usort($ordenados, function ($a, $b) {
        if ($a['price'] > 0 && $b['price'] <= 0) return -1;
        if ($a['price'] <= 0 && $b['price'] > 0) return 1;
        return 0;
    });

    wp_send_json($ordenados);
}



// ============================
// 2. CAMBIAR ESTADO AJAX
// ============================
if ($accion === 'cambiar_estado') {
    $id = intval($_GET['id'] ?? 0);
    $nuevo_estado = sanitize_text_field($_GET['estado'] ?? '');

    if (!$id || !$nuevo_estado) {
        wp_send_json_error('Datos inválidos');
    }

    $archivo = plugin_dir_path(__FILE__) . 'data/cotizaciones.json';
    $cotizaciones = file_exists($archivo) ? json_decode(file_get_contents($archivo), true) : [];

    $encontrado = false;

    foreach ($cotizaciones as &$c) {
        if ($c['id'] == $id) {
            $c['estado'] = $nuevo_estado;
            $encontrado = true;
            break;
        }
    }

    if ($encontrado) {
        $fp = fopen($archivo, 'c+');
        if (flock($fp, LOCK_EX)) {
            fseek($fp, 0);
            ftruncate($fp, 0);
            fwrite($fp, json_encode($cotizaciones, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            fflush($fp);
            flock($fp, LOCK_UN);
        }
        fclose($fp);

        wp_send_json(['success' => true]);
    } else {
        wp_send_json_error('Cotización no encontrada');
    }
}

// ============================
// 3. ELIMINAR COTIZACIÓN
// ============================
if ($accion === 'eliminar') {
    $id = intval($_GET['id'] ?? 0);
    if (!$id) {
        wp_send_json_error('ID inválido');
    }

    $archivo = plugin_dir_path(__FILE__) . 'data/cotizaciones.json';
    $cotizaciones = file_exists($archivo) ? json_decode(file_get_contents($archivo), true) : [];

    $nuevas = array_filter($cotizaciones, function ($c) use ($id) {
        return $c['id'] != $id;
    });

    // Reindexar IDs si es necesario o mantener los existentes
    $fp = fopen($archivo, 'c+');
    if (flock($fp, LOCK_EX)) {
        fseek($fp, 0);
        ftruncate($fp, 0);
        fwrite($fp, json_encode(array_values($nuevas), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        fflush($fp);
        flock($fp, LOCK_UN);
    }
    fclose($fp);

    wp_send_json(['success' => true]);
}

// ============================
// 4. SUGERIR EMPRESAS
// ============================
if ($accion === 'sugerir_empresas') {
    $term = strtolower(trim($_GET['term'] ?? ''));
    $empresas_file = plugin_dir_path(__FILE__) . 'data/empresas.json';
    $empresas = file_exists($empresas_file) ? json_decode(file_get_contents($empresas_file), true) : [];

    $resultados = [];
    foreach ($empresas as $empresa) {
        if (stripos($empresa, $term) !== false) {
            $resultados[] = $empresa;
        }
    }

    wp_send_json($resultados);
}
