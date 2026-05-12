Módulo Fecha Estimada de Entrega (delivery_estimate)
-------

Calcula y notifica la fecha estimada de entrega de los pedidos.

### Qué hace

- Calcula la fecha estimada al crear el pedido según el stock de cada producto.
- Permite edición manual de la fecha desde la ficha del pedido en admin.
- Envía email al cliente (multi-idioma) cuando se modifica la fecha desde admin.

### Reglas de cálculo

Se suma a la fecha de compra un número de días configurable según el peor caso de stock del pedido:

- Todos los productos con stock disponible      → `DELIVERY_ESTIMATE_DAYS_IN_STOCK` (por defecto 2)
- Algún producto con stock < 0 y > -800         → `DELIVERY_ESTIMATE_DAYS_NO_STOCK` (por defecto 14)
- Algún producto con stock = -800 (bajo pedido) → `DELIVERY_ESTIMATE_DAYS_BACKORDER` (por defecto 30)
- Cantidad pedida > stock disponible            → `DELIVERY_ESTIMATE_DAYS_NO_STOCK`

La opción `DELIVERY_ESTIMATE_BUSINESS_DAYS` permite contar sólo días laborables (excluye sábados y domingos).

### Tablas

- `orders_delivery_estimate`: histórico de fechas estimadas por pedido (automáticas y manuales).
- `delivery_estimate_email`: plantillas de asunto y cuerpo del email por idioma.

### Email

El asunto y el cuerpo del email son configurables por idioma desde esta pantalla (el cuerpo usa editor TinyMCE para permitir formato HTML). Botón "Vista previa" en la cabecera del bloque para ver cómo queda el email con datos de ejemplo antes de guardar. Shortcodes disponibles:

- `{ORDERS_ID}`     — ID del pedido
- `{CUSTOMER_NAME}` — Nombre del cliente
- `{FECHA_ENTREGA}` — Fecha estimada (dd/mm/aaaa)
- `{COMENTARIO}`    — Comentario que ha introducido el admin al cambiar la fecha
- `{STORE_NAME}`    — Nombre de la tienda
- `{LINK_PEDIDO}`   — URL absoluta al detalle del pedido en la cuenta del cliente (`account_history_info.php?order_id=...`)

### Instalación

La instalación es automática: al entrar la primera vez en `delivery_estimate.php` desde el admin se crean las tablas, el grupo de configuración y las plantillas por defecto para cada idioma instalado.
