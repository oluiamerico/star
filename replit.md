# eSIM Virtual Starlink - Landing Page

## Project Overview
A marketing/sales landing page funnel for "eSIM Virtual Starlink" service targeting the Portuguese market. Offers satellite-based mobile internet (Direct-to-Cell) plan selection and checkout.

## Tech Stack
- **Frontend:** Static HTML/CSS/JS (exported from Next.js)
- **Backend:** PHP 8.2 for payment API endpoints
- **Styling:** Tailwind CSS
- **Payment Gateway:** WayMB API

## Project Structure
```
/                   - Main landing page (index.html)
/api/               - PHP backend scripts
  create.php        - Payment creation endpoint (WayMB API)
  check.php         - Payment status check endpoint
/checkout/          - Checkout page
/escolher-chip/     - Plan selection page
/obrigado/          - Thank you / success page
/upsell-01/         - Upsell offer page
/css/               - Compiled CSS
/js/                - Compiled JavaScript
/fonts/             - Web fonts (woff2)
/images/            - Images
/_next/static/media/- Next.js static media (fonts symlinked)
```

## Running the App
- **Workflow:** "Start application" runs `php -S 0.0.0.0:5000`
- **Port:** 5000
- **Server:** PHP built-in server handles both static files and PHP scripts

## Deployment
- **Target:** Autoscale
- **Run Command:** `php -S 0.0.0.0:5000`

## Notes
- Target market: Portugal (+351 phone prefix, EUR currency)
- Payment processed via WayMB API at `api.waymb.com`
- Analytics via Utmify and Vercel Analytics
