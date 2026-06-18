# Flutter Mobile POS — Complete Implementation Guide

## 1. System Overview

This is a **multi-business, multi-location Point of Sale (POS) system**. A user belongs to a business and can access one or more locations. The mobile app mirrors the web-based POS with capabilities for creating sales, purchases, expenses, managing customers/suppliers, stock control, reporting, and payment tracking.

**Core Domain Concepts:**
- **Business** — top-level tenant. A user logs in with a `username` scoped to a `business_id`.
- **Location** — physical store/warehouse. Stock is tracked per-location.
- **Contact** — unified table for customers (type=`customer`/`both`) and suppliers (type=`supplier`/`both`).
- **Product** — has one "Default" variation for single-type products. Stock tracked at the variation+location level.
- **Transaction** — unified table for sells, purchases, expenses, stock adjustments, transfers. Differentiated by `type` and `status`.
- **Payment** — a `TransactionPayment` record linked to a transaction or contact.

---

## 2. Authentication Flow

### 2.1 Base URL
```
https://your-domain.com/api/mobile
```

### 2.2 Login (Public)
```
POST /api/mobile/login
Content-Type: application/json
Body: {
  "username": "string (required)",
  "password": "string (required)"
}
Response 200: {
  "success": true,
  "message": "Login successful",
  "data": {
    "token": "eyJ0eXAiOiJKV1Qi...",
    "token_type": "Bearer",
    "user": { /* AuthUserResource */ }
  }
}
Response 401: { "success": false, "message": "Invalid credentials", "data": null }
```

### 2.3 Token Management
- The API uses **Laravel Passport** OAuth tokens.
- Send the token on every authenticated request as:
  ```
  Authorization: Bearer <token>
  ```
- Tokens can be revoked on logout.
- There is no refresh token endpoint. If a token expires, the user must re-login.

### 2.4 Logout (Authenticated)
```
POST /api/mobile/logout
Response 200: { "success": true, "message": "Logged out successfully", "data": null }
```

### 2.5 Current User (Authenticated)
```
GET /api/mobile/me
Response 200: { "success": true, "data": { /* AuthUserResource */ } }
```

### 2.6 User Permissions (Authenticated)
```
GET /api/mobile/permissions
Response 200: {
  "data": {
    "all_permissions": ["sell.create", "sell.view", ...],
    "can_access_all_locations": bool,
    "role": "Admin"
  }
}
```

### 2.7 User Locations (Authenticated)
```
GET /api/mobile/locations
Response 200: { "data": [ /* LocationResource[] */ ] }
```

---

## 3. Global Response Format

Every endpoint returns:
```json
{
  "success": true|false,
  "message": "Human-readable message",
  "data": { ... }  // null on errors
}
```

**Error HTTP codes used:**
- `200` — success
- `201` — resource created
- `401` — unauthenticated
- `403` — unauthorized (insufficient permissions)
- `404` — resource not found
- `422` — validation error
- `500` — server error

**Validation errors** (422) follow Laravel's default format:
```json
{
  "message": "The given data was invalid.",
  "errors": { "field_name": ["Validation message"] }
}
```

---

## 4. Pagination

Endpoints that return lists use Laravel's LengthAwarePaginator. The paginator's metadata is returned at the **root level** of the response:

```json
{
  "success": true,
  "message": "Success",
  "data": [ ...items... ],
  "current_page": 1,
  "last_page": 5,
  "per_page": 20,
  "total": 100,
  "from": 1,
  "to": 20,
  "next_page_url": "https://...?page=2",
  "prev_page_url": null
}
```

**Query parameters for pagination:**
- `per_page` — items per page (default: 20 for most endpoints, 50 for POS products)
- `page` — page number (default: 1)

---

## 5. Complete API Reference

### 5.1 Auth

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/login` | Login (public) |
| POST | `/logout` | Logout (revoke token) |
| GET | `/me` | Current user profile |
| GET | `/permissions` | User permissions & role |
| GET | `/locations` | Permitted business locations |

### 5.2 Dashboard

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/dashboard` | Dashboard metrics |

**Query Parameters:**
- `start_date` (Y-m-d, optional)
- `end_date` (Y-m-d, optional)
- `location_id` (optional)

**Response data:**
```json
{
  "total_sale": 0.00,
  "actual_income": 0.00,
  "customer_payment": 0.00,
  "collection_payment": 0.00,
  "expenses": 0.00,
  "due": 0.00,
  "low_stock_count": 0,
  "recent_sales": [
    {
      "id": 1,
      "invoice_no": "SALE001",
      "final_total": 100.00,
      "payment_status": "paid",
      "transaction_date": "2024-01-01 10:00:00",
      "contact_name": "Walk-in Customer",
      "created_by": "Admin User"
    }
  ],
  "top_products": [
    {
      "name": "Product A",
      "total_qty": 50,
      "total_amount": 750.00
    }
  ]
}
```

### 5.3 POS

#### 5.3.1 POS Settings
```
GET /api/mobile/pos/settings
```
```json
{
  "data": {
    "business": { "id": 1, "name": "My Business", "currency": { /* Currency */ } },
    "locations": [ /* location objects with id, name, location_id, selling_price_group_id, default_payment_accounts, invoice_scheme_id, invoice_layout_id, sale_invoice_scheme_id */ ],
    "walk_in_customer": { /* Contact object */ },
    "tax_rates": [ { "id": 1, "name": "VAT 10%", "amount": 10, "is_tax_group": false } ],
    "payment_types": { "cash": "Cash", "card": "Card", ... },
    "currencies": [ /* Currency[] */ ]
  }
}
```

