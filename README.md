# 📼 Organizador de Blogs Estáticos v6.0

## 🎯 ¿Qué hace?

Este organizador automático toma un ZIP con archivos HTML y los organiza inteligentemente:

- ✅ **Clasifica automáticamente**: Separa páginas estáticas (Sobre mí, Blogs, Audios) de artículos
- ✅ **Extrae fechas reales**: Detecta fechas de metadatos, etiquetas `<time>` o texto
- ✅ **Ordena artículos**: Del más reciente al más antiguo
- ✅ **Corrige enlaces**: Elimina rutas rotas (file:///home/...) y redirige al índice
- ✅ **Genera índice automático**: Crea index.html con estilo retro y navegación
- ✅ **Buscador integrado**: Filtra artículos por título y año en tiempo real

## 🚀 Cómo usarlo

1. **Sube tu ZIP** con todos los archivos HTML
2. **El sistema procesa** y clasifica todo
3. **Descarga el ZIP organizado** con índice, páginas y artículos ordenados

## 📋 Requisitos

- PHP 7.4 o superior
- Extensión ZipArchive habilitada

## ⚙️ Configuración

Puedes modificar las primeras líneas del archivo:

```php
$config = [
    'paginas_estaticas' => [...] // Tus páginas especiales
    'exclusiones_articulos' => [...] // Palabras que identifican artículos
    'titulo_sitio' => 'EL CUARTO DE GATOOSCURO',
    'url_indice' => 'indice.html'
];
