# Jallous Webdesign — Projektregeln

## Stack
- Statisches HTML/CSS/JS Portfolio
- Hosting: Hostinger (LiteSpeed Server)
- Domain: jallous-webdesign.de

## Deploy (WICHTIG)

Bei JEDEM Live-Push MÜSSEN beide parallel passieren:
1. **SSH Deploy** → Hostinger Server (rsync via SSH)
2. **Git Push** → GitHub Repository

### SSH Deploy Befehl
```bash
# .env laden und per rsync deployen
source .env
sshpass -p "$SSH_PASS" rsync -avz --delete \
  -e "ssh -p $SSH_PORT -o StrictHostKeyChecking=no" \
  --exclude '.git' --exclude '.env' --exclude '.DS_Store' --exclude '__pycache__' --exclude 'generated-images' --exclude 'tools/.tmp' --exclude '.playwright-mcp' \
  ./ ${SSH_USER}@${SSH_HOST}:${SSH_REMOTE_PATH}/
```

### Credentials
SSH-Zugangsdaten liegen in `.env` (NICHT in Git — steht in .gitignore)

## Projekt-Struktur
- `/css/` — Stylesheets (shared.css, home.css, projekt-detail.css, cc.css)
- `/js/` — Scripts (shared.js, tracking.js, cc.js)
- `/api/` — PHP Backend (track.php = CAPI, submit-form.php = Formular-E-Mail, config.php = Credentials)
- `/projekte/` — Projekt-Detailseiten (8 Kunden)
- `/projects/` — Screenshots der Kundenprojekte
- `/logos/` — Favicons und Logos
- `index.html` — Homepage

## TODO-Liste (WICHTIG)
- **Immer `TODO.md` lesen** am Anfang jeder Session
- **Proaktiv neue Tasks hinzufügen** wenn dir Probleme, fehlende Features oder Verbesserungen auffallen
- **Tasks abhaken** sobald erledigt
- Die TODO.md ist die zentrale Aufgabenliste für dieses Projekt

## Meta Tracking
- **Pixel ID:** 2690088668021853
- **Ad Account:** act_2135868770663628
- **Tracking Script:** js/tracking.js (Pixel + CAPI, consent-gated)
- **Cookie Consent:** js/cc.js + css/cc.css (Fullscreen Overlay)
- **CAPI Endpoint:** api/track.php
- **Credentials:** api/config.php (gitignored)
- Cookie Consent wird NICHT auf Datenschutz/Impressum/AGB angezeigt

## LiteSpeed Cache (WICHTIG)
Hostinger LiteSpeed cached JS/CSS Dateien aggressiv. Bei Änderungen an JS/CSS Dateien:
- **Dateinamen ändern** (z.B. tracking.js → tracking-v2.js) und HTML-Referenzen updaten
- Oder Query-String anhängen (?v=2)
- Einfaches Überschreiben reicht NICHT — der Cache liefert die alte Version
