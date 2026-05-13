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

## 6) Customer Loans
```bash
curl -X GET "$BASE/customer/loans" \
  -H "Authorization: Bearer CUSTOMER_TOKEN"
```

## 7) Payment Receive (Staff)
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

## 8) GPS Update (Customer)
```bash
curl -X POST "$BASE/customer/location" \
  -H "Authorization: Bearer CUSTOMER_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"latitude":11.5564,"longitude":104.9282,"accuracy":6.5,"device_id":"flutter-device-1"}'
```

## 9) Chat Message (Customer)
```bash
curl -X POST "$BASE/customer/chats/1/messages" \
  -H "Authorization: Bearer CUSTOMER_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"message":"Hello collector","message_type":"text"}'
```

## 10) File Upload
```bash
curl -X POST "$BASE/files/upload" \
  -H "Authorization: Bearer STAFF_TOKEN" \
  -F "file=@/path/to/file.jpg" \
  -F "category=payment_proof"
```

