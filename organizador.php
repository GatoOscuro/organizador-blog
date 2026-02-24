<?php
// ==============================================
// ORGANIZADOR AUTOMÁTICO V6.0 - CON BUSCADOR
// ==============================================
// MEJORAS:
// ✅ Buscador en tiempo real por título
// ✅ Filtro por año (desplegable automático)
// ✅ NO se modifica procesamiento de HTML
// ✅ 100% compatible con versión anterior
// ==============================================

// ==============================================
// CONFIGURACIÓN DEL BLOG - FÁCIL DE MODIFICAR
// ==============================================
$config = [
    'paginas_estaticas' => [
        'sobre-mi',
        'sobre-mi.html',
        'alternativas',
        'alternativas.html',
        'fedisucks',
        'fedisucks.html',
        'fediamor',
        'fediamor.html',
        'blogs',
        'blogs.html',
        'audios',
        'audios.html',
        'art-culos-ndice',
        'art-culos-ndice-el-cuarto-de-gatooscuro.html',
        'artículos-índice',
        'indice-articulos'
    ],
    'exclusiones_articulos' => [
        'dwservice',
        'rustdesk',
        'control remoto',
        '100 alternativas',
        'escribir de forma anónima',
        'disroot',
        'europeas'
    ],
    'titulo_sitio' => 'EL CUARTO DE GATOOSCURO',
    'url_indice' => 'indice.html',
    'estilo_retro' => true
];

// ==============================================
// SISTEMA DE LOGGING
// ==============================================
function log_error($mensaje, $archivo = '') {
    $log_entry = date('Y-m-d H:i:s') . " - ERROR: $mensaje" . ($archivo ? " [$archivo]" : '') . PHP_EOL;
    file_put_contents('error_log.txt', $log_entry, FILE_APPEND);
}

function log_info($mensaje) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $log_entry = date('Y-m-d H:i:s') . " - INFO: $mensaje" . PHP_EOL;
        file_put_contents('proceso_log.txt', $log_entry, FILE_APPEND);
    }
}

// Configuración
$directorio_trabajo = 'temp_organizador';
$max_tamano = 100 * 1024 * 1024; // 100MB

// Crear directorio temporal con permisos seguros
if (!file_exists($directorio_trabajo)) {
    mkdir($directorio_trabajo, 0755, true);
}                                        

// Función para limpiar nombres
function limpiar_nombre($nombre) {
    $nombre = strtolower($nombre);
    $nombre = preg_replace('/[^a-z0-9]+/', '-', $nombre);
    $nombre = trim($nombre, '-');
    return $nombre ?: 'documento';
}

// Función para extraer título
function extraer_titulo($contenido) {
    if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $contenido, $matches)) {
        return trim(strip_tags($matches[1]));
    }
    if (preg_match('/<h1[^>]*>(.*?)<\/h1>/is', $contenido, $matches)) {
        return trim(strip_tags($matches[1]));
    }
    return 'Documento sin título';
}

