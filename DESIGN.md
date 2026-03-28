# Jallous Webdesign — Design System & Brand Guidelines

## Brand

- **Name:** Jallous Webdesign
- **Inhaber:** Hassan Jallous
- **Domain:** jallous-webdesign.de
- **Firma:** Cortex Made LLC (US LLC)
- **Sprache:** Deutsch
- **Zielgruppe:** Deutsche Unternehmer die premium, individuelle Websites wollen die konvertieren
- **Preise:** Websites 2-5k, Shops 4-10k
- **USP:** Keine Templates. Jede Website ist custom designed, conversion-optimiert und sieht aus wie nichts was die Konkurrenz hat.

---

## Vibe & Aesthetic Direction

### Kernidentitaet: LIQUID CHROME ON OBSIDIAN

Die Website soll aussehen wie ein Apple iPad Pro Wallpaper trifft Luxus-Automotive-Design trifft fluessiges Metall. Denke an poliertes Chrome in einem dunklen Raum — Licht gleitet ueber die Oberflaeche, bricht sich prismatisch an den Kanten, erzeugt subtile Regenbogenreflexe.

### Stimmung
- Premium, nicht billig
- Technisch, nicht verspielt
- Dunkel und edel, nicht bunt
- Selbstbewusst, nicht schreierisch
- Modern, nicht trendy (zeitlos > trendig)

### Referenzen
- Apple iPad Pro Marketing-Aesthetic (Chrome/Glas UI-Elemente)
- linear.app (Dark Glassmorphism, Typography-first)
- vercel.com (Geist Design System, Monochrome)
- stripe.com (Mesh Gradients, Premium Feel)
- Jasmin Huber (jasminhuber.de) — Dark, Premium Agentur
- Maurer Marketing (maurer-marketing.ch) — Clean, Professional

### Was die Seite NICHT sein soll
- Kein generisches Agentur-Template
- Kein AI-Slop (standard purple gradients, Inter font, langweilige 3-column grids)
- Kein WordPress-Look
- Keine Stock-Photos
- Kein Overdesign — Reduktion auf das Wesentliche

---

## Farben

### Dark Mode (Primaer)

| Rolle | Wert | Beschreibung |
|---|---|---|
| Background Primary | `#050505` | Fast schwarz, nicht grau |
| Background Secondary | `#0a0a0a` | Leicht heller fuer Sections |
| Card Background | `rgba(255,255,255, 0.03)` | Kaum sichtbar, nur Hauch von Glas |
| Card Background Hover | `rgba(255,255,255, 0.06)` | Leicht heller bei Hover |
| Border Subtle | `rgba(255,255,255, 0.06)` | Kaum sichtbare Trennlinien |
| Border Chrome | Metallic Gradient (siehe unten) | Der Haupt-Effekt |
| Text Primary | `#e8e8e8` | Nicht reines Weiss — leicht warm |
| Text Secondary | `rgba(255,255,255, 0.4)` | Gedaempft fuer Subtexte |
| Text Muted | `rgba(255,255,255, 0.25)` | Sehr dezent fuer Labels |
| Accent | Chrome/Silber Gradient | KEIN Lila, KEIN Blau als Akzent |
| Ambient Glow Cyan | `rgba(0,212,255, 0.08)` | Subtiler Hintergrund-Glow |
| Ambient Glow Orange | `rgba(255,140,0, 0.05)` | Subtiler Hintergrund-Glow |

### Light Mode

| Rolle | Wert | Beschreibung |
|---|---|---|
| Background Primary | `#f8f7f4` | Warmes Off-White, nicht kalt |
| Background Secondary | `#ffffff` | Reines Weiss fuer Karten |
| Card Background | `rgba(0,0,0, 0.02)` | Hauch von Grau |
| Border Subtle | `rgba(0,0,0, 0.06)` | Leichte Trennlinien |
| Text Primary | `#1a1a1a` | Fast Schwarz |
| Text Secondary | `rgba(0,0,0, 0.5)` | Gedaempft |
| Chrome-Effekte bleiben gleich aber mit mehr Kontrast zum hellen Hintergrund |

### Automatischer Dark/Light Mode
- `prefers-color-scheme` Media Query fuer automatische Erkennung
- Manueller Toggle (Sonne/Mond Icon) mit `localStorage` Speicherung
- CSS Custom Properties fuer alle Farben

