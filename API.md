# Zeker Gemak API Documentation

Welcome to the API documentation for the **Zeker Gemak** application. This API provides resources for user management, e-commerce catalog traversal, printer/product compatibility mapping, ordering/checkout (including Mollie payment webhook integration), and customer contact workflows.

---

## Authentication & Account Management

### Register User
* **Endpoint:** `POST /api/register`
* **Headers:** `Content-Type: application/json`, `Accept: application/json`
* **Request Body:**
  ```json
  {
    "name": "John Doe",
    "first_name": "John",
    "last_name": "Doe",
    "email": "john.doe@example.com",
    "password": "Password123!",
    "password_confirmation": "Password123!",
    "phone": "+31612345678",
    "company": "Example Company B.V.",
    "vat_number": "NL123456789B01",
    "kvk_number": "12345678",
    "street_address": "Main Street 12",
    "postcode": "1234AB",
    "city": "Amsterdam",
    "country_id": 1,
    "state_id": 12
  }
  ```
* **Response (201 Created):**
  ```json
  {
    "message": "Account created successfully.",
    "user": {
      "id": 42,
      "name": "John Doe",
      "email": "john.doe@example.com",
      "first_name": "John",
      "last_name": "Doe"
    },
    "access_token": "1|abcdef123456..."
  }
  ```

---

### Login User
* **Endpoint:** `POST /api/login`
* **Headers:** `Content-Type: application/json`, `Accept: application/json`
* **Request Body:**
  ```json
  {
    "email": "john.doe@example.com",
    "password": "Password123!",
    "remember": true
  }
  ```
* **Response (200 OK):**
  ```json
  {
    "success": true,
    "user": {
      "id": 42,
      "name": "John Doe",
      "email": "john.doe@example.com"
    },
    "access_token": "1|abcdef123456...",
    "message": "Login successful"
  }
  ```

---

### Logout User
* **Endpoint:** `POST /api/account/logout`
* **Headers:** `Authorization: Bearer <access_token>`, `Accept: application/json`
* **Response (200 OK):**
  ```json
  {
    "success": true,
    "message": "Logout successful"
  }
  ```

---

### Password Reset Link
* **Endpoint:** `POST /api/reset/password`
* **Request Body:**
  ```json
  {
    "email": "john.doe@example.com"
  }
  ```
* **Response (302 Redirect or 200 OK):** Sent to user email.

---

### Register Metadata (Countries & States)
* **Endpoint:** `GET /api/register/data`
* **Description:** Retrieves the list of available countries along with their provinces/states.

---

## User Profile & Addresses (Authenticated)

All endpoints in this section require `Authorization: Bearer <access_token>`.

### Get User Profile
* **Endpoint:** `GET /api/user/profile`

### Update User Profile
* **Endpoint:** `PUT /api/user/profile`
* **Request Body:**
  ```json
  {
    "name": "John Doe Updated",
    "email": "john.new@example.com"
  }
  ```

### Change Password
* **Endpoint:** `PUT /api/user/profile/password`
* **Request Body:**
  ```json
  {
    "current_password": "Password123!",
    "password": "NewPassword123!",
    "password_confirmation": "NewPassword123!"
  }
  ```

### Get My Addresses
* **Endpoint:** `GET /api/user/addresses`

### Save or Update My Address
* **Endpoint:** `POST /api/user/addresses`

---

## Favorites Management (Authenticated)

All endpoints in this section require `Authorization: Bearer <access_token>`.

### List Favorite Products
* **Endpoint:** `GET /api/user/favorite-products`

### Add Product to Favorites
* **Endpoint:** `POST /api/user/favorite-products/{type}/{id}`
* **Route Params:**
  * `type`: Type of product (`simple` or `variable`)
  * `id`: The integer ID of the product
* **Response (200 OK):**
  ```json
  {
    "success": true,
    "message": "Product added to favorites."
  }
  ```

### Remove Product from Favorites
* **Endpoint:** `DELETE /api/user/favorite-products/{type}/{id}`

