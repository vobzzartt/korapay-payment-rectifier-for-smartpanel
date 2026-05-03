# CPANEL MYSQL DATABASE AUTO BACKUP

Simple PHP script that sends a copy of your database to you on Telegram each time your cron runs.

---

## Why this exists

There have been many recent cyber attacks including AI-driven automated attacks where attackers gain access to servers and databases.

Once a server is compromised, data can be deleted, sold on the dark web, modified, or stolen.

This script makes sure your database is backed up outside your server and delivered directly to your Telegram account, so you always have a safe copy.

---

## How it works

- Connects to your MySQL database
- Dumps all tables into a .sql file
- Sends the backup file to your Telegram bot
- Deletes the file from the server after sending

---

## Setup

1. Upload the script to your server

2. Update the config file path inside the script:

$config_path = '/path/to/your/config.php';

This should point to your application's database config file.

If the path is not correct, update it to match your server.

---

## Database config

Your config file must define these constants:

DB_HOST  
DB_USER  
DB_PASS  
DB_NAME  
TIMEZONE  

Example:

define('DB_HOST', 'localhost');
define('DB_USER', 'your_database_user');
define('DB_PASS', 'your_database_password');
define('DB_NAME', 'your_database_name');
define('TIMEZONE', 'your_timezone');

If these values do not match your database, update them accordingly.

---

## Required constants

The script expects these:

DB_HOST  
DB_USER  
DB_PASS  
DB_NAME  
TIMEZONE  

If your system uses different names, update them in the script.

---

## Telegram setup

1. Open Telegram and search for BotFather  
2. Create a bot and copy your bot token  
3. Get your chat ID  

4. Replace in the script:

define('TELEGRAM_BOT_TOKEN', 'your_bot_token');
define('TELEGRAM_CHAT_ID', 'your_chat_id');

---

## Why chat ID matters

Backups are sent only to the chat ID you set.

- Only your Telegram account receives the backup  
- No public access  
- No shared storage  

Access depends on:
- Your bot token
- Your Telegram account

---

## Telegram security

- Enable Two-Step Verification in Telegram  
- Keep your bot token private  
- Do not expose this script publicly  
- Restrict server access  

Telegram provides secure, unlimited cloud storage, so your backups remain available each time

---

## Cron setup

Run the script using cron.

Example:

* * * * * /usr/bin/php /path/to/backup.php

You can change the schedule depending on how often you want backups.

---

## Restore

To restore your database:

Option 1:
- Open phpMyAdmin  
- Select your database  
- Click Import  
- Upload the .sql file  

Option 2:

mysql -u USER -p DB_NAME < backup.sql

This will overwrite existing data.

---

## Notes

- Runs only from CLI  
- Browser access is blocked  
- Backup is sent and removed from the server  

---

## License

Free to folk with and contribute.
Built with ❤️ from Victor Bodude www.victorbodude.com