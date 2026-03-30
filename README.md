# Plex → Discord Webhook Relay

A PHP script that receives Plex webhook events and automatically sends Discord notifications when new movies, series, or episodes are added to your Plex library.

## Why This Project?

I wanted to send simple webhook notifications to a Discord channel, but all existing solutions I found were way too overkill or complex. So I built a simple, lightweight PHP script that does exactly what it needs to do—no more, no less.

## Features

- 🎬 Notifications for new movies and series
- 📺 Notifications for new episodes with season and episode numbers
- 🖼️ Automatic thumbnail support
- 🌍 Multi-language: German (DE) and English (EN)
- 🔒 Webhook authentication with secret
- 📝 Optional logging

## Requirements

- PHP 8.0+ 
- cURL extension
- A Plex server
- A Discord webhook

## Installation & Setup

### 1. Create Discord Webhook

1. Open a Discord server and channel where notifications should appear
2. Go to **Channel Settings** → **Integrations** → **Webhooks**
3. Click on **Create Webhook**
4. Copy the **Webhook URL**

### 2. Get Plex Token

**⚠️ This step is OPTIONAL** — You only need the Plex token if you want to display thumbnails in Discord notifications. If you skip this, notifications will still work perfectly, just without images.

The Plex token is required to authenticate requests to your Plex server and retrieve thumbnail images. Additionally, you'll need a **static IP address or DynDNS** because your Plex server's URL may change (e.g., if your ISP reassigns your public IP). Without a stable address, the thumbnail URLs become invalid over time.

#### Method 1: Via Plex Web
1. Open `http://localhost:32400/web` or your Plex instance
2. Go to **Settings** → **Console**
3. The token will be displayed

#### Method 2: Via Browser Developer Tools
1. Open Plex Web and press **F12** (Developer Tools)
2. Go to the **Network** tab
3. Refresh the page
4. Look for a request to your Plex server
5. Search in the headers for `X-Plex-Token=...`

#### Method 3: Via cURL
```bash
curl "http://your-plex-server:32400/library/sections" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### 3. Set Environment Variables

**Option A: System Environment Variables (Recommended)**

Set the following environment variables on your server (e.g., in Plesk, Apache configuration):

#### Required:
- **`DISCORD_WEBHOOK_URL`** — Your Discord webhook URL (from step 1)
- **`WEBHOOK_SECRET`** — A secret password (e.g., a secure random string)

#### Optional:
- **`LANGUAGE`** — Language: `en` (English, default) or `de` (German)
- **`LOG_ENABLED`** — Logging: `false` (default) or `true`
- **`PLEX_BASE_URL`** — Plex server URL (for thumbnails, e.g., `http://192.168.1.100:32400`)
- **`PLEX_TOKEN`** — Your Plex token (for thumbnails)

**Option B: Direct Code Configuration (Simple)**

You can also edit the script directly and set the values at the beginning of `plex_discord.php` (lines 17-21):

```php
$discordWebhookUrl = 'https://discord.com/api/webhooks/YOUR_WEBHOOK_ID/YOUR_WEBHOOK_TOKEN';
$webhookSecret = 'MySecurePassword123';
$language = 'en';  // 'de' for German
$logEnabled = false;
// ... PLEX_BASE_URL and PLEX_TOKEN below
```

**Note:** `.env` files are not automatically loaded. If you need `.env` support, use the environment variable approach or manually parse the file in the script.

#### Example With Environment Variables:
```env
DISCORD_WEBHOOK_URL=https://discord.com/api/webhooks/YOUR_WEBHOOK_ID/YOUR_WEBHOOK_TOKEN
WEBHOOK_SECRET=MySecurePassword
LANGUAGE=en
LOG_ENABLED=false
PLEX_BASE_URL=http://192.168.1.100:32400
PLEX_TOKEN=abc123def456xyz789
```

### 4. Configure Plex Webhook

1. Open your Plex server → **Settings** → **Webhooks**
2. Click on **Add Webhook**
3. Enter your script URL **with the secret as a query parameter**:
   ```
   https://your-domain.com/plex_discord.php?secret=MySecurePassword
   ```
4. Save

### 5. Test

Add a new movie or series to Plex. You should immediately receive a Discord notification!

## Languages

The script supports two languages:

### English (EN) — Default
```env
LANGUAGE=en
```

**Example Notification:**
```
🎬 New Movie in Movies
Title: The Matrix (1999)
Library: Movies
Server: My Plex Server
```

### German (DE)
```env
LANGUAGE=de
```

**Example Notification:**
```
🎬 Neuer Film in Filme
Titel: The Matrix (1999)
Library: Filme
Server: Mein Plex Server
```

## Logging

The script creates a `plex.log` file in the same directory with the following information:
- Successfully processed events
- Errors and warnings
- Rejected requests (invalid secret, wrong method, etc.)

Logging can be disabled with `LOG_ENABLED=false`.

## Discord Notification Structure

### Movies
- 🎬 Emoji
- Title, year, library, server
- Color code: Blue (3447003)

### Series
- 📺 Emoji
- Title, year, library, server
- Color code: Dark blue (3447003)

### Episodes
- 📺 Emoji
- Format: `Series Title - S01E05 - Episode Title`
- Library, server
- Color code: Dark blue (3447003)

### With Thumbnails
If `PLEX_BASE_URL` and `PLEX_TOKEN` are set, the poster/thumbnail of the series or movie will be displayed.

## Thumbnails & Static IP Requirements

Thumbnail support requires both `PLEX_BASE_URL` and `PLEX_TOKEN` to be configured. However, there's an important caveat:

**You need a stable, static IP address or DynDNS** because Plex server URLs change when your ISP reassigns your public IP. If your address changes and you don't update `PLEX_BASE_URL`, the thumbnail URLs will break and Discord won't display the images.

If you don't have a stable address and don't want to deal with this, simply skip the thumbnail setup—notifications will still work perfectly without images.

## Troubleshooting

### "Discord webhook URL is not configured"
→ Set `DISCORD_WEBHOOK_URL` as an environment variable

### "Webhook secret is not configured"
→ Set `WEBHOOK_SECRET` as an environment variable

### "Unauthorized (401)"
→ The secret in the webhook URL does not match `WEBHOOK_SECRET`

### "Discord request failed"
→ Check:
- Webhook URL is correct
- Network connection is available
- discord.com is reachable
- Plex server sends the webhook correctly

### No Notifications
→ Check:
- Webhook enabled in Plex?
- Script URL configured correctly?
- Logs in `plex.log` for errors
- Is the event `library.new` and the media type `movie`, `show`, or `episode`?

## Supported Media Types

- ✅ `movie` — Movies
- ✅ `show` — Series/Seasons
- ✅ `episode` — Individual episodes
- ❌ Albums, artists, clips, etc. are ignored
