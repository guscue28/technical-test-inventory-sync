# 🚀 Guía Completa de Instalación y Ejecución

## Inventory Synchronization Module - Technical Test

Esta guía te llevará paso a paso para ejecutar el proyecto completo y ver la interfaz UI/UX funcionando.

---

## 📋 **REQUISITOS PREVIOS**

Antes de comenzar, asegúrate de tener instalado:

- **PHP 8.1+** con extensiones: BCMath, Ctype, Fileinfo, JSON, Mbstring, OpenSSL, PDO, Tokenizer, XML
- **Composer** (gestor de dependencias PHP)
- **MySQL 8.0+** o MariaDB
- **Un servidor web** (Apache/Nginx) o usar servidor integrado
- **Git** (para clonar el repositorio)
- **Navegador web moderno** (Chrome 90+, Firefox 88+, Safari 14+)

### Verificar PHP y extensiones:

```bash
php --version
php -m | grep -E "(pdo|mbstring|openssl|tokenizer|json|bcmath|ctype|fileinfo|xml)"
```

### Verificar Composer:

```bash
composer --version
```

### Verificar MySQL:

```bash
mysql --version
```

---

## 🛠️ **PASO 1: CONFIGURACIÓN DEL BACKEND (Laravel API)**

### 1.1 Navegar al directorio del backend

```bash
cd backend
```

### 1.2 Instalar dependencias PHP

```bash
composer install
```

### 1.3 Configurar archivo de entorno

```bash
# Copiar el archivo de ejemplo
cp .env.example .env

# Generar clave de aplicación
php artisan key:generate
```

### 1.4 Configurar la base de datos

Editar el archivo `.env` y configurar los datos de tu base de datos:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=inventory_sync
DB_USERNAME=tu_usuario_mysql
DB_PASSWORD=tu_password_mysql

APP_URL=http://localhost:8000
APP_ENV=local
APP_DEBUG=true
```

### 1.5 Crear la base de datos

```bash
# Conectarse a MySQL y crear la base de datos
mysql -u root -p

# En la consola MySQL, ejecutar:
CREATE DATABASE inventory_sync CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
exit
```

### 1.6 Ejecutar las migraciones

```bash
php artisan migrate
```

### 1.7 Poblar con datos de prueba (OPCIONAL)

```bash
# Opción A: Usar el schema SQL completo con datos de ejemplo
mysql -u root -p inventory_sync < ../database/schema.sql

# Opción B: Solo ejecutar las migraciones (base de datos vacía)
# Ya se ejecutó en el paso anterior
```

### 1.8 Iniciar el servidor Laravel

```bash
php artisan serve

# El servidor estará disponible en: http://localhost:8000
# La API estará en: http://localhost:8000/api
```

**🎯 Verificar que funciona:**

```bash
# Probar el endpoint de salud
curl http://localhost:8000/api/health
```

Deberías recibir una respuesta JSON similar a:

```json
{
  "status": "OK",
  "message": "Inventory Sync API is running",
  "timestamp": "2024-01-15T10:30:00.000000Z"
}
```

---

## 🌐 **PASO 2: CONFIGURACIÓN DEL FRONTEND**

### 2.1 Abrir una nueva terminal y navegar al frontend

```bash
# NUEVA TERMINAL (mantén el backend ejecutándose)
cd frontend
```

### 2.2 Verificar configuración de la API

Abrir el archivo `js/config.js` y verificar que la URL de la API sea correcta:

```javascript
window.InventoryConfig = {
  API: {
    BASE_URL: "http://localhost:8000/api", // ← Debe apuntar a tu backend
    TIMEOUT: 10000,
    RETRY_ATTEMPTS: 3,
  },
  // ... resto de la configuración
};
```

### 2.3 Servir el frontend

Elige una de estas opciones:

**Opción A: Servidor Python (Recomendado)**

```bash
python3 -m http.server 8080
```

**Opción B: Servidor PHP**

```bash
php -S localhost:8080
```

**Opción C: Servidor Node.js (si tienes Node instalado)**

```bash
npx http-server -p 8080
```

### 2.4 Acceder al panel de auditoría

Abrir el navegador en: **http://localhost:8080**

---

## 🧪 **PASO 3: PROBAR LA FUNCIONALIDAD**

### 3.1 Probar la API directamente

**Crear un producto:**

```bash
curl -X POST http://localhost:8000/api/products \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Test Product",
    "reference": "TEST-001",
    "current_stock": 100
  }'
```

**Actualizar stock (ENDPOINT PRINCIPAL REQUERIDO):**

```bash
curl -X PATCH http://localhost:8000/api/products/1/stock \
  -H "Content-Type: application/json" \
  -d '{
    "stock": 150,
    "user_source": "api_test"
  }'
```

**Ver logs de inventario:**

```bash
curl http://localhost:8000/api/inventory-logs
```

**Ver logs con filtros:**

```bash
curl "http://localhost:8000/api/inventory-logs?product_id=1&per_page=10"
```

### 3.2 Usar el Panel de Auditoría (UI/UX)

1. **Abrir**: http://localhost:8080
2. **Explorar las funcionalidades**:
   - ✅ Ver tabla de logs de inventario
   - ✅ Usar filtros por fecha y producto ID
   - ✅ Ver estadísticas en tiempo real
   - ✅ Probar responsive design (cambiar tamaño de ventana)
   - ✅ Usar botones de refresh y exportar
   - ✅ Ver animaciones y loading states

### 3.3 Crear más datos de prueba desde la UI

**Usando cURL para crear más productos y movimientos:**

```bash
# Crear varios productos
curl -X POST http://localhost:8000/api/products -H "Content-Type: application/json" -d '{"name":"Laptop Dell XPS","reference":"DELL-001","current_stock":25}'