**Payment type keys:** `cash`, `card`, `cheque`, `bank_transfer`, `advance`, `custom_pay_1` through `custom_pay_7`.

#### 5.3.2 POS Products
```
GET /api/mobile/pos/products
```
**Parameters:** `location_id`, `category_id`, `brand_id`, `search`, `per_page` (default 50)

**Note:** This returns only products that are `productForSales()` and `active`. The response is a paginated list of raw Product models (NOT `ProductResource`), so fields match the database columns directly with relationships loaded.

#### 5.3.3 Validate Cart
```
POST /api/mobile/pos/validate-cart
```
```json
{
  "products": [
    {
      "product_id": 1,
      "variation_id": 1,
      "quantity": 2.5,
      "unit_price_inc_tax": 15.00,
      "item_tax": 0.50,
      "tax_id": 1,
      "line_discount_type": "fixed|percentage",
      "line_discount_amount": 0
    }
  ],
  "discount_type": "fixed|percentage",
  "discount_amount": 10,
  "tax_rate_id": 1,
  "location_id": 1
}
```
**Response:**
```json
{
  "data": {
    "total_before_tax": 37.50,
    "tax": 3.75,
    "discount": 10.00,
    "final_total": 31.25,
    "item_count": 1,
    "errors": [],
    "warnings": ["Product A: Requested 100 but only 50 available."]
  }
}
```

#### 5.3.4 Create POS Sale
```
POST /api/mobile/pos/sales
Authorization: Bearer <token>
```
**Request body:**
```json
{
  "contact_id": 1,
  "location_id": 1,
  "transaction_date": "2024-01-15 10:30:00",
  "status": "final|draft|quotation",
  "products": [
    {
      "product_id": 1,
      "variation_id": 1,
      "quantity": 2,
      "unit_price": 10.00,
      "unit_price_inc_tax": 11.50,
      "item_tax": 1.50,
      "tax_id": 1,
      "line_discount_type": "fixed|percentage",
      "line_discount_amount": 0
    }
  ],
  "discount_type": "fixed|percentage",
  "discount_amount": 0,
  "tax_rate_id": 1,
  "sale_note": "Note for customer",
  "staff_note": "Internal note",
  "payments": [
    {
      "method": "cash",
      "amount": 23.00,
      "paid_on": "2024-01-15 10:30:00",
      "account_id": 1,
      "card_number": null,
      "card_holder_name": null,
      "card_transaction_number": null,
      "card_type": null,
      "cheque_number": null
    }
  ],
  "is_suspend": false,
  "shipping_charges": 0,
  "shipping_details": "",
  "shipping_address": "",
  "shipping_status": "",
  "delivered_to": "",
  "exchange_rate": 1,
  "pay_term_number": 30,
  "pay_term_type": "days|months",
  "is_credit_sale": 0,
  "commission_agent": null
}
```
**Note 1:** If `is_credit_sale = 1`, payment lines are ignored — no payment is recorded. If `status = quotation`, it is saved as a draft/quotation.
**Note 2:** Requires permission `sell.create`.

#### 5.3.5 Sale Receipt
```
GET /api/mobile/pos/receipt/{transaction_id}
```
**Response:** Returns the full `TransactionResource`, a `receipt` object with formatted data, and `html_content` (rendered Blade receipt HTML for printing).

