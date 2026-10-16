=== Free Social Replicator ===
Contributors: freesocialreplicator
Tags: social media, auto post, facebook, instagram, meta graph api, social auto poster
Requires at least: 5.8
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Publica automáticamente cada nueva entrada en Facebook e Instagram con una imagen con título destacado, resumen y enlace, usando la API oficial de Meta Graph sin intermediarios.

== Description ==

**Free Social Replicator (FSR)** es un plugin liviano, robusto y 100% independiente para WordPress que replica tus publicaciones automáticamente en tus páginas de Facebook y cuentas profesionales de Instagram al instante de ser publicadas.

### Características principales:
* **Conexión Directa**: Utiliza la API oficial de Meta Graph sin pasar por servicios de terceros como Zapier, Buffer o Make, evitando costos mensuales y límites artificiales.
* **Generación Automática de Imágenes**: Genera en el servidor imágenes optimizadas (1080×1350 vertical o 1080×1080 cuadrada) con la imagen destacada de fondo, degradados para legibilidad, el logo de tu sitio, la categoría y el título estilizado.
* **Plantillas Personalizables**: Personaliza el texto para Facebook e Instagram con etiquetas dinámicas `{titulo}`, `{resumen}`, `{enlace}`, `{categoria}`, `{hashtags}`.
* **Control Total por Entrada**: Caja en el editor de cada nota para omitir la publicación, forzar el envío manual, ver la vista previa de la imagen generada o reiniciar el estado.
* **Modo Prueba (Dry Run)**: Permite probar la generación de imágenes y simular el proceso antes de publicar en tus redes reales.
* **Registro de Actividad (Log)**: Panel de auditoría con los últimos eventos, estados y respuestas detalladas de la API.

== Installation ==

1. Sube la carpeta `free-social-replicator` al directorio `/wp-content/plugins/` o sube el archivo ZIP desde **Plugins > Añadir nuevo > Subir plugin**.
2. Activa el plugin a través del menú **Plugins** en WordPress.
3. Ve a **Ajustes > Social Replicator** para configurar el token de acceso de Meta y los IDs de página e Instagram.
4. Realiza una prueba con el botón **Probar conexión con Meta**.

== Frequently Asked Questions ==

= ¿Qué requisitos necesita el servidor? =
PHP 7.4 o superior con la extensión GD y soporte FreeType activados.

= ¿Cómo obtengo el token de Meta Graph API? =
Crea una App en developers.facebook.com de tipo Empresa ("Business"), añade los productos Facebook Pages e Instagram Graph API, y obtén un token de acceso de página con permisos `pages_manage_posts`, `pages_read_engagement`, `instagram_basic`, `instagram_content_publish`.

== Changelog ==

= 1.0.0 =
* Versión inicial oficial de Free Social Replicator.
