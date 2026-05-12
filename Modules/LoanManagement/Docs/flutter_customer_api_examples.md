# Flutter Customer API Examples

Base URL:
`/api/loan-management`

## 1) Login
`POST /customer/login`

```json
{
  "login": "012345678",
  "password": "secret123",
  "device_name": "android"
}
```

Response:
```json
{
  "success": true,
  "message": "Login success",
  "data": {
    "token": "SANCTUM_TOKEN"
  }
}
```

## 2) Profile
`GET /customer/profile`
Header: `Authorization: Bearer SANCTUM_TOKEN`

## 3) Change password
`POST /customer/change-password`

```json
{
  "current_password": "secret123",
  "new_password": "newSecret123",
  "new_password_confirmation": "newSecret123"
}
```

## 4) Own loans
`GET /customer/loans`

## 5) Loan schedules
`GET /customer/loans/{loanId}/schedules`

## 6) Payment history
`GET /customer/payments`

## 7) Balance summary
`GET /customer/payments/summary`

## 8) Upload payment proof
`POST /customer/payments/{paymentId}/proof`

```json
{
  "proof_file_id": 123
}
```

## 9) GPS status
`GET /customer/location/status`

## 10) Enable tracking
`POST /customer/location/enable`

```json
{
  "note": "Customer granted GPS permission"
}
```

## 11) Disable tracking
`POST /customer/location/disable`

```json
{
  "note": "Customer turned off tracking"
}
```

## 12) Realtime location update
`POST /customer/location`

```json
{
  "loan_id": 1,
  "latitude": 11.5564,
  "longitude": 104.9282,
  "accuracy": 10,
  "speed": 0,
  "heading": 0,
  "battery_level": 82,
  "device_id": "android-device-id",
  "app_version": "1.0.0",
  "recorded_at": "2026-05-12 14:30:00"
}
```

