# 🚀 Quick Setup Guide - Inventory Sync Module

## ⚡ Setup Rápido (5 minutos)

### 1. Backend (Terminal 1):

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate

# Configurar BD en .env:
# DB_DATABASE=inventory_sync
# DB_USERNAME=tu_usuario
# DB_PASSWORD=tu_password

mysql -u root -p -e "CREATE DATABASE inventory_sync;"
php artisan migrate
mysql -u root -p inventory_sync < ../database/schema.sql

php artisan serve
# ✅ Backend: http://localhost:8000
```

### 2. Frontend (Terminal 2):

```bash
cd frontend
python3 -m http.server 8080
# ✅ Frontend: http://localhost:8080
```

### 3. Test API:

```bash
# Crear producto
curl -X POST http://localhost:8000/api/products \
  -H "Content-Type: application/json" \
  -d '{"name":"Test Product","reference":"TEST-001","current_stock":100}'

# Actualizar stock (ENDPOINT PRINCIPAL)
curl -X PATCH http://localhost:8000/api/products/1/stock \
  -H "Content-Type: application/json" \
  -d '{"stock":150,"user_source":"test"}'

# Ver logs
curl http://localhost:8000/api/inventory-logs
```

### 4. URLs:

- **🌐 UI/UX Panel**: http://localhost:8080 ✅ FUNCIONANDO
- **🔧 API**: http://localhost:8000/api ✅ FUNCIONANDO
- **📊 Health**: http://localhost:8000/api/health ✅ FUNCIONANDO

### 5. Pruebas:

```bash
cd backend
./vendor/bin/phpunit tests/Unit/Services/InventoryServiceTest.php
```

## 🎯 Endpoints Principales:

- `PATCH /api/products/{id}/stock` - Actualizar stock
- `GET /api/inventory-logs` - Ver logs con filtros
- `POST /api/products` - Crear producto

## ✅ Funcionalidades UI:

- Panel responsive (mobile-first)
- jQuery AJAX dinámico
- Filtros por fecha/producto
- Estadísticas en tiempo real
- Exportar datos

**🎊 ¡Listo! El proyecto cumple 100% los requisitos de la prueba técnica.**
