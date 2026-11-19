# Connecting your React + Vite frontend to this backend

Your current frontend keeps everything in the browser (localStorage). This guide shows what must change so it uses the Laravel API instead.
**Nothing in your design changes** – only where the data comes from.

> I could not open your GitHub repository, so this guide is written for the frontend I generated earlier
> (`src/context/StoreContext.jsx`, `AuthContext.jsx`, `CartContext.jsx`, pages `C1…C10`, `A1…A6`).
> If you changed those files, adjust the names accordingly.

## Step 1 – Start both servers
```bash
# terminal 1 (backend folder)
php artisan serve              # http://localhost:8000

# terminal 2 (frontend folder)
npm run dev                    # http://localhost:5173
```
CORS already allows `http://localhost:5173` and `http://127.0.0.1:5173`.

## Step 2 – Add the API client
1. Copy `frontend-integration/apiClient.js` to `src/api/apiClient.js` in your frontend project.
2. Create a file named `.env` in the frontend root (next to `package.json`) with:
   ```
   VITE_API_URL=http://localhost:8000/api
   ```
3. Restart `npm run dev` (Vite reads `.env` only at start).

## Step 3 – What to change in the frontend

| Frontend file | Change |
|---|---|
| `context/AuthContext.jsx` | `login()` → `await api.login(email, password)` (customer) and `api.adminLogin(...)` for the admin page; store the returned user in state. `logout()` → `await api.logout()`. On start, if a token exists call `api.me()` to restore the session. Listen for the `auth:expired` window event to log the user out. **Customers log in with their email** (not their name). |
| `pages/customer/C2_RegisterPage.jsx` | Call `api.register({ name, email, password })`; show `err.field('email')` for server errors. Password minimum is **8** (frontend currently says 6 – update the message/check). |
| `context/StoreContext.jsx` | Replace the localStorage products/users/orders with API calls: `api.products()`, `api.admin.users()`, `api.admin.orders()` (load when needed, e.g. in `useEffect` of each page). `addProduct/updateProduct/deleteProduct` → `api.admin.createProduct / updateProduct / deleteProduct`, `addUser/…` → `api.admin.createUser / updateUser / deleteUser`. After each change, reload the list. Remove `resetDemoData` (the sidebar **Settings** button has no backend equivalent – remove or hide it). |
| `components/admin/ProductForm.jsx` | Send the **File** object (`e.target.files[0]`) with `api.admin.createProduct(product, file)` instead of a base64 data URL. The category select can keep sending the category **name** (`category`). |
| `context/CartContext.jsx` | **No change required.** Keep the localStorage cart and send the items at checkout. (Optional: use the server cart `api.cart()/addToCart()`.) |
| `pages/customer/C7_CheckoutPage.jsx` | `placeOrder` → `const order = await api.placeOrder(form, payment, lines.map(l => ({ productId: l.product.id, qty: l.qty })))` then `clearCart()` and `navigate('/order-confirmation', { state: { order } })`. **Never send prices.** Show `err.errors.items` (stock problems) to the customer. |
| `pages/customer/C9_MyOrdersPage.jsx`, `C10_OrderDetailsPage.jsx` | Use `api.myOrders()` and `api.myOrder(no)`. The order object has the same keys as before (`no`, `date`, `status`, `payment`, `items[].qty/price/name`, `total`). |
| `pages/admin/A2_DashboardPage.jsx` | `api.admin.dashboard()` → `total_products`, `total_orders`, `total_users`, `total_sales`, `recent_orders`. |
| `pages/admin/A3…A5…` | List pages use `api.admin.products()/orders()/users()`. Delete buttons call the delete endpoint and then reload. |
| `pages/admin/A4a_ViewOrderPage.jsx` | “Save Status” → `api.admin.updateOrderStatus(no, status)`. Show the 422 message if the change is not allowed (delivered/cancelled orders are final). |
| `pages/admin/A6_SalesReportPage.jsx` | `api.admin.salesReport(from, to)` → `buckets[]` (`label`, `value`), `total_sales`, `total_orders`, `average_order`. |
| `components/common/ProductImage.jsx` | Already supports `imageUrl`; **no change**. |

### Response shapes (same keys as your current data)
- Product: `id, name, category, price, stock, size, description, image (1-4), imageUrl, notes{top,heart,base}`
- Order: `no, customer, email, phone, address, city, date, status, payment, items[{productId,name,qty,price,lineTotal}], total`
- User: `id, name, email, role ("Admin"|"Customer")`

`api.products()` already returns the plain array (the client unwraps `data`). If you call `fetch` yourself, remember lists are inside `data`.

### Example – loading products in a page
```jsx
import { useEffect, useState } from 'react'
import { api } from '../../api/apiClient'

function useProducts() {
  const [products, setProducts] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  useEffect(() => {
    api.products().then(setProducts).catch((e) => setError(e.message)).finally(() => setLoading(false))
  }, [])
  return { products, loading, error }
}
```

### Example – checkout
```jsx
try {
  const order = await api.placeOrder(form, payment, lines.map((l) => ({ productId: l.product.id, qty: l.qty })))
  clearCart()
  navigate('/order-confirmation', { state: { order } })
} catch (err) {
  if (err.status === 422) setErrors({ ...err.errors, items: err.errors?.items })
  else setErrors({ general: err.message })
}
```

## Notes
- **Loading + error states:** API calls are asynchronous, so add small “Loading…” and error messages where lists are shown.
- **Token storage:** the example client keeps the token in `localStorage` (simple for a college project). Anyone able to run JavaScript on your page (XSS) could read it, so never render unescaped user text.
- **Demo accounts:** the old demo logins (`juan@shop.com`…) only exist if you run `php artisan db:seed --class=DemoDataSeeder`. Normally create your admin with `php artisan shop:create-admin`.
- **Production:** set `APP_ENV=production`, `APP_DEBUG=false`, use HTTPS, and put your real frontend URL in `CORS_ALLOWED_ORIGINS`.
