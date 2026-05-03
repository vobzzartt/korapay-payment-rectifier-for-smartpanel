# Korapay Payment Rectifier for SmartPanel

Cron script that fixes a common SmartPanel issue: users pay successfully, but Korapay webhook delays leave transactions stuck on "Waiting".

This script rechecks those transactions directly from Korapay and updates the wallet correctly.

---

## What it does

- Finds all "Waiting" Korapay transactions
- Verifies status directly from Korapay
- Credits wallet if payment is successful
- Cancels expired or failed transactions
- Rechecks pending ones after 90 minutes
- Skips anything already processed

---

## Why this was built

SmartPanel depends on webhooks for wallet funding.

When Korapay webhooks delay or fail:
- Payments go through but wallets are not credited
- Admins start handling funding manually
- "Waiting" transactions pile up
- Users lose trust

This script handles that by checking Korapay directly instead of relying on webhooks.

---

## How it works

1. Fetch all "Waiting" transactions from general_transaction_logs
2. For each transaction:
   - Check status from Korapay
   - If success → credit wallet
   - If expired or failed → cancel it
   - If still pending → save for later
3. Pending transactions are stored in pending_korapay.json
4. After 90 minutes, pending ones are checked again
5. Processed transactions are removed from pending automatically

All updates run inside MySQL transactions.

---

## Files created

- korapay_log.txt  
- pending_korapay.json  

Both are stored in the same directory as the script.

---

## Installation

1. Upload the script

Example path:
/home/username/public_html/cron/korapay_rectifier.php

2. Set your config path

require_once '/home/yourusername/public_html/app/config.php';

3. Add your Korapay key

$KORAPAY_SECRET_KEY = 'YOUR_KORAPAY_SECRET_KEY';

4. Set permissions

chmod 755 cron/
chmod 644 cron/korapay_log.txt
chmod 644 cron/pending_korapay.json

---

## Cron

Run every minute:

* * * * * /usr/bin/php -q /home/username/public_html/cron/korapay_rectifier.php

Runs in CLI only. Browser access is blocked.

---

## Status handling

- success → credit wallet  
- expired → cancel  
- failed → cancel  
- pending → recheck later  

---

## Notes

- Detects and skips already processed transactions  
- Prevents double crediting  
- Keeps pending list clean  

---

## Requirements

- SmartPanel SMM v3+
- Korapay API key
- PHP with curl enabled
- Cron access

---

## License

MIT
