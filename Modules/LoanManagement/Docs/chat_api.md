# LoanManagement Messenger Chat API

Base path: `/api/loan-management`

Authentication:
- Customer routes use `auth:customer_loan_api`.
- Staff routes use `auth:api`.
- Non-admin staff only see chats assigned to their `staff_id`.
- Users with `loan_management.chat.admin` can see all staff chats.

Staff permissions:
- `loan_management.chat.view`: list and show chats
- `loan_management.chat.reply`: create chats and send messages
- `loan_management.chat.close`: close/reopen chats
- `loan_management.chat.admin`: view all assigned/unassigned chats

## Customer Routes

- `GET /customer/chats`
- `GET /customer/chats/{thread}`
- `POST /customer/chats/{thread}/messages`
- `POST /customer/chats/{thread}/read`
- `POST /customer/chats/{thread}/typing`

## Staff Routes

- `GET /chats`
- `GET /chats/{thread}`
- `POST /chats/{thread}/messages`
- `POST /chats/{thread}/read`
- `POST /chats/{thread}/typing`
- `POST /chats/{thread}/close`

Existing staff helper routes remain available for back-office use:
- `POST /chats`
- `POST /chats/{thread}/assign`
- `POST /chats/{thread}/reopen`

## Chat List Response

`GET /customer/chats` and `GET /chats` return Messenger-friendly thread rows:

```json
{
  "success": true,
  "message": "Threads loaded",
  "data": [
    {
      "id": 1,
      "thread_number": "CHT-000001",
      "display_name": "Customer Name",
      "display_subtitle": "012345678 • LN-0001",
      "avatar_url": "",
      "status": "open",
      "is_online": false,
      "is_pinned": false,
      "is_muted": false,
      "last_message": "Hello",
      "last_message_type": "text",
      "last_message_at": "2026-05-15 10:00:00",
      "unread_count": 2,
      "typing": false
    }
  ]
}
```

`is_online` is true when the opposite side has `last_seen_*_at` within 2 minutes. `typing` is true when the opposite side has `typing_*_at` within 10 seconds.

## Chat Detail Response

`GET /customer/chats/{thread}` and `GET /chats/{thread}` return the same thread object plus `messages`.

```json
{
  "success": true,
  "message": "Thread loaded",
  "data": {
    "id": 1,
    "thread_number": "CHT-000001",
    "display_name": "Customer Name",
    "display_subtitle": "012345678 • LN-0001",
    "avatar_url": "",
    "status": "open",
    "is_online": false,
    "is_pinned": false,
    "is_muted": false,
    "last_message": "Hello",
    "last_message_type": "text",
    "last_message_at": "2026-05-15 10:00:00",
    "unread_count": 2,
    "typing": false,
    "messages": []
  }
}
```

## Message Response

Message send and detail endpoints return messages in this shape:

```json
{
  "id": 1,
  "thread_id": 1,
  "sender_type": "customer",
  "sender_id": 1,
  "sender_name": "Customer Name",
  "sender_avatar_url": "",
  "message": "Hello",
  "message_type": "text",
  "file": {},
  "location": {},
  "audio_duration_seconds": 0,
  "delivered_at": "2026-05-15 10:00:00",
  "read_at": null,
  "reaction": null,
  "reply_to_message_id": null,
  "is_own": false,
  "created_at": "2026-05-15 10:00:00"
}
```

## Sending Messages

`POST /customer/chats/{thread}/messages`
`POST /chats/{thread}/messages`

Form fields:
- `message_type`: `text`, `image`, `file`, `audio`, or `location`
- `message`: required for `text`, optional caption for media
- `file`: required for `image`, `file`, and `audio`
- `audio_duration_seconds`: optional integer for audio
- `latitude`, `longitude`, `address`: required coordinates for location messages
- `local_uuid`: optional idempotency key for offline retry
- `reply_to_message_id`: optional parent message id
- `reaction`: optional reaction string
- `metadata`: optional object

## Read And Typing

`POST /read` marks messages from the opposite side as read:
- customer read sets `read_by_customer_at` and resets `unread_customer_count`
- staff/admin read sets `read_by_staff_at` and resets `unread_staff_count`

`POST /typing` updates:
- customer: `typing_customer_at`
- staff/admin: `typing_staff_at`

Loading a chat list, opening a chat detail, marking read, and sending a message update the viewer side `last_seen_*_at`.
