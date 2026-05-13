# Final Menu & API Test Checklist

## Menu URLs
- [ ] `/loan-management/dashboard`
- [ ] `/loan-management/customers`
- [ ] `/loan-management/guarantors`
- [ ] `/loan-management/blacklist`
- [ ] `/loan-management/loans`
- [ ] `/loan-management/schedules`
- [ ] `/loan-management/monthly-payments`
- [ ] `/loan-management/overdue`
- [ ] `/loan-management/payments`
- [ ] `/loan-management/payment-history`
- [ ] `/loan-management/collection-visits`
- [ ] `/loan-management/gps`
- [ ] `/loan-management/chat`
- [ ] `/loan-management/aba`
- [ ] `/loan-management/reports`
- [ ] `/loan-management/import`
- [ ] `/loan-management/settings`

## Functional Checks
- [ ] Dashboard loads (no 500)
- [ ] Sidebar tree menus open
- [ ] Customer CRUD
- [ ] Guarantor CRUD-ready tables exist
- [ ] Loan create/edit/view
- [ ] Overdue tabs page opens
- [ ] Payment receive API works
- [ ] Staff GPS and customer GPS APIs work
- [ ] Chat thread list/send/read/close works
- [ ] ABA create/check-status APIs work
- [ ] File upload API works
- [ ] Import and reports pages open

## Flutter API Format Checks
- [ ] Every response has `success`, `message`, `data`
- [ ] Empty list is `[]`
- [ ] Empty object is `{}`
- [ ] IDs are integers
- [ ] Money fields are decimal strings
- [ ] Date fields are `YYYY-MM-DD` or `YYYY-MM-DD HH:mm:ss`

