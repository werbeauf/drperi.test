# Dr. Peri — Legal-Bundle (Österreich)

Sammlung von Rechtstext-Entwürfen für den Online-Shop **drperi.at** (B2C, ausschließlich kosmetische Produkte). Alle Dokumente in deutscher Sprache, formelle **Sie**-Anrede, ausgerichtet auf österreichisches Recht (FAGG, VGG, KSchG, ECG, UGB, MedienG, AStG, Kosmetik-VO).

> ⚠️ **Diese Dateien sind ENTWÜRFE.** Vor Veröffentlichung auf der Website **müssen** sie (a) mit den tatsächlichen Stammdaten von Dr. Peri befüllt und (b) von einer auf österreichisches Konsumentenschutz- und E-Commerce-Recht spezialisierten Rechtsanwältin oder einem Rechtsanwalt geprüft werden.

---

## Übersicht der Dateien

| Datei | Inhalt |
|---|---|
| [`IMPRESSUM-DRAFT.md`](./IMPRESSUM-DRAFT.md) | Anbieterkennzeichnung (§ 5 ECG) + § 14 UGB + § 25 Abs 5 MedienG + AStG-Hinweis + Urheberrechts-/Linkhaftungs-Disclaimer |
| [`AGB-DRAFT.md`](./AGB-DRAFT.md) | Allgemeine Geschäftsbedingungen, 15 Paragraphen |
| [`WIDERRUFSBELEHRUNG-DRAFT.md`](./WIDERRUFSBELEHRUNG-DRAFT.md) | Belehrung nach FAGG Anlage I/A + Hygiene-Ausnahme § 18 Abs 1 Z 5 FAGG + Hinweis Widerrufsbutton ab 19.06.2026 + Muster-Widerrufsformular FAGG Anlage I/B |
| [`VERSAND-DRAFT.md`](./VERSAND-DRAFT.md) | Versandbedingungen, 9 Paragraphen, inkl. Gefahrenübergang § 7b KSchG |
| [`ZAHLUNG-DRAFT.md`](./ZAHLUNG-DRAFT.md) | Zahlungsmethoden, 7 Paragraphen, inkl. WooPayments/Stripe-Klausel und PSD2-SCA |

**Nicht** in diesem Bundle enthalten:

- **Datenschutzerklärung (`/datenschutz/`)** — erfordert ein eigenes Audit (Cookie-Inventar, Tracking-Tools, Rechtsgrundlagen pro Verarbeitung, Drittlandtransfers, Borlabs-Cookie-Konfiguration, Auftragsverarbeitungsverträge mit Phorest, Stripe, ggf. SeoByRankMath, etc.). Die AGB verweist lediglich auf die Datenschutzerklärung — sie wird nicht dupliziert.

---

## Stand-Datum

Aktuelles Stand-Datum für alle Dokumente: **[PLATZHALTER: stand_datum]**

Empfohlen: einmal pro Bundle setzen, identisch in allen 5 Drafts (z.B. "Mai 2026").

---

## Konsolidierte Platzhalter-Liste

Alle Tokens haben das Format `[PLATZHALTER: <feldname>]` und sind case-sensitive. Identische Tokens in verschiedenen Dokumenten werden mit demselben Wert belegt — empfohlener Workflow: einmal pro Token via Suchen-und-Ersetzen über alle Dateien des `legal/`-Ordners.

### Stammdaten (in mehreren Dokumenten verwendet)

