# LoanManagement Chat Voice Message API

Voice messages use the existing chat message endpoints and `multipart/form-data`.

## Customer Endpoint

```bash
curl -X POST \
  -H "Authorization: Bearer TOKEN" \
  -F "message_type=audio" \
  -F "audio_duration_seconds=12" \
  -F "file=@voice.m4a" \
  https://domain.com/api/loan-management/customer/chats/1/messages
```

## Staff/Admin Endpoint

```bash
curl -X POST \
  -H "Authorization: Bearer TOKEN" \
  -F "message_type=audio" \
  -F "audio_duration_seconds=12" \
  -F "local_uuid=optional-client-uuid" \
  -F "file=@voice.m4a" \
  https://domain.com/api/loan-management/chats/1/messages
```

## Request Fields

- `message_type`: must be `audio`
- `file`: required voice file
- `audio_duration_seconds`: optional number from `0` to `3600`
- `local_uuid`: optional idempotency key for safe retry

Allowed MIME types: `audio/mpeg`, `audio/mp3`, `audio/mp4`, `audio/x-m4a`, `audio/aac`, `audio/wav`, `audio/x-wav`, `audio/ogg`, `audio/webm`, `audio/3gpp`.

Allowed extensions: `mp3`, `m4a`, `aac`, `wav`, `ogg`, `webm`, `3gp`.

Maximum size: `20MB`.

## Validation Errors

Missing file:

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "file": ["Voice file is required."]
  }
}
```

Unsupported type:

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "file": ["Unsupported voice file type."]
  }
}
```

## Response Shape

```json
{
  "success": true,
  "message": "Message sent",
  "data": {
    "id": 1,
    "thread_id": 1,
    "sender_type": "customer",
    "sender_id": 1,
    "sender_name": "Customer Name",
    "sender_avatar_url": "",
    "message": "",
    "message_type": "audio",
    "file": {
      "id": 10,
      "url": "https://domain.com/storage/loan/chat/audio/2026/05/voice.m4a",
      "name": "voice.m4a",
      "mime": "audio/x-m4a",
      "size": 123456,
      "extension": "m4a"
    },
    "audio_duration_seconds": 12,
    "is_own": false,
    "delivered_at": "2026-05-15 10:00:00",
    "read_at": null,
    "created_at": "2026-05-15 10:00:00"
  }
}
```

Audio messages update the thread preview as:

```json
{
  "last_message": "Voice message",
  "last_message_type": "audio"
}
```
