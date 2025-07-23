<?php
session_start();
require_once('functions.php');

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");


if (!isset($_SESSION['fl_user'])) {
    header('Location: ?view=login');
    exit;
}

$user = $_SESSION['fl_user'];

include 'templates/header.php';
?>

<div class="fl-dashboard">
    <h2>Hola, <?= $user['name'] ?></h2>
    <p style="margin-bottom: 2rem;"><a href="?view=logout" class="fl-logout">Cerrar sesión</a></p>

    <div class="fl-listado">
<form method="get" id="form-filtros" class="fl-filters">
    <input type="hidden" name="view" value="dashboard">

    <input type="text" name="cliente" placeholder="Buscar cliente..." id="filter-cliente" value="<?= esc_attr($_GET['cliente'] ?? '') ?>">
    <input type="text" name="empresa" placeholder="Buscar empresa..." id="filter-empresa" value="<?= esc_attr($_GET['empresa'] ?? '') ?>">


    <select name="vendedor" id="filter-vendedor">
        <option value="">Filtrar por vendedor</option>
        <?php
        $usuarios = fl_get_users();
        foreach ($usuarios as $u) {
            $selected = ($u['name'] === ($_GET['vendedor'] ?? '')) ? 'selected' : '';
            echo '<option value="' . esc_attr($u['name']) . '" ' . $selected . '>' . esc_html($u['name']) . '</option>';
        }
        ?>
    </select>

    <input type="date" name="desde" id="filter-desde" value="<?= esc_attr($_GET['desde'] ?? '') ?>">
    <input type="date" name="hasta" id="filter-hasta" value="<?= esc_attr($_GET['hasta'] ?? '') ?>">
</form>


        <p style="text-align: right; margin-bottom: 1rem;">
            <a href="?view=nueva" class="fl-btn-nueva">+ Nueva Cotización</a>
        </p>

<div style="display:flex; align-items:center; justify-content:space-between;">
    <h3 style="margin:0;">Listado de cotizaciones</h3>
    <button onclick="location.href = '?view=dashboard&ts=' + new Date().getTime();" style="background:#02196f; color:#fff; border:none; padding:6px 14px; border-radius:6px; cursor:pointer; font-size:14px; margin-bottom:5px">🔄 Actualizar</button>
</div>
        <div class="fl-tabla-wrapper">
            <table class="fl-table" id="tabla-cotizaciones">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Vendedor</th>
                        <th>Cliente</th>
                        <th>Empresa</th>
                        <th>Fecha</th>
                        <th style="text-align:center;">Acciones</th>
                    </tr>
                </thead>
                <tbody id="cotizaciones-listado">
<?php
$cotizaciones_file = plugin_dir_path(__FILE__) . 'data/cotizaciones.json';

$cotizaciones = file_exists($cotizaciones_file) ? json_decode(file_get_contents($cotizaciones_file), true) : [];

// 🔃 Ordenar por fecha descendente
usort($cotizaciones, function ($a, $b) {
    return strtotime($b['fecha']) - strtotime($a['fecha']);
});

// 🧠 Filtros desde GET
$filtro_cliente = strtolower($_GET['cliente'] ?? '');
$filtro_empresa = strtolower($_GET['empresa'] ?? '');
$filtro_vendedor = $_GET['vendedor'] ?? '';
$filtro_desde = $_GET['desde'] ?? '';
$filtro_hasta = $_GET['hasta'] ?? '';

// 🧪 Aplicar filtros
$cotizaciones = array_filter($cotizaciones, function ($c) use ($filtro_cliente, $filtro_empresa, $filtro_vendedor, $filtro_desde, $filtro_hasta) {
    $fecha = substr($c['fecha'], 0, 10); // YYYY-MM-DD

    if ($filtro_cliente && stripos($c['cliente'], $filtro_cliente) === false) return false;
if ($filtro_empresa && (!isset($c['empresa']) || stripos($c['empresa'], $filtro_empresa) === false)) return false;
    if ($filtro_vendedor && $c['vendedor'] !== $filtro_vendedor) return false;
    if ($filtro_desde && $fecha < $filtro_desde) return false;
    if ($filtro_hasta && $fecha > $filtro_hasta) return false;

    return true;
});

// 🔹 Paginación
$por_pagina = 24;
$total = count($cotizaciones);
$total_paginas = ceil($total / $por_pagina);
$pagina_actual = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$inicio = ($pagina_actual - 1) * $por_pagina;

$cotizaciones_pagina = array_slice(array_values($cotizaciones), $inicio, $por_pagina);


