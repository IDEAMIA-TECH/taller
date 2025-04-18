# Sistema de Gestión para Taller Mecánico

Sistema web multi-taller para la administración de talleres mecánicos, con funcionalidades para manejar clientes, vehículos, servicios, órdenes de servicio, recordatorios de mantenimiento, facturación 4.0 y generación de reportes.

## Requisitos del Sistema

- PHP 8.0 o superior
- MySQL 8.0 o superior
- Servidor web (Apache/Nginx)
- Composer (para gestión de dependencias)

## Instalación

1. Clonar el repositorio:
```bash
git clone [URL_DEL_REPOSITORIO]
cd taller
```

2. Configurar el servidor web:
   - Configurar el documento raíz (DocumentRoot) en la carpeta del proyecto
   - Asegurarse de que mod_rewrite esté habilitado (para Apache)
   - Configurar las reglas de reescritura (ver .htaccess)

3. Configurar la base de datos:
   - Crear una nueva base de datos MySQL
   - Importar el archivo `database.sql` que contiene la estructura inicial

4. Configurar el archivo de configuración:
   - Copiar `includes/config.example.php` a `includes/config.php`
   - Modificar las constantes de configuración según tu entorno

5. Configurar permisos:
```bash
chmod -R 755 .
chmod -R 777 uploads/ # si existe esta carpeta
```

## Estructura del Proyecto

```
/
├── assets/          # Archivos estáticos (CSS, JS, imágenes)
├── includes/        # Archivos PHP incluidos (header, footer, config)
├── modules/         # Módulos de la aplicación
├── templates/       # Plantillas principales
└── uploads/         # Archivos subidos por usuarios
```

## Módulos Principales

1. **Autenticación**
   - Login/Logout
   - Gestión de usuarios
   - Control de acceso

2. **Gestión de Talleres**
   - Alta de talleres
   - Configuración
   - Suscripciones

3. **Gestión de Clientes**
   - Registro de clientes
   - Historial de servicios
   - Vehículos asociados

4. **Órdenes de Servicio**
   - Creación y seguimiento
   - Asignación de mecánicos
   - Facturación

5. **Reportes**
   - Estadísticas
   - Facturación
   - Inventario

## Configuración del Entorno de Desarrollo

1. Configurar un entorno virtual (opcional):
```bash
php -S localhost:8000
```

2. Configurar el archivo hosts (opcional):
```
127.0.0.1 taller.local
```

3. Configurar el entorno de desarrollo en config.php:
```php
define('ENVIRONMENT', 'development');
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

## Seguridad

- Todas las contraseñas se almacenan con hash
- Protección contra CSRF
- Validación de entrada
- Sanitización de salida
- Control de acceso basado en roles

## Contribución

1. Fork el proyecto
2. Crea una rama para tu feature (`git checkout -b feature/AmazingFeature`)
3. Commit tus cambios (`git commit -m 'Add some AmazingFeature'`)
4. Push a la rama (`git push origin feature/AmazingFeature`)
5. Abre un Pull Request

## Licencia

Este proyecto está bajo la Licencia MIT. Ver el archivo `LICENSE` para más detalles.

## Soporte

Para soporte técnico, contactar a [CORREO_DE_SOPORTE]

## Créditos

Desarrollado por [NOMBRE_DEL_EQUIPO] 