### 5.4 Products

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/products` | List products (paginated) |
| GET | `/products/{id}` | Show product detail |
| POST | `/products` | Create product |
| PUT | `/products/{id}` | Update product |
| POST | `/products/{id}/image` | Upload product image |
| GET | `/products/{id}/stock` | Stock by location for a product |

**Products list params:** `location_id`, `category_id`, `brand_id`, `search` (name or SKU), `type`, `per_page`

**ProductResource:**
```json
{
  "id": 1,
  "name": "Product A",
  "sku": "SKU001",
  "type": "single|variable|combo",
  "unit_id": 1,
  "brand_id": 1,
  "category_id": 1,
  "sub_category_id": null,
  "tax": 1,
  "tax_type": "inclusive|exclusive",
  "enable_stock": true,
  "alert_quantity": 10,
  "image": "image.jpg",
  "image_url": "http://...",
  "product_description": "...",
  "weight": "1kg",
  "barcode_type": "C128",
  "not_for_selling": false,
  "created_at": "2024-01-01T00:00:00.000000Z",
  "brand": { "id": 1, "name": "Brand A" },
  "category": { "id": 1, "name": "Category A" },
  "unit": { "id": 1, "name": "Pieces", "short_name": "pcs" },
  "variations": [ /* VariationResource[] */ ],
  "product_locations": [ /* BusinessLocation[] */ ],
  "default_selling_price": 15.00,
  "default_purchase_price": 10.00
}
```

**VariationResource:**
```json
{
  "id": 1,
  "name": "Default",
  "product_id": 1,
  "sub_sku": "SKU001",
  "product_variation_id": null,
  "variation_value_id": null,
  "default_purchase_price": 10.00,
  "dpp_inc_tax": 10.00,
  "profit_percent": 50.00,
  "default_sell_price": 15.00,
  "sell_price_inc_tax": 15.00,
  "product_variation": null,
  "stock": [
    { "location_id": 1, "qty_available": 100 }
  ]
}
```

**Product stock by location:**
```
GET /api/mobile/products/{id}/stock?location_id=1
```
```json
{
  "data": [
    { "variation_id": 1, "variation_name": "Default", "sub_sku": "SKU001", "location_id": 1, "qty_available": 100 }
  ]
}
```

**Create product:** Requires permission `product.create`.
```json
{
  "name": "Product A",
  "sku": "optional-auto",
  "type": "single|variable|combo",
  "unit_id": 1,
  "brand_id": null,
  "category_id": null,
  "tax": null,
  "tax_type": "inclusive|exclusive",
  "enable_stock": true,
  "alert_quantity": 10,
  "sku_manual": "optional-manual-sku",
  "purchase_price": 10.00,
  "selling_price": 15.00,
  "product_description": "...",
  "sub_unit_ids": [],
  "barcode_type": "C128",
  "weight": null,
  "product_custom_field1": null,
  "product_custom_field2": null,
  "product_custom_field3": null,
  "product_custom_field4": null,
  "product_locations": [1, 2]
}
```

**Image upload:**
```
POST /api/mobile/products/{id}/image
Content-Type: multipart/form-data
Body: image=<file> (jpeg,png,jpg,gif, max 2MB)
```

### 5.5 Customers

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/customers` | List customers (paginated) |
| GET | `/customers/{id}` | Show customer with balances |
| POST | `/customers` | Create customer |
| PUT | `/customers/{id}` | Update customer |
| GET | `/customers/{id}/ledger` | Customer ledger |
| GET | `/customers/{id}/payments` | Customer payments |
| POST | `/customers/{id}/pay-due` | Pay customer due |

**CustomerResource:**
```json
{
  "id": 1,
  "name": "John Doe",
  "supplier_business_name": null,
  "contact_id": "CUST001",
  "mobile": "1234567890",
  "email": "john@example.com",
  "tax_number": null,
  "city": "New York",
  "state": "NY",
  "country": "USA",
  "address_line_1": "123 Main St",
  "address_line_2": null,
  "zip_code": "10001",
  "land_mark": null,
  "customer_group_id": null,
  "pay_term_number": null,
  "pay_term_type": null,
  "credit_limit": null,
  "balance": 0.00,
  "total_purchase": 0,
  "total_invoice": 0,
  "opening_balance": 0,
  "created_at": "2024-01-01T00:00:00.000000Z"
}
```

**Customer show** also returns additional financial data at the top level of `data`:
```json
{
  "total_purchase_due": 0,
  "total_invoice_due": 0,
  "purchase_return_balance": 0,
  "sell_return_balance": 0
}
```

**Create customer** requires permission `customer.create`:
```json
{
  "name": "John Doe",
  "mobile": "1234567890",
  "email": "john@example.com",
  "tax_number": null,
  "city": "NY",
  "state": "NY",
  "country": "USA",
  "address_line_1": "123 Main St",
  "address_line_2": null,
  "zip_code": "10001",
  "land_mark": null,
  "customer_group_id": null,
  "contact_id": "CUST001",
  "pay_term_number": null,
  "pay_term_type": null,
  "credit_limit": null,
  "opening_balance": 0
}
```

**Customer ledger:**
```
GET /api/mobile/customers/{id}/ledger?start_date=2024-01-01&end_date=2024-01-31&location_id=1
```
```json
{
  "data": {
    "contact": { "id": 1, "name": "John", "mobile": "123", "balance": 0 },
    "start_date": "2024-01-01",
    "end_date": "2024-01-31",
    "opening_balance": 100.00,
    "closing_balance": 0.00,
    "transactions": [
      { "date": "2024-01-15", "type": "sell", "invoice_no": "SALE001", "debit": 50, "credit": 0, "balance": 150, "note": "" }
    ]
  }
}
```

**Pay due:**
```
POST /api/mobile/customers/{id}/pay-due
{
  "amount": 50.00,
  "method": "cash",
  "paid_on": "2024-01-15",
  "account_id": 1,
  "note": "Payment note"
}
```

### 5.6 Suppliers

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/suppliers` | List suppliers (paginated) |
| GET | `/suppliers/{id}` | Show supplier with balances |
| POST | `/suppliers` | Create supplier |
| PUT | `/suppliers/{id}` | Update supplier |
| GET | `/suppliers/{id}/ledger` | Supplier ledger |
| POST | `/suppliers/{id}/pay-due` | Pay supplier due |

**SupplierResource** — same fields as CustomerResource except adds `supplier_business_name`, removes `customer_group_id`/`credit_limit`, and shows `total_purchase`/`total_paid`.

Additional fields on show: `total_purchase_due`, `total_purchase_return_due`, `total_sell_return`.

### 5.7 Sales

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/sales` | List sales (paginated) |
| GET | `/sales/{id}` | Show sale detail |
| POST | `/sales/{id}/payment` | Add payment to sale |
| POST | `/sales/{id}/return` | Process sell return |
| DELETE | `/sales/{id}` | Delete sale |

