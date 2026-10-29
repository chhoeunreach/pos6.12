# Live Chat

Open **Chats** and choose the speech-bubble **Live Chat** button, or open **Communication & Tracking → Live Chats**. Telegram remains a separate channel.

Live Chat uses the existing customer-app conversations and `/loan-management/chat-api/chats` endpoints. Staff can search customers, start conversations, send text/images/files, load older messages, see unread counts and typing/read indicators, and close/reopen chats when permitted. Customer-app messages appear automatically.

Updates use lightweight polling while the page is visible: new messages every two seconds and the conversation list about every six seconds. Background/offline tabs pause updates. Failed requests back off; expired sessions require signing in again. No websocket service is required.

Drafts stay in memory for each conversation while the page remains open. Retry retains the same message UUID to avoid duplicate sends. Do not reload the page to retry an unconfirmed send; use **Retry**.

The feature uses the module's existing `loan_chat_threads`, `loan_chat_messages`, `loan_chat_participants`, `loan_customers`, and `loan_files` tables. On an older installation missing the chat schema, an administrator can apply the existing chat workflow migration using the application's working database connection:

```sh
php artisan migrate --database=mysql_loan --path=Modules/LoanManagement/Database/migrations/2026_05_19_150000_complete_messenger_live_chat_workflow.php
```

Access follows the current chat permissions. Typing updates require `loan_management.chat.reply`; closing/reopening requires `loan_management.chat.close`.
