# Spearing Events API

All endpoints are relative to `/api`. JSON responses use `{ "success": true, "data": {} }`; failures use `{ "success": false, "error": { "code", "message", "fields" } }`.

Browser requests use same-origin PHP sessions. Read `csrfToken` from `GET /auth/me` and send it in `X-CSRF-Token` for every state-changing request. Booking creation also requires a UUID in `Idempotency-Key`.

| Method | Endpoint | Access | Purpose |
| --- | --- | --- | --- |
| POST | `/auth/register` | Public | Create email account (full name, username, email, password, termsAccepted) and session |
| POST | `/auth/login` | Public | Authenticate and rotate session ID |
| POST | `/auth/forgot-password` | Public | Request a one-hour, single-use email reset link |
| POST | `/auth/reset-password` | Public | Consume a reset token and replace the password |
| POST | `/auth/logout` | Signed in | End session |
| GET | `/auth/me` | Public | Session user and CSRF token |
| PATCH | `/auth/profile`, `/auth/password` | Signed in | Update account |
| GET | `/events`, `/events/{id}` | Public | Search, filter, paginate, view events |
| POST | `/bookings` | Signed in | Reserve inventory and create booking |
| GET | `/me/bookings`, `/bookings/{id}` | Signed in | Booking history/details |
| POST | `/payments/orders` | Signed in | Create Razorpay order |
| POST | `/payments/verify` | Signed in | Verify checkout signature |
| POST | `/payments/webhook` | Razorpay | Verify webhook and reconcile capture |
| GET | `/tickets/{token}`, `/tickets/{token}/qr` | Owner/staff | Ticket details and PNG QR |
| POST | `/tickets/{token}/check-in` | Staff/admin | Atomic entry check-in |
| POST | `/refunds` | Signed in | Request eligible refund |
| POST | `/reviews` | Confirmed attendee | Create/update review |
| GET/POST | `/notifications`, `/notifications/read-all` | Signed in | Read notifications |
| POST/PUT | `/admin/events`, `/admin/events/{id}` | Admin | Create/update event |
| POST | `/admin/events/{id}/publish|cancel|archive` | Admin | Lifecycle operations |
| POST | `/admin/events/{id}/image` | Admin | Validated local image upload |
| GET | `/admin/users`, `/admin/bookings`, `/admin/reports/summary` | Admin | Administration and reports |
| GET | `/payment-qr` | Public | Active UPI ID, payee name, and static fallback QR |
| GET | `/payments/upi-qr` | Public / Signed in | Dynamic UPI payment QR (supports `bookingId`, `eventId`, or `amount`) |
| POST | `/payments/upi-submissions` | Signed in | Submit UPI UTR / transaction reference for verification |
| POST | `/admin/payment-settings` | Admin | Update receiver UPI ID and payee display name |
| POST | `/admin/payment-qr` | Admin | Upload optional fallback static payment QR |
| POST | `/admin/bookings/{id}/confirm|cancel` | Admin | Booking management and manual UPI payment confirmation |
| POST | `/admin/refunds/{id}/approve|reject` | Admin | Refund decision |

Razorpay is authoritative: neither redirects nor browser responses confirm a paid booking until the signature or webhook is verified on this backend.