// FUNCIÓN CRÍTICA: Extraer fecha REAL del contenido
function extraer_fecha_real($contenido, $archivo_nombre) {
    $fecha_encontrada = null;
    
    // 1. BUSCAR EN METADATOS DE WORDPRESS
    if (preg_match('/<meta[^>]*property=["\']article:published_time["\'][^>]*content=["\']([^"\']*)["\']/i', $contenido, $matches)) {
        $fecha_encontrada = $matches[1];
    }
    elseif (preg_match('/<meta[^>]*name=["\']publication-date["\'][^>]*content=["\']([^"\']*)["\']/i', $contenido, $matches)) {
        $fecha_encontrada = $matches[1];
    }
    elseif (preg_match('/<meta[^>]*name=["\']date["\'][^>]*content=["\']([^"\']*)["\']/i', $contenido, $matches)) {
        $fecha_encontrada = $matches[1];
    }
    // 2. BUSCAR EN TIME TAG
    elseif (preg_match('/<time[^>]*datetime=["\']([^"\']*)["\']/i', $contenido, $matches)) {
        $fecha_encontrada = $matches[1];
    }
    // 3. BUSCAR EN EL TEXTO
    else {
        $patrones_busqueda = [
            '/\b(\d{1,2})\s+de\s+([a-z]+)\s+de\s+(\d{4})\b/i',
            '/\b(\d{1,2})\s+([a-z]+)\s*[,]?\s*(\d{4})\b/i',
            '/\b(20\d{2})[-\/](0[1-9]|1[0-2])[-\/](0[1-9]|[12][0-9]|3[01])\b/',
            '/\b(0[1-9]|[12][0-9]|3[01])[-\/](0[1-9]|1[0-2])[-\/](20\d{2})\b/'
        ];
        
        foreach ($patrones_busqueda as $patron) {
            if (preg_match($patron, $contenido, $matches)) {
                $fecha_encontrada = $matches[0];
                break;
            }
        }
    }
    
    if ($fecha_encontrada) {
        $fecha_limpia = preg_replace('/[^0-9a-z\-\/]/i', ' ', $fecha_encontrada);
        $timestamp = strtotime($fecha_limpia);
        if ($timestamp && $timestamp > 0) {
            return date('Y-m-d', $timestamp);
        }
    }
    
    // Último recurso
    return date('Y-m-d', filemtime($archivo_nombre));
}

// FUNCIÓN CORREGIDA: Detectar páginas estáticas
function es_pagina_estatica($nombre_archivo, $titulo, $config) {
    $nombre_base = strtolower(basename($nombre_archivo));
    $titulo_lower = strtolower($titulo);
    
    // PRIMERO: Verificar exclusiones (artículos que parecen páginas pero no lo son)
    foreach ($config['exclusiones_articulos'] as $excluir) {
        if (strpos($titulo_lower, $excluir) !== false) {
            log_info("EXCLUSIÓN: '$titulo' contiene '$excluir' -> ES ARTÍCULO");
            return false;
        }
    }
    
    // SEGUNDO: Verificar si coincide con páginas estáticas
    foreach ($config['paginas_estaticas'] as $pagina) {
        if (strpos($nombre_base, $pagina) !== false) {
            log_info("PÁGINA por nombre: $nombre_base");
            return true;
        }
        
        $pagina_limpia = str_replace(['-', '.html'], [' ', ''], $pagina);
        if (strpos($titulo_lower, $pagina_limpia) !== false) {
            log_info("PÁGINA por título: $titulo");
            return true;
        }
    }
    
    log_info("ARTÍCULO: $titulo");
    return false;
}

