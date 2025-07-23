<?php
session_start();
require_once('functions.php');

// ✅ AJAX buscador reparado — redirige a ajax-handler.php
if (isset($_GET['fl_ajax']) && $_GET['fl_ajax'] === 'buscar') {
    require_once('ajax-handler.php');
    exit;
}

// 🚫 Bloquea acceso sin login
if (!isset($_SESSION['fl_user'])) {
    header('Location: ?view=login');
    exit;
}

$user = $_SESSION['fl_user'];

$cotizaciones_file = plugin_dir_path(__FILE__) . 'data/cotizaciones.json';
$cotizaciones = file_exists($cotizaciones_file) ? json_decode(file_get_contents($cotizaciones_file), true) : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $productos_raw = $_POST['productos_data'] ?? '';
    $productos = !empty($productos_raw) ? json_decode(stripslashes($productos_raw), true) : [];

    // 📌 Obtener ID incremental
    $max_id = 0;
    foreach ($cotizaciones as $c) {
        if ($c['id'] > $max_id) {
            $max_id = $c['id'];
        }
    }
    $id = $max_id + 1;

    $fecha = date('Y-m-d H:i:s');

    $nueva = [
        'id' => $id,
        'vendedor' => $user['name'],
        'cliente' => sanitize_text_field($_POST['contacto_nombre']),
        'empresa' => sanitize_text_field($_POST['empresa_nombre']),
        'email' => sanitize_email($_POST['contacto_email']),
        'telefono' => sanitize_text_field($_POST['contacto_numero']),
        'condiciones' => sanitize_text_field($_POST['condiciones']),
        'fecha' => $fecha,
        'estado' => 'NUEVA',
        'productos' => $productos,
    ];

    $cotizaciones[] = $nueva;

    // 💾 Guardar JSON con flock
    $fp = fopen($cotizaciones_file, 'c+');
    if (flock($fp, LOCK_EX)) {
        fseek($fp, 0);
        ftruncate($fp, 0);
        fwrite($fp, json_encode($cotizaciones, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        fflush($fp);
        flock($fp, LOCK_UN);
    }
    fclose($fp);

    // ✅ Guardar nueva empresa si no existe
    $empresa_nombre = trim(sanitize_text_field($_POST['empresa_nombre']));
    $empresas_file = plugin_dir_path(__FILE__) . 'data/empresas.json';
    $empresas = file_exists($empresas_file) ? json_decode(file_get_contents($empresas_file), true) : [];
    if ($empresa_nombre && !in_array($empresa_nombre, $empresas)) {
        $empresas[] = $empresa_nombre;
        file_put_contents($empresas_file, json_encode($empresas, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    // 🎯 PDF directo (guardar y descargar)
    if (isset($_POST['guardar_y_descargar'])) {
        echo '<meta name="pdf-id" content="' . $id . '"/>';
        exit;
    }

    // 🔁 Redirige al dashboard por defecto
    header('Location: ?view=dashboard');
    exit;
}

// 🔧 Condiciones desde configuración
$config_file = plugin_dir_path(__FILE__) . 'admin/config.json';
$config_data = file_exists($config_file) ? json_decode(file_get_contents($config_file), true) : [];
$condiciones = is_array($config_data['condiciones']) ? $config_data['condiciones'] : [];

include 'templates/header.php';
?>

<!-- HTML COMIENZA -->
<div class="fl-dashboard">

<?php if (isset($_GET['pdf_id'])): ?>
<script>
window.addEventListener('DOMContentLoaded', function () {
    window.open('https://focuslogo.com.ar/wp-content/plugins/focuslogo-cotizador/pdf.php?id=<?= intval($_GET['pdf_id']) ?>', '_blank');
});
</script>
<?php endif; ?>

<h2>Nueva Cotización</h2>
<p style="margin-bottom: 2rem;"><a href="?view=dashboard">← Volver al dashboard</a></p>

<form id="form-cotizacion" method="post" autocomplete="off">
    <div style="margin-bottom: 1rem;">
        <label><strong>Nombre del contacto*</strong></label><br>
        <input type="text" name="contacto_nombre" required style="width:100%; padding:10px; border-radius:6px; border:1px solid #ccc;">
    </div>

    <div style="margin-bottom: 1rem; position: relative;">
        <label>Nombre de la empresa</label><br>
        <input type="text" name="empresa_nombre" id="empresa_nombre" autocomplete="off" style="width:100%; padding:10px; border-radius:6px; border:1px solid #ccc;">
        <ul id="sugerencias-empresas" style="position:absolute; background:#fff; border:1px solid #ccc; list-style:none; padding:0; margin:0; width:100%; max-height:140px; overflow-y:auto; display:none; z-index:1000;"></ul>
    </div>

    <div style="margin-bottom: 1rem;">
        <label>Email del contacto</label><br>
        <input type="email" name="contacto_email" style="width:100%; padding:10px; border-radius:6px; border:1px solid #ccc;">
    </div>

    <div style="margin-bottom: 1rem;">
        <label>Número de contacto</label><br>
        <input type="text" name="contacto_numero" style="width:100%; padding:10px; border-radius:6px; border:1px solid #ccc;">
    </div>

    <div style="margin-bottom: 2rem;">
        <label><strong>Condiciones comerciales</strong></label><br>
        <select name="condiciones" required style="width:100%; padding:10px; border-radius:6px; border:1px solid #ccc; color:#666;" onchange="this.style.color = '#000';">
            <option value="" disabled selected hidden>Selecciona una opción</option>
            <?php foreach ($condiciones as $nombre => $texto): ?>
                <option value="<?= esc_attr($nombre) ?>"><?= esc_html($nombre) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div style="margin-top:3rem;">
        <label><strong>Buscar productos</strong></label><br>
        <input type="text" id="buscar-producto" placeholder="Nombre o SKU del producto" style="width:100%; padding:10px; border-radius:6px; border:1px solid #ccc;">
        <ul id="resultados-productos" style="list-style:none; padding:0; margin-top:10px;"></ul>
    </div>

    <div style="margin-top:1rem;">
        <button type="button" id="btn-custom" style="padding: 8px 14px; border-radius: 6px; border: 1px solid #02196f; background: #fff; color: #02196f; cursor: pointer;">
            + Agregar producto personalizado
        </button>
    </div>

    <div id="form-custom-producto" style="display:none; margin-top:2rem; border:1px solid #ccc; border-radius:12px; padding:1rem;">
        <h4>Nuevo producto personalizado</h4>

        <div style="margin-bottom:1rem;">
            <label>Imagen del producto</label><br>
            <input type="file" id="custom-image" accept="image/*" style="margin-top:5px;">
        </div>

        <div style="margin-bottom:1rem;">
            <label>Nombre del producto*</label><br>
            <input type="text" id="custom-nombre" style="width:100%; padding:10px; border-radius:6px; border:1px solid #ccc;">
        </div>

        <div style="margin-bottom:1rem;">
            <label>SKU</label><br>
            <input type="text" id="custom-sku" style="width:100%; padding:10px; border-radius:6px; border:1px solid #ccc;">
        </div>

        <div style="margin-bottom:1rem;">
            <label>Precio unitario*</label><br>
            <input type="number" id="custom-precio" style="width:100%; padding:10px; border-radius:6px; border:1px solid #ccc;">
        </div>

        <div style="margin-bottom:1rem;">
            <label>MOQ (mínimo a ofrecer)*</label><br>
            <input type="number" id="custom-moq" value="100" style="width:100%; padding:10px; border-radius:6px; border:1px solid #ccc;">
        </div>

        <button type="button" id="btn-insertar-custom" style="margin-top:1rem; background:#02196f; color:#fff; border:none; padding:10px 16px; border-radius:6px; cursor:pointer;">
            Agregar a la cotización
        </button>
    </div>

    <div id="productos-seleccionados" style="margin-top:2rem;">
        <h4>Productos agregados</h4>
        <div id="lista-productos"></div>
    </div>

    <input type="hidden" name="productos_data" id="productos_data">

    <div style="text-align:right; margin-top: 2rem;">
        <button type="submit" name="guardar" class="fl-btn-nueva">Guardar y continuar</button>
        <button type="button" id="btn-guardar-descargar" class="fl-btn-nueva" style="background:#02196f; color:#fff;">Guardar y descargar</button>
    </div>
</form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('form-cotizacion');
    const input = document.getElementById('buscar-producto');
    const resultados = document.getElementById('resultados-productos');
    const lista = document.getElementById('lista-productos');
    const inputEmpresa = document.getElementById('empresa_nombre');
    const sugerencias = document.getElementById('sugerencias-empresas');

    // Manejo de envío del formulario
form.addEventListener('submit', function (e) {
    const productos = [];
    document.querySelectorAll('.producto-item').forEach(item => {
const imagenes = [
    item.dataset.imagen1,
    item.dataset.imagen2,
    item.dataset.imagen3
].filter(Boolean);

        productos.push({
    nombre: item.querySelector('strong')?.innerText,
    sku: item.querySelector('small')?.innerText?.replace('SKU: ', '').trim(),
    precio: item.querySelector('.precio')?.value,
    descuento: item.querySelector('.descuento')?.value,
    observaciones: item.querySelector('.observaciones')?.value,
    descripcion: item.querySelector('.descripcion')?.value,
    imagenes: imagenes,
    visuales: {
                v1: {
                    cantidad: item.querySelector('.v1-cantidad')?.value,
                    impresion: item.querySelector('.v1-impresion')?.value,
                    descuento: item.querySelector('.v1-descuento')?.value
                },
                v2: {
                    cantidad: item.querySelector('.v2-cantidad')?.value,
                    impresion: item.querySelector('.v2-impresion')?.value,
                    descuento: item.querySelector('.v2-descuento')?.value
                },
                v3: {
                    cantidad: item.querySelector('.v3-cantidad')?.value,
                    impresion: item.querySelector('.v3-impresion')?.value,
                    descuento: item.querySelector('.v3-descuento')?.value
                }
            }
        });
    });
    document.getElementById('productos_data').value = JSON.stringify(productos);
    // ✅ No se hace preventDefault, se deja seguir
});


    // Búsqueda AJAX de productos
    input.addEventListener('input', function () {
        const term = this.value.trim();
        if (term.length < 3) {
            resultados.innerHTML = '';
            return;
        }
        fetch('ajax-handler.php?fl_ajax=buscar&term=' + encodeURIComponent(term))
            .then(res => res.json())
            .then(data => {
                resultados.innerHTML = '';
                data.forEach(producto => {
                    const li = document.createElement('li');
                    li.style.cursor = 'pointer';
                    li.style.padding = '10px';
                    li.style.display = 'flex';
                    li.style.alignItems = 'center';
                    li.style.borderBottom = '1px solid #eee';
                    li.innerHTML = `
                        <img src="${producto.image}" width="40" height="40" style="border-radius:6px; margin-right:10px;">
                        <div>
                            <strong>${producto.name}</strong><br>
                            <small>SKU: ${producto.sku} | $${parseFloat(producto.price).toLocaleString()}</small>
                        </div>`;
                    li.addEventListener('click', () => {
                        agregarProducto({
                            name: producto.name,
                            sku: producto.sku,
                            price: producto.price,
                            moq: producto.moq,
                            imagenes: producto.imagenes,
                            image: producto.image,
                            descripcion: producto.description
                        });
                        resultados.innerHTML = '';
                        input.value = '';
                    });
                    resultados.appendChild(li);
                });
            });
    });
    
// 💡 Sugerencias de empresa (Argentina)
inputEmpresa.addEventListener('input', function () {
    const term = this.value.trim();
    if (term.length < 2) {
        sugerencias.style.display = 'none';
        return;
    }
    fetch('https://focuslogo.com.ar/wp-content/plugins/focuslogo-cotizador/ajax-handler.php?fl_ajax=sugerir_empresas&term=' + encodeURIComponent(term))
        .then(res => res.json())
        .then(data => {
            sugerencias.innerHTML = '';
            if (data.length === 0) {
                sugerencias.style.display = 'none';
                return;
            }
            data.forEach(nombre => {
                const li = document.createElement('li');
                li.textContent = nombre;
                li.style.padding = '8px';
                li.style.cursor = 'pointer';
                li.addEventListener('click', () => {
                    inputEmpresa.value = nombre;
                    sugerencias.style.display = 'none';
                });
                sugerencias.appendChild(li);
            });
            sugerencias.style.display = 'block';
        })
        .catch(err => {
            console.error('Error en sugerencias:', err);
            sugerencias.style.display = 'none';
        });
});


    // Cerrar sugerencias si se hace clic fuera
    document.addEventListener('click', function (e) {
        if (!sugerencias.contains(e.target) && e.target !== inputEmpresa) {
            sugerencias.style.display = 'none';
        }
    });

    // Mostrar formulario de producto personalizado
    document.getElementById('btn-custom').addEventListener('click', () => {
        const div = document.getElementById('form-custom-producto');
        div.style.display = div.style.display === 'none' ? 'block' : 'none';
    });

    // Agregar producto personalizado
    document.getElementById('btn-insertar-custom').addEventListener('click', () => {
        const nombre = document.getElementById('custom-nombre').value.trim();
        const sku = document.getElementById('custom-sku').value.trim();
        const precio = parseFloat(document.getElementById('custom-precio').value.trim());
        const moq = parseInt(document.getElementById('custom-moq').value.trim());
        const fileInput = document.getElementById('custom-image');
        const file = fileInput.files[0];

        if (!nombre || isNaN(precio) || isNaN(moq)) {
            alert('Por favor completa nombre, precio y MOQ');
            return;
        }

        const reader = new FileReader();
        reader.onload = function (e) {
            const producto = {
                name: nombre,
                sku: sku || 'custom',
                price: precio,
                moq: moq,
                image: e.target.result,
                imagenes: [e.target.result]
            };
            agregarProducto(producto);
            document.getElementById('form-custom-producto').style.display = 'none';
            fileInput.value = '';
            document.getElementById('custom-nombre').value = '';
            document.getElementById('custom-sku').value = '';
            document.getElementById('custom-precio').value = '';
            document.getElementById('custom-moq').value = 100;
        };

        if (file) {
            reader.readAsDataURL(file);
        } else {
            const producto = {
                name: nombre,
                sku: sku || 'custom',
                price: precio,
                moq: moq,
                image: '',
                imagenes: []
            };
            agregarProducto(producto);
            document.getElementById('form-custom-producto').style.display = 'none';
            document.getElementById('custom-nombre').value = '';
            document.getElementById('custom-sku').value = '';
            document.getElementById('custom-precio').value = '';
            document.getElementById('custom-moq').value = 100;
        }
    });
});


// Recalcular precios al cambiar campos
document.addEventListener('input', function (e) {
    if (e.target.matches('.precio, .descuento, .v1-cantidad, .v2-cantidad, .v3-cantidad, .v1-impresion, .v2-impresion, .v3-impresion, .v1-descuento, .v2-descuento, .v3-descuento')) {
        actualizarPrecios();
    }
});

// Al cargar, recalcular
document.addEventListener('DOMContentLoaded', actualizarPrecios);



</script>

<script>
function agregarProducto(p) {
    const moq = parseInt(p.moq) || 1;
    const cantidad1 = Math.round(moq / 2);
    const cantidad2 = moq;
    const cantidad3 = moq * 2;

    const div = document.createElement('div');
    div.className = 'producto-item';
    div.style.marginBottom = '20px';
    div.style.border = '1px solid #ddd';
    div.style.borderRadius = '8px';
    div.style.padding = '1rem';
    div.style.background = '#f9f9f9';

    div.innerHTML = `
        <div style="display:flex; gap:1rem; align-items:center;">
            <img src="${(p.imagenes && p.imagenes[0]) || p.image}" width="60" height="60" style="border-radius:6px;">
            <div style="flex:1;">
                <strong>${p.name}</strong><br>
                <small>SKU: ${p.sku}</small><br>
                <small>MOQ: ${moq}</small>
            </div>
            <button class="eliminar-producto" style="background:red;color:white;border:none;padding:6px 10px;border-radius:6px;cursor:pointer;">✖</button>
        </div>

        <div style="margin-top:1rem; display:grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
            <div>
                <label>Precio unitario:</label>
                <input type="number" value="${p.price}" class="precio" style="width:100%;padding:8px;border-radius:6px;border:1px solid #ccc;">
            </div>
            <div>
                <label>% Descuento global:</label>
                <input type="number" value="0" max="100" class="descuento" style="width:100%;padding:8px;border-radius:6px;border:1px solid #ccc;">
            </div>
        </div>

        <div style="margin-top:1rem;">
            <label>Observaciones:</label>
            <textarea class="observaciones" style="width:100%; border-radius:6px; border:1px solid #ccc;"></textarea>
        </div>
        
        <div style="margin-top:1rem;">
            <label>Descripción:</label>
            <textarea class="descripcion" style="width:100%; border-radius:6px; border:1px solid #ccc;">${p.descripcion || ''}</textarea>
        </div>

        <div class="visuales" style="margin-top:2rem;">
            <h4>Propuestas</h4>
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem;">
                ${[cantidad1, cantidad2, cantidad3].map((cantidad, i) => `
                    <div style="border: 1px solid #ccc; border-radius: 6px; padding: 10px;">
                        <strong>Visual ${i + 1}</strong><br>
                        <label>Cantidad:</label>
                        <input type="number" class="v${i+1}-cantidad" value="${cantidad}" style="width:100%;padding:6px;">
                        <label>Precio impresión:</label>
                        <input type="number" class="v${i+1}-impresion" value="${cantidad < moq ? 129900 : 69900}" style="width:100%;padding:6px;">
                        <label>% Descuento visual:</label>
                        <input type="number" class="v${i+1}-descuento" value="${i === 0 ? 0 : i === 1 ? 2 : 4}" style="width:100%;padding:6px;" max="100">
                        <div class="v${i+1}-preview" style="margin-top:10px; font-size:12px; color:#555;">
                            Precio unitario final: $0,00<br>
                            Importe total: $0,00
                        </div>
                    </div>
                `).join('')}
            </div>
        </div>
    `;

    // Asignar datasets con imágenes
    if (p.imagenes && Array.isArray(p.imagenes)) {
        p.imagenes.slice(0, 3).forEach((url, index) => {
            div.dataset[`imagen${index + 1}`] = url;
        });
    }

    div.querySelector('.eliminar-producto').addEventListener('click', () => div.remove());
    document.getElementById('lista-productos').appendChild(div);
    document.getElementById('resultados-productos').innerHTML = '';
    document.getElementById('buscar-producto').value = '';
    
    actualizarPrecios();

}


function formatoMoneda(valor) {
    return '$' + valor.toLocaleString('es-AR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

function actualizarPrecios() {
    document.querySelectorAll('.producto-item').forEach(producto => {
        const precioUnitarioInput = producto.querySelector('.precio');
        const descuentoGlobalInput = producto.querySelector('.descuento');

        const precioUnitarioBase = parseFloat(precioUnitarioInput.value) || 0;
        const descuentoGlobal = parseFloat(descuentoGlobalInput.value) || 0;

        for (let i = 1; i <= 3; i++) {
            const cantidadInput = producto.querySelector(`.v${i}-cantidad`);
            const impresionInput = producto.querySelector(`.v${i}-impresion`);
            const descuentoVisualInput = producto.querySelector(`.v${i}-descuento`);
            const previewDiv = producto.querySelector(`.v${i}-preview`);

            if (!cantidadInput || !previewDiv) continue;

            const cantidad = parseFloat(cantidadInput.value) || 0;
            const impresion = parseFloat(impresionInput.value) || 0;
            const descuentoVisual = parseFloat(descuentoVisualInput.value) || 0;

            const descuentoTotal = descuentoGlobal + descuentoVisual;
            const precioConDescuento = precioUnitarioBase * (1 - (descuentoTotal / 100));
            const importeTotal = (precioConDescuento * cantidad) + impresion;
            const precioUnitarioFinal = importeTotal / (cantidad || 1); // evita división por cero

            previewDiv.innerHTML = `
                Precio unitario final: ${formatoMoneda(precioUnitarioFinal)}<br>
                Importe total: ${formatoMoneda(importeTotal)}
            `;
        }
    });
}



// Botón Guardar y Descargar
document.getElementById('btn-guardar-descargar').addEventListener('click', function () {
    const form = document.getElementById('form-cotizacion');

    const productos = [];
    document.querySelectorAll('.producto-item').forEach(item => {
const imagenes = [
    item.dataset.imagen1,
    item.dataset.imagen2,
    item.dataset.imagen3
].filter(Boolean);


        productos.push({
            nombre: item.querySelector('strong')?.innerText,
            sku: item.querySelector('small')?.innerText?.replace('SKU: ', '').trim(),
            precio: item.querySelector('.precio')?.value,
            descuento: item.querySelector('.descuento')?.value,
            observaciones: item.querySelector('.observaciones')?.value,
            descripcion: item.querySelector('.descripcion')?.value,
            imagenes: imagenes,
            visuales: {
                v1: {
                    cantidad: item.querySelector('.v1-cantidad')?.value,
                    impresion: item.querySelector('.v1-impresion')?.value,
                    descuento: item.querySelector('.v1-descuento')?.value
                },
                v2: {
                    cantidad: item.querySelector('.v2-cantidad')?.value,
                    impresion: item.querySelector('.v2-impresion')?.value,
                    descuento: item.querySelector('.v2-descuento')?.value
                },
                v3: {
                    cantidad: item.querySelector('.v3-cantidad')?.value,
                    impresion: item.querySelector('.v3-impresion')?.value,
                    descuento: item.querySelector('.v3-descuento')?.value
                }
            }
        });
    });

    document.getElementById('productos_data').value = JSON.stringify(productos);

    const formData = new FormData(form);
    formData.append('guardar_y_descargar', '1');

    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(res => res.text())
    .then(response => {
        const match = response.match(/<meta name="pdf-id" content="(\d+)"\/>/);
        if (match) {
            const id = match[1];
            window.open(`https://focuslogo.com.ar/wp-content/plugins/focuslogo-cotizador/pdf.php?id=${id}`, '_blank');
            window.location.href = '?view=dashboard';
        } else {
            alert('No se pudo obtener el ID de la cotización. Intenta nuevamente.');
        }
    })
    .catch(() => alert('Error al generar el PDF.'));
});
</script>

<?php include 'templates/footer.php'; ?>