**Sales list params:** `location_id`, `customer_id`, `cashier_id`, `payment_status`, `start_date`, `end_date`, `status` (final/draft/quotation), `search` (invoice/ref), `per_page`

**TransactionResource (for sales):**
```json
{
  "id": 1,
  "type": "sell|sell_return",
  "status": "final|draft",
  "sub_status": "quotation|null",
  "invoice_no": "SALE001",
  "ref_no": null,
  "transaction_date": "2024-01-15 10:30:00",
  "total_before_tax": 100.00,
  "tax_amount": 10.00,
  "discount_type": "fixed|percentage",
  "discount_amount": 0,
  "shipping_charges": 0,
  "final_total": 110.00,
  "payment_status": "paid|due|partial",
  "additional_notes": null,
  "staff_note": null,
  "contact_id": 1,
  "location_id": 1,
  "created_by": 1,
  "is_direct_sale": 1,
  "is_suspend": 0,
  "pay_term_number": null,
  "pay_term_type": null,
  "created_at": "2024-01-15T10:30:00.000000Z",
  "contact": { "id": 1, "name": "Customer", "mobile": "123", "supplier_business_name": null },
  "location": { "id": 1, "name": "Main Store" },
  "created_by_user": { "id": 1, "full_name": "Admin User" },
  "payment_lines": [ /* PaymentResource[] */ ],
  "sell_lines": [ /* SellLineResource[] */ ],
  "paid_amount": 110.00,
  "due_amount": 0.00
}
```

**SellLineResource:**
```json
{
  "id": 1,
  "product_id": 1,
  "variation_id": 1,
  "quantity": 2,
  "unit_price": 10.00,
  "unit_price_inc_tax": 11.50,
  "unit_price_before_discount": 12.00,
  "line_discount_type": null,
  "line_discount_amount": 0,
  "item_tax": 1.50,
  "tax_id": 1,
  "sell_line_note": null,
  "sub_unit_id": null,
  "product": { "id": 1, "name": "Product A" },
  "variations": { "id": 1, "name": "Default", "sub_sku": "SKU001" }
}
```

**Add payment to sale:**
```
POST /api/mobile/sales/{id}/payment
{
  "amount": 50.00,
  "method": "cash",
  "paid_on": "2024-01-15",
  "account_id": 1,
  "card_number": null,
  "card_holder_name": null,
  "card_transaction_number": null,
  "card_type": null,
  "cheque_number": null,
  "note": "Partial payment"
}
```

**Sell return:**
```
POST /api/mobile/sales/{id}/return
{
  "transaction_date": "2024-01-16",
  "products": [
    {
      "sell_line_id": 1,
      "quantity": 1,
      "unit_price": 10.00
    }
  ]
}
```

**Delete sale:** `DELETE /api/mobile/sales/{id}` — soft-deletes the sale transaction and adjusts stock.

### 5.8 Purchases

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/purchases` | List purchases |
| GET | `/purchases/{id}` | Show purchase detail |
| POST | `/purchases` | Create purchase |
| POST | `/purchases/{id}/payment` | Add payment |
| POST | `/purchases/{id}/return` | Purchase return |

**Create purchase** requires permission `purchase.create`:
```json
{
  "contact_id": 1,
  "location_id": 1,
  "transaction_date": "2024-01-15",
  "status": "received|pending|ordered",
  "products": [
    {
      "product_id": 1,
      "variation_id": 1,
      "quantity": 10,
      "unit_cost_before_discount": 8.00,
      "unit_cost": 8.00,
      "unit_cost_inc_tax": 8.80,
      "item_tax": 0.80,
      "tax_id": 1,
      "line_discount_type": null,
      "line_discount_amount": 0
    }
  ],
  "discount_type": null,
  "discount_amount": 0,
  "tax_rate_id": null,
  "shipping_charges": 0,
  "additional_notes": "PO notes",
  "final_total": 88.00,
  "pay_term_number": 30,
  "pay_term_type": "days",
  "payments": [
    { "method": "cash", "amount": 88.00, "account_id": 1 }
  ]
}
```

**PurchaseLineResource:**
```json
{
  "id": 1,
  "product_id": 1,
  "variation_id": 1,
  "quantity": 10,
  "unit_cost_before_discount": 8.00,
  "unit_cost": 8.00,
  "unit_cost_inc_tax": 8.80,
  "line_discount_type": null,
  "line_discount_amount": 0,
  "item_tax": 0.80,
  "tax_id": 1,
  "purchase_line_note": null,
  "quantity_sold": 0,
  "quantity_adjusted": 0,
  "quantity_returned": 0,
  "product": { "id": 1, "name": "Product A" },
  "variations": { "id": 1, "name": "Default", "sub_sku": "SKU001" }
}
```

**Purchase return:**
```
POST /api/mobile/purchases/{id}/return
{
  "transaction_date": "2024-01-16",
  "products": [
    {
      "purchase_line_id": 1,
      "quantity": 2,
      "unit_cost": 8.00
    }
  ]
}
```

### 5.9 Expenses

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/expenses` | List expenses |
| GET | `/expenses/categories` | Expense categories tree |
| POST | `/expenses` | Create expense |
| PUT | `/expenses/{id}` | Update expense |
| DELETE | `/expenses/{id}` | Delete expense |