// ==============================================
// FUNCIÓN MEJORADA - CON BUSCADOR Y FILTROS
// ==============================================
function generar_indice($paginas, $articulos, $directorio, $config) {
    // Ordenar artículos por fecha (más reciente primero)
    usort($articulos, function($a, $b) {
        return strtotime($b['fecha']) - strtotime($a['fecha']);
    });
    
    // Obtener años únicos para el filtro
    $años = [];
    foreach ($articulos as $articulo) {
        $año = date('Y', strtotime($articulo['fecha']));
        if (!in_array($año, $años)) {
            $años[] = $año;
        }
    }
    rsort($años); // Ordenar descendente (más reciente primero)
    
    $html = '<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📚 ARCHIVO ' . $config['titulo_sitio'] . '</title>
    <style>
        body {
            background: #000;
            color: #0f0;
            font-family: "Courier New", monospace;
            margin: 20px;
            line-height: 1.6;
        }
        .contenedor {
            max-width: 1000px;
            margin: 0 auto;
            border: 3px double #f0f;
            padding: 25px;
            background: #0a0a0a;
        }
        h1 {
            color: #ff0;
            text-align: center;
            font-size: 2.5em;
            text-shadow: 3px 3px 0 #f0f;
            border-bottom: 2px solid #f0f;
            padding-bottom: 10px;
        }
        .blink {
            animation: parpadeo 1s infinite;
        }
        @keyframes parpadeo {
            50% { opacity: 0; }
        }
        .menu-categorias {
            display: flex;
            gap: 20px;
            justify-content: center;
            margin: 20px 0;
            padding: 10px;
            background: #111;
            border: 1px solid #f0f;
        }
        .menu-categorias a {
            color: #0ff;
            text-decoration: none;
            padding: 5px 15px;
            border: 1px solid #f0f;
        }
        .menu-categorias a:hover {
            background: #f0f;
            color: #000;
        }
        
        /* ===== NUEVO: ESTILOS DEL BUSCADOR ===== */
        .buscador {
            background: #111;
            border: 2px solid #f0f;
            padding: 20px;
            margin: 20px 0;
        }
        .buscador input, .buscador select {
            background: #000;
            color: #0f0;
            border: 1px solid #f0f;
            padding: 10px;
            font-family: "Courier New", monospace;
            font-size: 1em;
            margin: 5px;
        }
        .buscador input {
            width: 60%;
        }
        .buscador select {
            width: 30%;
            cursor: pointer;
        }
        .buscador input:focus, .buscador select:focus {
            outline: none;
            border-color: #ff0;
        }
        .resultados {
            color: #ff0;
            font-size: 0.9em;
            margin-top: 10px;
            text-align: right;
        }
        /* ===== FIN ESTILOS BUSCADOR ===== */
        
        h2 {
            color: #0ff;
            border-left: 4px solid #f0f;
            padding-left: 10px;
            margin-top: 30px;
        }
        .indice {
            list-style: none;
            padding: 0;
        }
        .indice li {
            margin: 15px 0;
            padding: 15px;
            border: 1px dashed #f0f;
            background: rgba(255,0,255,0.05);
            transition: all 0.3s ease;
        }
        .indice li:hover {
            border-color: #0f0;
            background: rgba(0,255,0,0.1);
        }
        .indice a {
            color: #0ff;
            text-decoration: none;
            font-size: 1.2em;
            font-weight: bold;
        }
        .indice a:hover {
            color: #f0f;
            background: #0f0;
            padding: 2px 5px;
        }
        .fecha {
            color: #ff0;
            font-size: 0.9em;
            margin-left: 10px;
            background: #1a1a1a;
            padding: 3px 8px;
        }
        .badge {
            display: inline-block;
            background: #f0f;
            color: #000;
            padding: 3px 10px;
            margin-right: 10px;
            font-size: 0.8em;
        }
        .contador {
            text-align: right;
            color: #ff0;
            border-top: 1px solid #f0f;
            margin-top: 20px;
            padding-top: 10px;
            font-size: 1.1em;
        }
        hr {
            border: none;
            border-top: 2px solid #0f0;
        }
        .pie {
            text-align: center;
            color: #666;
            margin-top: 30px;
            padding: 15px;
            border-top: 2px dashed #f0f;
        }
        .oculto {
            display: none;
        }
    </style>
</head>
<body>
<div class="contenedor">
    <h1>📼 ' . $config['titulo_sitio'] . ' <span class="blink">▌</span></h1>
    
    <div class="menu-categorias">
        <a href="#paginas">📄 PÁGINAS</a>
        <a href="#articulos">📰 ARTÍCULOS (' . count($articulos) . ')</a>
    </div>
    
    <hr>
    
    <!-- ===== NUEVO: BUSCADOR ===== -->
    <div class="buscador">
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <input type="text" id="buscador" placeholder="🔍 Buscar por título..." onkeyup="filtrarArticulos()">
            <select id="filtroAno" onchange="filtrarArticulos()">
                <option value="">📅 Todos los años</option>';
    
    foreach ($años as $año) {
        $html .= '<option value="' . $año . '">' . $año . '</option>';
    }
    
    $html .= '
            </select>
        </div>
        <div class="resultados" id="resultados">
            Mostrando ' . count($articulos) . ' artículos
        </div>
    </div>
    <!-- ===== FIN BUSCADOR ===== -->
    
    <h2 id="paginas">📄 PÁGINAS</h2>';
    
    if (empty($paginas)) {
        $html .= '<p style="color:#666;">No hay páginas estáticas</p>';
    } else {
        $html .= '<ul class="indice">';
        foreach ($paginas as $pagina) {
            $html .= '
            <li>
                <span class="badge">📄</span>
                <a href="' . $pagina['archivo'] . '">' . htmlspecialchars($pagina['titulo']) . '</a>
            </li>';
        }
        $html .= '</ul>';
    }
    
    $html .= '
    <h2 id="articulos">📰 ARTÍCULOS <span style="color:#ff0;" id="totalArticulos">(' . count($articulos) . ' total)</span></h2>';
    
    if (empty($articulos)) {
        $html .= '<p style="color:#666;">No hay artículos</p>';
    } else {
        $html .= '<ul class="indice" id="listaArticulos">';
        foreach ($articulos as $articulo) {
            $fecha_formateada = date('d/m/Y', strtotime($articulo['fecha']));
            $año_articulo = date('Y', strtotime($articulo['fecha']));
            $html .= '
            <li data-titulo="' . strtolower(htmlspecialchars($articulo['titulo'])) . '" data-fecha="' . $articulo['fecha'] . '" data-ano="' . $año_articulo . '">
                <span class="badge" style="background:#0f0; color:#000;">📰</span>
                <a href="' . $articulo['archivo'] . '">' . htmlspecialchars($articulo['titulo']) . '</a>
                <span class="fecha">📅 ' . $fecha_formateada . '</span>
            </li>';
        }
        $html .= '</ul>';
    }
    
    $total = count($paginas) + count($articulos);
    $html .= '
    <hr>
    <div class="contador">
        <span class="blink">🟢</span> TOTAL: ' . $total . ' | PÁGINAS: ' . count($paginas) . ' | ARTÍCULOS: ' . count($articulos) . '
    </div>
    <div class="pie">
        ⏳ Rescatando la web antigua · Conectando a 1200 baudios · ' . date('Y') . '
    </div>
</div>

<!-- ===== NUEVO: JAVASCRIPT DEL BUSCADOR ===== -->
<script>
function filtrarArticulos() {
    const textoBusqueda = document.getElementById("buscador").value.toLowerCase();
    const añoSeleccionado = document.getElementById("filtroAno").value;
    const articulos = document.querySelectorAll("#listaArticulos li");
    let contador = 0;
    
    articulos.forEach(articulo => {
        const titulo = articulo.getAttribute("data-titulo");
        const año = articulo.getAttribute("data-ano");
        
        // Verificar si coincide con búsqueda y año
        const coincideTexto = titulo.includes(textoBusqueda);
        const coincideAño = añoSeleccionado === "" || año === añoSeleccionado;
        
        if (coincideTexto && coincideAño) {
            articulo.classList.remove("oculto");
            contador++;
        } else {
            articulo.classList.add("oculto");
        }
    });
    
    // Actualizar contador
    document.getElementById("resultados").innerHTML = "Mostrando " + contador + " artículos";
    document.getElementById("totalArticulos").innerHTML = "(" + contador + " total)";
}

// Ejecutar al cargar la página para asegurar estado inicial
document.addEventListener("DOMContentLoaded", function() {
    filtrarArticulos();
});
</script>
</body>
</html>';
    
    return $html;
}

