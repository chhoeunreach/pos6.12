# Flutter API Test (cURL)

Base URL example:
```bash
BASE="http://localhost:8000/api/loan-management"
```

## 1) App Settings
```bash
curl -X GET "$BASE/app-settings"
```

## 2) Staff Login
```bash
curl -X POST "$BASE/login" \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"secret"}'
```

## 3) Customer Login
```bash
curl -X POST "$BASE/customer/login" \
  -H "Content-Type: application/json" \
  -d '{"login":"customer001","password":"secret","device_name":"flutter-app"}'
```

## 4) Customer Profile
```bash
curl -X GET "$BASE/customer/profile" \
  -H "Authorization: Bearer CUSTOMER_TOKEN"
```

## 5) Customer Dashboard
```bash
curl -X GET "$BASE/customer/dashboard" \
  -H "Authorization: Bearer CUSTOMER_TOKEN"
```

Expected response shape:
```json
{
  "success": true,
  "message": "Dashboard loaded",
  "data": {
    "summary": {
      "active_loans": 1,
      "total_balance": "440.00",
      "total_paid": "110.00",
      "late_amount": "0.00",
      "next_due_date": null
    },
    "loans": [
      {
        "id": 1,
        "loan_number": "LN-0001",
        "product_name": "iPhone 12 Pro Max",
        "product_price": "500.00",
        "imei_or_serial": "123456789",
        "total_paid_amount": "110.00",
        "remaining_balance": "440.00",
        "monthly_payment_amount": "55.00",
        "monthly_principal": "50.00",
        "monthly_interest": "5.00",
        "payoff_this_month_amount": "405.00",
        "payoff_by_full_schedule_amount": "440.00",
        "total_loan_amount": "550.00",
        "total_interest": "50.00",
        "paid_principal": "100.00",
        "paid_interest": "10.00",
        "remaining_principal": "400.00",
        "remaining_months": 8,
        "total_installment_months": 10,
        "paid_month_count": 2,
        "loan_status": "current",
        "loan_status_label": "Current",
        "loan_status_color": "green",
        "currency": "USD"
      }
    ]
  }
}
```

## 6) Customer Loans
```bash
curl -X GET "$BASE/customer/loans" \
  -H "Authorization: Bearer CUSTOMER_TOKEN"
```

Returns a list of the same loan summary objects used in the dashboard `data.loans` array.

## 7) Customer Loan Detail
```bash
curl -X GET "$BASE/customer/loans/1" \
  -H "Authorization: Bearer CUSTOMER_TOKEN"
```

`data.loan_summary` contains the same formatted loan summary object. Money values are decimal strings, integer values are integers, list values are arrays, and empty objects are returned as `{}`.

## 8) Payment Receive (Staff)
```bash
curl -X POST "$BASE/mobile/payments" \
  -H "Authorization: Bearer STAFF_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "loan_id": 1,
    "customer_id": 1,
    "currency": "USD",
    "amount": 50,
    "details": [
      {"method":"cash","amount":30,"currency":"USD"},
      {"method":"aba","amount":20,"currency":"USD","transaction_no":"TXN-001"}
    ]
  }'
```

## 9) GPS Update (Customer)
```bash
curl -X POST "$BASE/customer/location" \
  -H "Authorization: Bearer CUSTOMER_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"latitude":11.5564,"longitude":104.9282,"accuracy":6.5,"device_id":"flutter-device-1"}'
```

## 10) Chat Message (Customer)
```bash
curl -X POST "$BASE/customer/chats/1/messages" \
  -H "Authorization: Bearer CUSTOMER_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"message":"Hello collector","message_type":"text"}'
```

## 11) File Upload
```bash
curl -X POST "$BASE/files/upload" \
  -H "Authorization: Bearer STAFF_TOKEN" \
  -F "file=@/path/to/file.jpg" \
  -F "category=payment_proof"
```