---

## Typografie

### KEINE generischen Fonts
Inter, Roboto, Arial, System-Fonts sind VERBOTEN. Die Font muss Charakter haben.

### Optionen (in Reihenfolge der Praeferenz)

**Headlines:**
- **Festgelegt:** `Clash Display` (Fontshare) — Geometrisch, bold, sharp angles. Premium-Feel, perfekt fuer Chrome-Gradienten. 6 Weights verfuegbar.

**Body Text:**
- **Option 1:** `DM Sans` — Clean, professionell, gut lesbar.
- **Option 2:** `Outfit` — Modern, geometrisch.
- **Option 3:** `Manrope` — Geometrisch, modern, gut fuer Tech/Premium.

### Typografie-Regeln
- Headlines: Bold (700), gross, dominant
- Hero Headline: `clamp(2.5rem, 6vw, 5.5rem)` — muss den Viewport dominieren
- Section Headlines: `clamp(1.8rem, 4vw, 3rem)`
- Body: 400-500 weight, `1rem` bis `1.1rem`
- Letter-spacing fuer Labels: `0.15em` bis `0.3em`
- Logo "JALLOUS": `letter-spacing: 0.3em`, Chrome-Text-Gradient

---

## Der Chrome/Shine Effekt — DAS Kernelement

### Was es sein soll
Ein Lichtreflex der ueber eine polierte Chrome/Metall-Oberflaeche gleitet. Wie Sonnenlicht auf einem polierten Auto. An den Kanten bricht sich das Licht prismatisch (subtile Regenbogenfarben).

### Technische Umsetzung

#### 1. Chrome Border (Metallischer Rahmen)
```css
/* Basis: Silber/Chrome Gradient als Border */
background: linear-gradient(
  135deg,
  #2a2a2a 0%, #555 15%, #888 25%, #ccc 35%,
  #fff 50%,
  #ccc 65%, #888 75%, #555 85%, #2a2a2a 100%
);
padding: 3-4px; /* Das IST die Border-Breite */
```
Der Trick: Element hat einen metallic gradient als Background + Padding. Das innere Element (`.chrome-inner`) ueberdeckt den Content-Bereich. Der Gradient scheint als "Border" durch.

#### 2. Shine Sweep (Lichtreflex der gleitet)
```css
/* Pseudo-Element das ueber die Chrome-Border gleitet */
.chrome-border::before {
  content: '';
  position: absolute;
  top: -50%; left: -100%;
  width: 60%; height: 200%;
  background: linear-gradient(
    90deg,
    transparent 0%,
    rgba(255,255,255,0.05) 30%,
    rgba(255,255,255,0.4) 45%,
    rgba(255,255,255,0.8) 50%,  /* Hellster Punkt */
    rgba(255,255,255,0.4) 55%,
    rgba(255,255,255,0.05) 70%,
    transparent 100%
  );
  animation: shine-sweep 3s ease-in-out infinite;
}

@keyframes shine-sweep {
  0% { transform: translateX(0); }
  100% { transform: translateX(500%); }
}
```

#### 3. Prismatischer Nachschein (Regenbogen der dem Licht folgt)
```css
/* Zweites Pseudo-Element — subtile Regenbogenfarben */
.chrome-border::after {
  background: linear-gradient(
    90deg,
    transparent 0%,
    rgba(0,212,255,0.15) 30%,    /* Cyan */
    rgba(255,140,0,0.2) 45%,     /* Orange */
    rgba(255,215,0,0.15) 55%,    /* Gold */
    rgba(65,105,225,0.15) 70%,   /* Blau */
    transparent 100%
  );
  animation: shine-sweep 3s ease-in-out infinite;
  animation-delay: 0.15s; /* Leicht versetzt zum Hauptlicht */
}
```

#### 4. Chrome Text Gradient (Metallischer Text)
```css
.chrome-text {
  background: linear-gradient(
    135deg,
    #666 0%, #ccc 20%, #fff 40%, #999 50%,
    #fff 60%, #ccc 80%, #666 100%
  );
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}
```

