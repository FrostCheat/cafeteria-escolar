# 🍽️ Cafetería Escolar Digital

> Sistema web full-stack para la gestión de pedidos de la cafetería del Colegio Santa Juana de Lestonnac. Los estudiantes ordenan desde su celular, reciben un código QR único y retiran sin hacer fila. El administrador gestiona todo en tiempo real desde un panel web.

<div align="center">

![PHP](https://img.shields.io/badge/PHP-8.1-777BB4?style=flat-square&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-8.0-4479A1?style=flat-square&logo=mysql&logoColor=white)
![Apache](https://img.shields.io/badge/Apache-2.4-D22128?style=flat-square&logo=apache&logoColor=white)
![Docker](https://img.shields.io/badge/Docker-Ready-2496ED?style=flat-square&logo=docker&logoColor=white)
![PayPal](https://img.shields.io/badge/PayPal-Integrado-003087?style=flat-square&logo=paypal&logoColor=white)
![SSE](https://img.shields.io/badge/Tiempo_Real-SSE-FF4D1F?style=flat-square)
![License](https://img.shields.io/badge/License-MIT-green?style=flat-square)

</div>

---

## Tabla de Contenidos

- [Descripción General](#descripción-general)
- [Características](#características)
- [Tecnologías](#tecnologías)
- [Arquitectura del Proyecto](#arquitectura-del-proyecto)
- [Estructura de Carpetas](#estructura-de-carpetas)
- [Base de Datos](#base-de-datos)
- [API REST](#api-rest)
- [Flujo de un Pedido](#flujo-de-un-pedido)
- [Sistema de Pagos](#sistema-de-pagos)
- [Sistema de Turnos](#sistema-de-turnos)
- [Tiempo Real con SSE](#tiempo-real-con-sse)
- [Seguridad](#seguridad)
- [Instalación y Configuración](#instalación-y-configuración)
- [Variables de Entorno](#variables-de-entorno)
- [Despliegue con Docker](#despliegue-con-docker)
- [Red Local sin Internet](#red-local-sin-internet)
- [Panel de Administración](#panel-de-administración)
- [Roles de Usuario](#roles-de-usuario)
- [Manejo de Errores](#manejo-de-errores)
- [Recomendaciones para Producción](#recomendaciones-para-producción)
- [Hoja de Ruta](#hoja-de-ruta)
- [Contribución](#contribución)
- [Licencia](#licencia)

---

## Descripción General

La **Cafetería Escolar Digital** reemplaza el proceso manual de atención en cafeterías escolares por un flujo 100% digital. Fue construida específicamente para el Colegio Santa Juana de Lestonnac, aunque su arquitectura es fácilmente adaptable a cualquier institución.

El sistema opera en dos modos complementarios:

- **Online**: desplegado en un servidor web con acceso a internet.
- **Red local**: funciona sin internet sobre una red Wi-Fi interna del colegio (ver [Red Local sin Internet](#red-local-sin-internet)).

**Flujo principal:**

```
Estudiante se registra → Selecciona productos → Confirma pedido → Recibe QR + turno
→ Espera notificación en tiempo real → Presenta QR en ventanilla → Paga y retira
```

---

## Características

### Para Estudiantes

| Funcionalidad | Descripción |
|---|---|
| Registro institucional | Solo acepta correos `@santajuanalestonnac.edu.co` |
| Catálogo en tiempo real | Productos con fotos, precios, stock y categorías |
| Carrito inteligente | Validación de stock antes de confirmar |
| Código QR único | Generado en el cliente con token secreto de 48 caracteres |
| Sistema de turnos | Ve su número en pantalla con alerta `🎉 ¡Es tu turno!` |
| Historial de pedidos | Con estados y posibilidad de reabrir cualquier QR anterior |
| Múltiples métodos de pago | Efectivo en ventanilla o PayPal (tarjeta, Apple Pay, Google Pay) |
| Interfaz responsiva | Optimizada para celulares, tablets y computadores |

### Para el Administrador

| Funcionalidad | Descripción |
|---|---|
| Dashboard estadístico | Ingresos (hoy / mes / año / total), pedidos, usuarios, stock |
| Gráficos avanzados | Últimos 6 meses, horas pico, top productos, categorías, grados |
| Insights clave | Mejor/peor día del mes, espera promedio, pedido más caro |
| Exportación de datos | CSV de pedidos por día o mes, PDF de reporte de contabilidad |
| Escáner QR | Cámara integrada con detección automática (html5-qrcode) |
| Sistema de turnos | Activar/desactivar, avanzar, retroceder, saltar a número específico |
| Gestión de productos | CRUD completo con toggle activo/inactivo y eliminación permanente |
| Gestión de usuarios | Ver, bloquear/desbloquear, eliminar, ver historial de pedidos |
| Gestión de pedidos | Filtros por estado, cambio de estado, visualización de QR |
| Alertas de stock | Notificación automática cuando un producto tiene ≤ 3 unidades |
| Actualizaciones SSE | Todo el panel se actualiza sin recargar la página |

---

## Tecnologías

### Frontend

| Capa | Tecnología |
|---|---|
| Lenguaje | HTML5, CSS3, JavaScript ES2020+ (Vanilla — sin frameworks) |
| Tipografía | Google Fonts: Unbounded, Outfit, Plus Jakarta Sans |
| QR Generator | [qrcodejs](https://github.com/davidshimjs/qrcodejs) v1.0.0 (CDN) |
| QR Scanner | [html5-qrcode](https://github.com/mebjas/html5-qrcode) (CDN) |
| Pagos | PayPal JS SDK v2 (cargado dinámicamente) |
| Tiempo real | `EventSource` nativo del navegador (SSE) |

### Backend

| Capa | Tecnología |
|---|---|
| Lenguaje | PHP 8.1 |
| Servidor web | Apache 2.4 con `mod_rewrite` |
| Base de datos | MySQL 5.7+ / 8.0 |
| Autenticación | JWT HS256 (implementación propia, sin dependencias) |
| Hashing | `password_hash()` con `PASSWORD_BCRYPT` |
| Contenedor | Docker (imagen `php:8.1-apache`) |
| Pagos | PayPal REST API v2 (`/v2/checkout/orders`) |

### Infraestructura

```
Cliente (navegador)  ←→  Apache (mod_rewrite)  ←→  PHP Router  ←→  MySQL
                              │
                          .htaccess (reglas de rewrite y seguridad)
```

---

## Arquitectura del Proyecto

El sistema sigue una arquitectura **MVC simplificada** sin frameworks:

- **Router** (`api/index.php`): recibe todas las peticiones `GET /api/*` y las despacha al controlador correspondiente.
- **Controladores** (`api/controllers/`): lógica de negocio separada por dominio.
- **Middleware** (`api/middleware/auth.php`): verificación JWT antes de ejecutar cualquier endpoint protegido.
- **SSE** (`api/sse.php`): conexión persistente que escucha la tabla `events` y retransmite cambios a los clientes.
- **Frontend** (`store/`, `admin/`, `auth/`): SPAs ligeras que consumen la API REST vía `fetch`.

```
┌─────────────────────────────────────────────────────┐
│                    Cliente (browser)                  │
│  store/index.html  │  admin/index.html  │  auth/      │
│       SSE client (sse.js)  │  API client (app.js)    │
└───────────────────────────┬─────────────────────────┘
                            │ HTTP / SSE
┌───────────────────────────▼─────────────────────────┐
│                    Apache 2.4 + PHP 8.1              │
│  .htaccess → api/index.php (router)                  │
│    ├── controllers/auth.php                          │
│    ├── controllers/products.php                      │
│    ├── controllers/cart.php                          │
│    ├── controllers/orders.php                        │
│    ├── controllers/paypal.php                        │
│    ├── controllers/queue.php                         │
│    ├── controllers/stats.php                         │
│    ├── controllers/users.php                         │
│    └── sse.php  (conexión persistente)               │
└───────────────────────────┬─────────────────────────┘
                            │ PDO
┌───────────────────────────▼─────────────────────────┐
│                       MySQL                          │
│  users │ products │ orders │ order_items             │
│  cart_items │ queue_config │ events                  │
└─────────────────────────────────────────────────────┘
```

---

## Estructura de Carpetas

```
cafeteria-escolar/
│
├── index.html                   # Landing page pública
├── .htaccess                    # Router SPA + seguridad Apache
├── .env                         # Variables de entorno (NO subir a Git)
├── .env.example                 # Plantilla de variables de entorno
├── Dockerfile                   # Imagen Docker (php:8.1-apache)
│
├── admin/
│   └── index.html               # Panel de administración (SPA)
│
├── auth/
│   └── index.html               # Login y registro (SPA)
│
├── store/
│   └── index.html               # Tienda del estudiante (SPA)
│
├── assets/
│   ├── css/
│   │   └── main.css             # Design system compartido
│   └── js/
│       ├── app.js               # Cliente API, auth, modales, toasts
│       └── sse.js               # Cliente SSE con reconexión exponencial
│
├── api/
│   ├── index.php                # Router principal
│   ├── sse.php                  # Endpoint SSE (conexión persistente)
│   │
│   ├── config/
│   │   ├── database.php         # Conexión PDO + initDB()
│   │   ├── helpers.php          # Utilidades: emitEvent, jsonResponse, tokens
│   │   └── logger.php           # Sistema de logs con rotación automática
│   │
│   ├── middleware/
│   │   └── auth.php             # JWT: encode, decode, requireAuth, requireAdmin
│   │
│   ├── controllers/
│   │   ├── auth.php             # Registro, login, /me
│   │   ├── products.php         # CRUD productos
│   │   ├── cart.php             # Carrito de compras
│   │   ├── orders.php           # Pedidos y checkout (efectivo)
│   │   ├── paypal.php           # Integración PayPal REST API v2
│   │   ├── queue.php            # Sistema de turnos
│   │   ├── stats.php            # Estadísticas del dashboard
│   │   └── users.php            # Gestión de usuarios (admin)
│   │
│   ├── logs/
│   │   └── error.log            # Logs del sistema (auto-rotado a 5MB)
│   │
│   └── .htaccess                # Seguridad API: preserva Authorization header
│
├── error/
│   ├── 400/index.html
│   ├── 401/index.html
│   ├── 403/index.html
│   ├── 404/index.html
│   ├── 500/index.html
│   └── 503/index.html
│
└── imgs/
    ├── icon.png
    ├── fav-icon.png
    └── uploads/                 # Imágenes subidas (chmod 777)
```

---

## Base de Datos

Las tablas se crean automáticamente en el primer acceso via `initDB()` en `api/config/database.php`. No es necesario importar ningún SQL manual.

### Esquema

```sql
-- Usuarios del sistema (estudiantes y admins)
users (id, full_name, birth_date, grade, doc_type, doc_number,
       email, password, role, blocked, created_at)

-- Catálogo de productos
products (id, name, description, price, stock, category, image,
          qr_code, active, created_at)

-- Carrito persistente en base de datos
cart_items (id, user_id, product_id, quantity)
-- UNIQUE KEY (user_id, product_id)

-- Pedidos confirmados
orders (id, user_id, total, status, payment_method,
        paypal_order_id, paypal_capture_id, paypal_funding_source,
        qr_code, qr_token, notes, turn_number, paid_at, created_at)

-- Ítems de cada pedido (snapshot de precio/nombre al momento de compra)
order_items (id, order_id, product_id, product_name,
             product_price, quantity, subtotal)

-- Configuración del sistema de turnos (siempre 1 fila, id=1)
queue_config (id, enabled, current_turn, current_order_id, updated_at)

-- Cola de eventos para SSE (se limpia automáticamente cada 2h)
events (id, type, payload JSON, created_at)
```

### Índices relevantes

- `orders.qr_token` — `UNIQUE`, búsqueda O(1) al escanear QR
- `orders.paypal_capture_id` — `UNIQUE`, previene cobros duplicados
- `orders.created_at` — para filtros por fecha en el dashboard
- `products.active + category` — para filtros del catálogo

---

## API REST

**Base URL:** `/api/`

Todas las respuestas son `application/json`. Los errores siguen el formato `{"error": "mensaje"}`.

### Autenticación — `/api/auth`

```
POST   /api/auth/register    Sin auth   Crear cuenta de estudiante
POST   /api/auth/login       Sin auth   Login → devuelve JWT
GET    /api/auth/me          JWT        Datos del usuario autenticado
```

El JWT se envía como `Authorization: Bearer <token>` o como query param `?token=<token>` (necesario para SSE, ya que `EventSource` no admite headers personalizados).

### Productos — `/api/products`

```
GET    /api/products                  Sin auth   Listar productos activos
GET    /api/products?all=1            Admin      Incluye inactivos
GET    /api/products?category=bebidas Sin auth   Filtrar por categoría
GET    /api/products?q=empanada       Sin auth   Búsqueda por texto
GET    /api/products/{id}             Sin auth   Detalle de un producto
POST   /api/products                  Admin      Crear producto
PUT    /api/products/{id}             Admin      Editar producto
PUT    /api/products/{id}/toggle      Admin      Activar / desactivar
DELETE /api/products/{id}             Admin      Soft delete (desactivar)
DELETE /api/products/{id}/hard        Admin      Eliminar permanentemente
```

### Carrito — `/api/cart`

```
GET    /api/cart                  JWT   Ver carrito del usuario
POST   /api/cart/add              JWT   Agregar producto {product_id, quantity}
PUT    /api/cart/update           JWT   Cambiar cantidad {product_id, quantity}
DELETE /api/cart/{product_id}     JWT   Eliminar un ítem
DELETE /api/cart/clear            JWT   Vaciar todo el carrito
```

### Pedidos — `/api/orders`

```
GET    /api/orders                JWT/Admin  Todos los pedidos (admin) 
GET    /api/orders/my             JWT        Pedidos del usuario autenticado
GET    /api/orders/stats          Admin      Estadísticas generales
GET    /api/orders/{id}           JWT        Detalle de un pedido
GET    /api/orders/scan/{token}   Admin      Buscar pedido por token QR
POST   /api/orders/checkout       JWT        Confirmar carrito → pago efectivo
PUT    /api/orders/{id}/status    Admin      Cambiar estado del pedido
```

### PayPal — `/api/paypal`

```
GET    /api/paypal/config           Sin auth   Client ID y entorno para el SDK
POST   /api/paypal/create-order     JWT        Crear orden en PayPal REST API
POST   /api/paypal/capture-order    JWT        Capturar pago y registrar pedido
```

### Turnos — `/api/queue`

```
GET    /api/queue              Sin auth   Estado actual del sistema
PUT    /api/queue/toggle       Admin      Activar / desactivar
PUT    /api/queue/next         Admin      Avanzar al siguiente turno
PUT    /api/queue/prev         Admin      Retroceder un turno
PUT    /api/queue/set/{n}      Admin      Saltar directamente al turno N
POST   /api/queue/reset        Admin      Reiniciar conteo a 0
```

### Usuarios — `/api/users`

```
GET    /api/users              Admin   Listar estudiantes (con ?q=búsqueda)
GET    /api/users/{id}         Admin   Detalle + historial de pedidos
PUT    /api/users/{id}         Admin   Editar datos del usuario
PUT    /api/users/{id}/block   Admin   Bloquear / desbloquear
DELETE /api/users/{id}         Admin   Eliminar (no aplica a admins)
```

### Estadísticas — `/api/stats`

```
GET    /api/stats/full    Admin   Dashboard completo con métricas históricas
```

### SSE — `/api/sse`

```
GET    /api/sse?token={jwt}    JWT   Conexión SSE persistente (hasta 40s, luego reconecta)
```

---

## Flujo de un Pedido

### Pago en Efectivo

```
1. POST /api/orders/checkout
   ├── Valida ítems del carrito
   ├── Verifica stock de cada producto
   ├── Calcula total
   ├── Genera qr_token (hex 48 chars) con random_bytes()
   ├── Asigna turn_number si el sistema de turnos está activo
   ├── INSERT orders (status = 'pending', payment_method = 'cash')
   ├── INSERT order_items (snapshot de nombre y precio)
   ├── UPDATE products SET stock = stock - cantidad
   ├── DELETE cart_items del usuario
   └── INSERT events (type = 'order_created')

2. El navegador genera la imagen QR del qr_token con qrcodejs

3. El administrador escanea el QR → GET /api/orders/scan/{token}

4. Admin marca como pagado → PUT /api/orders/{id}/status {status: 'paid'}
   └── INSERT events (type = 'order_updated')
   └── SSE notifica al estudiante
```

### Pago con PayPal

```
1. El frontend carga el PayPal JS SDK dinámicamente desde /api/paypal/config

2. createOrder (botón PayPal presionado):
   └── POST /api/paypal/create-order → servidor llama a PayPal REST API
       └── Devuelve paypal_order_id al SDK

3. onApprove (usuario completó el pago en el popup de PayPal):
   └── POST /api/paypal/capture-order {paypal_order_id}
       ├── Servidor llama a PayPal /v2/checkout/orders/{id}/capture
       ├── Verifica que status === 'COMPLETED'
       ├── Verifica captureId único (previene cobros duplicados)
       ├── INSERT orders (status = 'paid', payment_method = 'paypal')
       ├── INSERT order_items
       ├── UPDATE stock
       ├── DELETE cart_items
       └── INSERT events (type = 'order_created')

4. Frontend muestra modal de éxito con QR
```

---

## Sistema de Pagos

### Efectivo

El flujo de efectivo es el método por defecto. El pedido queda en estado `pending` hasta que el administrador lo escanea y marca como `paid` desde el panel.

### PayPal

La integración usa la **PayPal REST API v2** directamente desde PHP (sin SDK de PHP). El JS SDK se carga en el cliente solo cuando el usuario selecciona PayPal como método de pago.

**Métodos de pago soportados por PayPal:**
- Saldo de cuenta PayPal
- Tarjeta de crédito / débito
- Apple Pay
- Google Pay
- Venmo (en mercados disponibles)
- Pagar después (Pay Later)

**Seguridad anti-fraude implementada:**

| Mecanismo | Descripción |
|---|---|
| `paypal_order_id` UNIQUE | Previene que una misma orden se procese dos veces |
| `paypal_capture_id` UNIQUE | Previene que una captura se registre duplicada |
| Verificación de estado | Solo procesa órdenes con `status === 'COMPLETED'` |
| Timeout por operación | `createOrder` tiene 20s de límite, `captureOrder` tiene 30s |
| Logging completo | Errores de PayPal se registran en `api/logs/error.log` |
| Tolerancia de monto | Alerta cuando el monto capturado difiere más del 5% del esperado |

**Tasa de conversión COP → USD:**

La conversión se hace en el backend usando una tasa fija (`0.00024` por defecto). Para producción se recomienda obtener la tasa de una API de tipo de cambio en tiempo real.

```php
// api/controllers/paypal.php
function convertCOPtoUSD(float $cop): float {
    $rate = defined('COP_USD_RATE') ? (float)COP_USD_RATE : 0.00024;
    return max(0.01, round($cop * $rate, 2));
}
```

Para actualizar la tasa, agrega `COP_USD_RATE` en tu `.env`.

### Configuración de PayPal (Sandbox vs Producción)

| Variable | Sandbox | Producción |
|---|---|---|
| `PAYPAL_ENV` | `sandbox` | `production` |
| `PAYPAL_CLIENT_ID` | ID de app sandbox | ID de app live |
| `PAYPAL_CLIENT_SECRET` | Secret de app sandbox | Secret de app live |
| API URL | `api-m.sandbox.paypal.com` | `api-m.paypal.com` |
| SDK URL | `sandbox.paypal.com/sdk/js` | `paypal.com/sdk/js` |

El código selecciona automáticamente la URL correcta según `PAYPAL_ENV`. No se requiere ningún cambio en el código para pasar de sandbox a producción, solo actualizar las variables de entorno.

---

## Sistema de Turnos

El sistema de turnos es opcional y el administrador lo activa/desactiva desde su panel.

**Al activar el sistema:**
1. Se reasignan números correlativos (1, 2, 3…) a todos los pedidos `pending` del día, ordenados por `created_at`.
2. Los nuevos pedidos reciben automáticamente el siguiente número disponible.
3. Se emite un evento `queue_changed` vía SSE a todos los clientes conectados.

**El administrador controla:**
- `◀ Anterior` / `▶ Siguiente` para avanzar en la cola
- Campo numérico para saltar directamente a cualquier turno
- Botón de reinicio para empezar desde 0

**El estudiante ve:**
- El turno actual en un banner fijo en la parte superior de la tienda
- Una alerta animada `🎉 ¡Es tu turno!` cuando su número coincide con el turno actual
- Sus pedidos en `Mis Pedidos` resaltados con borde dorado cuando están en turno

---

## Tiempo Real con SSE

El archivo `api/sse.php` implementa **Server-Sent Events** usando una conexión HTTP persistente.

### Ciclo de vida

```
Cliente → GET /api/sse?token={jwt}
  │
  ├── Servidor valida JWT
  ├── Envía estado inicial completo (queue, products, orders)
  └── Bucle cada 5s:
        ├── Consulta tabla events WHERE id > lastEventId
        ├── Procesa eventos en lote (evita N+1 queries)
        ├── Envía solo eventos relevantes según rol
        ├── Envía heartbeat (comentario SSE para mantener conexión)
        └── Después de 40s → envía evento 'reconnect'

Cliente recibe 'reconnect' → EventSource reconecta automáticamente
```

### Eventos emitidos

| Evento SSE | Destinatario | Trigger |
|---|---|---|
| `connected` | Todos | Conexión inicial |
| `queue` | Todos | `queue_changed` o `order_created` |
| `products_updated` | Todos | `product_changed` |
| `orders_updated` | Admin | `order_created` o `order_updated` |
| `my_orders_updated` | Usuario (solo los propios) | `order_updated` con su `user_id` |
| `stats_updated` | Admin | `order_created` o `order_updated` |
| `admin_products_updated` | Admin | `product_changed` |
| `reconnect` | Todos | Fin del ciclo de 40s |

### Reconexión exponencial (`sse.js`)

```javascript
// Delay inicial: 8s → ×1.5 por cada fallo → máximo 60s
reconnectDelay = Math.min(reconnectDelay * 1.5, 60000);
```

---

## Seguridad

| Aspecto | Implementación |
|---|---|
| **Autenticación** | JWT HS256, expiración 7 días, verificado con `hash_equals()` (anti timing-attack) |
| **Contraseñas** | `password_hash(PASSWORD_BCRYPT)` — no reversible ni visible para admins |
| **SQL Injection** | PDO con Prepared Statements en todas las queries |
| **Acceso a rutas** | Middleware `requireAuth()` / `requireAdmin()` en cada endpoint protegido |
| **Correos institucionales** | Validación de dominio en registro y login (`@santajuanalestonnac.edu.co`) |
| **Directorios** | `Options -Indexes` en `.htaccess` — sin listado de archivos |
| **Archivos sensibles** | `.htaccess` bloquea `.log`, `.sql`, `.env`, `.json`, `config.php` |
| **Token QR** | `random_bytes(24)` — 48 caracteres hex criptográficamente seguros |
| **Cobros duplicados** | `UNIQUE INDEX` en `paypal_capture_id` — previene double-charge |
| **CORS** | Configurable en `api/index.php` — ajustar en producción |
| **Logging** | Toda acción sospechosa queda en `api/logs/error.log` con IP y URI |

> ⚠️ **Para producción**: restringe el `Access-Control-Allow-Origin` a tu dominio específico y habilita HTTPS (ver [Recomendaciones para Producción](#recomendaciones-para-producción)).

---

## Instalación y Configuración

### Requisitos

- PHP 8.1+ con extensiones `pdo_mysql`, `mysqli`
- MySQL 5.7+ o 8.0
- Apache 2.4 con `mod_rewrite`
- Docker (opcional, recomendado)

### Opción A — Con Docker (recomendado)

```bash
# 1. Clona el repositorio
git clone https://github.com/tu-usuario/cafeteria-escolar.git
cd cafeteria-escolar

# 2. Crea el archivo de variables de entorno
cp .env.example .env
# Edita .env con tus credenciales

# 3. Construye y corre el contenedor
docker build -t cafeteria-escolar .
docker run -d \
  --name cafeteria \
  -p 8080:80 \
  --env-file .env \
  cafeteria-escolar

# 4. Abre http://localhost:8080
```

### Opción B — Docker Compose (app + base de datos)

Crea un archivo `docker-compose.yml` en la raíz del proyecto:

```yaml
version: '3.8'

services:
  app:
    build: .
    ports:
      - "8080:80"
    depends_on:
      db:
        condition: service_healthy
    env_file:
      - .env
    environment:
      DB_HOST: db

  db:
    image: mysql:8.0
    environment:
      MYSQL_ROOT_PASSWORD: cafeteria2026
      MYSQL_DATABASE: cafeteria_escolar
    volumes:
      - mysql_data:/var/lib/mysql
    healthcheck:
      test: ["CMD", "mysqladmin", "ping", "-h", "localhost"]
      interval: 10s
      timeout: 5s
      retries: 5

volumes:
  mysql_data:
```

```bash
docker-compose up -d
# Las tablas se crean automáticamente en el primer acceso
```

### Opción C — Servidor local (XAMPP / Laragon)

```bash
# 1. Clona en la carpeta htdocs o www
git clone https://github.com/tu-usuario/cafeteria-escolar.git

# 2. Edita api/config/database.php con tus credenciales locales
# O crea el archivo .env (requiere que getenv() funcione en tu entorno)

# 3. Asegúrate de que mod_rewrite esté habilitado en Apache
# 4. Abre http://localhost/cafeteria-escolar
```

---

## Variables de Entorno

Crea un archivo `.env` en la raíz del proyecto basado en la siguiente plantilla:

```env
# ── Base de Datos ──────────────────────────────────────
DB_HOST=localhost
DB_NAME=cafeteria_escolar
DB_USER=root
DB_PASS=tu_contraseña_segura

# ── JWT ────────────────────────────────────────────────
# Usa una cadena larga y aleatoria (mínimo 32 caracteres)
JWT_SECRET=genera_un_secreto_largo_y_aleatorio_aqui

# ── PayPal ─────────────────────────────────────────────
# Obtén tus credenciales en: https://developer.paypal.com/dashboard/
PAYPAL_CLIENT_ID=tu_client_id_aqui
PAYPAL_CLIENT_SECRET=tu_client_secret_aqui

# sandbox = pruebas | production = producción
PAYPAL_ENV=sandbox

# ── Opcional ───────────────────────────────────────────
# Tasa de conversión COP → USD (si no se define, usa 0.00024)
# COP_USD_RATE=0.00024

# URL base del servidor (para logs y redirects)
# BASE_URL=https://tudominio.com
```

> ⚠️ **Nunca subas `.env` a Git.** El archivo ya está incluido en `.gitignore`.

### Cómo obtener las credenciales de PayPal

1. Ve a [developer.paypal.com](https://developer.paypal.com/dashboard/)
2. Inicia sesión o crea una cuenta de desarrollador
3. Ve a **My Apps & Credentials**
4. Crea una nueva aplicación (o usa la que aparece por defecto)
5. Copia el **Client ID** y el **Secret** del entorno que necesites (Sandbox / Live)
6. Para probar pagos en sandbox, usa las cuentas de prueba en **Sandbox > Accounts**

---

## Despliegue con Docker

El `Dockerfile` incluido instala todas las dependencias necesarias:

```dockerfile
FROM php:8.1-apache

# Extensiones de MySQL
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Habilitar mod_rewrite
RUN a2enmod rewrite
RUN sed -i 's|AllowOverride None|AllowOverride All|g' /etc/apache2/apache2.conf

COPY . /var/www/html/

RUN chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html && \
    mkdir -p /var/www/html/imgs/uploads && \
    chmod -R 777 /var/www/html/imgs/uploads

EXPOSE 80
```

**Comandos útiles:**

```bash
# Ver logs del contenedor
docker logs cafeteria -f

# Acceder al contenedor
docker exec -it cafeteria bash

# Ver logs de la aplicación
docker exec cafeteria cat /var/www/html/api/logs/error.log

# Detener y eliminar
docker stop cafeteria && docker rm cafeteria
```

---

## Red Local sin Internet

El sistema puede funcionar completamente **offline** usando una red Wi-Fi local. Esto es ideal para colegios con conectividad limitada.

### Arquitectura recomendada

```
[Router Wi-Fi "Cafeteria-SantaJuana"]
    │
    ├── Wi-Fi → Celulares de estudiantes (192.168.1.X)
    │
    └── Cable Ethernet → Servidor local
        ├── Raspberry Pi 4 (~$50 USD) — recomendado para uso permanente
        │   └── IP fija: 192.168.1.100
        │   └── Docker: PHP + MySQL
        │   └── dnsmasq: cafeteria.local → 192.168.1.100
        │
        └── Laptop — para demos o instalaciones temporales
            └── Hotspot Wi-Fi activado
            └── Docker corriendo localmente
```

### Configuración con Raspberry Pi

```bash
# En la Raspberry Pi
curl -fsSL https://get.docker.com | sh
sudo usermod -aG docker pi

git clone https://github.com/tu-usuario/cafeteria-escolar.git
cd cafeteria-escolar
cp .env.example .env
# Edita .env con las credenciales locales

docker-compose up -d
```

**Configurar DNS local con dnsmasq:**

```bash
sudo apt install dnsmasq -y

# /etc/dnsmasq.conf
address=/cafeteria.local/192.168.1.100
# Para redirigir cualquier dominio (portal cautivo):
# address=/#/192.168.1.100

sudo systemctl restart dnsmasq
```

Configura el router para usar la IP de la Pi como servidor DNS primario. Los estudiantes solo necesitan conectarse al Wi-Fi y abrir el navegador.

### PWA — Instalación en celular

La app puede instalarse como **Progressive Web App** (sin tienda de aplicaciones):

- **Android:** Chrome → menú `⋮` → "Agregar a pantalla de inicio"
- **iOS:** Safari → botón compartir → "Añadir a pantalla de inicio"

Para habilitarlo, agrega un `manifest.json` y un Service Worker a tu instalación (ver documentación de PWA).

---

## Panel de Administración

### Acceso

```
URL:      /admin/
Email:    admin@santajuanalestonnac.edu.co
Password: admin123
```

> ⚠️ **Cambia la contraseña inmediatamente** después de la primera instalación.

El admin por defecto se crea automáticamente en `initDB()` si no existe.

### Secciones del panel

| Sección | Funcionalidades principales |
|---|---|
| **Dashboard** | Ingresos (hoy/mes/año/total), gráficos, top productos, insights, exportación CSV/PDF |
| **Turnos** | Control en tiempo real del sistema de turnos, vista del pedido en turno actual |
| **Escáner QR** | Cámara con detección automática, búsqueda manual, resultado inmediato |
| **Productos** | Tabla con búsqueda, modal de creación/edición, toggle activo, eliminación |
| **Usuarios** | Tabla con búsqueda, bloquear/desbloquear, historial de pedidos por usuario |
| **Pedidos** | Filtros por estado, cambio de estado masivo, visualización de QR |

### Exportaciones disponibles

- **CSV Hoy**: todos los pedidos del día actual
- **CSV Mes**: todos los pedidos del mes actual
- **Reporte PDF**: abre ventana de impresión con resumen financiero del mes (ingresos, ticket promedio, listado de pedidos pagados)

---

## Roles de Usuario

| Rol | Accede a | Puede hacer |
|---|---|---|
| `user` (Estudiante) | `/store/` | Ver catálogo, gestionar carrito, hacer pedidos, ver historial y QRs propios |
| `admin` | `/admin/` | Control total: productos, pedidos, usuarios, turnos, estadísticas, escáner |

Los roles se verifican en el middleware del backend (`requireAuth()`, `requireAdmin()`). Un estudiante que intente acceder a un endpoint de admin recibe `403 Forbidden`.

---

## Manejo de Errores

### Backend

- Todos los errores de controlador quedan en `api/logs/error.log` con timestamp, IP, URI y datos de contexto.
- El log rota automáticamente al superar 5MB. Se conservan máximo 5 archivos históricos.
- Los errores fatales de PHP son capturados por `register_shutdown_function` y devuelven JSON en lugar de HTML.

### Frontend

- El cliente API (`app.js`) captura todos los errores de `fetch` y los expone como excepciones con `err.status` y `err.details`.
- Los toasts informan al usuario de errores en tiempo real (rojo para errores, verde para éxito).
- Los errores de PayPal se muestran dentro del modal de pago sin cerrarlo, para que el usuario pueda reintentar.

### Páginas de error HTTP

El proyecto incluye páginas de error personalizadas en `/error/400/`, `/error/401/`, `/error/403/`, `/error/404/`, `/error/500/` y `/error/503/`.

---

## Recomendaciones para Producción

### HTTPS (obligatorio)

```nginx
# Nginx como proxy reverso frente a Apache/Docker
server {
    listen 443 ssl http2;
    server_name tudominio.com;

    ssl_certificate /etc/letsencrypt/live/tudominio.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/tudominio.com/privkey.pem;

    location / {
        proxy_pass http://localhost:8080;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-Proto https;

        # Necesario para SSE
        proxy_buffering off;
        proxy_cache off;
        proxy_read_timeout 120s;
    }
}
```

### Checklist de producción

- [ ] Cambiar `PAYPAL_ENV` de `sandbox` a `production`
- [ ] Actualizar `PAYPAL_CLIENT_ID` y `PAYPAL_CLIENT_SECRET` con las credenciales live
- [ ] Cambiar la contraseña del admin por defecto
- [ ] Generar un `JWT_SECRET` largo y aleatorio (mínimo 64 caracteres)
- [ ] Configurar una tasa COP/USD real o conectar a una API de tipo de cambio
- [ ] Restringir `Access-Control-Allow-Origin` en `api/index.php` a tu dominio
- [ ] Habilitar HTTPS con Let's Encrypt
- [ ] Asegurarse de que `imgs/uploads/` no sea accesible públicamente si contiene datos sensibles
- [ ] Configurar backups automáticos de la base de datos MySQL
- [ ] Revisar `api/logs/error.log` periódicamente o configurar alertas
- [ ] Considerar rate limiting en los endpoints de `auth` para prevenir fuerza bruta

---

## Hoja de Ruta

### Próximas mejoras planificadas

- [ ] **PWA completa**: `manifest.json` + Service Worker con caché offline
- [ ] **Notificaciones push**: cuando es el turno del estudiante, aunque la app esté en segundo plano
- [ ] **Tasa de cambio dinámica**: conexión a API de tipo de cambio para pagos PayPal
- [ ] **Múltiples menús por día**: desayuno, media mañana, almuerzo con horarios
- [ ] **Reportes avanzados**: gráficos de tendencias semanales, comparativas entre meses
- [ ] **Gestión de categorías**: crear/editar categorías desde el panel admin
- [ ] **API para padres de familia**: consultar historial de compras de un estudiante
- [ ] **Integración con Wompi / Nequi**: métodos de pago locales de Colombia

---

## Contribución

Las contribuciones son bienvenidas. Por favor sigue estos pasos:

1. Haz un **fork** del repositorio
2. Crea una rama para tu funcionalidad: `git checkout -b feature/nombre-de-la-funcionalidad`
3. Haz commit de tus cambios: `git commit -m 'feat: descripción clara del cambio'`
4. Sube tu rama: `git push origin feature/nombre-de-la-funcionalidad`
5. Abre un **Pull Request** describiendo qué cambiaste y por qué

**Convenciones de commit:**

```
feat:     Nueva funcionalidad
fix:      Corrección de error
docs:     Cambios solo en documentación
style:    Formato, puntos y comas, etc.
refactor: Refactorización sin cambiar funcionalidad
security: Mejoras de seguridad
```

---

## Licencia

Este proyecto está bajo la [Licencia MIT](LICENSE). Puedes usarlo, modificarlo y distribuirlo libremente siempre que incluyas el aviso de copyright original.

---

## Créditos

**Desarrollado para:** Colegio Santa Juana de Lestonnac  
**Propósito:** Proyecto escolar para modernizar la gestión de la cafetería  
**Zona horaria:** `America/Bogota` (UTC-5)  
**Moneda:** Pesos Colombianos (COP)  
**Idioma de la interfaz:** Español  
**Año:** 2026  

---

<div align="center">

Hecho con ❤️ para la comunidad estudiantil del Colegio Santa Juana de Lestonnac

</div>