| Token | Beschreibung | Beispielwert |
|---|---|---|
| `[PLATZHALTER: firma]` | Vollständige Firma inkl. Rechtsform | "Dr. Peri Skincare GmbH" |
| `[PLATZHALTER: rechtsform]` | Rechtsform | "Gesellschaft mit beschränkter Haftung (GmbH)" |
| `[PLATZHALTER: anschrift_strasse]` | Straße + Hausnummer | "Beispielgasse 1/2" |
| `[PLATZHALTER: anschrift_plz_ort]` | PLZ + Ort | "1010 Wien" |
| `[PLATZHALTER: anschrift_land]` | Land | "Österreich" |
| `[PLATZHALTER: telefon]` | Telefonnummer (international) | "+43 1 234 56 78" |
| `[PLATZHALTER: email_kontakt]` | Allgemeine Kontakt-E-Mail | "info@drperi.at" |
| `[PLATZHALTER: email_widerruf]` | Optional separate E-Mail für Widerrufe (sonst = email_kontakt) | "widerruf@drperi.at" |
| `[PLATZHALTER: uid_nummer]` | UID-Nummer | "ATU XXXXXXXX" |
| `[PLATZHALTER: firmenbuch_nummer]` | Firmenbuchnummer (FN) | "FN XXXXXXa" |
| `[PLATZHALTER: firmenbuch_gericht]` | Firmenbuchgericht | "Handelsgericht Wien" |
| `[PLATZHALTER: geschaeftsfuehrer]` | Geschäftsführer:innen (bei juristischer Person) | "Vorname Nachname" |
| `[PLATZHALTER: gewerbewortlaut]` | Gewerbewortlaut laut Gewerberegister | "Handelsgewerbe gemäß § 124 Z 11 GewO" |
| `[PLATZHALTER: aufsichtsbehoerde]` | Zuständige Bezirksverwaltungsbehörde | "Magistratisches Bezirksamt für den 1. Bezirk, Wien" |
| `[PLATZHALTER: kammer]` | WKO-Kammer + Sparte | "Wirtschaftskammer Wien, Sparte Handel" |
| `[PLATZHALTER: unternehmensgegenstand]` | Kurzbeschreibung des Unternehmensgegenstands (MedienG § 25 Abs 5) | "Online-Handel mit Kosmetikprodukten" |
| `[PLATZHALTER: stand_datum]` | Stand der letzten Aktualisierung | "Mai 2026" |

### Versand-spezifisch

| Token | Beschreibung | Beispielwert |
|---|---|---|
| `[PLATZHALTER: versanddienstleister]` | Versanddienstleister | "Österreichische Post AG" |
| `[PLATZHALTER: versandkosten_at]` | Versandkosten Österreich | "€ 4,90" |
| `[PLATZHALTER: versandkosten_eu]` | Versandkosten übrige EU | "€ 9,90" |
| `[PLATZHALTER: versandfreigrenze]` | Versandkostenfreigrenze | "€ 50,—" oder "kein Mindestbestellwert" |
| `[PLATZHALTER: lieferzeit_at]` | Lieferzeit Österreich | "2–4 Werktage" |
| `[PLATZHALTER: lieferzeit_eu]` | Lieferzeit übrige EU | "4–7 Werktage" |
| `[PLATZHALTER: lieferlaender]` | Liste der Liefergebiete | "Österreich, Deutschland, …" |
| `[PLATZHALTER: versandlager_ort]` | Standort des Versandlagers | "Wien, Österreich" |

### Zahlung-spezifisch

| Token | Beschreibung | Beispielwert |
|---|---|---|
| `[PLATZHALTER: aktive_zahlungsarten]` | Aufzählung der tatsächlich aktivierten Zahlungsmittel | "Kredit- und Debitkarte (Visa, Mastercard, American Express) via WooPayments" |
| `[PLATZHALTER: woopayments_anbieter]` | Aktueller WooPayments-Vertragspartner | "WooCommerce Ireland Ltd." |

### Newsletter

| Token | Beschreibung | Beispielwert |
|---|---|---|
| `[PLATZHALTER: newsletter_anbieter]` | Verarbeiter / Anmeldedienstleister | "Phorest Salon Software Ltd., Dublin, Irland" |

### Optional

| Token | Beschreibung | Verwendet in |
|---|---|---|
| `[PLATZHALTER: bildnachweise]` | Liste der Stock-/Drittfoto-Quellen, falls vorhanden | IMPRESSUM-DRAFT.md |

---

## Risk-Flags

Diese Punkte sind in den Drafts als `> **Hinweis Anwalt (R…):** …` markiert. Vor Veröffentlichung mit der Anwältin / dem Anwalt durchgehen.