**Create expense** requires permission `expense.add`:
```json
{
  "location_id": 1,
  "expense_category_id": 1,
  "expense_for": null,
  "ref_no": "EXP-001",
  "transaction_date": "2024-01-15",
  "final_total": 100.00,
  "tax_id": null,
  "additional_notes": "Office supplies",
  "payment": [
    { "method": "cash", "amount": 100.00, "account_id": 1 }
  ]
}
```

**Expense categories:**
```
GET /api/mobile/expenses/categories
```
```json
{
  "data": [
    {
      "id": 1,
      "name": "Utilities",
      "code": "UTIL",
      "parent_id": null,
      "sub_categories": [
        { "id": 2, "name": "Electricity", "code": "ELEC", "parent_id": 1, "sub_categories": [] }
      ]
    }
  ]
}
```

**ExpenseResource:**
```json
{
  "id": 1,
  "type": "expense",
  "ref_no": "EXP-001",
  "transaction_date": "2024-01-15",
  "final_total": 100.00,
  "payment_status": "paid",
  "additional_notes": "Office supplies",
  "expense_category_id": 1,
  "expense_for": null,
  "location_id": 1,
  "created_by": 1,
  "tax_id": null,
  "tax_amount": 0,
  "created_at": "2024-01-15T00:00:00.000000Z",
  "expense_category": null,
  "location": { "id": 1, "name": "Main Store" },
  "transaction_for": null,
  "payment_lines": [ /* PaymentResource[] */ ]
}
```

### 5.10 Stock

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/stock` | Stock report by product |
| GET | `/stock/low` | Low stock items |
| GET | `/stock/adjustments` | Stock adjustments |
| POST | `/stock/adjustments` | Create adjustment |
| GET | `/stock/transfers` | Stock transfers |
| POST | `/stock/transfers` | Create transfer |

**Stock list:** Returns products with `total_stock` (sum of all variations across loaded locations) and `stock_details` per variation per location.

**Low stock:** Returns array of items where `qty_available <= alert_quantity`:
```json
{
  "data": [
    { "product_id": 1, "product_name": "A", "sku": "SKU001", "variation_id": 1, "variation_name": "Default", "location_id": 1, "qty_available": 3, "alert_quantity": 10 }
  ]
}
```

**Stock adjustment** requires `stock_adjustment.create`:
```json
{
  "location_id": 1,
  "transaction_date": "2024-01-15",
  "products": [
    {
      "product_id": 1,
      "variation_id": 1,
      "quantity": -5,
      "type": "normal|abnormal",
      "unit_cost": 10.00,
      "reason": "Damaged"
    }
  ],
  "additional_notes": "Monthly adjustment",
  "total_amount": 50.00
}
```

**Stock transfer** requires `stock_transfer.create`:
```json
{
  "location_id": 1,
  "transfer_location_id": 2,
  "transaction_date": "2024-01-15",
  "products": [
    {
      "product_id": 1,
      "variation_id": 1,
      "quantity": 10,
      "unit_cost": 10.00
    }
  ],
  "additional_notes": "Stock transfer",
  "shipping_charges": 0
}
```

### 5.11 Payments

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/payments` | List payments |
| GET | `/payments/{id}` | Show payment |
| POST | `/payments` | Create payment |
| PUT | `/payments/{id}` | Update payment |

**Payment list params:** `contact_id`, `method`, `start_date`, `end_date`, `transaction_id`, `per_page`

**Create payment:**
```json
{
  "transaction_id": null,
  "contact_id": 1,
  "amount": 100.00,
  "method": "cash",
  "paid_on": "2024-01-15",
  "account_id": 1,
  "card_number": null,
  "card_holder_name": null,
  "card_transaction_number": null,
  "card_type": null,
  "cheque_number": null,
  "note": "Payment"
}
```
If `transaction_id` is provided, the payment is attached to that transaction. If not, `contact_id` is used and the payment is recorded as a contact payment (reducing balance).

**PaymentResource:**
```json
{
  "id": 1,
  "transaction_id": 1,
  "amount": 100.00,
  "method": "cash",
  "payment_ref_no": "PAY-001",
  "paid_on": "2024-01-15 10:00:00",
  "card_transaction_number": null,
  "card_number": null,
  "card_type": null,
  "card_holder_name": null,
  "cheque_number": null,
  "bank_account_number": null,
  "note": "Payment note",
  "account_id": 1,
  "payment_for": 1,
  "created_by": 1,
  "is_return": 0,
  "created_at": "2024-01-15T10:00:00.000000Z",
  "payment_account": { "id": 1, "name": "Cash" },
  "created_user": { "id": 1, "full_name": "Admin" },
  "transaction": { "id": 1, "invoice_no": "SALE001", "type": "sell", "final_total": 110.00 }
}
```

