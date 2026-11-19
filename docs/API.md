# API Documentation – Perfume Shop

Base URL (local): `http://localhost:8000/api`
All requests/answers are JSON. Send `Accept: application/json` (the API forces JSON answers anyway).

## Authentication
After login/register you get a **token**. Send it on protected routes:

```
Authorization: Bearer <token>
```

Tokens expire after `SANCTUM_TOKEN_EXPIRATION` minutes (default 7 days). Logout deletes the token.

| Role | Can use |
|---|---|
| Guest | auth routes, categories, products |
| `customer` | + cart, orders (own only) |
| `admin` | + everything under `/api/admin/*` (admins cannot use cart/checkout) |

## Response formats

**Single item / list:** `{ "data": ... }`  
**Paginated list:** `{ "data": [...], "links": {...}, "meta": { "current_page", "last_page", "per_page", "total", ... } }`  
**Message:** `{ "message": "..." }`

**Validation error – 422**
```json
{
  "message": "The price field must be at least 0.01.",
  "errors": { "price": ["The price field must be at least 0.01."] }
}
```

| Status | Meaning |
|---|---|
| 200 / 201 | OK / created |
| 401 | Missing, invalid or expired token |
| 403 | Logged in but not allowed (wrong role) |
| 404 | URL or item not found |
| 409 | Conflict (e.g. category still has products) |
| 422 | Validation failed (including "not enough stock") |
| 429 | Too many requests (rate limit) |

---

## Endpoint list

### Auth
| Method | URL | Who | Description |
|---|---|---|---|
| POST | `/auth/register` | guest | Create a customer account (returns token) |
| POST | `/auth/login` | guest | Customer login (email + password) |
| POST | `/auth/admin/login` | guest | Admin login (admin email or name + password) |
| POST | `/auth/logout` | logged in | Delete current token |
| GET | `/auth/me` | logged in | Current user |

### Catalog (public)
| Method | URL | Description |
|---|---|---|
| GET | `/categories` | All categories with `products_count` |
| GET | `/categories/{id}` | One category |
| GET | `/products` | Product list (search, filter, sort, pagination) |
| GET | `/products/{id}` | Product details |

`GET /products` query parameters: `search`, `category` (name or slug; `All` = no filter), `category_id`, `min_price`, `max_price`, `in_stock=1`, `low_stock=1`, `sort` (`id|name|price|stock|created_at`), `direction` (`asc|desc`), `per_page` (1–100, default 12), `page`.

### Cart (customer)
| Method | URL | Description |
|---|---|---|
| GET | `/cart` | My cart (server calculates `subtotal`) |
| POST | `/cart/items` | `{product_id, quantity}` – adds to the existing quantity |
| PUT | `/cart/items/{productId}` | `{quantity}` – sets the quantity |
| DELETE | `/cart/items/{productId}` | Remove one product |
| DELETE | `/cart` | Empty the cart |

### Orders (customer)
| Method | URL | Description |
|---|---|---|
| POST | `/orders` | Checkout (see below) |
| GET | `/orders` | My order history, newest first (`per_page`, `page`) |
| GET | `/orders/{orderNumber}` | One of my orders |
| POST | `/orders/{orderNumber}/cancel` | Cancel my order while it is `Pending` (stock is returned) |