#### 5. Glass Card Effect
```css
.glass-card {
  background: rgba(255,255,255, 0.03);
  backdrop-filter: blur(16px);
  -webkit-backdrop-filter: blur(16px);
  border: 1px solid rgba(255,255,255, 0.06);
  border-radius: 20px;
  box-shadow: 0 8px 32px rgba(0,0,0,0.2);
}
```

#### 6. Apple Liquid Glass (Advanced — fuer spaeter)
```css
.liquid-glass {
  background: rgba(255,255,255, 0.15);
  backdrop-filter: blur(2px) saturate(180%);
  border: 1px solid rgba(255,255,255, 0.8);
  border-radius: 2rem;
  box-shadow:
    0 8px 32px rgba(31,38,135,0.2),
    inset 0 4px 20px rgba(255,255,255,0.3);
}
```

### Wo Chrome-Effekte eingesetzt werden
- **Immer sichtbar:** CTA-Buttons, "Projekt starten" Nav-Button, "BELIEBTESTE WAHL" Badge
- **Auf Hover:** Service-Cards, Portfolio-Cards, Pricing-Cards
- **Chrome Text:** "verkaufen" im Hero, Preise, Schrittnummern im Prozess
- **NICHT ueberall:** Weniger ist mehr. Chrome ist der Akzent, nicht die Basis.

---

## Hintergrund & Atmosphaere

### Dark Mode Background
- Basis: `#050505` (fast schwarz)
- Subtiles Dot-Grid Pattern (opacity 0.03-0.05)
- 2-3 Ambient Glow Orbs (grosse, verschwommene farbige Kreise)
  - Oben links: Cyan-ish `rgba(0,212,255, 0.08)`, 400px, blur 150px
  - Unten rechts: Orange-ish `rgba(255,140,0, 0.05)`, 300px, blur 120px
  - Mitte: Gruenlich `rgba(0,255,170, 0.03)`, 350px, blur 130px
- Film Grain Overlay (sehr subtil, opacity 0.02-0.04)

### Light Mode Background
- Basis: `#f8f7f4` (warmes Off-White)
- Keine Glow-Orbs noetig
- Subtile Textur optional

---

## Layout & Spacing

### Grundregeln
- Max Container Width: `1200px`, mit `90vw` Safety
- Section Padding: `clamp(80px, 12vw, 140px)` vertikal
- Card Padding: `32px` bis `48px` intern
- Kein langweiliges 3-Column Grid — asymmetrisch, interessant
- Elemente sollen "schweben" — grosszuegiger Negativraum

### Responsive Breakpoints
- Desktop: > 1024px
- Tablet: 768px - 1024px
- Mobile: < 768px
- Small Mobile: < 480px

---

## Animationen

### Scroll Reveal
- Intersection Observer mit threshold 0.05
- Hero-Elemente sofort sichtbar (kein Delay)
- Andere Elemente: Fade in + translateY(30px)
- Staggered Delays: 100ms zwischen Elementen
- Transition: `0.7s cubic-bezier(0.16, 1, 0.3, 1)`

### Chrome Shine
- Continuous sweep auf permanent-chrome Elementen
- Duration: 3s ease-in-out infinite

### Hover Effects
- Cards: Chrome-Border erscheint + leichter Lift
- Buttons: Shine-Sweep beschleunigt
- Links: Color transition 0.3s

### Accessibility
- `prefers-reduced-motion: reduce` → ALLE Animationen aus
- Smooth scroll deaktiviert

---

## Website-Struktur & Copy (Deutsch)

### Navigation (fixed, glass)
- Links: Logo "JALLOUS" (Chrome-Text, letter-spacing 0.3em)
- Mitte/Rechts: Leistungen | Portfolio | Ueber mich | Kontakt
- Rechts: Theme Toggle + "Projekt starten" (Chrome-Border Button)

### Hero Section (100vh)
- Label: "PREMIUM WEBDESIGN" (letter-spaced, subtle)
- Headline Z1: "Websites die"
- Headline Z2: "verkaufen." (Chrome-Text-Gradient)
- Headline Z3: "Nicht nur existieren." (gedaempfte Opacity)
- Subheadline: "Individuelles Webdesign das deine Marke unverwechselbar macht — und Besucher in Kunden verwandelt."
- CTA Primary: "Kostenloses Erstgespraech buchen →" (Chrome-Border)
- CTA Secondary: "Portfolio ansehen" (Ghost Button)
- Trust Bar: "Individuelle Designs · Conversion-optimiert · Made in Germany"

