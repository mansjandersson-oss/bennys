# Patch notes — senaste månaden (25 feb 2026 → 25 mar 2026)

## 🎯 Höjdpunkter
- **Migrering till JSON-lagring**: Backend har flyttats från SQLite till JSON-baserade tabeller under `data/json/*`.
- **Discord-integration**: Stöd för webhook/bot och Discord-baserat kvittoflöde har lagts till.
- **Nytt kontoflöde**: Självregistrering + admin-godkännande för nya användare.
- **UI/UX-förbättringar**: Flera kompakteringar och läsbarhetsförbättringar i adminpanelen.

## ✅ Vad som ändrats

### Backend / data
- Migrering och drift med JSON-tabeller istället för SQLite.
- Tog bort hårdkodad auto-seeding av standard-ranks, användare, rabattpresets och tjänster.
- Förbättrad kundnamnshantering: gemena namn normaliseras till titel-format (t.ex. `anna andersson` → `Anna Andersson`).

### Inloggning & användare
- Nytt flöde i login-vyn: **“Skapa inlogg”**.
- Registrerade användare blir väntande tills admin godkänner kontot.
- Admin kan godkänna användare direkt i adminpanelen.
- Admin kan se och snabbredigera användar-lösenord i användarlistan.

### Adminpanel / visualisering
- **Ekonomi-sektionen** är mer kompakt och stilren.
- **Bäst presterande mekaniker** visar nu en mer kompakt **Top 5**-vy i kolumn/bar-format.
- Summor i adminkort formateras nu med svensk valutaformattering för bättre läsbarhet (t.ex. miljonbelopp).

### Mobil / responsivitet
- Förbättrad mobil visning för flera sektioner (bl.a. servicekort och tabeller), med bättre stackning och mindre överlapp.

## 🧪 Notering
- Ändringar har verifierats med grundläggande syntax- och API-smokechecks under arbetets gång.
