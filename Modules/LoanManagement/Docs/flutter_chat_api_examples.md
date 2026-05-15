# Flutter Live Chat API Examples

Base URL: `/api/loan-management`

Polling: call thread detail every `5s` (config `LOAN_CHAT_POLLING_INTERVAL`, default `5`).

Realtime broadcasting is optional and disabled by default so chat APIs do not require Pusher, WebSocket, Soketi, or Laravel Reverb:

```env
LOAN_CHAT_BROADCASTING_ENABLED=false
LOAN_CHAT_POLLING_INTERVAL=5
```

When `LOAN_CHAT_BROADCASTING_ENABLED=true`, the backend will attempt to broadcast chat events and log a warning if broadcasting fails. Message creation still returns success after the message is stored, so Flutter polling remains the safe fallback.

All success responses use:
```json
{
  "success": true,
  "message": "Message",
  "data": {}
}
```

List endpoints return `data` as an array. Empty lists return `[]`.

Use `local_uuid` on every outbound Flutter message when offline retry/sync is enabled. The API stores it on `loan_chat_messages` and returns the existing message instead of creating a duplicate when the same sender retries the same `local_uuid` in the same thread.

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
  "message": "Hello staff",
  "local_uuid": "e4b30a7c-1a96-4c48-a522-2f2bfc0a7ac2"
}
```

Location message:
```json
{
  "message_type": "location",
  "latitude": 11.5564,
  "longitude": 104.9282,
  "address": "Phnom Penh",
  "local_uuid": "8aaf7b54-6b5e-42e6-8e2f-2f7d44d03f75"
}
```

Image/file/audio message: `multipart/form-data`
- `message_type`: `image|file|audio`
- `file`: upload
- `message`: optional caption
- `audio_duration_seconds`: optional (audio only)
- `local_uuid`: optional but recommended for retry-safe upload sync

Allowed uploads:
- image: `jpg,jpeg,png,webp`
- file: `pdf,doc,docx,xls,xlsx,txt,zip`
- audio: `mp3,m4a,aac,wav,ogg,webm`

Allowed audio MIME types:
- `audio/mpeg`
- `audio/mp4`
- `audio/x-m4a`
- `audio/aac`
- `audio/wav`
- `audio/ogg`
- `audio/webm`

### Mark read
`POST /customer/chats/{thread}/read`

## Staff/Admin

### Inbox
`GET /chats?status=open&priority=high`

Non-admin staff see assigned chats only. Users with `loan_management.chat.admin` can access all chats.

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

## Permissions

- `loan_management.chat.view`: list/show chats
- `loan_management.chat.reply`: create/reply
- `loan_management.chat.assign`: assign staff
- `loan_management.chat.close`: close/reopen
- `loan_management.chat.admin`: view and operate on all chats