### Leistungen Section
- Label: "LEISTUNGEN"
- Headline: "Was ich fuer dich baue"
- 4 Cards (2x2 Grid):
  1. Custom Webdesign — "Keine Templates. Jede Website wird von Grund auf fuer deine Marke designed."
  2. Online Shops — "E-Commerce Loesungen die verkaufen — nicht nur gut aussehen."
  3. Conversion-Optimierung — "Jede Seite wird auf maximale Conversion optimiert. Psychologie trifft Design."
  4. Wartung & Support — "Deine Website laeuft. Immer. Updates, Hosting, Support inklusive."

### Portfolio Section
- Headline: "Projekte die fuer sich sprechen"
- 3 Projekt-Cards (asymmetrisches Layout)
- Placeholder-Projekte mit Gradient-Bildern
- Stats-Badges mit Chrome-Text ("+240% Leads", "+340% Umsatz")

### Ueber mich Section
- Headline: "Hi, ich bin Hassan."
- Zwei-Spalten: Foto-Placeholder (Kreis) + Text
- Text: "Ich baue Websites die anders aussehen als alles was du bisher gesehen hast..."
- Versprechen-Box (Glass Card mit Chrome-Border): "Wenn deine neue Website nicht besser aussieht und performt als alles was deine Konkurrenz hat — zahlst du nichts."

### Pricing Section
- Headline: "Investment in dein Business"
- 3 Tier-Cards:
  1. Starter — ab 2.000 EUR — One-Pager/Landing Page
  2. Professional — ab 3.500 EUR — Multi-Page Website (BELIEBTESTE WAHL Badge, Chrome-Border)
  3. E-Commerce — ab 5.000 EUR — Online Shop
- Darunter: "Jedes Projekt ist individuell. Lass uns sprechen."

### Prozess Section
- Headline: "So arbeiten wir zusammen"
- 4 Steps (Horizontal Timeline):
  1. Erstgespraech — Kostenlos, unverbindlich
  2. Konzept & Design — Individuelles Design-Konzept
  3. Entwicklung — Pixel-perfekte Umsetzung
  4. Launch & Support — Go-Live + laufender Support

### FAQ Section (Accordion)
1. Was kostet eine Website?
2. Wie lange dauert es?
3. Warum bist du teurer als andere?
4. Was passiert nach dem Launch?
5. Arbeitest du mit WordPress?

### Final CTA Section
- Headline: "Bereit fuer eine Website die wirklich verkauft?"
- Subline: "Kostenloses Erstgespraech. Unverbindlich."
- Grosser CTA mit Chrome-Border

### Footer
- "2026 Jallous Webdesign · Ein Service der Cortex Made LLC"
- Impressum | Datenschutz

---

## Technische Anforderungen

- Single `index.html` (alles inline — CSS + JS)
- Keine Frameworks — pure HTML, CSS, JavaScript
- Google Fonts (distinctive, keine Standard-Fonts)
- Mobile-first Responsive Design
- `prefers-color-scheme` + manueller Toggle
- `prefers-reduced-motion` Support
- Intersection Observer fuer Scroll-Animationen
- SEO: Meta Tags, Open Graph, Heading-Hierarchie
- Performance: Effiziente CSS-Animationen, keine JS-Libraries
- Smooth Scroll fuer Nav-Links

---

## Ressourcen & Inspiration

### CSS Chrome-Effekte
- ibelick.com/blog/creating-metallic-effect-with-css
- codepen.io/bramus/pen/rNWByYz (Animated Rainbow Gradient Border)
- metallicss.com (MetalliCSS Library)

### Apple Liquid Glass
- dev.to/kevinbism (Liquid Glass mit Pure CSS)
- github.com/nikdelvin/liquid-glass (Pixel-perfect Recreation)
- css-tricks.com (Getting Clarity on Liquid Glass)
- designfast.io/liquid-glass

### Premium Dark Websites
- linear.app
- vercel.com
- stripe.com

### Konkurrenz (Webdesign Agenturen)
- jasminhuber.de (Top-Player, sehr erfolgreich)
- maurer-marketing.ch (Top-Player)
- am-beratung.de
- atellinghusen-marketing.de
- lau.do/webdesign-berlin/ (gutes SEO)
