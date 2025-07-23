<?php
if (!current_user_can('manage_options')) return;

$config_file = plugin_dir_path(__FILE__) . 'config.json';
$config = file_exists($config_file) ? json_decode(file_get_contents($config_file), true) : [];

// Guardar cambios
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fl_admin_nonce']) && wp_verify_nonce($_POST['fl_admin_nonce'], 'fl_guardar_config')) {
    $condiciones_raw = $_POST['condiciones'] ?? [];
    $condiciones = [];
    foreach ($condiciones_raw['nombre'] as $i => $nombre) {
        if (trim($nombre)) {
            $condiciones[trim($nombre)] = trim($condiciones_raw['texto'][$i] ?? '');
        }
    }

    $config['condiciones'] = $condiciones;
    $config['mensaje_final'] = sanitize_textarea_field($_POST['mensaje_final']);
    $config['descuento_maximo'] = intval($_POST['descuento_maximo']);
    $config['html_pdf'] = stripslashes($_POST['html_pdf']);

    file_put_contents($config_file, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo '<div class="updated"><p>Configuración guardada.</p></div>';
}
?>

<div class="wrap">
    <h1>Configuración del Cotizador</h1>
    <form method="post">
        <?php wp_nonce_field('fl_guardar_config', 'fl_admin_nonce'); ?>

<h2>Condiciones comerciales</h2>
<table class="form-table" id="condiciones-table">
    <thead>
        <tr><th>Nombre</th><th>Texto</th><th></th></tr>
    </thead>
    <tbody id="condiciones-body">
        <?php foreach ($config['condiciones'] ?? [] as $nombre => $texto): ?>
            <tr>
                <td><input type="text" name="condiciones[nombre][]" value="<?= esc_attr($nombre) ?>" class="regular-text"></td>
                <td><input type="text" name="condiciones[texto][]" value="<?= esc_attr($texto) ?>" class="regular-text"></td>
                <td><button type="button" class="button borrar-condicion">✖</button></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<p><button type="button" id="agregar-condicion" class="button">+ Agregar condición</button></p>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const body = document.getElementById('condiciones-body');
    const btnAgregar = document.getElementById('agregar-condicion');

    btnAgregar.addEventListener('click', () => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td><input type="text" name="condiciones[nombre][]" class="regular-text"></td>
            <td><input type="text" name="condiciones[texto][]" class="regular-text"></td>
            <td><button type="button" class="button borrar-condicion">✖</button></td>
        `;
        body.appendChild(row);
    });

    body.addEventListener('click', function (e) {
        if (e.target.classList.contains('borrar-condicion')) {
            e.target.closest('tr').remove();
        }
    });
});
</script>


        <h2>Descuento máximo permitido (%)</h2>
        <input type="number" name="descuento_maximo" value="<?= esc_attr($config['descuento_maximo'] ?? 25) ?>" class="small-text">

        <h2>Mensaje final para el PDF</h2>
        <textarea name="mensaje_final" rows="4" class="large-text"><?= esc_textarea($config['mensaje_final'] ?? '') ?></textarea>

        <h2>Plantilla HTML del PDF (estructura visual)</h2>
        <textarea name="html_pdf" rows="10" style="width:100%;"><?= esc_textarea($config['html_pdf'] ?? '') ?></textarea>

        <p><button type="submit" class="button button-primary">Guardar configuración</button></p>
    </form>
</div>
