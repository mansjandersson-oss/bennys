# Benny's Motorworks Dashboard

En GTA RP-inspirerad dashboard där inloggade mekaniker kan skapa kvitton, och admins kan se statistik + hantera användare.

## InfinityFree-anpassad version

Den här versionen är byggd för att fungera som en vanlig **PHP-sida på InfinityFree**:
- `index.php` = all backendlogik
- `index2.html` = layout/template

## Krav

- PHP 8+
- PDO SQLite aktiverat
- Skrivrättigheter i mappen `data/`

## Upload till InfinityFree (steg för steg)

1. Logga in i InfinityFree Control Panel.
2. Öppna **File Manager**.
3. Gå till mappen `htdocs/` för din domän.
4. Ladda upp exakt dessa filer:
   - `index.php`
   - `index2.html`
5. Ladda upp mappen `data/` (tom mapp går bra).
6. Sätt skrivrättigheter så `data/` är skrivbar.
7. Öppna din domän – sidan startar på inloggning direkt.

> Databasen (`bennys.sqlite`) skapas automatiskt i `data/` första gången sidan används.

## Demo-konton

- **Admin**: `19900101-1234 / motor123`
- **Mekaniker**: `19920202-5678 / garage123`
- **Mekaniker**: `19950505-9012 / bennys123`

## Funktioner

- Inloggning med personnummer + lösenord
- Kvittoflöde med validering
- Arbetsorder med dynamiskt löpnummer
- **Adminpanel**
  - Statistik med filter (datum, arbetstyp, mekaniker)
  - Skapa ny användare
  - Redigera användare (lösenord + roll)
