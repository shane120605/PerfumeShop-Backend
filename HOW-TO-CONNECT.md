# Connect the frontend to the backend

You need **two terminals** open at the same time: one for the backend, one for the frontend.

## 1. Backend (Laravel) – folder `backend`
```bash
cd backend
composer install
copy .env.example .env          # macOS/Linux: cp .env.example .env
php artisan key:generate
```
Create the database `perfume_shop` in phpMyAdmin (XAMPP: start **MySQL** first), then open `backend/.env` and set
`DB_USERNAME` and `DB_PASSWORD` (XAMPP default: `root` and an empty password). Then:
```bash
php artisan migrate --seed       # tables + 6 categories + 12 perfumes
php artisan storage:link         # so uploaded product photos can be shown
php artisan shop:create-admin    # asks for the admin name, email and a password (8+ characters)
php artisan serve                # keep this terminal open -> http://localhost:8000
```
Check it works: open http://localhost:8000/api/products in the browser. You should see the 12 perfumes as JSON.

## 2. Frontend (React) – folder `frontend`
Make sure the file `frontend/.env` has this line:
```
VITE_API_URL=http://localhost:8000/api
```
```bash
cd frontend
npm install
npm run dev                      # http://localhost:5173
```
Put your photos in `frontend/public/images/` (`hero.png`, `perfume-1.png` … `perfume-4.png`).

## 3. Try it
| What | Where |
|---|---|
| Shop (products from the database) | http://localhost:5173/shop |
| Register a customer (password 8+ characters), then log in with the **email** | /register, /login |
| Place an order (Cash on Delivery / GCash) | add to cart → Checkout |
| Admin panel | http://localhost:5173/admin/login |

## What is connected
* Products, categories, stock → database (Home, Shop, Product Details)
* Register / Login / Logout → Laravel Sanctum token (kept in the browser, restored on refresh)
* Checkout → saves the order, reduces stock. The server calculates prices; the browser sends only product ids and quantities
* My Orders / Order Details → the logged-in customer's own orders
* Admin: dashboard, products (with real image upload), orders (status), users, sales report
* The shopping cart itself stays in the browser until the order is placed

## If something goes wrong
| You see | Fix |
|---|---|
| "Cannot reach the server" | The backend is not running. Start `php artisan serve`, and check `VITE_API_URL`. Restart `npm run dev` after editing `.env`. |
| Browser console says "blocked by CORS" | In `backend/.env` set `CORS_ALLOWED_ORIGINS=http://localhost:5173,http://127.0.0.1:5173`, then run `php artisan config:clear`. |
| Admin login says the password is incorrect | Run `php artisan shop:create-admin` again, or log in with the admin **email**. |
| Product list is empty | Run `php artisan migrate --seed`. |
| Uploaded product photo does not show | Run `php artisan storage:link` and make sure `APP_URL=http://localhost:8000` in `backend/.env`. |
| More about backend errors | `backend/docs/TROUBLESHOOTING.md` |