foreach ($cotizaciones_pagina as $c) {
    echo '<tr>';
    echo '<td>' . esc_html($c['id']) . '</td>';
    echo '<td>' . esc_html($c['vendedor']) . '</td>';
    echo '<td>' . esc_html($c['cliente']) . '</td>';
    echo '<td>' . esc_html($c['empresa']) . '</td>';
    $fecha = new DateTime($c['fecha']);
    $fecha->setTimezone(new DateTimeZone('America/Argentina/Buenos_Aires'));
    echo '<td>' . esc_html($fecha->format('d-m-Y H:i')) . '</td>';

    echo '<td style="text-align:center;">';
    echo '<form method="get" action="" style="display:inline;">
        <input type="hidden" name="view" value="editar">
        <input type="hidden" name="id" value="' . esc_attr($c['id']) . '">
        <button type="submit" title="Editar" style="background:#f4f4f4; border:1px solid #ccc; border-radius:6px; padding:6px 8px; cursor:pointer; font-size:16px; margin-right:5px;">✏️</button>
    </form>';

    echo '<form method="get" style="display:inline;" onsubmit="return confirm(\'¿Eliminar la cotización #' . esc_attr($c['id']) . '?\');">
        <input type="hidden" name="fl_ajax" value="eliminar">
        <input type="hidden" name="id" value="' . esc_attr($c['id']) . '">
        <button type="submit" title="Eliminar" style="background:#f4f4f4; border:1px solid #ccc; border-radius:6px; padding:6px 8px; cursor:pointer; font-size:16px; margin-right:5px;">🗑️</button>
    </form>';

    echo '<a href="https://focuslogo.com.ar/wp-content/plugins/focuslogo-cotizador/pdf.php?id=' . esc_attr($c['id']) . '" target="_blank" title="Ver PDF" style="text-decoration:none;">
        <button type="button" style="background:#f4f4f4; border:1px solid #ccc; border-radius:6px; padding:6px 8px; font-size:16px; cursor:pointer;">📄</button>
    </a>';

    echo '</td>';
    echo '</tr>';
}

?>
                </tbody>
            </table>
            
            <?php if ($total_paginas > 1): ?>
<div style="margin-top:1rem; text-align:center;">
    <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
<?php
$query_params = $_GET;
$query_params['pagina'] = $i;
$query_string = http_build_query($query_params);
?>
<a href="?<?= $query_string ?>" style="margin:0 4px; padding:8px 12px; border-radius:6px; border:1px solid #ccc; background:<?= $i == $pagina_actual ? '#02196f' : '#fff' ?>; color:<?= $i == $pagina_actual ? '#fff' : '#02196f' ?>; text-decoration:none;">
    <?= $i ?>
</a>

    <?php endfor; ?>
</div>
<?php endif; ?>

            
            
        </div>
    </div>
</div>


<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('form-filtros');
    const clienteInput = document.getElementById('filter-cliente');
    const empresaInput = document.getElementById('filter-empresa');
    const vendedorSelect = document.getElementById('filter-vendedor');
    const desdeInput = document.getElementById('filter-desde');
    const hastaInput = document.getElementById('filter-hasta');

    // Enviar al cambiar filtros
    clienteInput.addEventListener('input', function () {
        clearTimeout(this._t);
        this._t = setTimeout(() => form.submit(), 500); // espera 0.5s para evitar spam
    });

    vendedorSelect.addEventListener('change', () => form.submit());
    desdeInput.addEventListener('change', () => form.submit());
    hastaInput.addEventListener('change', () => form.submit());
    
        empresaInput.addEventListener('input', function () {
        clearTimeout(this._t);
        this._t = setTimeout(() => form.submit(), 500);
    });
    
});
</script>


<script>
document.addEventListener("DOMContentLoaded", function () {
    const form = document.getElementById('refreshForm');

    document.querySelectorAll('.btn-eliminar').forEach(btn => {
        btn.addEventListener('click', function () {
            const id = this.dataset.id;
            if (!confirm('¿Eliminar la cotización #' + id + '?')) return;

            fetch('?fl_ajax=eliminar&id=' + id)
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        form.submit();
                    } else {
                        alert('Error al eliminar');
                    }
                });
        });
    });

    document.querySelectorAll('.btn-guardar-estado').forEach(btn => {
        btn.addEventListener('click', function () {
            const id = this.dataset.id;
            const select = document.querySelector('.cambio-estado[data-id="' + id + '"]');
            const nuevoEstado = select.value;

            fetch('?fl_ajax=cambiar_estado&id=' + id + '&estado=' + encodeURIComponent(nuevoEstado))
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        btn.innerText = '✅';
                        setTimeout(() => { btn.innerText = '💾'; }, 1500);
                    } else {
                        alert('Error al guardar el estado');
                    }
                });
        });
    });
});
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.estado-guardar').forEach(btn => {
        btn.addEventListener('click', function () {
            const id = this.dataset.id;
            const select = document.querySelector(`.cambio-estado[data-id="${id}"]`);
            const nuevoEstado = select.value;

            this.innerText = '⏳';

            fetch(`?fl_ajax=cambiar_estado&id=${id}&estado=${nuevoEstado}`)
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        this.innerText = '✅';
                        setTimeout(() => {
                            this.innerText = '💾';
                        }, 2000);
                    } else {
                        this.innerText = '❌';
                    }
                })
                .catch(() => {
                    this.innerText = '❌';
                });
        });
    });
});
</script>


<form id="refreshForm" method="get" action="" style="display:none;">
    <input type="hidden" name="view" value="dashboard">
</form>

<?php include 'templates/footer.php'; ?>