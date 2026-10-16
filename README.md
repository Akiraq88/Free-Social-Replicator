# Free Social Replicator (FSR)

![License: GPL v2](https://img.shields.io/badge/License-GPLv2-blue.svg)
![WordPress Version](https://img.shields.io/badge/WordPress-5.8%2B-21759B.svg)
![PHP Version](https://img.shields.io/badge/PHP-7.4%2B-777BB4.svg)
![Meta Graph API](https://img.shields.io/badge/Meta%20Graph%20API-v25.0-0081FB.svg)

Plugin open-source para WordPress que replica automáticamente tus notas y entradas en **Facebook Pages** e **Instagram Professional** utilizando la API oficial **Meta Graph**, sin intermediarios (como Zapier, Make o Buffer), sin suscripciones y sin límites artificiales.

---

## 🚀 Características Principales

- **Conexión Directa con Meta**: Conecta directamente tu sitio WordPress con Facebook e Instagram mediante la API oficial de Meta Graph (`v25.0`).
- **Generación Automática de Placas / Imágenes**:
  - Dibuja en el servidor imágenes profesionales de 1080×1350 (formato vertical 4:5 óptimo para Instagram/Facebook) o 1080×1080 (cuadradas).
  - Incluye la imagen destacada recortada inteligentemente, degradado para contraste de lectura, logo del sitio, categoría temática con barra de acento y tipografía cuidada.
- **Plantillas Dinámicas y Variables**:
  - Personalizables con etiquetas: `{titulo}`, `{resumen}`, `{enlace}`, `{categoria}`, `{hashtags}`.
- **Control por Entrada (Metabox)**:
  - Opción de omitir publicación en entradas específicas.
  - Botón para publicar inmediatamente o reintentar.
  - Vista previa en tiempo real de la imagen antes de publicar.
  - Opción de reiniciar el estado para volver a publicar si se corrigió una nota.
- **Modo Prueba (Dry Run)**:
  - Genera la imagen y simula la publicación en el registro de actividad sin emitir contenido a tus redes sociales reales.
- **Registro de Auditoría (Log)**:
  - Guarda los últimos 60 eventos con detalle de errores, códigos de respuesta de Meta y enlaces a las notas.
- **Privacidad y Seguridad**:
  - Los tokens de acceso nunca se exponen en texto plano en el registro ni en el código fuente.

---

## 📁 Estructura del Plugin

```text
free-social-replicator/
├── assets/
│   └── fonts/
│       ├── Poppins-Bold.ttf      # Tipografía incluida para títulos
│       ├── Poppins-Medium.ttf    # Tipografía incluida para etiquetas y pie
│       └── LICENSE.txt           # Licencias de tipografías abiertas
├── includes/
│   └── image.php                 # Motor GD para la composición de la placa
├── fsr.php                       # Archivo principal del plugin WordPress
├── uninstall.php                 # Limpieza completa al desinstalar
├── readme.txt                    # Metadatos estándar de WordPress
└── README.md                     # Documentación completa y guía de configuración
```

---

## 🛠️ Requisitos del Servidor

- **PHP**: 7.4 o superior (compatible con PHP 8.0, 8.1, 8.2 y 8.3).
- **Extensiones PHP**:
  - `GD` con soporte para **FreeType** (`imagettftext`). *En Hostinger u otros cPanel/hPanel suele venir activado por defecto.*
  - `cURL` y `OpenSSL`.
- **WordPress**: 5.8 o superior.
- **WP-Cron** funcionando (o cron de sistema en el panel de hosting).

---

## 🔑 Guía Paso a Paso: Configuración de Meta Graph API

Para conectar el plugin con Facebook e Instagram necesitas crear una App en Meta for Developers y obtener un Token de Acceso de Página.

### Paso 1: Requisitos previos en Meta
1. Tener una **Página de Facebook** (Fan Page) de la cual seas Administrador.
2. Tener una cuenta de **Instagram Profesional** (Empresa o Creador) vinculada a esa Página de Facebook (se vincula desde *Configuración de la Página > Cuentas vinculadas > Instagram*).

### Paso 2: Crear la App en Meta for Developers
1. Ingresa a [developers.facebook.com](https://developers.facebook.com/) con tu cuenta de Facebook.
2. Haz clic en **Mis Apps** > **Crear app**.
3. Selecciona el tipo de caso de uso: **Otro** > Siguiente > **Negocios (Business)**.
4. Asígnale un nombre a tu app (ejemplo: `Mi Sitio Web Replicator`) y confirma tu correo de contacto.

### Paso 3: Agregar permisos a la App
En el panel de la App, ve a **Permisos y características** (o Graph API Explorer) y asegúrate de que la App cuente con:
- `pages_show_list`
- `pages_read_engagement`
- `pages_manage_posts`
- `instagram_basic`
- `instagram_content_publish`

### Paso 4: Generar un Token de Página de Larga Duración
1. Ve a la herramienta **Graph API Explorer** ([developers.facebook.com/tools/explorer](https://developers.facebook.com/tools/explorer/)).
2. En el desplegable **App de Meta**, elige tu app recién creada.
3. En **Usuario o página**, selecciona tu **Página de Facebook** (al seleccionarla se solicitarán los permisos; concédelos todos).
4. Copia el **Token de acceso** que aparece.
5. Para que el token no expire cada 2 o 3 horas, conviértelo en un token de larga duración (60 días) o token permanente de sistema desde el **Administrador comercial (Meta Business Suite > Usuarios del sistema)**.

### Paso 5: Obtener los IDs
- **ID de la Página de Facebook**: Ingresa a tu página de Facebook, ve a la sección "Información" o mira el número en la barra de direcciones o en el Explorador de Graph API.
- **ID de Instagram**: Si ya colocaste el ID de la Página de Facebook y el Token en el plugin, ¡no necesitas buscar el ID de Instagram manualmente! Al presionar el botón **"Probar conexión con Meta"** dentro del plugin, detectará automáticamente la cuenta de Instagram vinculada.

---

## ⚙️ Configuración en WordPress

1. Instala y activa el plugin en tu WordPress.
2. Dirígete a **Ajustes > Social Replicator** (`options-general.php?page=fsr`).
3. Completa los campos:
   - **Modo prueba**: Déjalo marcado inicialmente para verificar la generación de imágenes sin publicar en redes reales.
   - **ID de la página de Facebook**: Pega el ID numérico de tu página.
   - **Token de acceso de la página**: Pega tu token de acceso de Meta.
   - **Versión de la API**: Por defecto `v25.0` (o la versión vigente en Meta).
   - **Esperar antes de publicar**: Minutos que el plugin espera antes de enviar la publicación (recomendado: 3 a 5 minutos, para permitir corregir erratas o imágenes tras pulsar publicar).
   - **Formato de imagen**: Elige entre *Vertical 4:5 (1080×1350)* (ideal para feed de Instagram y móvil) o *Cuadrada (1080×1080)*.
   - **Plantillas**: Configura los textos para Facebook e Instagram a tu gusto utilizando variables.
4. Haz clic en **Guardar ajustes**.
5. Presiona **Probar conexión con Meta** para verificar que los datos sean válidos.
6. Cuando estés conforme, desmarca la casilla **Modo prueba** y guarda los ajustes.

---

## ⏱️ Configuración Recomendada de Cron (Hostinger / cPanel)

WordPress ejecuta tareas programadas (como el retraso de publicación del plugin) mediante `WP-Cron`. Si tu sitio tiene poco tráfico durante ciertas horas, el cron puede demorarse hasta que alguien visite una página.

Para garantizar publicaciones exactas:
1. En tu archivo `wp-config.php`, agrega:
   ```php
   define( 'DISABLE_WP_CRON', true );
   ```
2. En tu panel de hosting (ej. **Hostinger hPanel > Avanzado > Tareas Cron**):
   - **Tipo**: PHP o Wget / Curl.
   - **Comando**:
     ```bash
     wget -q -O - https://tudominio.com/wp-cron.php?doing_wp_cron >/dev/null 2>&1
     ```
   - **Frecuencia**: Cada 5 o 10 minutos (`*/5 * * * *`).

---

## ❓ Preguntas Frecuentes y Solución de Problemas

#### 1. ¿Por qué las notas no se publican de inmediato?
El plugin incorpora un retraso configurable (por defecto 3 minutos) para que, si publicas una nota y notas un error en el título o contenido, puedas corregirlo antes de que salga en las redes sociales. Puedes ajustar este tiempo a 1 minuto en los ajustes.

#### 2. Error: "Instagram no aceptó la imagen"
Instagram requiere que la imagen sea accesible públicamente por HTTP/HTTPS para procesarla en sus servidores de Meta. Verifica que tu sitio no esté en modo mantenimiento ni bloqueando bots de Facebook/Meta mediante reglas de Cloudflare o plugins de seguridad.

#### 3. Error HTTP 190 de Meta
Indica que el token de acceso expiró o fue revocado. Genera un nuevo token de página de larga duración y guárdalo en **Ajustes > Social Replicator**.

#### 4. ¿Cómo cambio el logo que aparece en la imagen?
El generador utiliza automáticamente el logotipo cargado en tu tema de WordPress (*Apariencia > Personalizar > Identidad del sitio > Logotipo*) o, en su defecto, el ícono del sitio (Favicon).

---

## 📄 Licencia

Este proyecto está bajo la licencia **GPLv2 o posterior**. Las fuentes Poppins y Josefin Sans están licenciadas bajo **SIL Open Font License 1.1**.