| ID | Punkt | Wo |
|---|---|---|
| **R1** | Eigentumsvorbehalt B2C (nur einfacher EV nach § 6 KSchG) | AGB § 6 |
| **R2** | Salvatorische Klausel B2C (geltungserhaltende Reduktion umstritten, vgl. OGH 7 Ob 173/10g) | AGB § 15 |
| **R3** | Haftungsbegrenzung in B2C-AGB (KSchG § 6) | AGB § 9 |
| **R4** | Kosmetik-VO 1223/2009: Eigenmarke vs. Resale — Verantwortliche-Person-Pflichten (Art. 5, 10, 19) | AGB § 10 |
| **R5** | Salon ↔ Online-Shop Entitäts-Verhältnis (selbe Rechtsperson?) | AGB § 1 (5), VERSAND § 5 |
| **R6** | B2B-Käufe — derzeit explizit ausgeschlossen | AGB § 1 (4) |
| **R7** | Frontend-Implementierung des FAGG-Widerrufsbuttons bis 19.06.2026 (separater Dev-Task) | WIDERRUFSBELEHRUNG Teil 4 |
| **R8** | WooPayments-Methoden-Inventur — tatsächlich aktive Zahlungsmethoden in WP-Admin verifizieren | ZAHLUNG § 1 |
| **R9** | Aufsichtsbehörde — abhängig vom Sitz | IMPRESSUM Block 1 |
| **R10** | WPML-Konfiguration — Legal-Pages dürfen NICHT automatisch ins EN übersetzt werden | (siehe unten) |
| **R-OS** | EU-OS-Plattform aufgehoben per 20.07.2025 — kein Link auf ec.europa.eu/odr | IMPRESSUM, AGB § 13 |
| **R-FAGG** | Verbatim-Wortlaut von FAGG Anlage I/A und I/B — Anwalt prüft gegen aktuelle RIS-Fassung | WIDERRUFSBELEHRUNG Teil 1, 2, 5 |

---

## Anwalts-Checkliste

Empfohlene Punkte zur Vorlage bei der Anwältin / beim Anwalt:

