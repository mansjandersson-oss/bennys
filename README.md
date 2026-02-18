# Benny's Motorworks Dashboard

En GTA RP-inspirerad dashboard där inloggade mekaniker kan skapa kvitton och kopiera arbetsorder.

## Varför denna version är lätt att hosta

Den här versionen är byggd i **ren PHP + SQLite** (ingen build, inga npm-paket, inga Python-servrar krävs).
Du laddar bara upp filerna till webbhotellet och öppnar sidan.

## Krav på hosting

- PHP 8+
- PDO SQLite aktiverat
- Skrivrättigheter i mappen `data/`

## Snabbstart lokalt

```bash
php -S 0.0.0.0:8000
```

Öppna: `http://localhost:8000` (första sidan är inloggning med personnummer + lösenord)

## Deploy (uppladdning till hosting)

1. Ladda upp exakt dessa två webb-filer till din webbroot (`public_html`, `www`, etc):
   - `index.php`
   - `index2.html`
2. Ladda även upp mappen `data/` (behövs för SQLite-databasen).
3. Säkerställ att `data/` är skrivbar av webbservern.
4. Öppna din domän.
5. Klart — databasen skapas automatiskt första gången sidan öppnas.

## Demo-inloggningar

- `19900101-1234 / motor123`
- `19920202-5678 / garage123`
- `19950505-9012 / bennys123`

## Funktioner

- Inloggning/session
- Skapa kvitto med validering:
  - Mekaniker = inloggad användare
  - Typ av arbete: Reperation / Styling / Prestanda
  - Antal delar krävs för Styling/Prestanda
  - Kund krävs
  - Regplåt måste vara `XXX-000`
- Kvitton sparas i SQLite
- Arbetsorder som kan kopieras i format:
  - `Benny's Arbetsorder - 00000`
