# Guía de Contribución

¡Gracias por tu interés en contribuir a **Free Social Replicator**!

## ¿Cómo contribuir?

### Reportar un Error (Bug)
1. Revisa los Issues abiertos y cerrados para asegurarte de que el problema no haya sido reportado previamente.
2. Si es un nuevo error, abre un Issue describiendo:
   - Versión de WordPress y PHP.
   - Entorno de hosting (Hostinger, Apache, LiteSpeed, Nginx, etc.).
   - Pasos exactos para reproducir el fallo.
   - Mensaje de error registrado en `Ajustes > Social Replicator > Actividad reciente` (¡asegúrate de que no contenga tokens o información sensible!).

### Proponer una Mejora o Feature
Abre un Issue con la etiqueta `enhancement` detallando qué funcionalidad propones y cuál es el caso de uso.

### Enviar un Pull Request (PR)
1. Haz un Fork del repositorio.
2. Crea una rama para tu cambio: `git checkout -b feature/nueva-mejora` o `git checkout -b fix/correccion-error`.
3. Sigue los estándares de código de WordPress (WordPress Coding Standards).
4. Asegúrate de sanitizar las entradas y escapar todas las salidas.
5. Envía tu Pull Request con una descripción detallada de los cambios.
