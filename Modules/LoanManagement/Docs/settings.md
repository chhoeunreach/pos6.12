# Business Settings

Open **Settings → Business Settings**. Sections cover company details, loan defaults, branding, currency and document prefixes, payment channels, printing, Telegram credentials/preferences, social links, CMS, and portal access. The full CMS editor remains available for page sections and partners.

- `loan_management.settings.view`, `loan_management.setting`, or `loan_management.view` allows viewing. Only `loan_management.setting` allows saving or checking Telegram credentials. Viewers receive disabled controls and no bot token in the rendered page.
- Save All Settings submits every section. Search and tab selection leave form fields intact. Invalid fields are revealed even when their section is hidden. Server validation errors link to the affected controls and retain submitted values.
- Changes are marked as unsaved until submitted; Reset changes reloads the saved page after confirmation. Hash links open a section directly. Section links support arrow keys and Home/End.
- Payment channels can be added, ordered, and deactivated. Duplicate names are rejected. Methods already used in payments retain their historical names; deactivate them and add another channel instead. Usage totals aggregate each split detail and include legacy payments without details once.
- Image removal clears both the saved path and stored asset. Settings JSON is replaced atomically to avoid partial reads.
- **Check connection** calls Telegram `getMe` with the current token. It sends no messages, registers no webhook, and does not save the form. A configured token does not establish that scheduled alerts or Telegram chat webhooks are running; those still depend on the application's notification workflows and deployment configuration.

Validation uses isolated SQLite databases, temporary settings storage, fake uploads, and mocked Telegram responses. The UI checks use jsdom; they do not replace a visual check in the deployed browser.