### Check if Product is in Favorites
* **Endpoint:** `GET /api/user/favorite-products/{type}/{id}/check`

---

## Products Catalog & Compatibility

### Get Catalog Filters
* **Endpoint:** `GET /api/filters`
* **Description:** Returns the filter metadata needed to build the storefront filter UI. Use this endpoint to render category trees, sort options, product type options, price ranges, brands, and product attribute filters.
* **Query Parameters:**
  * `lang`: Optional locale string (e.g. `nl` or `en`)
* **Response (200 OK):**
  ```json
  {
    "data": {
      "types": [
        { "value": "simple", "label": "Simple" },
        { "value": "variable", "label": "Variable" }
      ],
      "sort": [
        { "value": "latest", "label": "Latest" },
        { "value": "price_asc", "label": "Price Low to High" }
      ],
      "categories": [
        {
          "id": 1,
          "name": "Labels",
          "slug": "labels",
          "count": 24,
          "translations": {
            "nl": { "name": "Etiketten", "slug": "etiketten" },
            "en": { "name": "Labels", "slug": "labels" }
          },
          "categories": [
            {
              "id": 12,
              "name": "Thermal Labels",
              "slug": "thermal-labels",
              "count": 8,
              "children": []
            }
          ]
        }
      ],
      "filters": [
        {
          "key": "price",
          "label": "Price",
          "type": "range",
          "query": {
            "min": "price_min",
            "max": "price_max"
          },
          "min": 0,
          "max": 250
        },
        {
          "key": "catalog_brand",
          "label": "Brand",
          "type": "multi_select",
          "query": "brand",
          "options": [
            { "value": "brother", "label": "Brother", "count": 12 }
          ]
        }
      ],
      "meta": {
        "afwerking": [
          { "value": "mat", "label": "Mat" }
        ]
      }
    }
  }
  ```

### List Categories
* **Endpoint:** `GET /api/categories`
* **Description:** Returns category groups and nested categories with product counts. Useful for category archive pages and navigation menus.
* **Query Parameters:**
  * `lang`: Optional locale string (e.g. `nl` or `en`)

### List Products in a Category
* **Endpoint:** `GET /api/categories/{slug}/products`
* **Description:** Returns products assigned to a category slug.
* **Route Params:**
  * `slug`: Category slug
* **Query Parameters:**
  * `per_page`: Number of products per page
  * `page`: Pagination page number

### List/Paginate Products
* **Endpoint:** `GET /api/products`
* **Description:** Returns paginated active products from the catalog search index. Use this endpoint for the main storefront listing, search results, category pages, and combined filters.
* **Query Parameters:**
  * `lang`: String locale (e.g. `nl` or `en`)
  * `search`: Search query string
  * `page`: Pagination page number. Defaults to `1`
  * `per_page`: Products per page. Defaults to `12`, maximum `100`
  * `sort`: Sort option. Allowed values: `latest`, `oldest`, `title_asc`, `title_desc`, `price_asc`, `price_desc`
  * `type`: Product type. Allowed values: `simple`, `variable`, `group`
  * `product_type`: Alias for `type`
  * `category`: Filter by category slug. Multiple values may be comma-separated
  * `category_slug`: Alias for `category`
  * `category_id`: Filter by category ID. Multiple values may be comma-separated
  * `category_path`: Filter by nested category path, such as `labels/thermal-labels`
  * `category_paths`: Alias for `category_path`
  * `brand`: Filter by product brand. Multiple values may be comma-separated
  * `catalog_brand`: Alias for `brand`
  * `price_min`: Minimum product price
  * `price_max`: Maximum product price
  * `in_stock`: Stock filter. Use `1`, `true`, `yes`, or `on` for in-stock products; use `0`, `false`, `no`, or `off` for out-of-stock products
  * `id`: Filter by product ID. Multiple values may be comma-separated
  * `slug`: Filter by product slug. Multiple values may be comma-separated
  * `article_number`: Filter by article number. Multiple values may be comma-separated
  * `afwerking`: Filter by finishing value. Multiple values may be comma-separated
  * `lijm`: Filter by adhesive value. Multiple values may be comma-separated
  * `materiaal-code`: Filter by material code. Multiple values may be comma-separated
  * `printmethode`: Filter by print method. Multiple values may be comma-separated
  * `breedte`: Filter by exact width option
  * `breedte_min`: Minimum width
  * `breedte_max`: Maximum width
  * `hoogte`: Filter by exact height option
  * `hoogte_min`: Minimum height
  * `hoogte_max`: Maximum height
  * `kern`: Filter by exact core option
  * `kern_min`: Minimum core size
  * `kern_max`: Maximum core size
  * `buiten-diameter`: Filter by exact outer diameter option
  * `buiten-diameter_min`: Minimum outer diameter
  * `buiten-diameter_max`: Maximum outer diameter
  * `detectie`: Filter by detection value. Multiple values may be comma-separated
  * `merken`: Filter by compatible brand/mark value. Multiple values may be comma-separated
