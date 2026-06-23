# Frontend API Quick Reference - Printer-Product Compatibility

## New Endpoint: Get Compatible Printers for a Product

### Endpoint
```
POST /api/products/product-printers
```

### Purpose
Show compatible printers on a product detail page.

### Request
```json
{
  "product_id": 456,
  "per_page": 15,           // optional, default: 15
  "status": "published"     // optional, default: "published"
}
```

### Response
```json
{
  "product": {
    "id": 456,
    "name": "Thermal Label 102x150mm",
    "sku": "TL-102-150",
    "properties": {
      "printmethode": ["TD"],
      "breedte": [102],
      "kern": ["76"],
      "buiten-diameter": [101]
    }
  },
  "printers": {
    "data": [
      {
        "id": 123,
        "title": "Zebra ZD621",
        "slug": "zebra-zd621",
        "excerpt": "High-performance thermal printer",
        "properties": {
          "printmethode": ["TD", "TT"],
          "label-breedte-min": ["25.4"],
          "label-breedte-max": ["112"],
          "kern": ["25", "38", "76"],
          "buiten-diameter": ["66", "75", "101", "127"]
        }
      }
    ],
    "meta": {
      "current_page": 1,
      "from": 1,
      "last_page": 1,
      "per_page": 15,
      "to": 5,
      "total": 5
    }
  }
}
```

### Frontend Implementation Example

```javascript
// src/lib/api/products.js

/**
 * Get printers compatible with a specific product
 * @param {number} productId - Product ID
 * @param {object} options - Optional parameters
 * @returns {Promise<{product: Product, printers: {data: Printer[], meta: PaginationMeta}}>}
 */
export async function getProductCompatiblePrinters(productId, options = {}) {
  const response = await fetch('/api/products/product-printers', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      product_id: productId,
      per_page: options.perPage || 15,
      status: options.status || 'published',
    }),
  });

  if (!response.ok) {
    throw new Error('Failed to fetch compatible printers');
  }

  return response.json();
}
```

### Usage in Product Detail Page

```jsx
// Example: ProductDetailPage.jsx

import { useEffect, useState } from 'react';
import { getProductCompatiblePrinters } from '@/lib/api/products';

function ProductDetailPage({ productId }) {
  const [compatiblePrinters, setCompatiblePrinters] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    async function loadPrinters() {
      try {
        const { printers } = await getProductCompatiblePrinters(productId);
        setCompatiblePrinters(printers.data);
      } catch (error) {
        console.error('Failed to load compatible printers:', error);
      } finally {
        setLoading(false);
      }
    }

    loadPrinters();
  }, [productId]);

  return (
    <div>
      <h2>Compatible Printers</h2>
      {loading ? (
        <p>Loading printers...</p>
      ) : compatiblePrinters.length > 0 ? (
        <ul>
          {compatiblePrinters.map(printer => (
            <li key={printer.id}>
              <a href={`/printers/${printer.slug}`}>
                {printer.title}
              </a>
            </li>
          ))}
        </ul>
      ) : (
        <p>No compatible printers found for this product.</p>
      )}
    </div>
  );
}
```

---

## All Compatibility Endpoints

### 1. Get Compatible Products for a Printer
```
POST /api/products/printer-products
```

**Request:**
```json
{
  "printer_id": 123,
  "product_type": "labels",  // optional: "labels" or "ink"
  "per_page": 15
}
```

### 2. Get Compatible Printers for a Product (NEW)
```
POST /api/products/product-printers
```

**Request:**
```json
{
  "product_id": 456,
  "per_page": 15,
  "status": "published"
}
```

### 3. Check Specific Compatibility
```
POST /api/products/compatibility
```

**Request:**
```json
{
  "product_id": 456,
  "printer_id": 123
}
```

**Response:**
```json
{
  "compatibility": true
}
```

---

## Compatibility Rules

Products and printers are matched based on these properties:

| Property | Match Type | Description |
|----------|------------|-------------|
| **printmethode** | Equality | Product's print method must be supported by printer |
| **breedte** | Range/Set | Product width must fit within printer's min-max range or be in supported widths |
| **kern** | Set | Product core diameter must be in printer's supported cores |
| **buiten-diameter** | Set/Max | Product outer diameter must be in printer's supported diameters or ≤ max |

---

## Error Handling

```javascript
try {
  const { printers } = await getProductCompatiblePrinters(productId);
  // Success
} catch (error) {
  if (error.response?.status === 404) {
    // Product not found
    console.error('Product not found');
  } else if (error.response?.status === 500) {
    // Server error
    console.error('Server error:', error.response.data.error);
  } else {
    // Network or other error
    console.error('Failed to fetch printers:', error.message);
  }
}
```

---

## Next.js Integration

Add to `src/lib/api/products.js`:

```javascript
/**
 * Get printers compatible with a specific product
 * @param {number} productId - Product ID
 * @param {object} params - Optional query parameters
 */
export async function getProductCompatiblePrinters(productId, params = {}) {
  const { perPage = 15, status = 'published' } = params;
  
  const response = await apiClient.post('/products/product-printers', {
    product_id: productId,
    per_page: perPage,
    status,
  });
  
  return response.data;
}
```

Add JSDoc type to `src/lib/api/types.js`:

```javascript
/**
 * @typedef {Object} ProductPrintersResponse
 * @property {Product} product - The product details
 * @property {Object} printers - Compatible printers
 * @property {Printer[]} printers.data - Array of printer objects
 * @property {PaginationMeta} printers.meta - Pagination metadata
 */
```

---

**Last Updated:** May 12, 2026
