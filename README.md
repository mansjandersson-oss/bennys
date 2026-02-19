# Benny's Motorworks (Minimal hemsida)

Ren hemsida med **minimal PHP** + **HTML/JS**.
Ingen lokal app-launcher, inga batch/shell-startscript.

## Funktioner
- Inloggning med personnummer + lösenord
- Skapa kvitto
- Lista kvitton
- Kopiera arbetsorder (`Benny's Arbetsorder - 00000`)
- SQLite lagring i `data/bennys.sqlite`

## Kör sidan
Starta bara en vanlig PHP-server i projektmappen:

```bash
php -S 127.0.0.1:8000
```

Öppna sedan:
- `http://127.0.0.1:8000/index.php`

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