### 5.12 Settings

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/settings` | All business settings |
| GET | `/payment-methods` | Payment method definitions |
| GET | `/business` | Business info |

**Settings:**
```json
{
  "data": {
    "business": {
      "id": 1,
      "name": "My Business",
      "start_date": "2020-01-01",
      "default_profit_percent": 25,
      "currency": { /* Currency with id, code, symbol, thousand_separator, decimal_separator */ },
      "currency_precision": 2,
      "quantity_precision": 2,
      "time_format": "h:i A"
    },
    "locations": [ /* BusinessLocation[] with id, name, location_id, landmark, city, state, country */ ],
    "tax_rates": [ { "id": 1, "name": "VAT 10%", "amount": 10, "is_tax_group": false } ],
    "payment_accounts": [ { "id": 1, "name": "Cash", "account_type": "cash" } ]
  }
}
```

**Payment methods:**
```json
{
  "data": [
    { "key": "cash", "label": "Cash" },
    { "key": "card", "label": "Card" },
    { "key": "cheque", "label": "Cheque" },
    { "key": "bank_transfer", "label": "Bank Transfer" },
    { "key": "advance", "label": "Advance" },
    { "key": "custom_pay_1", "label": "Custom 1" },
    ...
  ]
}
```

### 5.13 Reports

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/reports/sales` | Sales report with summary |
| GET | `/reports/products` | Product sales report |
| GET | `/reports/customers-due` | Customers with due amounts |
| GET | `/reports/suppliers-due` | Suppliers with due amounts |
| GET | `/reports/stock` | Stock valuation report |
| GET | `/reports/payments` | Payment report by method |
| GET | `/reports/expenses` | Expense report |
| GET | `/reports/purchases` | Purchase report |
| GET | `/reports/profit-loss` | Profit & loss |
| GET | `/reports/local-cashier` | Cashier daily report |

All reports accept: `location_id`, `start_date`, `end_date`, `user_id` (where applicable).

**Sales report:**
```json
{
  "data": {
    "summary": { "total_sales": 5, "total_amount": 550.00, "total_paid": 500.00, "total_due": 50.00 },
    "sales": [ /* full Transaction objects */ ]
  }
}
```

**Customers due:**
```json
{
  "data": {
    "total_due": 1500.00,
    "customers": [
      { "id": 1, "name": "John", "mobile": "123", "email": "j@e.com", "balance": 500.00, "credit_limit": 1000.00 }
    ]
  }
}
```

**Stock report:**
```json
{
  "data": {
    "total_stock_value": 5000.00,
    "products": [
      { "id": 1, "name": "Product A", "sku": "SKU001", "total_qty": 100, "stock_value": 1000.00, "alert_quantity": 10 }
    ]
  }
}
```

---

## 6. Flutter Data Models

Use `json_serializable` or `freezed` for production.

