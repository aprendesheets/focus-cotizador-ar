<?php
require_once('lib/dompdf/autoload.inc.php');

use Dompdf\Dompdf;
use Dompdf\Options;

$options = new Options();
$options->set('isRemoteEnabled', true);
$dompdf = new Dompdf($options);

$cotizaciones_file = __DIR__ . '/data/cotizaciones.json';
$config_file = __DIR__ . '/admin/config.json';

$cotizaciones = file_exists($cotizaciones_file) ? json_decode(file_get_contents($cotizaciones_file), true) : [];
$config = file_exists($config_file) ? json_decode(file_get_contents($config_file), true) : [];

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$actual = null;

foreach ($cotizaciones as $c) {
    if ($c['id'] === $id) {
        $actual = $c;
        break;
    }
}

if (!$actual) {
    echo "Cotización no encontrada";
    exit;
}

$plantilla = $config['html_pdf'] ?? '<p>Plantilla no definida</p>';

$reemplazos = [
    '{{id}}' => $actual['id'],
    '{{fecha}}' => date('d/m/Y', strtotime($actual['fecha'])),
    '{{cliente}}' => $actual['cliente'],
    '{{empresa}}' => $actual['empresa'],
    '{{email}}' => $actual['email'],
    '{{telefono}}' => $actual['telefono'],
    '{{condiciones}}' => $actual['condiciones'],
    '{{vendedor}}' => $actual['vendedor'],
    '{{mensaje_final}}' => nl2br($config['mensaje_final'] ?? ''),
];

$html_productos = '';
foreach ($actual['productos'] as $p) {
    $precio_unitario = floatval($p['precio']);
    $descuento_global = intval($p['descuento']);
    $observaciones = nl2br(htmlspecialchars($p['observaciones']));
    $descripcion = $p['descripcion'] ?? '';

    $img = '';
    if (!empty($p['imagenes']) && is_array($p['imagenes'])) {
        $img .= "<table cellpadding='0' cellspacing='0' style='margin: 0 auto; border-collapse: separate; border-spacing: 10px;'><tr>";
        foreach (array_slice($p['imagenes'], 0, 3) as $img_url) {
            $img .= "<td style='background:#f9f9f9; padding:8px; border-radius:16px; box-shadow:0 0 3px rgba(0,0,0,0.1); text-align:center;'>
                        <img src='{$img_url}' width='90' height='90' style='border-radius:10px; object-fit:contain; display:block;'>
                    </td>";
        }
        $img .= "</tr></table>";
    } elseif (!empty($p['image'])) {
        $img = "<img src='{$p['image']}' width='120' style='border-radius:10px;' />";
    }

$visuales_html = '';
foreach (['v1', 'v2', 'v3'] as $v) {
    $cant = intval($p['visuales'][$v]['cantidad'] ?? 0);
    $impresion = floatval($p['visuales'][$v]['impresion'] ?? 0);
    $dcto_visual = floatval($p['visuales'][$v]['descuento'] ?? 0);

    if ($cant === 0) continue;

    $total_descuento = $descuento_global + $dcto_visual;
    $precio_unitario_desc = $precio_unitario * (1 - $total_descuento / 100);
    $importe_venta = $precio_unitario_desc * $cant + $impresion;

    // Calcular precio final unitario (importe venta / cantidad)
    $precio_final_unitario = $importe_venta / $cant;

    $visuales_html .= "<tr style='text-align:center; vertical-align:middle;'>
        <td>{$cant}</td>
        <td>$" . number_format($precio_unitario_desc, 0, ',', '.') . " <small>({$total_descuento}% dcto)</small></td>
        <td>$" . number_format($impresion, 0, ',', '.') . "</td>
        <td>$" . number_format($precio_final_unitario, 0, ',', '.') . "</td>
        <td><strong>$" . number_format($importe_venta, 0, ',', '.') . "</strong></td>
    </tr>";
}

    $html_productos .= "
    <div class='producto' style='page-break-inside: avoid; margin-bottom: 3rem; border:1px solid #ccc; border-radius:16px; padding:1rem 1rem 0.1rem 1rem;'>
        <table width='100%' style='margin-bottom:1rem;'>
            <tr>
                <td style='width: 380px; vertical-align:top;'>{$img}</td>
                <td style='vertical-align:top; padding-left: 20px; text-align:justify;'>
                    <strong style='font-size: 18px; color:#000'>{$p['nombre']}</strong><br>
                    <small style='color:#333;'>Precio original: \${$precio_unitario}</small><br>
                    <small style='color:#333;'>Descuento global: {$descuento_global}%</small><br>
                    <small style='color:#333;'>Observaciones:<br>{$observaciones}</small>
                </td>
            </tr>
        </table>

        <div style='margin:1rem 0 0.5rem; padding:10px 12px; background:#fdfdfd; border-radius:8px; border:1px solid #e0e0e0; font-size:13px; text-align:justify; line-height:1.4; color:#333;'>
            {$descripcion}
        </div>

        <table width='100%' class='propuestas' style='border-radius: 8px; overflow:hidden; text-align:center; margin-top:1rem;'>
<thead>
    <tr style='background:#f4f4f4;'>
        <th>Cantidad</th>
        <th>Precio unitario s/logo</th>
        <th>Costo Impresión</th>
        <th>Precio unitario c/logo</th> <!-- Nueva columna -->
        <th>Importe total</th>
    </tr>
</thead>
            <tbody>
                {$visuales_html}
            </tbody>
        </table>
    </div>";
}

$reemplazos['{{productos}}'] = $html_productos;

$html_final = str_replace(array_keys($reemplazos), array_values($reemplazos), $plantilla);

$dompdf->loadHtml($html_final);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream("cotizacion_{$actual['id']}.pdf", ["Attachment" => false]);
exit;
