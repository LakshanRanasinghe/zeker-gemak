curl -X POST http://127.0.0.1:8002/api/guest/orders \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "status": "pending",
    "lang": "nl",
    "billpayer_is_organization": false,
    "billing_firstname": "Test",
    "billing_lastname": "User",
    "billing_email": "test@example.com",
    "billing_address": "Test Street 1",
    "billing_city": "Test City",
    "billing_country_id": "NL",
    "payment_method": "ideal",
    "order_items": [
      {
        "product_id": 102,
        "product_type": "simple",
        "quantity": 1,
        "name": "Test Product",
        "price": 100.00
      }
    ]
  }'