### Admin (`role: admin`)
| Method | URL | Description |
|---|---|---|
| GET | `/admin/dashboard` | Totals + 5 recent orders |
| GET | `/admin/reports/sales?from=&to=` | Sales report (A6) |
| GET/POST | `/admin/categories` | List / create |
| GET/PUT/PATCH/DELETE | `/admin/categories/{id}` | Show / update / delete (409 if it has products) |
| GET | `/admin/products`, `/admin/products/{id}` | Same as the public product endpoints |
| POST | `/admin/products` | Create product (JSON or `multipart/form-data` with `image`) |
| PUT/PATCH/**POST** | `/admin/products/{id}` | Update product. Use **POST** when uploading an image |
| DELETE | `/admin/products/{id}` | Delete product |
| GET/POST | `/admin/users` | List (`search`, `role`, `per_page`) / create |
| GET/PUT/PATCH/DELETE | `/admin/users/{id}` | Show / update / delete |
| GET | `/admin/orders` | List (`status`, `search`, `from`, `to`, `per_page`) |
| GET | `/admin/orders/{orderNumber}` | Order details |
| PATCH | `/admin/orders/{orderNumber}/status` | `{ "status": "Delivered" }` |

---

## Examples

### Register
`POST /api/auth/register`
```json
{ "name": "Juan Dela Cruz", "email": "juan@example.com", "password": "my-long-password" }
```
**201**
```json
{
  "message": "Account created successfully.",
  "token": "1|xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx",
  "token_type": "Bearer",
  "expires_in": 604800,
  "user": { "id": 7, "name": "Juan Dela Cruz", "email": "juan@example.com", "role": "Customer", "created_at": "2026-11-02T10:15:00+00:00" }
}
```

### Login (wrong password) – 422
```json
{ "message": "The email or password is incorrect.", "errors": { "identifier": ["The email or password is incorrect."] } }
```

### Admin login
`POST /api/auth/admin/login`
```json
{ "identifier": "admin@example.com", "password": "your-admin-password" }
```

### List products with filters
`GET /api/products?search=rose&category=Floral&sort=price&direction=desc&per_page=12`
```json
{
  "data": [
    {
      "id": 1, "name": "Rose Éclat", "category": "Floral", "category_id": 1,
      "price": 2450.0, "stock": 10, "size": "50 ml",
      "description": "A radiant bouquet of Bulgarian rose...",
      "image": 1, "imageUrl": null,
      "notes": { "top": "Bergamot", "heart": "Rose, Peony", "base": "White Musk" },
      "created_at": "2026-11-02T09:00:00+00:00"
    }
  ],
  "links": { "first": "...", "last": "...", "prev": null, "next": null },
  "meta": { "current_page": 1, "last_page": 1, "per_page": 12, "total": 1 }
}
```
`image` = the bundled photo number (1–4) the frontend already has; `imageUrl` is set when the admin uploaded a photo.

### Create a product (admin)
`POST /api/admin/products` (`Authorization: Bearer <admin token>`)
```json
{ "name": "Peony Blush", "category": "Floral", "price": 2250, "stock": 11, "size": "50 ml",
  "description": "Juicy pink peony.", "notes": { "top": "Lychee", "heart": "Peony", "base": "Woods" } }
```
With an image (curl):
```bash
curl -X POST http://localhost:8000/api/admin/products \
  -H "Accept: application/json" -H "Authorization: Bearer <token>" \
  -F "name=Peony Blush" -F "category_id=1" -F "price=2250" -F "stock=11" -F "image=@bottle.jpg"
```
Images: jpg, jpeg, png, webp, max 2 MB. Stored in `storage/app/public/products`, served from `/storage/products/...` (run `php artisan storage:link`).

### Checkout – place an order
`POST /api/orders` (customer token). **Do not send prices or totals – they are ignored.**
```json
{
  "full_name": "Juan Dela Cruz",
  "address": "24 Rizal Street, Barangay Poblacion",
  "city": "Liloan, Cebu",
  "phone": "0917 123 4567",
  "payment_method": "Cash on Delivery",
  "items": [ { "product_id": 1, "quantity": 1 }, { "product_id": 4, "quantity": 1 } ]
}
```
`payment_method` must be `Cash on Delivery` or `GCash / Online Payment`.  
If `items` is left out, the server-side cart is used.

**201**
```json
{
  "message": "Order placed successfully.",
  "data": {
    "no": 12347, "customer": "Juan Dela Cruz", "email": "juan@example.com", "phone": "0917 123 4567",
    "address": "24 Rizal Street, Barangay Poblacion", "city": "Liloan, Cebu", "date": "2026-11-02",
    "status": "Pending", "payment": "Cash on Delivery",
    "subtotal": 4430.0, "shipping_fee": 0.0, "total": 4430.0,
    "items": [
      { "productId": 1, "name": "Rose Éclat", "qty": 1, "price": 2450.0, "lineTotal": 2450.0 },
      { "productId": 4, "name": "Vanilla Bloom", "qty": 1, "price": 1980.0, "lineTotal": 1980.0 }
    ]
  }
}
```

**Not enough stock – 422 (nothing is saved)**
```json
{
  "message": "Only 1 of Amber Muse left in stock.",
  "errors": { "items": ["Only 1 of Amber Muse left in stock."] }
}
```

### Update order status (admin)
`PATCH /api/admin/orders/12347/status`
```json
{ "status": "Delivered" }
```
Allowed: `Pending → Delivered`, `Pending → Cancelled` (stock is returned). `Delivered` and `Cancelled` are final (422 otherwise).

### Dashboard (admin)
`GET /api/admin/dashboard`
```json
{ "data": { "total_products": 12, "total_orders": 12, "total_users": 6, "total_customers": 5,
            "total_sales": 38090.0, "pending_orders": 3, "low_stock_products": 3, "recent_orders": [ ... ] } }
```
`total_sales` does not count cancelled orders.

### Sales report (admin)
`GET /api/admin/reports/sales?from=2025-05-01&to=2025-05-31`
```json
{ "data": { "from": "2025-05-01", "to": "2025-05-31", "unit": "week",
            "total_sales": 38090.0, "total_orders": 11, "average_order": 3462.73,
            "buckets": [ { "label": "May 1", "start": "2025-05-01", "value": 6850.0 }, ... ] } }
```
Ranges up to ~3 months return weekly buckets (`unit: "week"`), longer ranges monthly buckets.

### Create a user (admin)
`POST /api/admin/users`
```json
{ "name": "Maria Lopez", "email": "maria@example.com", "role": "Customer", "password": "a-long-password" }
```
`role` accepts `Admin`/`Customer` (any letter case). On update, leave `password` empty to keep the old one.
Rules: you cannot delete yourself or remove your own admin role.

---

## Security notes
- Passwords are hashed (bcrypt). Minimum 8 characters.
- Registration always creates a `customer`; admin accounts are created only by `php artisan shop:create-admin`, the seeder (from `.env`) or by an existing admin.
- Prices, totals and stock are decided only on the server, inside a database transaction with row locks.
- Login/register are rate limited (10 per minute per email+IP); every API route is limited to 120 per minute.
- CORS only allows the origins in `CORS_ALLOWED_ORIGINS`.