* **Examples:**
  ```http
  GET /api/products?lang=nl&category=etiketten&page=1
  GET /api/products?lang=nl&search=brother&category=etiketten&sort=price_asc&in_stock=1
  GET /api/products?brand=brother,dymo&price_min=10&price_max=50
  GET /api/products?category_path=labels/thermal-labels
  GET /api/products?breedte_min=50&breedte_max=100&hoogte_min=20
  ```
* **Response (200 OK):**
  ```json
  {
    "data": [
      {
        "id": 102,
        "model_id": 102,
        "type": "simple",
        "is_group_product": false,
        "api_path_by_id": "/api/products/simple/102",
        "api_path_by_slug": "/api/products/simple/slug/brother-dk-11201",
        "title": "Brother DK-11201",
        "slug": "brother-dk-11201",
        "sku": "DK-11201",
        "price": 12.95,
        "stock": 25,
        "in_stock": true,
        "main_image": "https://example.com/storage/..."
      }
    ],
    "links": {
      "first": "https://example.com/api/products?page=1",
      "last": "https://example.com/api/products?page=4",
      "prev": null,
      "next": "https://example.com/api/products?page=2"
    },
    "meta": {
      "current_page": 1,
      "from": 1,
      "last_page": 4,
      "per_page": 12,
      "to": 12,
      "total": 48,
      "in_stock_count": 42
    }
  }
  ```

### Frontend Filter Flow
* Load `GET /api/filters?lang=nl` to render the filter sidebar/menu.
* Store selected filter values in the Next.js URL query string.
* Pass selected filters to `GET /api/products`.
* Use the `query` field from each `/api/filters` item when building product query parameters.
* Use comma-separated values for multi-select filters, for example `brand=brother,dymo`.
* Prefer `GET /api/products?category={slug}` for category product pages when combining category, search, sort, price, and attribute filters.

Example Next.js query builder:
```ts
const params = new URLSearchParams();

params.set('lang', locale);
params.set('page', String(page));

if (search) params.set('search', search);
if (categorySlug) params.set('category', categorySlug);
if (sort) params.set('sort', sort);
if (inStock) params.set('in_stock', '1');
if (brands.length) params.set('brand', brands.join(','));
if (priceMin) params.set('price_min', String(priceMin));
if (priceMax) params.set('price_max', String(priceMax));

const response = await fetch(`${apiUrl}/api/products?${params.toString()}`);
const payload = await response.json();
```

### Search Products
* **Endpoint:** `GET /api/search`
* **Description:** Lightweight product search endpoint. Returns up to 15 matching simple products and up to 15 matching group products.
* **Query Parameters:**
  * `query`: Required search query string, maximum 500 characters

### Get Single Product
* **Endpoint:** `GET /api/products/{type}/slug/{slug}`  
  * *Example:* `GET /api/products/simple/slug/brother-dk-11201`
* **Endpoint:** `GET /api/products/{type}/{id}`  
  * *Example:* `GET /api/products/simple/102`

---

## Shipping