```dart
// === Base ===
class ApiResponse<T> {
  final bool success;
  final String message;
  final T? data;
  final int? currentPage;
  final int? lastPage;
  final int? perPage;
  final int? total;
  final String? nextPageUrl;
  final String? prevPageUrl;
}

// === Auth ===
class User {
  final int id;
  final String? surname;
  final String? firstName;
  final String? lastName;
  final String fullName;
  final String username;
  final String? email;
  final String? language;
  final int businessId;
  final String? imageUrl;
  final String? roleName;
}

class PermissionData {
  final List<String> allPermissions;
  final bool canAccessAllLocations;
  final String? role;
}

// === Business / Location ===
class Business {
  final int id;
  final String name;
  final String? startDate;
  final double? defaultProfitPercent;
  final Currency? currency;
  final int? currencyPrecision;
  final int? quantityPrecision;
  final String? timeFormat;
}

class Currency {
  final int id;
  final String code;
  final String symbol;
  final String? thousandSeparator;
  final String? decimalSeparator;
}

class BusinessLocation {
  final int id;
  final String name;
  final String? locationId;
  final String? landmark;
  final String? city;
  final String? state;
  final String? country;
  final String? zipCode;
  final String? mobile;
  final String? email;
  final bool? isActive;
  final int? invoiceSchemeId;
  final int? invoiceLayoutId;
  final int? saleInvoiceSchemeId;
  final int? sellingPriceGroupId;
  final String? address;
}

// === Contact ===
class Contact {
  final int id;
  final String name;
  final String? supplierBusinessName;
  final String? contactId;
  final String? mobile;
  final String? email;
  final String? taxNumber;
  final String? city;
  final String? state;
  final String? country;
  final String? addressLine1;
  final String? addressLine2;
  final String? zipCode;
  final String? landMark;
  final int? customerGroupId;
  final double? payTermNumber;
  final String? payTermType;
  final double? creditLimit;
  final double balance;
  final double? totalPurchase;
  final double? totalInvoice;
  final double? openingBalance;
  final DateTime? createdAt;
}

// === Product ===
class Product {
  final int id;
  final String name;
  final String sku;
  final String type;
  final int? unitId;
  final int? brandId;
  final int? categoryId;
  final int? tax;
  final String? taxType;
  final bool enableStock;
  final double? alertQuantity;
  final String? image;
  final String? imageUrl;
  final String? productDescription;
  final String? weight;
  final String? barcodeType;
  final bool? notForSelling;
  final DateTime? createdAt;
  final Brand? brand;
  final Category? category;
  final Unit? unit;
  final List<Variation> variations;
  final List<BusinessLocation>? productLocations;
  final double? defaultSellingPrice;
  final double? defaultPurchasePrice;
}

class Variation {
  final int id;
  final String name;
  final int productId;
  final String? subSku;
  final double defaultPurchasePrice;
  final double dppIncTax;
  final double profitPercent;
  final double defaultSellPrice;
  final double sellPriceIncTax;
  final List<VariationStock>? stock;
}

class VariationStock {
  final int locationId;
  final double qtyAvailable;
}

// === Transaction ===
class Transaction {
  final int id;
  final String type;
  final String? status;
  final String? subStatus;
  final String? invoiceNo;
  final String? refNo;
  final String transactionDate;
  final double totalBeforeTax;
  final double taxAmount;
  final String? discountType;
  final double discountAmount;
  final double shippingCharges;
  final double finalTotal;
  final String? paymentStatus;
  final String? additionalNotes;
  final String? staffNote;
  final int contactId;
  final int locationId;
  final int createdBy;
  final bool? isDirectSale;
  final bool? isSuspend;
  final double? payTermNumber;
  final String? payTermType;
  final DateTime createdAt;
  final ContactSummary? contact;
  final LocationSummary? location;
  final UserSummary? createdByUser;
  final List<Payment>? paymentLines;
  final List<SellLine>? sellLines;
  final List<PurchaseLine>? purchaseLines;
  final double? paidAmount;
  final double? dueAmount;
}

class SellLine {
  final int id;
  final int productId;
  final int variationId;
  final double quantity;
  final double unitPrice;
  final double unitPriceIncTax;
  final double? unitPriceBeforeDiscount;
  final String? lineDiscountType;
  final double lineDiscountAmount;
  final double itemTax;
  final int? taxId;
  final String? sellLineNote;
  final int? subUnitId;
  final ProductSummary? product;
  final VariationSummary? variations;
}

class PurchaseLine {
  final int id;
  final int productId;
  final int variationId;
  final double quantity;
  final double unitCostBeforeDiscount;
  final double unitCost;
  final double unitCostIncTax;
  final String? lineDiscountType;
  final double lineDiscountAmount;
  final double itemTax;
  final int? taxId;
  final String? purchaseLineNote;
  final double? quantitySold;
  final double? quantityAdjusted;
  final double? quantityReturned;
  final ProductSummary? product;
  final VariationSummary? variations;
}

// === Payment ===
class Payment {
  final int id;
  final int? transactionId;
  final double amount;
  final String method;
  final String? paymentRefNo;
  final String? paidOn;
  final String? cardTransactionNumber;
  final String? cardNumber;
  final String? cardType;
  final String? cardHolderName;
  final String? chequeNumber;
  final String? bankAccountNumber;
  final String? note;
  final int? accountId;
  final int? paymentFor;
  final int? createdBy;
  final bool? isReturn;
  final DateTime? createdAt;
  final PaymentAccount? paymentAccount;
  final UserSummary? createdUser;
  final TransactionSummary? transaction;
}

// === Expense ===
class Expense {
  final int id;
  final String type;
  final String? refNo;
  final String transactionDate;
  final double finalTotal;
  final String? paymentStatus;
  final String? additionalNotes;
  final int? expenseCategoryId;
  final int? expenseFor;
  final int locationId;
  final int? createdBy;
  final double? taxAmount;
  final DateTime? createdAt;
  final LocationSummary? location;
  final UserSummary? transactionFor;
  final List<Payment>? paymentLines;
}

class ExpenseCategory {
  final int id;
  final String name;
  final String? code;
  final int? parentId;
  final List<ExpenseCategory>? subCategories;
}

// === Ledger ===
class Ledger {
  final ContactSummary contact;
  final String startDate;
  final String endDate;
  final double openingBalance;
  final double closingBalance;
  final List<LedgerEntry> transactions;
}

class LedgerEntry {
  final String date;
  final String type;
  final String invoiceNo;
  final double debit;
  final double credit;
  final double balance;
  final String note;
}

// === Dashboard ===
class DashboardData {
  final double totalSale;
  final double actualIncome;
  final double customerPayment;
  final double collectionPayment;
  final double expenses;
  final double due;
  final int lowStockCount;
  final List<RecentSale> recentSales;
  final List<TopProduct> topProducts;
}

class RecentSale {
  final int id;
  final String invoiceNo;
  final double finalTotal;
  final String paymentStatus;
  final String transactionDate;
  final String contactName;
  final String createdBy;
}

class TopProduct {
  final String name;
  final double totalQty;
  final double totalAmount;
}
```

---

## 7. Service / Repository Layer

```dart
// lib/core/network/api_client.dart
class ApiClient {
  static const String baseUrl = 'https://your-domain.com/api/mobile';
  final Dio _dio;

  ApiClient({required TokenStorage tokenStorage}) : _dio = Dio(BaseOptions(
    baseUrl: baseUrl,
    connectTimeout: Duration(seconds: 15),
    receiveTimeout: Duration(seconds: 15),
    headers: {'Content-Type': 'application/json', 'Accept': 'application/json'},
  )) {
    _dio.interceptors.add(AuthInterceptor(tokenStorage));
    _dio.interceptors.add(LogInterceptor(responseBody: true));
  }

  Future<ApiResponse<T>> get<T>(String path, {Map<String, dynamic>? queryParams, T Function(dynamic)? fromJson});
  Future<ApiResponse<T>> post<T>(String path, {dynamic data, T Function(dynamic)? fromJson});
  Future<ApiResponse<T>> put<T>(String path, {dynamic data, T Function(dynamic)? fromJson});
  Future<ApiResponse<T>> delete<T>(String path, {T Function(dynamic)? fromJson});
  Future<ApiResponse<T>> upload<T>(String path, {required FormData data, T Function(dynamic)? fromJson});
}

// lib/core/network/auth_interceptor.dart
class AuthInterceptor extends Interceptor {
  final TokenStorage tokenStorage;

  @override
  void onRequest(RequestOptions options, RequestInterceptorHandler handler) async {
    final token = await tokenStorage.getToken();
    if (token != null) {
      options.headers['Authorization'] = 'Bearer $token';
    }
    handler.next(options);
  }

  @override
  void onError(DioException err, ErrorInterceptorHandler handler) async {
    if (err.response?.statusCode == 401) {
      await tokenStorage.clearToken();
      // Navigate to login screen
    }
    handler.next(err);
  }
}
```

