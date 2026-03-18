# Redline Performance (Minimal hemsida)

Ren hemsida med **minimal PHP** + **HTML/JS**.
Ingen lokal app-launcher, inga batch/shell-startscript.

## Funktioner
- Inloggning med personnummer + lösenord
- Skapa kvitto
- Lista kvitton
- Kopiera arbetsorder (`Redline Performance Arbetsorder - 00000`)
- Kundregister (namn + telefon)
- Fordonsdatabas (regplåt + modell)
- JSON-lagring per tabell i `data/json/*.json`

## Kör sidan
Starta bara en vanlig PHP-server i projektmappen:

```bash
php -S 127.0.0.1:8000
```

Öppna sedan:
- `http://127.0.0.1:8000/index.php`


## Discord bot/webhook för kvitton (valfritt)
Du kan automatiskt posta varje nytt kvitto till Discord och få tillbaka en text som frontend kopierar till urklipp.

**Rekommenderat (Discord Bot):**
1. Skapa en Discord Bot i Developer Portal och bjud in den till servern.
2. Sätt miljövariabler:
   - `DISCORD_BOT_TOKEN`
   - `DISCORD_BOT_CHANNEL_ID`

Exempel:

```bash
DISCORD_BOT_TOKEN="din-bot-token" DISCORD_BOT_CHANNEL_ID="123456789012345678" php -S 127.0.0.1:8000
```

**Fallback (Webhook):**
- Sätt webhook via **Admin → Discord webhook (Admin)** i appen (kräver användarhantering-behörighet), eller använd `DISCORD_RECEIPT_WEBHOOK_URL`.

När ett kvitto sparas:
- backend postar kvittotext via bot (om token+channel finns), annars webhook
- svaret returneras till frontend
- frontend försöker kopiera svarstexten till urklipp automatiskt


### Skapa kvitto från Discord-kommando
Du kan låta en Discord-bot skapa kvitton via endpoint:
`POST index.php?action=api_discord_create_receipt`

Kräver miljövariabel:

```bash
DISCORD_COMMAND_SECRET="byt-denna-hemlighet" php -S 127.0.0.1:8000
```

Exempel payload med kommandoformat:

```json
{
  "secret": "byt-denna-hemlighet",
  "command": "/kvitto ABC123;Volvo AB;Service + Styling;2499;Akutjobb;19900101-1234"
}
```

Format: `/kvitto regnr;kund;jobb;summa;[kommentar];[mekaniker_pnr]`

Endpointen svarar med `receipt_id` och `reply_text` som kan skickas tillbaka i Discord.

## Automatiska rabatter
- **Stammis**: Om en kund har fler än **15 kvitton** får kunden automatiskt rabattpreset **Stammis (25%)**, men bara om kunden inte redan har en annan rabatt vald.
- **Anställd**: Om kundens namn/personnummer matchar en anställd användare får kunden automatiskt **Anställd (50%)**.
- Anställd-rabatten har prioritet över Stammis-regeln.

## Demo-konton
- `19900101-1234 / motor123`
- `19920202-5678 / garage123`
- `19950505-9012 / bennys123`

## API-endpoints (används av frontend)
- `GET index.php?action=api_me`
- `POST index.php?action=api_login`
- `POST index.php?action=api_logout`
- `GET index.php?action=api_receipts`
- `POST index.php?action=api_create_receipt`

Design/layout ändras i `index2.html`.
Backend/API ligger i `index.php`.
