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


## Discord webhook för kvitton (valfritt)
Du kan automatiskt posta varje nytt kvitto till Discord och få tillbaka en text som frontend kopierar till urklipp.

1. Skapa en Discord webhook i din kanal.
2. Sätt webhook via **Admin → Discord webhook (Admin)** i appen (kräver användarhantering-behörighet), eller använd miljövariabeln `DISCORD_RECEIPT_WEBHOOK_URL`.

Exempel:

```bash
DISCORD_RECEIPT_WEBHOOK_URL="https://discord.com/api/webhooks/..." php -S 127.0.0.1:8000
```

När ett kvitto sparas:
- backend postar kvittotext till webhooken (`wait=true`)
- svaret returneras till frontend
- frontend försöker kopiera svarstexten till urklipp automatiskt


### Skapa kvitto från Discord-kommando (webhook)
Du kan låta en Discord-bot/webhook skapa kvitton via endpoint:
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