**Service modules (one file per domain):**
- `auth_service.dart` — login, logout, me, permissions, locations
- `dashboard_service.dart` — dashboard data
- `pos_service.dart` — pos settings, pos products, validate cart, create sale, receipt
- `product_service.dart` — CRUD, image upload, stock by location
- `customer_service.dart` — CRUD, ledger, payments, pay-due
- `supplier_service.dart` — CRUD, ledger, pay-due
- `sale_service.dart` — list, show, add payment, return, delete
- `purchase_service.dart` — list, show, create, add payment, return
- `expense_service.dart` — list, categories, CRUD
- `stock_service.dart` — list, low stock, adjustments CRUD, transfers CRUD
- `payment_service.dart` — list, show, CRUD
- `report_service.dart` — all 10 report endpoints
- `settings_service.dart` — settings, payment-methods, business

---

## 8. State Management (Recommended: Riverpod)

```dart
final authProvider = StateNotifierProvider<AuthNotifier, AuthState>((ref) {
  return AuthNotifier(ref.read(apiClientProvider), ref.read(tokenStorageProvider));
});

class AuthState {
  final bool isAuthenticated;
  final User? user;
  final List<String> permissions;
  final bool canAccessAllLocations;
  final String? role;
  final List<BusinessLocation> locations;
  final bool isLoading;
  final String? error;
}
```

---

## 9. Screen-by-Screen Breakdown

### 9.1 Auth Flow
- **Login Screen** — username + password, call `POST /login`, store token via `flutter_secure_storage`, navigate to dashboard

### 9.2 Main Shell (Bottom Navigation)
Tabs: **Dashboard | POS | Sales | Purchases | Stock | More**
"More" opens: Customers, Suppliers, Products, Expenses, Payments, Reports, Settings

### 9.3 Dashboard
- KPI cards: Total Sales, Actual Income, Collected Payments, Expenses, Total Due
- Low stock count alert (tappable)
- Recent sales list (last 10), Top products (last 5)
- Filters: date range + location dropdown

### 9.4 POS
- **Settings** loaded on entry: locations, tax rates, walk-in customer, payment types
- **Product selection**: search, category/brand filters, grid/list with stock badge
- **Cart**: qty adjustment, line discounts, subtotal/tax/discount/final total
- Customer selection (default: walk-in)
- Payment form: method, amount, card/cheque details
- "Hold" (draft) vs "Complete Sale" (final)
- Receipt view

### 9.5 Products — list, detail, create/edit, image upload
### 9.6 Customers — list, detail (tabs: ledger/payments/transactions), create/edit, pay due
### 9.7 Suppliers — same as Customers
### 9.8 Sales — list (filtered), detail, add payment, return, delete
### 9.9 Purchases — list, detail, create, add payment, return
### 9.10 Expenses — list, create/edit/delete, category tree
### 9.11 Stock — overview, low stock, adjustments CRUD, transfers CRUD
### 9.12 Payments — list (filtered), detail, create
### 9.13 Reports — 10 report types with date/location/user filters
### 9.14 Settings — read-only: business info, locations, tax rates, payment accounts

---

## 10. Error Handling

```dart
class ApiException implements Exception {
  final String message;
  final int? statusCode;
  final Map<String, dynamic>? errors; // validation errors per field

  ApiException({required this.message, this.statusCode, this.errors});
}
```

Handle 422 validation errors by iterating `errors` map and displaying per-field messages. Handle 401 by clearing token and redirecting to login.

---

## 11. Key Implementation Notes

- **Date format**: API accepts/returns `'Y-m-d H:i:s'`. Use `intl` package.
- **Currency**: precision from `business.currency_precision`. Display with currency symbol.
- **Images**: Product `image_url` is full URL. Upload via multipart/form-data.
- **Permissions**: Check `PermissionData.allPermissions` to show/hide UI elements.
- **Pagination**: Pagination metadata at root level of response JSON. Parse `current_page`, `last_page`, `per_page`, `total`.
- **Receipt HTML**: `GET /pos/receipt/{id}` returns `html_content` — render with `flutter_widget_from_html` or `webview_flutter`.
- **Project structure**:
  ```
  lib/
  ├── core/
  │   ├── network/     (api_client, api_exception, api_response, auth_interceptor)
  │   ├── storage/     (token_storage)
  │   └── theme/
  ├── data/
  │   ├── models/
  │   └── services/
  ├── providers/
  ├── ui/
  │   ├── auth/  dashboard/  pos/  products/
  │   ├── customers/  suppliers/  sales/  purchases/
  │   ├── expenses/  stock/  payments/  reports/  settings/
  ├── widgets/
  └── main.dart
  ```