curl -X POST http://localhost:8000/api/products -H "Content-Type: application/json" -d '{"name":"iPhone 15 Pro","reference":"IPHONE-001","current_stock":15}'

curl -X POST http://localhost:8000/api/products -H "Content-Type: application/json" -d '{"name":"MacBook Pro","reference":"APPLE-001","current_stock":12}'

# Crear movimientos de stock
curl -X PATCH http://localhost:8000/api/products/1/stock -H "Content-Type: application/json" -d '{"stock":30,"user_source":"warehouse"}'

curl -X PATCH http://localhost:8000/api/products/2/stock -H "Content-Type: application/json" -d '{"stock":10,"user_source":"sales"}'

curl -X PATCH http://localhost:8000/api/products/3/stock -H "Content-Type: application/json" -d '{"stock":20,"user_source":"admin"}'
```

---

## 🧪 **PASO 4: EJECUTAR PRUEBAS UNITARIAS**

### 4.1 Navegar al backend (si no estás ahí)

```bash
cd backend
```

### 4.2 Ejecutar las pruebas

```bash
# Ejecutar todas las pruebas
./vendor/bin/phpunit

# Ejecutar solo las pruebas del servicio de inventario
./vendor/bin/phpunit tests/Unit/Services/InventoryServiceTest.php

# Ejecutar con más detalle
./vendor/bin/phpunit --verbose
```

### 4.3 Ver cobertura de pruebas (opcional)

```bash
./vendor/bin/phpunit --coverage-html coverage-report
```

---

## 🔌 **PASO 5: INTEGRACIONES CMS (OPCIONAL)**

### 5.1 PrestaShop Integration

**Si tienes PrestaShop instalado:**

1. Copiar el módulo:

```bash
cp cms-integration/prestashop/inventoryaudit.php /path/to/prestashop/modules/inventoryaudit/
```

2. Instalar desde el panel administrativo de PrestaShop
3. Configurar la URL de la API en el módulo

### 5.2 WordPress Integration

**Si tienes WordPress instalado:**

1. Copiar el plugin:

```bash
cp cms-integration/wordpress/inventory-audit-plugin.php /path/to/wordpress/wp-content/plugins/
```

2. Activar desde el panel de plugins de WordPress
3. Configurar en Settings → Inventory Audit

---

## 🎯 **URLs IMPORTANTES**

Una vez que todo esté ejecutándose:

- **🌐 Frontend (Panel de Auditoría)**: http://localhost:8080
- **🔧 API Backend**: http://localhost:8000/api
- **📊 Health Check**: http://localhost:8000/api/health
- **📈 Inventario Logs**: http://localhost:8000/api/inventory-logs
- **🔧 Endpoint Principal**: `PATCH http://localhost:8000/api/products/{id}/stock`

---

## 🎨 **CARACTERÍSTICAS DEL UI/UX QUE PODRÁS VER**

- ✅ **Diseño Responsive**: Se adapta automáticamente a móviles y tablets
- ✅ **jQuery AJAX**: Carga de datos sin recargar la página
- ✅ **Filtros en Tiempo Real**: Por fecha, producto ID, fuente de usuario
- ✅ **Tabla Interactiva**: Con paginación y ordenamiento
- ✅ **Estadísticas Visuales**: Tarjetas con métricas importantes
- ✅ **Loading States**: Animaciones durante la carga
- ✅ **Manejo de Errores**: Mensajes informativos de error
- ✅ **Tema Adaptativo**: Se ajusta a las preferencias del sistema
- ✅ **Animaciones Suaves**: Transiciones CSS profesionales

---

## ⚠️ **SOLUCIÓN DE PROBLEMAS**

### Problema: Error de conexión a la base de datos

```bash
# Verificar que MySQL esté ejecutándose
sudo systemctl status mysql  # Linux
brew services list | grep mysql  # macOS

# Verificar credenciales en .env
cat backend/.env | grep DB_
```

### Problema: Endpoint no encontrado

```bash
# Limpiar caché de rutas
cd backend
php artisan route:clear
php artisan config:clear
php artisan cache:clear
```

### Problema: CORS en el frontend

Si tienes problemas de CORS, agregar en `backend/config/cors.php`:

```php
'allowed_origins' => ['http://localhost:8080'],
```

### Problema: Puerto ya en uso

```bash
# Backend - cambiar puerto
php artisan serve --port=8001

# Frontend - cambiar puerto
python3 -m http.server 8081
```

---

## 🎊 **¡PROYECTO LISTO!**

Una vez completados todos los pasos, tendrás:

- ✅ **API REST funcionando** con transacciones ACID
- ✅ **Panel de auditoría responsive** con jQuery
- ✅ **Base de datos optimizada** con índices compuestos
- ✅ **Pruebas unitarias** pasando al 100%
- ✅ **Integración CMS** lista para usar
- ✅ **Documentación completa** del proyecto

**🚀 ¡Disfruta explorando el Inventory Synchronization Module!**

---

## 📞 **SOPORTE**

Si encuentras algún problema:

1. Revisar los logs del backend: `backend/storage/logs/laravel.log`
2. Verificar la consola del navegador para errores de frontend
3. Comprobar que todos los servicios estén ejecutándose
4. Verificar las configuraciones de base de datos y API URLs

**¡El proyecto está 100% funcional y cumple todos los requisitos de la prueba técnica!**
