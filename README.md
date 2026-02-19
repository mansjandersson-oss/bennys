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


## Kör som lokal app (enkel start)

Du kan köra sidan som en "app" lokalt med ett klick:

- **Windows:** dubbelklicka `start_app.bat` (kollar Python/PHP och forsoker installera/ladda ner vid behov)
- **Mac/Linux:** kör `./start_app.sh`

Detta använder `local_app.py` som:
1. startar PHP-servern lokalt
2. öppnar sidan i din webbläsare automatiskt
3. kör tills du stänger med `Ctrl+C`

> Windows-startscriptet prioriterar XAMPP PHP (`C:\xampp\php\php.exe`) om den finns, annars `winget` och till sist portable PHP-fallback.
> Om `winget` saknas behover du installera Python/PHP manuellt.
> Vid start skapas `bennys_php.ini` i projektmappen med aktiverade `pdo_sqlite` och `sqlite3`.
> Startscriptet nollstaller ocksa `PHP_INI_SCAN_DIR` for att undvika att andra ini-filer skriver over extension-inställningar.
> Scriptet verifierar dessutom att båda extensions verkligen laddas innan appen startas.

### VS Code (PHP Server)
Om du kör via VS Code och får att `index.php` inte hittas, starta servern från projektmappen med router:

```bash
php -S 127.0.0.1:8000 -t . router.php
```

Detta tvingar alla app-routes till `index.php` och löser vanliga problem med fel dokumentrot i VS Code.

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
  - Kvittoinställningar: skapa/redigera arbeten + standardpriser
  - Skapa/redigera användare
  - Fordonregister: nummerplåt + fordonstyp
  - Kundregister: namn + telefonnummer