### List Shipping Methods
* **Endpoint:** `GET /api/shipping-methods`
* **Description:** Returns shipping methods for checkout. By default, only active shipping methods are returned, and methods attached to inactive carriers are excluded.
* **Query Parameters:**
  * `active_only`: Optional boolean. Defaults to `true`. Use `0` or `false` to include inactive methods.
  * `zone_id`: Optional zone ID. When provided, returns methods assigned to that zone plus global methods with no zone.
* **Response (200 OK):**
  ```json
  {
    "data": [
      {
        "id": 1,
        "name": "Standard Shipping",
        "carrier": {
          "id": 3,
          "name": "PostNL",
          "is_active": true
        },
        "zone": {
          "id": 2,
          "name": "Netherlands",
          "scope": "shipping"
        },
        "calculator": "flat_fee",
        "is_active": true,
        "eta": {
          "min": 1,
          "max": 3,
          "units": "days"
        },
        "configuration": {
          "title": "Shipping fee",
          "cost": 7.95,
          "free_threshold": 100
        },
        "cost": 7.95,
        "title": "Shipping fee",
        "free_threshold": 100,
        "discounted_threshold": null,
        "discounted_cost": null
      }
    ]
  }
  ```
* **Examples:**
  ```http
  GET /api/shipping-methods
  GET /api/shipping-methods?zone_id=2
  GET /api/shipping-methods?active_only=0
  ```


## Order Placement & Checkout

### Guest Checkout
* **Endpoint:** `POST /api/guest/orders`
* **Request Body:**
  ```json
  {
    "customer": {
      "first_name": "Jane",
      "last_name": "Doe",
      "email": "jane.doe@example.com",
      "phone": "+31698765432"
    },
    "billing_address": {
      "address": "Second Street 45",
      "postalcode": "4321XY",
      "city": "Rotterdam",
      "country_id": 1
    },
    "shipping_address": {
      "address": "Second Street 45",
      "postalcode": "4321XY",
      "city": "Rotterdam",
      "country_id": 1
    },
    "items": [
      {
        "product_id": 102,
        "product_type": "simple",
        "quantity": 2
      }
    ],
    "coupon_code": "DISCOUNT10", // Optional
    "payment_method": "mollie_ideal"
  }
  ```
* **Response (201 Created):**
  ```json
  {
    "success": true,
    "order": {
      "id": 152,
      "number": "ORD-2026-000152",
      "total": 45.90,
      "payment_url": "https://www.mollie.com/checkout/select-wallet/..."
    }
  }
  ```

### Get Guest Order Details
* **Endpoint:** `GET /api/guest/orders/{number}`
* **Route Params:**
  * `number`: The order number (e.g., `ORD-2026-000152`)

### Mollie Webhook Callback
* **Endpoint:** `POST /api/webhooks/mollie`
* **Description:** Asynchronous webhook endpoint used by Mollie payment gateway to update order status.

---

## Contact & Lead Capture Requests

All endpoints in this section are public and validate their inputs, sending notification emails to administrators upon submission.

### Drawer Booking Request
* **Endpoint:** `POST /api/drawer-booking`

### General Contact Form
* **Endpoint:** `POST /api/drawer-contact`

### Custom Made Label Request
* **Endpoint:** `POST /api/custom-made-request`

### ICC Color Profile Request
* **Endpoint:** `POST /api/icc-profile-request`

### Printer Demonstration/Offer Request
* **Endpoint:** `POST /api/request-printer`

### Recycle Program Request
* **Endpoint:** `POST /api/recycle-request`

---

## Static & Miscellaneous Resources

### FAQ Items
* **Endpoint:** `GET /api/faq`
* **Endpoint:** `GET /api/faq/slug/{slug}`

### Reviews
* **Endpoint:** `GET /api/reviews`
* **Endpoint:** `POST /api/reviews` (Throttled to 5 requests per minute)

### Blog Posts
* **Endpoint:** `GET /api/posts`
* **Endpoint:** `GET /api/posts/slug/{slug}`

### Site Search
* **Endpoint:** `GET /api/search?q={query}`
