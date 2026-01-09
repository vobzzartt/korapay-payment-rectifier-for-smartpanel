# Korapay Payment Rectifier for SmartPanel

A clean, reliable, cron-driven solution designed to solve one of the biggest issues SmartPanel owners face:  
**users make payments successfully, but Korapay webhooks fail or delay — leaving deposits stuck on “Waiting”.**

This script automatically rechecks any **Waiting** Korapay transaction from your SmartPanel database, verifies the real payment status directly from Korapay, and updates your SmartPanel wallet system accordingly.

It ensures:
- Successful payments are **automatically credited** to the user.
- Expired or failed transactions are **cleanly cancelled**.
- Already-processed transactions are **skipped safely** without looping forever.
- Pending transactions get rechecked again after 90 minutes.
- The system stays safe, accurate, and SmartPanel-compatible.

---

## 🚀 Why This Script Was Built

SmartPanel depends heavily on external webhooks for wallet funding.  
But with Korapay, **webhook delays and failures are common**, resulting in:

- Users paying but wallet not funded  
- Admins manually adding funds  
- Hundreds of *“Waiting”* transactions piling up  
- Angry customers thinking your panel is fake  
- Lost trust & lost money

This script solves that completely.

It acts as a **backup brain** for your SmartPanel billing:

- Checks Korapay directly (not webhook)
- Confirms the real status
- Applies SmartPanel-style crediting logic
- Cleans expired payments
- Runs silently through cron

Once installed, it handles everything automatically.

---

## 🧠 How It Works (Simple Explanation)

1. **Find every “Waiting” Korapay transaction** in the SmartPanel `general_transaction_logs` table.
2. For each one, the script:
   - Calls Korapay to verify the transaction status.
   - If *success* → credit user’s wallet using SmartPanel logic.
   - If *expired/failed* → mark as cancelled.
   - If *still pending* → store it for rechecking later.
3. **Pending transactions are saved in `pending_korapay.json`.**
4. After **90 minutes**, the script rechecks old pending ones:
   - If payment eventually succeeds → wallet is funded.
   - If still not paid → the transaction is automatically cancelled.
5. To avoid clogging logs or loops:
   - If the webhook already handled it, the script ignores it cleanly.
   - If SmartPanel has changed the status already, it is removed from pending.

Everything is safe, atomic, and uses MySQL transactions to protect the wallet.

---

## 📂 Files Generated

- **`korapay_log.txt`**  
  All processing logs are stored here.

- **`pending_korapay.json`**  
  Tracks transactions that didn’t succeed immediately and must be rechecked later.

Both files live inside the same directory as the script.

---

## 📥 Installation

### 1. Upload the Script
Place the file in any secure directory in your hosting, e.g.:

```
/home/username/public_html/cron/korapay_rectifier.php
```

### 2. Update Your Config Path
Change this line to match your SmartPanel config.php path:

```php
require_once '/home/yourusername/public_html/app/config.php';
```

### 3. Add Your Korapay Secret Key

```php
$KORAPAY_SECRET_KEY = 'YOUR_KORAPAY_SECRET_KEY';
```

### 4. Give Write Permission to Logs

```
chmod 755 cron/
chmod 644 cron/korapay_log.txt
chmod 644 cron/pending_korapay.json
```

---

## ⏱️ Setting Up the Cron Job

Run the script **every 1 minute** for best accuracy:

```
* * * * * /usr/bin/php -q /home/username/public_html/cron/korapay_rectifier.php
```

Make sure it is running as CLI.  
The script automatically blocks browser access.

---

## 🔍 Supported Korapay Statuses

The script checks:

- **success** → wallet credited  
- **expired** → automatically cancelled  
- **failed** → automatically cancelled  
- anything else → stored for future recheck  

---

## 🛡 Protective Features

### ✔ Prevents smartpanel double-crediting
If the webhook already credited the user, the script **detects it and skips automatically**.

### ✔ MySQL transaction safety
Balance updates & status updates happen **inside a transaction**, protecting your database from race conditions.

### ✔ Pending cleaner
Removes any processed ID from pending to avoid loops or repeated logs.

---

## 📌 Requirements

- SmartPanel SMM v3 and above
- Korapay merchant API keys
- PHP 7.2+ with curl enabled
- Cron access

---

## 🤝 Created By

**Victor Bodude **  
Built for SmartPanel owners who want **instant, accurate, automated wallet funding** even when Korapay webhook fails.

---

## 📄 License

MIT — Free to use, modify, and contribute.

---

## ❤️ Contributions Welcome

If you improve the script, feel free to open a PR or issue.