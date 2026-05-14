# Flutter Live Chat API Examples

Base URL: `/api/loan-management`

Polling: call thread detail every `5s` (config `LOAN_CHAT_POLLING_SECONDS`, default `5`).

## Customer

### Get my chats
`GET /customer/chats`

### Create thread
`POST /customer/chats`
```json
{
  "loan_id": 10,
  "subject": "Need extension",
  "type": "customer_collector",
  "priority": "high"
}
```

### Thread detail
`GET /customer/chats/{thread}`

### Send text message
`POST /customer/chats/{thread}/messages`
```json
{
  "message_type": "text",
  "message": "Hello staff"
}
```

Location message:
```json
{
  "message_type": "location",
  "latitude": 11.5564,
  "longitude": 104.9282,
  "address": "Phnom Penh"
}
```

Image/file/audio message: `multipart/form-data`
- `message_type`: `image|file|audio`
- `file`: upload
- `message`: optional caption
- `audio_duration_seconds`: optional (audio only)

### Mark read
`POST /customer/chats/{thread}/read`

## Staff/Admin

### Inbox
`GET /chats?status=open&priority=high`

### Create thread
`POST /chats`

### Send message
`POST /chats/{thread}/messages`

### Assign
`POST /chats/{thread}/assign`
```json
{ "staff_id": 15 }
```

### Read / Close / Reopen
- `POST /chats/{thread}/read`
- `POST /chats/{thread}/close`
- `POST /chats/{thread}/reopen`