// ==============================================
// FUNCIÓN CORREGIDA - ENLACES ARREGLADOS (SIN CAMBIOS)
// ==============================================
function procesar_html($contenido, $archivo_actual, $titulo_original, $config) {
    $titulo = extraer_titulo($contenido);
    
    // PASO 1: Extraer el body de forma CORRECTA
    $contenido_original = $contenido; // Guardamos copia por si acaso
    
    // Buscar el body con una expresión regular que funcione
    if (preg_match('/<body[^>]*>(.*)<\/body>/is', $contenido, $matches)) {
        $contenido_body = $matches[1];
    } else {
        // Si no hay body, usamos todo pero quitamos head
        $contenido_body = preg_replace('/<head[^>]*>.*?<\/head>/is', '', $contenido);
        $contenido_body = preg_replace('/<!DOCTYPE[^>]*>/i', '', $contenido_body);
        $contenido_body = preg_replace('/<html[^>]*>|<\/html>/i', '', $contenido_body);
    }
    
    // ==============================================
    // CORRECCIÓN ESPECÍFICA: ARREGLAR ENLACES ROTOS
    // ==============================================
    
    // 1. Eliminar TODOS los enlaces a file:// (cualquier ruta)
    $contenido_body = preg_replace('/href=["\']file:\/\/[^"\']*["\']/', 'href="' . $config['url_indice'] . '"', $contenido_body);
    
    // 2. Eliminar específicamente el enlace a /home/nombre de usuario/Descargas/index.html
    $contenido_body = preg_replace('/href=["\']\/home\/nombre de usuario\/Descargas\/index\.html["\']/', 'href="' . $config['url_indice'] . '"', $contenido_body);
    $contenido_body = preg_replace('/href=["\']file:\/\/\/nombre de usuario\/gato\/Descargas\/index\.html["\']/', 'href="' . $config['url_indice'] . '"', $contenido_body);
    
    // 3. Buscar el texto exacto "El cuarto de GatoOscuro" y arreglar su enlace
    $contenido_body = preg_replace(
        '/<a[^>]*href=["\']file:\/\/[^"\']*["\'][^>]*>El cuarto de GatoOscuro<\/a>/i',
        '<a href="' . $config['url_indice'] . '" style="color:#0ff;">📼 El cuarto de GatoOscuro</a>',
        $contenido_body
    );
    
    // 4. Buscar el texto "← Volver al inicio" y arreglar su enlace
    $contenido_body = preg_replace(
        '/<a[^>]*href=["\']file:\/\/[^"\']*["\'][^>]*>← Volver al inicio<\/a>/i',
        '<a href="' . $config['url_indice'] . '" style="color:#0ff;">⬅️ VOLVER AL INICIO</a>',
        $contenido_body
    );
    
    // 5. También arreglar cualquier enlace a "index.html" que sea absoluto
    $contenido_body = preg_replace('/href=["\'](?:\.\.\/|\/)?(?:home\/[^"\']*|Descargas\/[^"\']*|.*\/)?index\.html["\']/', 'href="' . $config['url_indice'] . '"', $contenido_body);
    
    // PASO 3: Limpieza MÍNIMA (solo textos obvios de WordPress)
    $contenido_body = str_replace('Sitio generado a partir de WordPress.', '', $contenido_body);
    $contenido_body = str_replace('Estilo minimalista.', '', $contenido_body);
    
    // Barra de navegación (siempre apunta a indice.html)
    $navegacion = '<div style="background:#000; border:2px solid #f0f; padding:10px; margin-bottom:20px; color:#0f0;">';
    $navegacion .= '<span style="color:#ff0;">⚡ ' . $config['titulo_sitio'] . '</span> | ';
    $navegacion .= '<a href="' . $config['url_indice'] . '" style="color:#0ff;">🏠 INICIO</a> | ';
    $navegacion .= '<a href="' . $config['url_indice'] . '#paginas" style="color:#0ff;">📄 PÁGINAS</a> | ';
    $navegacion .= '<a href="' . $config['url_indice'] . '#articulos" style="color:#0ff;">📰 ARTÍCULOS</a>';
    
    if (es_pagina_estatica($archivo_actual, $titulo, $config)) {
        $navegacion .= ' | <span style="color:#ff0;">📌 PÁGINA ESPECIAL</span>';
    }
    
    $navegacion .= '</div>';
    
    // PASO 4: Verificar que NO estamos perdiendo contenido
    if (strlen($contenido_body) < 100) {
        log_error("¡ALERTA! Contenido muy pequeño en $archivo_actual");
        // Si el contenido es muy pequeño, usamos el original completo
        $contenido_body = $contenido_original;
    }
    
    // PASO 5: Construir HTML final
    $nuevo_html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>' . htmlspecialchars($titulo) . ' - ARCHIVO</title>
    <base href="./">
    <style>
        body {
            background: #000;
            color: #0f0;
            font-family: "Courier New", monospace;
            margin: 20px;
            line-height: 1.6;
        }
        a { 
            color: #f0f; 
            text-decoration: none;
            border-bottom: 1px dashed #f0f;
        }
        a:hover { 
            background: #0f0; 
            color: #000;
        }
        .navegacion {
            background: #111;
            border: 2px solid #f0f;
            padding: 10px;
            margin-bottom: 20px;
        }
        hr {
            border: none;
            border-top: 2px dashed #f0f;
        }
        .contenido {
            padding: 20px;
            min-height: 200px;
            background: #0a0a0a;
        }
        .pie-pagina {
            text-align: center;
            margin-top: 30px;
            padding: 15px;
            border-top: 1px solid #0f0;
        }
        .blink {
            animation: parpadeo 1s infinite;
        }
        @keyframes parpadeo {
            50% { opacity: 0; }
        }
    </style>
</head>
<body>
    ' . $navegacion . '
    <hr>
    <div class="contenido">
        ' . $contenido_body . '
    </div>
    <hr>
    <div class="pie-pagina">
        <a href="' . $config['url_indice'] . '">⬅️ VOLVER AL ÍNDICE</a> | 
        <a href="' . $config['url_indice'] . '">🏠 PÁGINA PRINCIPAL</a>
        <div style="color:#666; margin-top:10px;">
            <span class="blink">🟢</span> ' . date('d/m/Y') . '
        </div>
    </div>
</body>
</html>';
    
    return $nuevo_html;
}

