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

### List/Paginate Products
* **Endpoint:** `GET /api/products`
* **Query Parameters:**
  * `lang`: String locale (e.g. `nl` or `en`)
  * `search`: Search query string
  * `category`: Filter by category slug
  * `page`: Pagination page number

### Get Single Product
* **Endpoint:** `GET /api/products/{type}/slug/{slug}`  
  * *Example:* `GET /api/products/simple/slug/brother-dk-11201`
* **Endpoint:** `GET /api/products/{type}/{id}`  
  * *Example:* `GET /api/products/simple/102`


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