1. **Verbatim-Abgleich Widerrufsbelehrung** gegen aktuelle Fassung von FAGG Anlage I Teil A und Teil B im Rechtsinformationssystem des Bundes (https://www.ris.bka.gv.at). Die in `WIDERRUFSBELEHRUNG-DRAFT.md` reproduzierte Fassung ist nach bestem Wissen korrekt, der formale Verbatim-Abgleich obliegt jedoch der Anwältin / dem Anwalt.
2. **R1 Eigentumsvorbehalt** — Formulierung absegnen.
3. **R2 Salvatorische Klausel** — Formulierung B2C-konform?
4. **R3 Haftungsbegrenzung** — Formulierung B2C-konform?
5. **R4 Kosmetik-VO** — Klären, ob Dr. Peri Eigenmarke (verantwortliche Person nach Art. 5) oder Resale.
6. **R5 Entitäts-Verhältnis** Salon ↔ Online-Shop.
7. **R6 B2B-Ausschluss** — bestätigen oder eigenes B2B-AGB-Set ergänzen.
8. **R7 Widerrufsbutton** — technische Umsetzung bis 19.06.2026 sicherstellen oder Belehrung Teil 4 anpassen.
9. **R8 Zahlungsarten-Inventur** — `[PLATZHALTER: aktive_zahlungsarten]` in `ZAHLUNG-DRAFT.md` mit tatsächlich aktivierten Methoden befüllen.
10. **R9 Aufsichtsbehörde** — abhängig vom Sitz (Wien: zuständiges Magistratisches Bezirksamt; Bundesländer: BH).
11. **Datenschutzerklärung** ist nicht Teil dieses Bundles — separates Audit beauftragen.

---

## WPML-Konfiguration (nach Veröffentlichung in WP)

Die Pages `/agb/`, `/widerruf/`, `/versand/`, `/zahlung/` und `/impressum/` sollen ausschließlich in **Deutsch** existieren. Nach Erstellung der WP-Pages in WP-Admin:

1. WPML → **Translation Management** → Pages: für die genannten 5 Pages den Status auf **"Don't translate"** oder **"Duplicate"** setzen.
2. Footer-Menü `footer-menu` (siehe `werbeauf-customs/templates/footer.php`) prüfen: in der EN-Variante des Footers sollten die Legal-Links entweder ausgeblendet oder auf die DE-Versionen verlinkt werden — keine englischen Übersetzungs-Stubs zulassen.
3. Verifikation: `https://www.drperi.at/en/agb/` (oder `/en/terms/`) darf **nicht** existieren oder muss auf die DE-Version weiterleiten.

Hintergrund / Helpers: siehe `wp-content/plugins/werbeauf-customs/docs/WPML.md` (insbesondere `wa_wpml_is_default_lang_post()`).

---

## Empfohlener Workflow zur Veröffentlichung

1. **Stammdaten erheben:** UID-Nr., FN, FB-Gericht, Anschrift, GF, Aufsichtsbehörde, Kammerzugehörigkeit, Gewerbewortlaut von der Buchhaltung / WKO holen.
2. **WP-Admin → WooPayments** auf aktive Zahlungsmethoden prüfen → R8 auflösen.
3. **Versanddaten konkretisieren:** Versanddienstleister, Versandkosten, Lieferzeiten, Liefergebiete, Versandfreigrenze.
4. **Platzhalter ersetzen** in allen 5 Drafts (Suche und Ersetze pro Token).
5. **Drafts an Anwalt** zur Prüfung schicken (Anwalts-Checkliste oben). Insbesondere R1, R2, R3, R4, R-FAGG.
6. **Korrekturen einarbeiten.**
7. **WP-Pages anlegen / befüllen** unter `/agb/`, `/widerruf/`, `/versand/`, `/zahlung/`, `/impressum/`. Body-Class `wa-content-shell` ist bereits via `werbeauf-customs/includes/layout/wc-shell.php` aktiv.
8. **WPML-Konfiguration** wie oben (Don't translate für die 5 Pages).
9. **Footer-Menü** `footer-menu` prüfen: alle 5 Slugs verlinkt + Datenschutz-Link.
10. **`dev-flush`** auf der lokalen Umgebung; auf Live nach Deploy ebenfalls `wp super-cache flush` o.ä.
11. **Live-Verifikation:** alle 5 Pages erreichbar, AGB-Checkbox im Checkout funktioniert, Bestellbestätigung verlinkt korrekt.
12. **Versionierung:** beim nächsten Update das Stand-Datum global aktualisieren; bei materiellen Änderungen eine versionierte Kopie in `legal/archive/` ablegen.

---

## Referenzen

Rechtsnormen (Stand Mai 2026, https://www.ris.bka.gv.at):

- **FAGG** — Fern- und Auswärtsgeschäfte-Gesetz, BGBl I 33/2014 idgF
- **VGG** — Verbrauchergewährleistungsgesetz, BGBl I 175/2021 idgF
- **KSchG** — Konsumentenschutzgesetz, BGBl 140/1979 idgF
- **ECG** — E-Commerce-Gesetz, BGBl I 152/2001 idgF
- **UGB** — Unternehmensgesetzbuch, dRGBl S 219/1897 idgF
- **MedienG** — Mediengesetz, BGBl 314/1981 idgF
- **AStG** — Alternative-Streitbeilegung-Gesetz, BGBl I 105/2015 idgF
- **DSG 2018** — Datenschutzgesetz, BGBl I 165/1999 idgF + DSGVO (VO 2016/679)
- **UStG 1994** — Umsatzsteuergesetz, BGBl 663/1994 idgF
- **GewO 1994** — Gewerbeordnung, BGBl 194/1994 idgF
- **VO (EG) 1223/2009** — Kosmetik-Verordnung
- **EU ODR-VO 524/2013** — **aufgehoben per 20.07.2025**

Hilfreiche Anlaufstellen:

- WKO Internetrecht: https://www.wko.at/internetrecht
- Internet-Ombudsstelle: https://www.ombudsstelle.at
- VKI / Konsumentenfragen.at: https://www.konsumentenfragen.at
