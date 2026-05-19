# LoanManagement Voice Message API

Voice messages are sent through the same chat message endpoint used for files.

## Customer Send Voice Message

```bash
curl -X POST "$BASE/customer/chats/1/messages" \
  -H "Authorization: Bearer CUSTOMER_TOKEN" \
  -F "message_type=audio" \
  -F "audio_duration_seconds=12" \
  -F "file=@/path/to/voice.m4a"
```

## Staff Send Voice Message

```bash
curl -X POST "$BASE/chats/1/messages" \
  -H "Authorization: Bearer STAFF_TOKEN" \
  -F "message_type=audio" \
  -F "audio_duration_seconds=12" \
  -F "file=@/path/to/voice.m4a"
```

## Allowed Audio Types

- mp3
- m4a
- aac
- wav
- ogg
- webm

## Response Contract

```json
{
  "success": true,
  "message": "Message sent",
  "data": {
    "id": 10,
    "message_type": "audio",
    "file": {},
    "audio_duration_seconds": 12
  }
}
```

Money fields are not used in this API. Integer fields must be integers, list fields must be arrays, and object fields must be JSON objects.
