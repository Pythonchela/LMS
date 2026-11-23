# Payments, blog and SEO

What was added, how it works, and what has to be done once on the server.

## Payments (Razorpay, inside the LMS)

- A student opens a batch at `/store/<batch>` (public page), optionally types a **coupon**, and pays with Razorpay (UPI, cards, net banking).
- The **price always comes from the batch** and the discount from the coupon rules, on the server. The browser never sends an amount.
- Access is given only after Razorpay's **signature is verified**, or Razorpay's **webhook** confirms the payment. Both can arrive; the student is enrolled **once**.
- Buying gives the student every course in the batch (same `EnrollmentService` as the WordPress link, so validity rules still apply). Buying again extends the access.
- A 100% coupon (or a free batch) enrols straight away, no Razorpay.
- The student sees **Payments** in the sidebar: a list and a printable **receipt** (Print / Save as PDF).
- Admin: **Sales > Payments** (list, filters, totals, "Record refund") and **Sales > Coupons**. Permission: `manage_payments` (super admin always).
- If Razorpay keys are empty, paid checkout is off and the store falls back to the batch's old purchase link.

### One-time setup on the server
1. `git pull`, `composer install --no-dev`, `php artisan migrate --force` (3 new tables: `coupons`, `payments`, `blog_*`), then the usual `config:cache`, `route:cache`, `view:cache`.
2. In `backend/.env` add the keys (see `.env.example`): `RAZORPAY_KEY_ID`, `RAZORPAY_KEY_SECRET`, `RAZORPAY_WEBHOOK_SECRET`. **Start with TEST keys.** Never paste keys in chat or commit them.
3. Razorpay dashboard > Webhooks: URL `https://api.<domain>/api/v1/payments/webhook/razorpay`, same secret as above, events `payment.captured`, `payment.failed`, `order.paid`, `refund.processed`.
4. Make a test purchase with a Razorpay test card/UPI, check the receipt and the student's courses, then swap in the live keys.
5. Optional: `LMS_GST_PERCENT`, `LMS_BUSINESS_NAME/ADDRESS/GSTIN` for the receipt.

Refunds: send the money back from the Razorpay dashboard. The webhook marks the payment refunded, or use "Record refund" in the admin. Access is not removed automatically: remove it under Enrollments if needed.

## Blog (`/blog`)

- Admin: **Blog > Blog posts** and **Blog categories**. Permission: `manage_blog` (admins have it by default).
- A post has a title, address (slug), excerpt, rich text body, cover image + alt text, category, author, **publish date** (a future date schedules it) and an SEO box (search title, description, canonical, hide from search).
- The body is cleaned on save (scripts, inline styles, event handlers are removed).
- Cover images and pictures in posts are stored on the `public` disk: run `php artisan storage:link` once on the server.

## SEO

- `/store`, `/store/<batch>`, `/blog`, `/blog/<post>`, `/blog/category/<slug>` are **server-rendered** (search engines read the full text), each with its own title, description, canonical link, Open Graph / Twitter tags and JSON-LD (BlogPosting, Product + Offer, BreadcrumbList, ItemList).
- `/sitemap.xml` (store, batches, blog posts, categories), `/robots.txt`, RSS at `/blog/feed.xml`.
- The student's private pages (login, courses, tests, results...) are in robots.txt **and** sent with `X-Robots-Tag: noindex`.
- After going live: add the site in Google Search Console and submit `https://lms.<domain>/sitemap.xml`.
- The student site must run as a Node app (it already does: it has redirects). `NEXT_PUBLIC_SITE_URL` is optional.

## Theme
Teal and orange. Colours live in `student-app/src/app/globals.css` (`brand-*` teal, `accent-*` orange, `navy-*` deep teal) and the admin panel colours in `AdminPanelProvider`.