// PROCESAR FORMULARIO (SIN CAMBIOS)
$mensaje = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['archivo_zip'])) {
    log_info("=== INICIO DE PROCESAMIENTO ===");
    $archivo = $_FILES['archivo_zip'];
    
    if ($archivo['error'] === UPLOAD_ERR_OK && $archivo['size'] <= $max_tamano) {
        $session_id = uniqid('org_');
        $dir_sesion = $directorio_trabajo . '/' . $session_id;
        $dir_original = $dir_sesion . '/original';
        $dir_organizado = $dir_sesion . '/organizado';
        
        mkdir($dir_sesion, 0755, true);
        mkdir($dir_original, 0755, true);
        mkdir($dir_organizado, 0755, true);
        
        $zip_path = $dir_original . '/subido.zip';
        move_uploaded_file($archivo['tmp_name'], $zip_path);
        
        $zip = new ZipArchive;
        if ($zip->open($zip_path) === TRUE) {
            $zip->extractTo($dir_original);
            $zip->close();
            
            // Buscar HTMLs
            $archivos_html = [];
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir_original));
            foreach ($iterator as $archivo) {
                if ($archivo->isFile()) {
                    $ext = strtolower($archivo->getExtension());
                    if ($ext === 'html' || $ext === 'htm') {
                        $archivos_html[] = $archivo->getPathname();
                    }
                }
            }
            
            if (!empty($archivos_html)) {
                $archivos_procesados = [];
                $paginas = [];
                $articulos = [];
                $errores = 0;
                
                log_info("Total archivos HTML: " . count($archivos_html));
                
                foreach ($archivos_html as $ruta_original) {
                    try {
                        $contenido = file_get_contents($ruta_original);
                        if ($contenido === false) {
                            throw new Exception("No se pudo leer");
                        }
                        
                        $nombre_original = basename($ruta_original);
                        $titulo = extraer_titulo($contenido);
                        $fecha = extraer_fecha_real($contenido, $ruta_original);
                        $es_pagina = es_pagina_estatica($nombre_original, $titulo, $config);
                        
                        log_info("Procesando: $nombre_original -> " . ($es_pagina ? 'PÁGINA' : 'ARTÍCULO'));
                        
                        // Generar nombre
                        $nombre_base = $es_pagina ? pathinfo($nombre_original, PATHINFO_FILENAME) : limpiar_nombre($titulo);
                        $nombre_archivo = $nombre_base . '.html';
                        
                        // Evitar duplicados
                        $counter = 1;
                        while (in_array($nombre_archivo, $archivos_procesados)) {
                            $nombre_archivo = $nombre_base . '-' . $counter . '.html';
                            $counter++;
                        }
                        
                        // Procesar y guardar
                        $nuevo_contenido = procesar_html($contenido, $nombre_archivo, $titulo, $config);
                        
                        // VERIFICACIÓN CRÍTICA
                        if (strlen($nuevo_contenido) < 500) {
                            log_error("⚠️ POSIBLE CONTENIDO VACÍO en $nombre_archivo");
                        } else {
                            log_info("✅ Contenido generado correctamente: " . strlen($nuevo_contenido) . " bytes");
                        }
                        
                        file_put_contents($dir_organizado . '/' . $nombre_archivo, $nuevo_contenido);
                        $archivos_procesados[] = $nombre_archivo;
                        
                        $info = [
                            'archivo' => $nombre_archivo,
                            'titulo' => $titulo,
                            'fecha' => $fecha
                        ];
                        
                        if ($es_pagina) {
                            $paginas[] = $info;
                        } else {
                            $articulos[] = $info;
                        }
                        
                    } catch (Exception $e) {
                        $errores++;
                        log_error($e->getMessage(), $ruta_original);
                        continue;
                    }
                }
                
                log_info("RESUMEN: Páginas=" . count($paginas) . " Artículos=" . count($articulos) . " Errores=$errores");
                
                // Generar índice (AHORA CON BUSCADOR)
                $indice_html = generar_indice($paginas, $articulos, $dir_organizado, $config);
                file_put_contents($dir_organizado . '/indice.html', $indice_html);
                
                // Index.html
                $index_html = '<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="refresh" content="0; url=indice.html">
    <title>' . $config['titulo_sitio'] . '</title>
    <style>
        body { background: #000; color: #0f0; font-family: monospace; text-align: center; padding: 50px; }
    </style>
</head>
<body>
    <h1>📼 CARGANDO ARCHIVO...</h1>
    <p><a href="indice.html">Ir al índice</a></p>
</body>
</html>';
                file_put_contents($dir_organizado . '/index.html', $index_html);
                
                // Crear ZIP final
                $zip_final = new ZipArchive();
                $zip_final_path = $directorio_trabajo . '/' . $session_id . '_organizado.zip';
                
                if ($zip_final->open($zip_final_path, ZipArchive::CREATE) === TRUE) {
                    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir_organizado));
                    foreach ($files as $file) {
                        if (!$file->isDir()) {
                            $relativePath = substr($file->getPathname(), strlen($dir_organizado) + 1);
                            $zip_final->addFile($file->getPathname(), $relativePath);
                        }
                    }
                    $zip_final->close();
                    
                    log_info("ZIP generado: $zip_final_path");
                    
                    header('Content-Type: application/zip');
                    header('Content-Disposition: attachment; filename="el-cuarto-de-gatooscuro-organizado.zip"');
                    header('Content-Length: ' . filesize($zip_final_path));
                    readfile($zip_final_path);
                    exit;
                }
            } else {
                $mensaje = '<div style="color:#f00;">❌ No hay archivos HTML</div>';
            }
        } else {
            $mensaje = '<div style="color:#f00;">❌ Error al abrir ZIP</div>';
        }
    } else {
        $mensaje = '<div style="color:#f00;">❌ Error en el archivo</div>';
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>ORGANIZADOR V6.0 - CON BUSCADOR</title>
    <style>
        body {
            background: #000;
            color: #0f0;
            font-family: 'Courier New', monospace;
            margin: 40px;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            border: 4px double #f0f;
            padding: 30px;
            background: #0a0a0a;
        }
        h1 {
            color: #ff0;
            text-align: center;
            font-size: 2.2em;
            text-shadow: 3px 3px 0 #f0f;
        }
        .blink {
            animation: parpadeo 1s infinite;
        }
        @keyframes parpadeo {
            50% { opacity: 0; }
        }
        .feature-list {
            list-style: none;
            padding: 0;
        }
        .feature-list li {
            margin: 10px 0;
            color: #0ff;
        }
        .feature-list li:before {
            content: "✅ ";
            color: #0f0;
        }
        input, button {
            background: #000;
            color: #0f0;
            border: 2px solid #f0f;
            padding: 12px;
            font-family: monospace;
            font-size: 1em;
            width: 100%;
            margin: 10px 0;
        }
        button {
            background: #f0f;
            color: #000;
            font-weight: bold;
            cursor: pointer;
        }
        button:hover {
            background: #0f0;
        }
        .paginas-lista {
            background: #111;
            padding: 10px;
            border-left: 3px solid #f0f;
            margin: 10px 0;
            font-size: 0.9em;
        }
        .error-log-link {
            text-align: right;
            font-size: 0.8em;
            margin-top: 10px;
        }
        .error-log-link a {
            color: #666;
            text-decoration: none;
        }
        .error-log-link a:hover {
            color: #f0f;
        }
        .importante {
            color: #ff0;
            font-weight: bold;
            border: 2px solid #f0f;
            padding: 10px;
            margin: 10px 0;
            text-align: center;
            background: #1a0a1a;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📼 ORGANIZADOR V6.0 <span class="blink">▌</span></h1>
        <div class="importante">
            🔴 NUEVO: BUSCADOR Y FILTRO POR AÑO
        </div>
        <hr>
        <ul class="feature-list">
            <li>✅ BUSCADOR: Filtra artículos por título en tiempo real</li>
            <li>✅ FILTRO: Selecciona año específico (desplegable automático)</li>
            <li>✅ CONTADOR: Muestra resultados visibles</li>
            <li>✅ 100% compatible con versión anterior</li>
        </ul>
        <div class="paginas-lista">
            <strong style="color:#ff0;">📄 PÁGINAS DETECTADAS:</strong><br>
            • sobre-mi.html<br>
            • alternativas.html<br>
            • fedisucks.html<br>
            • fediamor.html<br>
            • blogs.html<br>
            • audios.html<br>
            • art-culos-ndice-el-cuarto-de-gatooscuro.html
        </div>
        <hr>
        <?php echo $mensaje; ?>
        <form method="POST" enctype="multipart/form-data">
            <input type="file" name="archivo_zip" accept=".zip" required>
            <button type="submit">🚀 ORGANIZAR AHORA</button>
        </form>
        <hr>
        <div style="text-align:center; color:#666;">
            <span class="blink">🟢</span> VERSIÓN 6.0 - CON BUSCADOR INTEGRADO
        </div>
        <div class="error-log-link">
            <a href="error_log.txt" target="_blank">📋 Ver logs de error</a> | 
            <a href="proceso_log.txt" target="_blank">📊 Ver registro de proceso</a>
        </div>
    </div>
</body>
</html>
