
const BASE_URL = (import.meta.env.VITE_API_URL || 'http://localhost:8000/api').replace(/\/$/, '')
const TOKEN_KEY = 'perfume-shop:token'

export const getToken = () => localStorage.getItem(TOKEN_KEY)
export const setToken = (token) => (token ? localStorage.setItem(TOKEN_KEY, token) : localStorage.removeItem(TOKEN_KEY))

/** Error thrown for every non-2xx answer. `errors` = field errors from Laravel, e.g. { email: ['...'] } */
export class ApiError extends Error {
  constructor(message, status, errors = {}) {
    super(message)
    this.name = 'ApiError'
    this.status = status
    this.errors = errors
  }
  /** first message of a field, e.g. err.field('email') */
  field(name) {
    return this.errors?.[name]?.[0] || ''
  }
}

async function request(path, { method = 'GET', body, query, form } = {}) {
  const url = new URL(BASE_URL + path)
  Object.entries(query || {}).forEach(([k, v]) => {
    if (v !== undefined && v !== null && v !== '') url.searchParams.set(k, v)
  })

  const headers = { Accept: 'application/json' }
  const token = getToken()
  if (token) headers.Authorization = `Bearer ${token}`

  const options = { method, headers }
  if (form) {
    options.body = form // FormData: the browser sets the Content-Type (with boundary) itself
  } else if (body !== undefined) {
    headers['Content-Type'] = 'application/json'
    options.body = JSON.stringify(body)
  }

  let response
  try {
    response = await fetch(url, options)
  } catch {
    throw new ApiError('Cannot reach the server. Is the Laravel backend running?', 0)
  }

  const data = await response.json().catch(() => ({}))

  if (!response.ok) {
    if (response.status === 401 && token) {
      setToken(null) // token expired or revoked
      window.dispatchEvent(new Event('auth:expired'))
    }
    throw new ApiError(data.message || 'Something went wrong.', response.status, data.errors)
  }
  return data
}

/** builds FormData for product create/update (with an optional image File) */
export function productFormData(p, imageFile) {
  const form = new FormData()
  ;['name', 'category', 'category_id', 'price', 'stock', 'size', 'description'].forEach((k) => {
    if (p[k] !== undefined && p[k] !== null) form.append(k, p[k])
  })
  if (imageFile) form.append('image', imageFile)
  return form
}

export const api = {
  // ----- auth -----
  async register({ name, email, password }) {
    return request('/auth/register', { method: 'POST', body: { name, email, password } })
  },
  async login(identifier, password) {
    const res = await request('/auth/login', { method: 'POST', body: { identifier, password } })
    setToken(res.token)
    return res.user
  },
  async adminLogin(identifier, password) {
    const res = await request('/auth/admin/login', { method: 'POST', body: { identifier, password } })
    setToken(res.token)
    return res.user
  },
  async logout() {
    try {
      await request('/auth/logout', { method: 'POST' })
    } finally {
      setToken(null)
    }
  },
  me: () => request('/auth/me').then((r) => r.data),

  // ----- catalog (public) -----
  categories: () => request('/categories').then((r) => r.data),
  /** params: search, category, min_price, max_price, in_stock, sort, direction, per_page, page */
  products: (params = {}) => request('/products', { query: { per_page: 100, ...params } }).then((r) => r.data),
  product: (id) => request(`/products/${id}`).then((r) => r.data),

  // ----- cart (server side, optional) -----
  cart: () => request('/cart').then((r) => r.data),
  addToCart: (product_id, quantity = 1) => request('/cart/items', { method: 'POST', body: { product_id, quantity } }),
  setCartQuantity: (productId, quantity) => request(`/cart/items/${productId}`, { method: 'PUT', body: { quantity } }),
  removeFromCart: (productId) => request(`/cart/items/${productId}`, { method: 'DELETE' }),

  // ----- customer orders -----
  /** shipping = { fullName, address, city, phone }, items = [{ productId, qty }]  (NO prices are sent) */
  placeOrder: (shipping, payment, items) =>
    request('/orders', {
      method: 'POST',
      body: {
        full_name: shipping.fullName,
        address: shipping.address,
        city: shipping.city,
        phone: shipping.phone,
        payment_method: payment,
        items: items.map((i) => ({ product_id: i.productId, quantity: i.qty })),
      },
    }).then((r) => r.data),
  myOrders: () => request('/orders', { query: { per_page: 100 } }).then((r) => r.data),
  myOrder: (no) => request(`/orders/${no}`).then((r) => r.data),
  cancelMyOrder: (no) => request(`/orders/${no}/cancel`, { method: 'POST' }).then((r) => r.data),

  // ----- admin -----
  admin: {
    dashboard: () => request('/admin/dashboard').then((r) => r.data),
    salesReport: (from, to) => request('/admin/reports/sales', { query: { from, to } }).then((r) => r.data),

    products: (params = {}) => request('/admin/products', { query: { per_page: 100, ...params } }).then((r) => r.data),
    createProduct: (product, imageFile) =>
      request('/admin/products', { method: 'POST', form: productFormData(product, imageFile) }).then((r) => r.data),
    // POST (not PUT) so that a file can be uploaded
    updateProduct: (id, product, imageFile) =>
      request(`/admin/products/${id}`, { method: 'POST', form: productFormData(product, imageFile) }).then((r) => r.data),
    deleteProduct: (id) => request(`/admin/products/${id}`, { method: 'DELETE' }),

    categories: () => request('/admin/categories').then((r) => r.data),
    createCategory: (body) => request('/admin/categories', { method: 'POST', body }).then((r) => r.data),
    updateCategory: (id, body) => request(`/admin/categories/${id}`, { method: 'PUT', body }).then((r) => r.data),
    deleteCategory: (id) => request(`/admin/categories/${id}`, { method: 'DELETE' }),

    users: (params = {}) => request('/admin/users', { query: { per_page: 100, ...params } }).then((r) => r.data),
    createUser: (body) => request('/admin/users', { method: 'POST', body }).then((r) => r.data),
    updateUser: (id, body) => request(`/admin/users/${id}`, { method: 'PUT', body }).then((r) => r.data),
    deleteUser: (id) => request(`/admin/users/${id}`, { method: 'DELETE' }),

    orders: (params = {}) => request('/admin/orders', { query: { per_page: 100, ...params } }).then((r) => r.data),
    order: (no) => request(`/admin/orders/${no}`).then((r) => r.data),
    updateOrderStatus: (no, status) =>
      request(`/admin/orders/${no}/status`, { method: 'PATCH', body: { status } }).then((r) => r.data),
  },
}
