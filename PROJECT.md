# 🍽️ Cafetería Escolar — Colegio Santa Juana de Lestonnac

> Sistema digital de gestión de pedidos para la cafetería escolar. Permite a los estudiantes ordenar su comida desde el celular, generar un código QR único y pagarlo en efectivo al momento de retirarlo, eliminando las filas tradicionales.

---

## 📋 Tabla de Contenidos

1. [Descripción General](#descripción-general)
2. [Características del Sistema](#características-del-sistema)
3. [Tecnologías Utilizadas](#tecnologías-utilizadas)
4. [Estructura Completa del Proyecto](#estructura-completa-del-proyecto)
5. [Base de Datos](#base-de-datos)
6. [API REST — Endpoints](#api-rest--endpoints)
7. [Sistema de Turnos (Queue)](#sistema-de-turnos-queue)
8. [Sistema de Eventos en Tiempo Real (SSE)](#sistema-de-eventos-en-tiempo-real-sse)
9. [Autenticación y Seguridad](#autenticación-y-seguridad)
10. [Descripción Detallada de Cada Archivo](#descripción-detallada-de-cada-archivo)
11. [Flujo Completo de un Pedido](#flujo-completo-de-un-pedido)
12. [Panel de Administración](#panel-de-administración)
13. [Despliegue con Docker](#despliegue-con-docker)
14. [Solución de Conectividad sin Internet (Red Local)](#solución-de-conectividad-sin-internet-red-local)
15. [Roles de Usuario](#roles-de-usuario)
16. [Consideraciones de Seguridad](#consideraciones-de-seguridad)

---

## Descripción General

La **Cafetería Escolar Digital** es una aplicación web full-stack diseñada específicamente para el Colegio Santa Juana de Lestonnac. El sistema reemplaza el proceso manual de atención en la cafetería por un flujo completamente digital:

1. El estudiante se registra con su correo institucional.
2. Selecciona productos del catálogo digital.
3. Confirma su pedido y obtiene un **código QR único**.
4. Espera su **turno numerado** (si el sistema está activo).
5. Presenta el QR en la ventanilla, paga en efectivo y retira.

El administrador (encargado de la cafetería) gestiona todo desde un **panel web en tiempo real**: escanea QRs con la cámara, avanza los turnos, controla el inventario y visualiza estadísticas.

---

## Características del Sistema

### Para Estudiantes
- ✅ Registro con correo institucional `@santajuanalestonnac.edu.co`
- ✅ Catálogo de productos filtrable por categoría y búsqueda en tiempo real
- ✅ Carrito de compras con control de cantidades y validación de stock
- ✅ Generación de código QR único por pedido
- ✅ Historial de pedidos con estados (Pendiente / Pagado / Cancelado)
- ✅ Sistema de turnos: el estudiante ve su número en pantalla
- ✅ Alerta visual cuando es su turno (`🎉 ¡Es tu turno!`)
- ✅ Visualización del turno actual en tiempo real (sin recargar la página)
- ✅ Interfaz 100% responsiva (funciona en celular, tablet y computador)

### Para el Administrador
- ✅ Dashboard con estadísticas en tiempo real (pedidos, ingresos, usuarios, stock)
- ✅ Gráfico de ganancias de los últimos 6 meses
- ✅ Top 5 productos más vendidos
- ✅ Alertas automáticas de stock bajo (≤ 3 unidades)
- ✅ Escáner de QR con cámara integrada (detección automática)
- ✅ Búsqueda manual de pedidos por token/ID
- ✅ Sistema de turnos: activar/desactivar, avanzar, retroceder, saltar a un número
- ✅ Gestión completa de productos (crear, editar, activar/desactivar, eliminar)
- ✅ Gestión de usuarios (ver, bloquear/desbloquear, eliminar)
- ✅ Panel de pedidos con filtros por estado
- ✅ Actualizaciones en tiempo real vía SSE (sin recargar la página)

### Características Técnicas
- ✅ Autenticación con JWT (JSON Web Tokens), duración 7 días
- ✅ API REST en PHP con enrutamiento dinámico
- ✅ Server-Sent Events (SSE) para actualizaciones en tiempo real
- ✅ Base de datos MySQL con transacciones ACID
- ✅ Sistema de logs detallados por nivel (info, warning, error, debug)
- ✅ Código QR generado en el cliente con la librería `qrcodejs`
- ✅ Desplegable con Docker
- ✅ Protección de rutas con `.htaccess`

---

## Tecnologías Utilizadas

| Capa | Tecnología | Versión |
|------|-----------|---------|
| Frontend | HTML5, CSS3, JavaScript (Vanilla) | ES2020+ |
| Backend | PHP | 8.1 |
| Base de Datos | MySQL | 5.7+ / 8.0 |
| Servidor Web | Apache 2.4 (con `mod_rewrite`) | 2.4 |
| Contenedor | Docker + Docker Compose | — |
| Fuentes | Google Fonts (Unbounded, Outfit) | — |
| QR Code | qrcodejs (CDN) | 1.0.0 |
| Escáner QR | html5-qrcode (CDN) | — |
| Autenticación | JWT HS256 (implementación propia en PHP) | — |
| Tiempo Real | Server-Sent Events (SSE) nativo | — |

---

## Estructura Completa del Proyecto

```
cafeteria-escolar/
│
├── 📄 index.html                    # Página de inicio / Landing page
├── 📄 .htaccess                     # Configuración principal de Apache
├── 📄 .env                          # Variables de entorno (NO subir a Git)
├── 📄 Dockerfile                    # Imagen Docker del proyecto
│
├── 📁 admin/
│   └── 📄 index.html               # Panel de administración completo
│
├── 📁 auth/
│   └── 📄 index.html               # Página de login y registro
│
├── 📁 store/
│   └── 📄 index.html               # Tienda / interfaz del estudiante
│
├── 📁 assets/
│   ├── 📁 css/
│   │   └── 📄 main.css             # Estilos globales del sistema
│   └── 📁 js/
│       ├── 📄 app.js               # Lógica global: API, auth, utilidades
│       └── 📄 sse.js               # Cliente SSE para tiempo real
│
├── 📁 api/
│   ├── 📄 index.php                # Router principal de la API
│   ├── 📄 sse.php                  # Endpoint de Server-Sent Events
│   │
│   ├── 📁 config/
│   │   ├── 📄 database.php         # Conexión a BD e inicialización de tablas
│   │   ├── 📄 helpers.php          # Funciones auxiliares globales
│   │   └── 📄 logger.php           # Sistema de logging por niveles
│   │
│   ├── 📁 middleware/
│   │   └── 📄 auth.php             # JWT: encode, decode, requireAuth, requireAdmin
│   │
│   ├── 📁 controllers/
│   │   ├── 📄 auth.php             # Registro, login, /me
│   │   ├── 📄 products.php         # CRUD de productos
│   │   ├── 📄 cart.php             # Carrito de compras
│   │   ├── 📄 orders.php           # Pedidos y checkout
│   │   ├── 📄 users.php            # Gestión de usuarios (admin)
│   │   └── 📄 queue.php            # Sistema de turnos
│   │
│   ├── 📁 logs/
│   │   └── 📄 error.log            # Archivo de logs (generado automáticamente)
│   │
│   └── 📁 .htaccess                # Seguridad y rewrite para la API
│
└── 📁 imgs/
    ├── 📄 icon.png                  # Ícono de la app (navbar)
    ├── 📄 fav-icon.png             # Favicon del sitio
    └── 📁 uploads/                 # Imágenes subidas (chmod 777)
```

---

## Base de Datos

La base de datos se llama `cafeteria_escolar` y sus tablas se crean automáticamente al iniciar la aplicación (función `initDB()` en `database.php`).

### Tabla `users` — Usuarios del sistema

| Columna | Tipo | Descripción |
|---------|------|-------------|
| `id` | INT AUTO_INCREMENT PK | Identificador único |
| `full_name` | TEXT NOT NULL | Nombre completo (mín. 3 palabras) |
| `birth_date` | TEXT NOT NULL | Fecha de nacimiento |
| `grade` | TEXT NOT NULL | Grado escolar (ej: `11B`, `6D`) |
| `doc_type` | TEXT NOT NULL | Tipo de documento: TI, CC, CE, PA |
| `doc_number` | VARCHAR(50) UNIQUE | Número de documento |
| `email` | VARCHAR(100) UNIQUE | Correo institucional |
| `password` | VARCHAR(255) | Hash bcrypt de la contraseña |
| `role` | VARCHAR(20) | `user` o `admin` |
| `blocked` | INT DEFAULT 0 | 1 = cuenta bloqueada |
| `created_at` | TIMESTAMP | Fecha de registro |

### Tabla `products` — Catálogo de productos

| Columna | Tipo | Descripción |
|---------|------|-------------|
| `id` | INT AUTO_INCREMENT PK | Identificador único |
| `name` | VARCHAR(100) | Nombre del producto |
| `description` | TEXT | Descripción (opcional) |
| `price` | DECIMAL(10,2) | Precio en COP |
| `stock` | INT DEFAULT 0 | Unidades disponibles |
| `category` | VARCHAR(50) | almuerzos / desayunos / rapidos / bebidas / snacks / postres / general |
| `image` | VARCHAR(255) | URL de imagen (opcional) |
| `qr_code` | VARCHAR(255) | Datos QR del producto en base64url |
| `active` | INT DEFAULT 1 | 1 = visible en la tienda |
| `created_at` | TIMESTAMP | Fecha de creación |

### Tabla `cart_items` — Carrito de compras

| Columna | Tipo | Descripción |
|---------|------|-------------|
| `id` | INT AUTO_INCREMENT PK | Identificador único |
| `user_id` | INT FK → users.id | Usuario dueño del carrito |
| `product_id` | INT FK → products.id | Producto en el carrito |
| `quantity` | INT DEFAULT 1 | Cantidad deseada |

> Restricción UNIQUE sobre `(user_id, product_id)` — evita duplicados.

### Tabla `orders` — Pedidos confirmados

| Columna | Tipo | Descripción |
|---------|------|-------------|
| `id` | INT AUTO_INCREMENT PK | Identificador único |
| `user_id` | INT FK → users.id | Estudiante que realizó el pedido |
| `total` | DECIMAL(10,2) | Monto total del pedido |
| `status` | VARCHAR(20) | `pending` / `paid` / `cancelled` |
| `qr_code` | VARCHAR(255) | Payload QR completo (base64url) |
| `qr_token` | VARCHAR(100) UNIQUE | Token secreto del QR (hex 48 chars) |
| `notes` | TEXT | Notas adicionales (opcional) |
| `turn_number` | INT | Número de turno asignado (NULL si el sistema estaba desactivado) |
| `paid_at` | TIMESTAMP NULL | Fecha y hora exacta del pago |
| `created_at` | TIMESTAMP | Fecha de creación |

### Tabla `order_items` — Ítems de cada pedido

| Columna | Tipo | Descripción |
|---------|------|-------------|
| `id` | INT AUTO_INCREMENT PK | Identificador único |
| `order_id` | INT FK → orders.id | Pedido al que pertenece |
| `product_id` | INT FK → products.id | Producto pedido |
| `product_name` | VARCHAR(255) | Nombre del producto al momento del pedido (snapshot) |
| `product_price` | DECIMAL(10,2) | Precio al momento del pedido (snapshot) |
| `quantity` | INT | Cantidad |
| `subtotal` | DECIMAL(10,2) | `quantity × product_price` |

> Los campos `product_name` y `product_price` son snapshots: guardan los valores en el momento de la compra, de modo que si después se modifica el producto, el historial de pedidos permanece exacto.

### Tabla `queue_config` — Configuración del sistema de turnos

| Columna | Tipo | Descripción |
|---------|------|-------------|
| `id` | INT PK DEFAULT 1 | Siempre hay una sola fila (id=1) |
| `enabled` | TINYINT(1) | 1 = sistema de turnos activo |
| `current_turn` | INT DEFAULT 0 | Número de turno que se está atendiendo |
| `current_order_id` | INT | ID del pedido asociado al turno actual |
| `updated_at` | TIMESTAMP | Última modificación (auto-actualiza) |

### Tabla `events` — Cola de eventos para SSE

| Columna | Tipo | Descripción |
|---------|------|-------------|
| `id` | INT AUTO_INCREMENT PK | ID secuencial del evento |
| `type` | VARCHAR(50) | Tipo: `order_created`, `order_updated`, `product_changed`, `queue_changed` |
| `payload` | JSON | Datos del evento (ej: `{"order_id": 5, "user_id": 3}`) |
| `created_at` | TIMESTAMP | Fecha de creación |

> Un evento de MySQL (`cleanup_events`) limpia automáticamente los registros de más de 2 horas para no llenar la tabla.

---

## API REST — Endpoints

Base URL: `/api/`

### 🔐 Autenticación — `/api/auth`

| Método | Ruta | Auth | Descripción |
|--------|------|------|-------------|
| POST | `/api/auth/register` | No | Crear nueva cuenta de estudiante |
| POST | `/api/auth/login` | No | Iniciar sesión, devuelve JWT |
| GET | `/api/auth/me` | JWT | Obtener datos del usuario autenticado |

**Validaciones del registro:**
- Nombre completo: mínimo 3 palabras
- Grado: formato `XY` donde X = número, Y = letra (ej: `11B`)
- Email: debe terminar en `@santajuanalestonnac.edu.co`
- Contraseña: mínimo 6 caracteres
- Tipo de documento: TI, CC, CE o PA

### 📦 Productos — `/api/products`

| Método | Ruta | Auth | Descripción |
|--------|------|------|-------------|
| GET | `/api/products` | No | Listar productos activos (con filtros `?all=1`, `?category=`, `?q=`) |
| GET | `/api/products/{id}` | No | Obtener un producto por ID |
| POST | `/api/products` | Admin | Crear nuevo producto |
| PUT | `/api/products/{id}` | Admin | Editar producto existente |
| PUT | `/api/products/{id}/toggle` | Admin | Activar/desactivar producto |
| DELETE | `/api/products/{id}` | Admin | Desactivar producto (soft delete) |
| DELETE | `/api/products/{id}/hard` | Admin | Eliminar producto permanentemente |

### 🛒 Carrito — `/api/cart`

| Método | Ruta | Auth | Descripción |
|--------|------|------|-------------|
| GET | `/api/cart` | JWT | Ver contenido del carrito |
| POST | `/api/cart/add` | JWT | Agregar producto (`product_id`, `quantity`) |
| PUT | `/api/cart/update` | JWT | Cambiar cantidad (`product_id`, `quantity`) |
| DELETE | `/api/cart/{product_id}` | JWT | Eliminar un producto del carrito |
| DELETE | `/api/cart/clear` | JWT | Vaciar todo el carrito |

### 📋 Pedidos — `/api/orders`

| Método | Ruta | Auth | Descripción |
|--------|------|------|-------------|
| GET | `/api/orders` | Admin | Listar todos los pedidos |
| GET | `/api/orders/stats` | Admin | Estadísticas generales |
| GET | `/api/orders/my` | JWT | Pedidos del usuario autenticado |
| GET | `/api/orders/{id}` | JWT | Detalle de un pedido |
| GET | `/api/orders/scan/{token}` | Admin | Buscar pedido por token QR |
| POST | `/api/orders/checkout` | JWT | Confirmar carrito y crear pedido |
| PUT | `/api/orders/{id}/status` | Admin | Cambiar estado (`pending`/`paid`/`cancelled`) |

### 👥 Usuarios — `/api/users`

| Método | Ruta | Auth | Descripción |
|--------|------|------|-------------|
| GET | `/api/users` | Admin | Listar todos los estudiantes |
| GET | `/api/users/{id}` | Admin | Detalle de un usuario + sus pedidos |
| PUT | `/api/users/{id}` | Admin | Editar datos del usuario |
| PUT | `/api/users/{id}/block` | Admin | Bloquear/desbloquear usuario |
| DELETE | `/api/users/{id}` | Admin | Eliminar usuario (no admins) |

### 🎫 Turnos — `/api/queue`

| Método | Ruta | Auth | Descripción |
|--------|------|------|-------------|
| GET | `/api/queue` | No | Estado actual del sistema de turnos |
| PUT | `/api/queue/toggle` | Admin | Activar/desactivar el sistema |
| PUT | `/api/queue/next` | Admin | Avanzar al siguiente turno |
| PUT | `/api/queue/prev` | Admin | Retroceder al turno anterior |
| PUT | `/api/queue/set/{n}` | Admin | Saltar directamente al turno N |
| POST | `/api/queue/reset` | Admin | Reiniciar conteo a 0 |

### 🔴 SSE — `/api/sse`

| Método | Ruta | Auth | Descripción |
|--------|------|------|-------------|
| GET | `/api/sse?token={jwt}` | JWT | Conexión SSE para tiempo real |

---

## Sistema de Turnos (Queue)

Cuando el administrador **activa** el sistema de turnos:

1. Se asigna un `turn_number` secuencial a todos los pedidos `pending` del día (ordenados por `created_at`).
2. Los pedidos nuevos creados mientras el sistema está activo reciben el siguiente número disponible automáticamente.
3. El administrador avanza los turnos (`next`/`prev`/`set`) desde su panel.
4. Cada cambio emite un evento `queue_changed` que llega a todos los clientes SSE conectados.
5. El estudiante ve el turno actual en el banner superior de la tienda.
6. Cuando el turno actual coincide con uno de sus pedidos, aparece una **alerta visual y toast** (`🎉 ¡Es tu turno!`).

Cuando el administrador **desactiva** el sistema, los pedidos nuevos no reciben `turn_number` (se almacena `NULL`).

---

## Sistema de Eventos en Tiempo Real (SSE)

El archivo `api/sse.php` implementa el estándar **Server-Sent Events** del navegador.

### Flujo de conexión:
1. El cliente abre `EventSource('/api/sse?token=...')`.
2. El servidor valida el JWT y establece la conexión persistente.
3. Al conectar, el servidor envía el estado inicial completo (cola, productos, pedidos).
4. El servidor entra en un bucle con `sleep(3)`, consultando la tabla `events` por eventos nuevos.
5. Según los eventos encontrados, calcula qué datos han cambiado y los envía al cliente.
6. Después de 55 segundos, envía `reconnect` y el cliente reconecta automáticamente.

### Tipos de eventos emitidos:

| Evento SSE | Destinatario | Descripción |
|-----------|-------------|-------------|
| `connected` | Todos | Confirmación de conexión exitosa |
| `queue` | Todos | Estado completo del sistema de turnos |
| `products_updated` | Todos | Lista actualizada de productos activos |
| `orders_updated` | Admin | Lista completa de pedidos (100 más recientes) |
| `my_orders_updated` | Usuario | Pedidos propios actualizados |
| `stats_updated` | Admin | Estadísticas del dashboard |
| `admin_products_updated` | Admin | Productos con stock para alertas |
| `reconnect` | Todos | Señal para que el cliente reconecte |

### Reconexión automática (sse.js):
El cliente implementa reconexión exponencial: espera 2s → 3s → 4.5s → ... hasta máximo 30s entre intentos. Si pierde conexión, emite el evento interno `disconnected`.

---

## Autenticación y Seguridad

### JWT (JSON Web Tokens)

Implementado manualmente en `api/middleware/auth.php`:

- **Algoritmo:** HS256 (HMAC SHA-256)
- **Expiración:** 7 días desde la emisión
- **Payload:** `{ id, email, role, name, iat, exp }`
- **Secret:** definido en `JWT_SECRET` (variable de entorno)

El token se envía en el header `Authorization: Bearer <token>` o como query param `?token=<token>` (para SSE, ya que `EventSource` no admite headers personalizados).

### Funciones de autenticación:
- `jwtEncode(array $payload): string` — Genera el token
- `jwtDecode(string $token): ?array` — Verifica y decodifica (devuelve `null` si es inválido o expirado)
- `requireAuth(): array` — Middleware: exige JWT válido o devuelve 401
- `requireAdmin(): array` — Middleware: exige JWT válido con `role = admin` o devuelve 403

### Protección de contraseñas:
Las contraseñas se hashean con `PASSWORD_BCRYPT` (factor de costo por defecto de PHP).

---

## Descripción Detallada de Cada Archivo

---

### 📄 `index.html` — Landing Page

Página pública de bienvenida. No requiere autenticación.

**Secciones:**
- **Hero:** Titular animado, descripción del sistema, pasos de uso (`¿Cómo funciona?`), estadísticas decorativas.
- **Features:** Tarjetas de características (rápido, móvil, seguro, panel admin).
- **Menú:** Accesos directos a cada categoría de la tienda.
- **CTA:** Llamado a la acción con botones dinámicos según si el usuario está autenticado.
- **Footer:** Información de contacto, links legales, créditos.
- **Modal de Términos y Condiciones:** Se muestra antes del registro. Incluye la cláusula de **falta disciplinaria Tipo 2** por no pagar (multa del doble + anotación en observador).

**Comportamiento dinámico:** Si el usuario ya inició sesión, los botones del hero cambian a `"Ir a la tienda"` o `"Panel Admin"`.

---

### 📄 `auth/index.html` — Autenticación

Página de login y registro con diseño de dos paneles:

- **Panel izquierdo (oscuro):** Visual de marca, características del sistema en "pills".
- **Panel derecho (claro):** Formulario con tabs "Iniciar sesión" / "Registrarse".

**Login:**
- Campos: email, contraseña.
- Validación: dominio `@santajuanalestonnac.edu.co`.
- Manejo de errores: usuario no encontrado, bloqueado, credenciales incorrectas.

**Registro:**
- Campos: nombre completo, fecha de nacimiento, grado, tipo y número de documento, email, contraseña.
- Validaciones en tiempo real: formato del grado (`11B`), dominio del email, nombre con mínimo 3 palabras, edad entre 5 y 20 años.
- El grado se auto-formatea (elimina espacios y pone mayúsculas).

**Al autenticarse exitosamente:** guarda `token` y `user` en `localStorage` y redirige a `/store/` o `/admin/` según el rol.

---

### 📄 `store/index.html` — Tienda (Estudiante)

Interfaz principal del estudiante. Requiere autenticación (redirige a `/auth/` si no hay sesión).

**Layout:**
- Barra lateral izquierda con categorías (desktop).
- Barra de categorías horizontal deslizable (móvil).
- Área principal con 3 tabs:

**Tab "Carta" (Productos):**
- Buscador en tiempo real por nombre y descripción.
- Grid de tarjetas de producto con imagen, categoría, precio y stock.
- Indicador de stock bajo (≤3 unidades).
- Botón `+` para agregar al carrito (deshabilita temporalmente para evitar doble clic).
- Productos agotados muestran badge `"Agotado"` sin botón.

**Tab "Pedido" (Carrito):**
- Lista de ítems con foto, nombre, controles de cantidad (−/+) y precio.
- Botón para eliminar ítem individual o vaciar todo.
- Tarjeta de resumen con total y botón `"Confirmar y obtener QR"`.
- Al confirmar: llama a `POST /orders/checkout`, muestra modal con el QR generado y el número de turno asignado.

**Tab "Mis Pedidos":**
- Lista de pedidos históricos con estado, items, total y turno.
- Botón `"Ver QR"` en cada pedido para mostrar el código de nuevo.
- Si es el turno actual del estudiante, la tarjeta se resalta con borde dorado.

**Banner de turno actual** (parte superior, aparece cuando el sistema de turnos está activo):
- Muestra el turno que se está atendiendo en tiempo real.
- Si es el turno del estudiante, aparece `"🎉 ¡Es tu turno!"`.

---

### 📄 `admin/index.html` — Panel de Administración

Panel completo para el encargado de la cafetería. Requiere rol `admin`.

**Layout:**
- Barra lateral fija (desktop) con navegación entre secciones.
- Barra de navegación inferior (móvil).
- Área principal que renderiza dinámicamente cada sección.

**Sección Dashboard:**
- Fecha actual en zona horaria de Colombia (`America/Bogota`).
- Ingresos cobrados: hoy / mes / año / histórico total (solo pedidos `paid`).
- Tarjetas de estadísticas en vivo: pagados, pendientes, cancelados, productos activos, stock total, usuarios.
- Gráfico de barras de ganancias últimos 6 meses.
- Lista de top 5 productos más vendidos.
- Alerta de productos con stock bajo.
- Tarjeta de ingresos pendientes (pedidos no cobrados).
- Indicador de conexión SSE en vivo.

**Sección Turnos:**
- Número del turno actual (enorme, en dorado).
- Botones de control: `◀` anterior, `▶` siguiente, campo para saltar a número específico.
- Toggle para activar/desactivar el sistema de turnos.
- Botón de reinicio del conteo.
- Muestra el pedido del turno actual con datos del estudiante y total.
- Tabla de todos los pedidos de hoy con turno asignado.

**Sección Escáner QR:**
- Tabs para tipo de escaneo: Pedidos / Usuarios / Productos.
- Cámara en tiempo real (html5-qrcode, cámara trasera preferida).
- Detección automática con debounce de 4 segundos para no procesar duplicados.
- Campo de búsqueda manual como alternativa a la cámara.
- Panel de resultado con toda la información del ítem escaneado y botones de acción.

**Sección Productos:**
- Tabla con imagen en miniatura, nombre, categoría, precio, stock y estado.
- Búsqueda en tiempo real en la tabla.
- Modal para crear/editar producto (nombre, descripción, precio, stock, categoría, URL de imagen).
- Botones: editar, ver QR, activar/desactivar, eliminar permanentemente.

**Sección Usuarios:**
- Tabla con nombre, email, grado, documento, fecha de registro y estado.
- Búsqueda en tiempo real.
- Botones: bloquear/desbloquear, ver detalle con historial de pedidos, eliminar.

**Sección Pedidos:**
- Tabla completa de todos los pedidos.
- Filtro por estado (todos / pendientes / pagados / cancelados).
- Búsqueda en tiempo real.
- Botones de cambio de estado directo desde la tabla.
- Modal de detalle con QR del pedido.

---

### 📄 `assets/css/main.css` — Estilos Globales

Archivo CSS compartido por todas las páginas. Define:

**Sistema de diseño (variables CSS):**
- Colores: `--ink` (negro), `--paper` (blanco cálido), `--accent` (naranja-rojo `#ff4d1f`), `--success`, `--error`, `--warning`, `--info`.
- Tipografía: `Plus Jakarta Sans` (display) y `Satoshi` (cuerpo).
- Espaciado: radios de borde, sombras, transiciones.

**Componentes globales:**
- `.btn` con variantes: `btn-accent`, `btn-primary`, `btn-outline`, `btn-ghost`, `btn-success`, `btn-danger`, `btn-warning`, tamaños `btn-sm`, `btn-lg`, `btn-full`.
- `.field` con inputs, selects y textareas estilizados y estados `input-error` / `input-ok`.
- `.nav` con soporte para modo oscuro (`.dark`), logo, links, botón de carrito, botón de menú móvil.
- Menú móvil con overlay y panel deslizable desde la derecha.
- `.card` con hover effect.
- `.modal` con animaciones de entrada y cierre por click en overlay.
- `.toast` con variantes por tipo (success, error, info, warning).
- Tabla (`table`, `thead`, `tbody`) con estilos consistentes.
- `.stat-card` para tarjetas de métricas.
- Animaciones: `spin`, `fadeIn`, `fadeUp`, `scaleIn`, `slideRight`, `float`, `pulse-ring`.

**Diseño responsivo** con breakpoints a 900px y 480px.

---

### 📄 `assets/js/app.js` — Lógica Global

Módulo JavaScript compartido. Expone objetos y funciones globales:

**`api` — Cliente HTTP:**
```javascript
api.get('/products')           // GET
api.post('/auth/login', body)  // POST
api.put('/orders/1/status', b) // PUT
api.del('/cart/5')             // DELETE
```
- Agrega automáticamente el header `Authorization: Bearer <token>`.
- Parsea la respuesta JSON y lanza error descriptivo si `!res.ok`.
- Intercepta errores de red (sin conexión).
- Logea en consola todas las peticiones y respuestas.

**`auth` — Gestión de autenticación:**
```javascript
auth.getUser()       // Objeto usuario del localStorage
auth.getToken()      // Token JWT string
auth.isLoggedIn()    // Boolean
auth.isAdmin()       // Boolean (role === 'admin')
auth.save(t, u)      // Guardar token y usuario
auth.logout()        // Limpiar y redirigir a /
auth.requireLogin()  // Redirige a /auth/ si no está logueado
auth.requireAdmin()  // Redirige a / si no es admin
```

**`toast(msg, type)` — Notificaciones:**
- Tipos: `success`, `error`, `info`, `warning`.
- Se apilan en la esquina inferior derecha.
- Auto-desaparecen (8s para errores, 5s para el resto).
- Botón ✕ para cerrar manualmente.

**`fmtCurrency(n)`** — Formatea moneda en pesos colombianos: `$12.500`.

**`fmtDate(d)`** — Formatea fechas en zona horaria Colombia con `toLocaleString`.

**`modal(content, opts)`** — Genera y muestra un modal. Cierra con click en overlay o botón ✕. Devuelve el elemento DOM.

**`confirm(msg, onYes)`** — Modal de confirmación con botones "Confirmar" / "Cancelar".

**`statusBadge(s)`** — Genera HTML del badge de estado.

**`buildNav(activePage)`** — Genera dinámicamente la barra de navegación según si el usuario está autenticado, su rol y la página activa. También crea el menú móvil.

**`updateCartBadge()`** — Consulta el carrito y actualiza el badge numérico en el ícono del carrito.

**`formatGrade(val)`** — Normaliza el grado a formato `11B`.

---

### 📄 `assets/js/sse.js` — Cliente SSE

Módulo singleton para la conexión Server-Sent Events:

```javascript
SSE.connect()              // Conectar al endpoint /api/sse
SSE.disconnect()           // Desconectar y limpiar handlers
SSE.on('queue', fn)        // Suscribirse a evento
SSE.off('queue', fn)       // Desuscribirse
SSE.isConnected()          // Boolean
```

**Lógica de reconexión:**
- Si el servidor cierra la conexión o hay error, espera `reconnectDelay` ms antes de reconectar.
- El delay inicial es de 2000ms y crece ×1.5 en cada intento fallido, hasta máximo 30000ms (30s).
- Al reconectar exitosamente, el delay se resetea a 2000ms.

---

### 📄 `api/index.php` — Router Principal de la API

Punto de entrada único para toda la API (todas las rutas llegan aquí vía `.htaccess`).

**Responsabilidades:**
1. Configurar CORS (permite todos los orígenes, métodos y headers necesarios).
2. Responder inmediatamente a solicitudes `OPTIONS` (preflight) con 204.
3. Parsear la URL para extraer: `$resource` (ej: `products`), `$id` (número) y `$action` (texto).
4. Leer el body JSON de la solicitud.
5. Manejar errores fatales de PHP (registrar en log, devolver JSON de error).
6. Enrutar al controlador correspondiente.
7. Exponer `GET /api/health` para chequeo del servidor.

**Tabla de enrutamiento:**
```
/api/auth/*      → controllers/auth.php
/api/products/*  → controllers/products.php
/api/cart/*      → controllers/cart.php
/api/orders/*    → controllers/orders.php
/api/users/*     → controllers/users.php
/api/queue/*     → controllers/queue.php
/api/sse         → sse.php
/api/health      → {"status":"ok","time":"..."}
```

---

### 📄 `api/config/database.php` — Base de Datos

- Define las constantes de conexión: `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`, `JWT_SECRET`, `UPLOAD_PATH`, `BASE_URL`.
- `getDB()`: función singleton que devuelve la instancia `PDO`. Solo crea la conexión una vez por petición.
- `initDB(PDO $pdo)`: crea todas las tablas si no existen, inserta el registro de `queue_config`, y crea el admin por defecto (`admin@santajuanalestonnac.edu.co` / `admin123`). Se ejecuta en la primera petición.
- Zona horaria: `America/Bogota`.
- PDO configurado con `ERRMODE_EXCEPTION` y `FETCH_ASSOC`.

---

### 📄 `api/config/helpers.php` — Funciones Auxiliares

- `emitEvent(PDO $db, string $type, array $payload)`: Inserta un evento en la tabla `events` para que los clientes SSE lo reciban.
- `jsonResponse(mixed $data, int $code)`: Serializa y envía la respuesta JSON con el código HTTP indicado, luego hace `exit`.
- `jsonError(string $message, int $code)`: Atajo para respuestas de error.
- `formatGrade(string $input)`: Normaliza el grado escolar (ej: `"11 b"` → `"11B"`).
- `generateToken(int $length)`: Genera un token hexadecimal aleatorio criptográficamente seguro.
- `generateQRData(array $data)`: Serializa un array a JSON y lo codifica en base64url para el QR.

---

### 📄 `api/config/logger.php` — Sistema de Logs

Sistema de logging por niveles con rotación automática de archivos:

- `logError($message, $data)` — Errores críticos.
- `logWarning($message, $data)` — Advertencias.
- `logInfo($message, $data)` — Información general.
- `logDebug($message, $data)` — Solo si `DEBUG_MODE=true` en entorno.
- `logDatabaseError($action, $error, $query)` — Errores de base de datos con contexto.
- `logControllerError($controller, $action, $error, $data)` — Errores de controladores.

Cada entrada incluye: timestamp, nivel, IP del cliente, URI, mensaje y datos opcionales.

**Rotación:** Cuando el archivo supera 5MB, se renombra con timestamp y se crea uno nuevo. Se conservan máximo 5 archivos de respaldo.

**Ubicación:** `api/logs/error.log`

---

### 📄 `api/middleware/auth.php` — Autenticación JWT

Implementación completa de JWT sin dependencias externas:

- `base64url_encode(string)` / `base64url_decode(string)` — Codificación RFC 4648.
- `jwtEncode(array $payload)` — Genera token firmado con HS256.
- `jwtDecode(string $token)` — Verifica firma con `hash_equals` (seguro contra timing attacks) y valida expiración.
- `getBearerToken()` — Busca el token en múltiples lugares del request: `HTTP_AUTHORIZATION`, `REDIRECT_HTTP_AUTHORIZATION`, iteración de todas las variables `$_SERVER`. Soporta configuraciones de Apache que transforman los headers.
- `getAuthUser()` — Intenta decodificar el token y devuelve el payload o `null`.
- `requireAuth()` — Fuerza autenticación o devuelve 401 y hace `exit`.
- `requireAdmin()` — Fuerza rol admin o devuelve 403 y hace `exit`.

---

### 📄 `api/controllers/auth.php`

Maneja 3 rutas: `register`, `login`, `me`.

**register:** Valida todos los campos, verifica que el email y documento no existan, hashea la contraseña, inserta el usuario y devuelve JWT + datos del usuario.

**login:** Busca por email, verifica contraseña con `password_verify`, comprueba que no esté bloqueado, y devuelve JWT + datos del usuario.

**me:** Devuelve los datos del usuario autenticado desde la BD (siempre frescos, no del token).

---

### 📄 `api/controllers/products.php`

CRUD completo de productos:

- **GET lista:** Soporta `?all=1` (incluye inactivos, para admin), `?category=`, `?q=` (búsqueda).
- **GET by ID:** Devuelve producto por ID (activo o inactivo).
- **POST:** Crea producto, genera su código QR base64url automáticamente, emite evento `product_changed`.
- **PUT update:** Actualiza campos especificados. Si cambia nombre o precio, regenera el QR. Emite evento.
- **PUT toggle:** Alterna el campo `active`. Emite evento.
- **DELETE soft:** Desactiva el producto (`active=0`).
- **DELETE hard:** Elimina el registro permanentemente de la BD.

---

### 📄 `api/controllers/cart.php`

Gestión del carrito con validaciones de stock:

- **GET:** Devuelve ítems del carrito con datos del producto (join con `products`).
- **POST add:** Si el producto ya está en el carrito, suma la cantidad. Valida que no supere el stock disponible.
- **PUT update:** Si la cantidad es ≤0, elimina el ítem. Si es positiva, valida stock y actualiza.
- **DELETE item:** Elimina un producto específico del carrito.
- **DELETE clear:** Vacía todo el carrito del usuario.

Usa transacciones de BD (`beginTransaction`/`commit`/`rollBack`) en las operaciones de agregar.

---

### 📄 `api/controllers/orders.php`

**checkout (POST):** Proceso completo en una transacción:
1. Obtiene ítems del carrito (solo productos activos).
2. Valida que haya ítems y que cada uno tenga stock suficiente.
3. Calcula el total.
4. Genera un token QR aleatorio de 48 caracteres hex.
5. Si el sistema de turnos está activo, calcula el siguiente número de turno del día.
6. Inserta el pedido.
7. Inserta cada `order_item` con snapshot de nombre y precio.
8. Descuenta el stock de cada producto.
9. Vacía el carrito.
10. Emite evento `order_created`.
11. Devuelve el pedido creado.

**scan (GET):** Busca un pedido por `qr_token` (solo admin). Devuelve el pedido con datos del usuario e ítems.

**status (PUT):** Cambia el estado del pedido. Si es `paid`, guarda `paid_at`. Emite evento `order_updated`.

---

### 📄 `api/controllers/users.php`

Todos los endpoints requieren rol `admin`:

- **GET lista:** Devuelve todos los usuarios con rol `user`. Soporta `?q=` para buscar en nombre, email, documento y grado.
- **GET by ID:** Devuelve datos del usuario más todos sus pedidos.
- **PUT block:** Alterna el campo `blocked`.
- **PUT update:** Permite actualizar campos personales y contraseña.
- **DELETE:** Elimina el usuario. No permite eliminar administradores.

---

### 📄 `api/controllers/queue.php`

**GET estado:** Devuelve `enabled`, `current_turn`, `current_order` (con datos del usuario) y `last_updated`.

**PUT toggle:** Al activar, reasigna turnos a todos los pedidos pendientes del día en orden cronológico desde 1. Al desactivar, solo cambia el flag. Emite evento.

**PUT next/prev:** Incrementa o decrementa `current_turn`. Busca el pedido del nuevo turno y actualiza `current_order_id`. Emite evento.

**PUT set/{n}:** Salta directamente a un turno específico.

**POST reset:** Pone `current_turn = 0` y `current_order_id = NULL`. Emite evento.

---

### 📄 `api/sse.php` — Server-Sent Events

Conexión persistente de hasta 55 segundos. Al conectar:
1. Desactiva buffering de output.
2. Configura headers de SSE (`Content-Type: text/event-stream`, `Cache-Control: no-cache`, etc.).
3. Valida JWT (del header o del query param `?token=`).
4. Envía estado inicial completo.
5. Entra en bucle con `sleep(3)`:
   - Consulta eventos nuevos en la tabla `events` por ID.
   - Procesa eventos en lote: determina qué datos han cambiado.
   - Envía solo los eventos relevantes a cada cliente (admin vs usuario normal).
   - Envía heartbeat (comentario SSE) para mantener conexión viva.
6. Al acabar el tiempo, envía evento `reconnect`.

**Diferenciación de contenido por rol:**
- Admin recibe: `orders_updated`, `stats_updated`, `admin_products_updated`.
- Usuario recibe: `my_orders_updated` (solo sus pedidos, con items incluidos).
- Ambos reciben: `queue`, `products_updated`.

---

### 📄 `.htaccess` — Configuración Principal de Apache

```apache
DirectoryIndex index.html index.php

<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteRule ^api/(.*)$ api/index.php [QSA,L]   # Todo /api/* → api/index.php
    RewriteCond %{REQUEST_FILENAME} -f
    RewriteRule ^ - [L]                              # Archivos estáticos se sirven directo
    RewriteCond %{REQUEST_FILENAME} -d
    RewriteRule ^ - [L]                              # Directorios también
    RewriteRule ^ index.html [L]                     # Resto → SPA
</IfModule>

Options -Indexes                                      # Deshabilitar listado de directorios

<FilesMatch "\.(ini|log|sqlite|db|sql)$">
    Require all denied                                # Bloquear acceso a archivos sensibles
</FilesMatch>

<FilesMatch "^(config|database)\.php$">
    Require all denied                                # Bloquear config PHP
</FilesMatch>
```

---

### 📄 `api/.htaccess` — Configuración de Seguridad de la API

- Preserva el header `Authorization` que Apache a veces elimina (reglas `SetEnvIf`).
- Redirige todas las rutas de la API a `index.php` (excepto archivos y directorios reales).
- Bloquea acceso directo a cualquier `.php` que no sea `index.php`.
- Bloquea acceso a archivos `.ini`, `.log`, `.sqlite`, `.db`, `.sql`, `.env`, `.json`.
- Deshabilita listado de directorios.

---

### 📄 `.env` — Variables de Entorno

> ⚠️ **NUNCA subir este archivo a un repositorio público (Git).**

```env
DB_HOST=localhost
DB_NAME=cafeteria_escolar
DB_USER=root
DB_PASS=<contraseña_segura>
JWT_SECRET=<secreto_jwt_muy_largo_y_aleatorio>
```

Este archivo es para **desarrollo local**. En producción, las mismas variables están definidas directamente en `api/config/database.php`.

---

### 📄 `Dockerfile` — Imagen Docker

```dockerfile
FROM php:8.1-apache

RUN docker-php-ext-install mysqli pdo pdo_mysql   # Extensiones PHP para MySQL
RUN a2enmod rewrite                                 # Activar mod_rewrite
RUN sed -i 's|AllowOverride None|AllowOverride All|g' /etc/apache2/apache2.conf  # Permitir .htaccess

COPY . /var/www/html/                              # Copiar código fuente

RUN chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html && \
    mkdir -p /var/www/html/imgs/uploads && \
    chmod -R 777 /var/www/html/imgs/uploads         # Permisos de subida de imágenes

EXPOSE 80
```

**Para construir y correr:**
```bash
docker build -t cafeteria-escolar .
docker run -p 8080:80 \
  -e DB_HOST=host.docker.internal \
  cafeteria-escolar
```

---

### 📄 `api/logs/error.log` — Archivo de Logs

Generado automáticamente por el sistema. Cada entrada tiene el formato:

```
[2026-04-27 10:30:00] [INFO] [IP: 192.168.1.5] [URI: /api/auth/login] Login exitoso
Data: {"user_id": 3, "email": "estudiante@santajuanalestonnac.edu.co"}
--------------------------------------------------------------------------------
```

Se rota automáticamente al superar 5MB. Se conservan hasta 5 archivos históricos.

---

## Flujo Completo de un Pedido

```
1. Estudiante abre /store/
   └── JavaScript llama GET /api/products
   └── Renderiza el catálogo

2. Estudiante agrega producto
   └── POST /api/cart/add { product_id: 3, quantity: 2 }
   └── Valida stock disponible

3. Estudiante confirma pedido
   └── POST /api/orders/checkout
   ├── Valida stock (última verificación)
   ├── Calcula total
   ├── Genera token QR (hex 48 chars)
   ├── Asigna turn_number si sistema activo
   ├── INSERT orders
   ├── INSERT order_items (×productos)
   ├── UPDATE products SET stock=stock-N
   ├── DELETE cart_items
   ├── INSERT events (type='order_created')
   └── Responde con el pedido + QR

4. Modal QR se muestra al estudiante
   └── qrcodejs genera imagen QR del token
   └── Estudiante puede capturar pantalla

5. SSE emite a admin: orders_updated, stats_updated

6. Admin ve pedido nuevo en su panel en tiempo real

7. Admin activa sistema de turnos (si no estaba activo)
   └── PUT /api/queue/toggle
   └── Se asignan números a todos los pendientes del día
   └── SSE emite queue a todos

8. Estudiante ve su turno en el banner
   └── Banner aparece con número actual

9. Admin avanza el turno
   └── PUT /api/queue/next
   └── SSE emite queue a todos

10. Si es el turno del estudiante
    └── Banner destella: "🎉 ¡Es tu turno!"
    └── Toast de notificación

11. Estudiante muestra QR en ventanilla
    └── Admin abre escáner o ingresa token

12. Admin escanea QR
    └── GET /api/orders/scan/{token}
    └── Ve datos del pedido y botón "✅ Marcar pagado"

13. Admin marca como pagado
    └── PUT /api/orders/3/status { status: 'paid' }
    └── INSERT events (type='order_updated')
    └── SSE notifica al estudiante: my_orders_updated
    └── Toast al estudiante: "✅ Tu pedido #3 fue marcado como pagado"
```

---

## Panel de Administración

### Credenciales por defecto

```
Email:    admin@santajuanalestonnac.edu.co
Password: admin123
```

> ⚠️ **Cambiar la contraseña inmediatamente** en producción.

### Acceso
El panel está en `/admin/`. Si un usuario sin rol `admin` intenta acceder, `auth.requireAdmin()` lo redirige al inicio.

---

## Despliegue con Docker

### Requisitos
- Docker Desktop (o Docker Engine en Linux)
- MySQL 5.7+ corriendo (puede ser otro contenedor)

### Opción A — Solo la app (BD externa)

```bash
# 1. Clonar el repositorio
git clone <url-repo> cafeteria-escolar
cd cafeteria-escolar

# 2. Editar las credenciales de BD en api/config/database.php

# 3. Construir imagen
docker build -t cafeteria-escolar .

# 4. Correr contenedor
docker run -d \
  --name cafeteria \
  -p 8080:80 \
  cafeteria-escolar

# 5. Abrir http://localhost:8080
```

### Opción B — App + BD con Docker Compose

Crear `docker-compose.yml`:

```yaml
version: '3.8'
services:
  app:
    build: .
    ports:
      - "8080:80"
    depends_on:
      - db
    environment:
      - DB_HOST=db

  db:
    image: mysql:8.0
    environment:
      MYSQL_ROOT_PASSWORD: cafeteria2026
      MYSQL_DATABASE: cafeteria_escolar
    volumes:
      - mysql_data:/var/lib/mysql
    ports:
      - "3306:3306"

volumes:
  mysql_data:
```

```bash
docker-compose up -d
```

Las tablas se crean automáticamente en el primer acceso.

---

## Solución de Conectividad sin Internet (Red Local)

Esta sección describe cómo hacer que la aplicación funcione para estudiantes que **no tienen acceso a Internet**, creando una **red Wi-Fi local dedicada** que da acceso únicamente a la aplicación de la cafetería.

### Concepto

Se configura un **router o punto de acceso Wi-Fi** que:
1. Sirve como red inalámbrica a la que se conectan los celulares.
2. Enruta el tráfico a un servidor local (Raspberry Pi, laptop o PC) donde corre la app.
3. **No tiene acceso a Internet real** — solo funciona para la app de la cafetería.
4. Opcionalmente, redirige cualquier dominio (portal cautivo) a la app.

---

### Opción 1 — Raspberry Pi como servidor + Router Wi-Fi

#### Hardware necesario:
- **Raspberry Pi 4** (2GB RAM mínimo) — ~$50 USD
- **Router Wi-Fi** (cualquier router doméstico) — ~$20–40 USD
- **Tarjeta microSD** 32GB+ — ~$10 USD
- **Cable de red** para conectar Pi al router

#### Configuración paso a paso:

**Paso 1: Instalar sistema operativo en la Raspberry Pi**
```bash
# Descargar Raspberry Pi OS Lite (sin escritorio) desde raspberrypi.com
# Grabar en microSD con Raspberry Pi Imager
# Habilitar SSH en la configuración del imager
```

**Paso 2: Instalar Docker en la Raspberry Pi**
```bash
ssh pi@<ip-de-la-pi>
curl -fsSL https://get.docker.com | sh
sudo usermod -aG docker pi
sudo apt install docker-compose -y
```

**Paso 3: Clonar y correr la aplicación**
```bash
git clone <url-repo> cafeteria-escolar
cd cafeteria-escolar
docker-compose up -d
# La app corre en http://<ip-de-la-pi>:8080
```

**Paso 4: Configurar el Router Wi-Fi**
- Conectar la Raspberry Pi al router por cable ethernet.
- Configurar el router con:
  - **SSID:** `Cafeteria-SantaJuana` (sin contraseña o con una sencilla)
  - **DHCP:** Activado (asigna IPs automáticamente)
  - **IP de la Pi:** Asignar IP fija, ej: `192.168.1.100`
  - **Deshabilitar acceso a Internet** (si el router lo permite, o simplemente no conectar el WAN)

**Paso 5: Configurar DNS local (opcional pero recomendado)**

Para que `cafeteria.local` apunte a la Pi, instalar `dnsmasq` en la Pi:

```bash
sudo apt install dnsmasq -y
```

Editar `/etc/dnsmasq.conf`:
```
# Redirigir cafeteria.local a la Pi
address=/cafeteria.local/192.168.1.100
address=/cafeteria.edu.co/192.168.1.100

# Resolver cualquier dominio a la Pi (portal cautivo)
address=/#/192.168.1.100
```

```bash
sudo systemctl restart dnsmasq
```

En el router, configurar el **servidor DNS primario** como la IP de la Pi (`192.168.1.100`).

**Paso 6: Configurar Nginx como proxy y portal cautivo (opcional)**

```bash
sudo apt install nginx -y
```

Crear `/etc/nginx/sites-available/cafeteria`:
```nginx
server {
    listen 80 default_server;
    server_name _;

    # Redirigir todo al puerto 8080 de Docker
    location / {
        proxy_pass http://localhost:8080;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
    }
}
```

```bash
sudo ln -s /etc/nginx/sites-available/cafeteria /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl restart nginx
```

Ahora cualquier URL que el celular abra (`http://cualquier-cosa.com`) llegará a la app de la cafetería.

**Resultado:** El estudiante conecta su celular a `Cafeteria-SantaJuana` Wi-Fi → abre el navegador → automáticamente ve la app.

---

### Opción 2 — Laptop/PC como servidor (más sencillo)

Si no hay presupuesto para Raspberry Pi, una laptop puede hacer lo mismo:

```bash
# En la laptop (Windows con WSL2, macOS o Linux)
git clone <url-repo> cafeteria-escolar
cd cafeteria-escolar
docker-compose up -d
```

La laptop crea un **hotspot Wi-Fi** desde su configuración:
- **Windows:** Configuración → Red e Internet → Zona de cobertura móvil → Activar
- **macOS:** Preferencias del Sistema → Compartir → Compartir Internet
- **Linux:** `nmcli device wifi hotspot ssid "Cafeteria" password "cafeteria2026"`

Los celulares se conectan al hotspot y acceden a `http://<ip-de-la-laptop>:8080`.

---

### Opción 3 — Aplicación Móvil Nativa (PWA)

La aplicación puede instalarse como **Progressive Web App (PWA)** en los celulares, lo que la hace funcionar como una app nativa:

**Agregar a `index.html` y `store/index.html`:**
```html
<link rel="manifest" href="/manifest.json">
<meta name="theme-color" content="#ff3c00">
```

**Crear `/manifest.json`:**
```json
{
  "name": "Cafetería Santa Juana",
  "short_name": "CafeteriaSJ",
  "start_url": "/store/",
  "display": "standalone",
  "background_color": "#faf9f7",
  "theme_color": "#ff3c00",
  "icons": [
    { "src": "/imgs/icon.png", "sizes": "192x192", "type": "image/png" },
    { "src": "/imgs/icon.png", "sizes": "512x512", "type": "image/png" }
  ]
}
```

**Crear Service Worker para caché offline** (`/sw.js`):
```javascript
const CACHE = 'cafeteria-v1';
const ASSETS = ['/', '/store/', '/auth/', '/assets/css/main.css', '/assets/js/app.js'];

self.addEventListener('install', e => {
  e.waitUntil(caches.open(CACHE).then(c => c.addAll(ASSETS)));
});

self.addEventListener('fetch', e => {
  e.respondWith(
    caches.match(e.request).then(r => r || fetch(e.request))
  );
});
```

Con esto, la app se puede **instalar en el celular** (Android: "Agregar a pantalla de inicio", iOS: botón compartir → "Añadir a pantalla de inicio") y funciona sin necesidad de abrir el navegador.

---

### Topología de Red Recomendada para el Colegio

```
Internet (WAN)
     │
     │ (opcional, no requerido)
     │
[Router Principal del Colegio]
     │
     │ Cable ethernet
     │
[Router/AP dedicado "Cafeteria-SantaJuana"]
     │
     ├── Wi-Fi: Celulares de estudiantes
     │         (solo ven la app de la cafetería)
     │
     └── Cable ethernet
              │
         [Servidor Local]
         Raspberry Pi / Laptop
         - Docker: PHP + MySQL
         - IP fija: 192.168.1.100
         - Puerto 80 → App
         - dnsmasq → DNS local
```

### Ventajas de esta arquitectura:
- ✅ Funciona **sin Internet** completamente.
- ✅ Los datos permanecen dentro del colegio.
- ✅ Rápido: latencia de red local < 1ms.
- ✅ Bajo costo (Raspberry Pi ~$50 USD, amortizable en años).
- ✅ Escalable: si hay más estudiantes, solo se agrega otro AP.

### Limitaciones:
- ❌ Los estudiantes deben estar en el rango Wi-Fi del colegio para hacer pedidos.
- ❌ Si el servidor local se apaga, la app no funciona.
- ❌ No hay respaldo en la nube (solución: hacer backup diario de la BD).

---

## Roles de Usuario

### Usuario / Estudiante (`role = 'user'`)
- Accede a `/store/`
- Puede ver el catálogo, agregar al carrito, hacer checkout, ver sus pedidos y QRs
- No puede acceder a `/admin/`

### Administrador (`role = 'admin'`)
- Accede a `/admin/`
- Control total: productos, usuarios, pedidos, turnos, escáner
- No tiene carrito (la interfaz de la tienda no le muestra el botón de carrito)
- El admin por defecto es creado automáticamente al iniciar la app

---

## Consideraciones de Seguridad

| Aspecto | Implementación |
|---------|---------------|
| Contraseñas | bcrypt hash (no reversible) |
| Tokens | JWT HS256 con expiración de 7 días |
| Autorización | Middleware en cada endpoint admin |
| SQL Injection | PDO con prepared statements en todos los queries |
| Listado de directorios | `Options -Indexes` en `.htaccess` |
| Archivos sensibles | `.htaccess` bloquea `.log`, `.sql`, `.env`, `config.php` |
| Timing attacks | `hash_equals()` para comparar firmas JWT |
| CORS | Configurado en `api/index.php` (ajustar en producción) |
| XSS | `escapeStr()` en el frontend antes de insertar HTML |
| Rate limiting | No implementado (recomendado agregar en producción) |
| HTTPS | No incluido en Docker (usar Nginx + Let's Encrypt en producción) |

---

## Créditos y Contexto Académico

- **Institución:** Colegio Santa Juana de Lestonnac
- **Propósito:** Proyecto escolar para modernizar la gestión de la cafetería
- **Zona horaria:** `America/Bogota` (UTC-5)
- **Moneda:** Pesos Colombianos (COP)
- **Idioma:** Español
- **Año:** 2026

---

*Este sistema fue diseñado para funcionar en un entorno escolar real, con enfoque en simplicidad de uso para estudiantes y eficiencia operativa para el personal de la cafetería.*