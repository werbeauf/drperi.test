# WooCommerce Bestellstatus — Dr. Peri Skincare

Übersicht aller WooCommerce-Status, was jeweils ausgelöst wird (E-Mails, Lager, Phorest, Zahlungen).

---

## 1. `pending` — Bezahlung ausstehend

Bestellung wurde erstellt, aber noch nicht bezahlt (z.B. Kunde bricht beim Checkout ab).

**Was passiert:**
- Gutschein-Nutzung wird aktualisiert
- Lagerbestand wird ggf. wieder **erhöht** (falls vorher reserviert)
- **Keine E-Mail** an Kunden oder Admin

---

## 2. `processing` — In Bearbeitung

Zahlung ist eingegangen, Bestellung muss versandt werden.

**Was passiert:**
- Download-Berechtigungen werden erstellt (bei digitalen Produkten)
- Verkaufszähler wird hochgezählt
- Gutschein-Nutzung wird aktualisiert
- **Lagerbestand wird reduziert** (WooCommerce-seitig)
- Reservierter Stock wird freigegeben
- PayPal-Zahlung wird ggf. captured
- **E-Mail an Kunden:** „Ihre Bestellung bei {site_title} ist eingegangen"
- **E-Mail an Admin:** „Neue Bestellung #{order_number}"

---

## 3. `on-hold` — In Wartestellung

Bestellung wartet auf Bestätigung (z.B. Banküberweisung, EPS vor Bestätigung).

**Was passiert:**
- Verkaufszähler wird hochgezählt
- Gutschein-Nutzung wird aktualisiert
- **Lagerbestand wird reduziert**
- Reservierter Stock wird freigegeben
- **E-Mail an Kunden:** „Ihre Bestellung bei {site_title} wurde empfangen"

---

## 4. `completed` — Abgeschlossen

Bestellung ist komplett abgewickelt und versandt.

**Was passiert:**
- WPML: E-Mail-Sprache wird auf Bestellsprache gesetzt
- **Phorest: Lager wird ABGEZOGEN** (`DEDUCT`) — sendet Stock-Adjustments an Phorest API (nur einmalig, via `_phorest_stock_deducted` Meta)
- Kunde wird als „zahlender Kunde" markiert
- Download-Berechtigungen werden erstellt
- Verkaufszähler wird hochgezählt
- Gutschein-Nutzung wird aktualisiert
- Lagerbestand wird reduziert (falls noch nicht geschehen)
- WooPayments: Autorisierung wird captured
- PayPal: Zahlung wird captured
- **E-Mail an Kunden:** „Ihre Bestellung bei {site_title} ist abgeschlossen"

---

## 5. `cancelled` — Storniert

Bestellung wurde storniert (durch Admin oder Kunde).

**Was passiert:**
- **Phorest: Lager wird ERHÖHT** (`INCREASE`) — nur wenn vorher abgezogen wurde (`_phorest_stock_deducted` existiert)
- Gutschein-Nutzung wird zurückgesetzt
- **Lagerbestand wird wieder erhöht** (WooCommerce-seitig)
- WooPayments: Autorisierung wird storniert
- **E-Mail an Admin:** „Bestellung #{order_number} wurde storniert"
- **E-Mail an Kunden:** AUS (aktuell deaktiviert)

---

## 6. `refunded` — Rückerstattet

Vollständige Rückerstattung wurde durchgeführt.

**Was passiert:**
- **Phorest: Lager wird ERHÖHT** (`INCREASE`) — nur wenn vorher abgezogen wurde
- WooCommerce `wc_order_fully_refunded` wird ausgeführt
- **E-Mail an Kunden:** „Ihre Bestellung wurde erstattet"

Bei **Teil-Erstattung** (`woocommerce_order_partially_refunded`):
- Refund-Typ-Meta wird gespeichert
- **E-Mail an Kunden:** „Ihre Bestellung wurde teilweise erstattet"

---

## 7. `failed` — Fehlgeschlagen

Zahlung ist fehlgeschlagen (z.B. Kreditkarte abgelehnt).

**Was passiert:**
- WPML: E-Mail-Sprache wird gesetzt
- Gutschein-Nutzung wird zurückgesetzt
- **E-Mail an Admin:** „Bestellung #{order_number} fehlgeschlagen"
- **E-Mail an Kunden:** „Ihre Bestellung ist fehlgeschlagen"

---

## 8. `checkout-draft` — Entwurf

Interner Status für Block-Checkout. Bestellung wird im Hintergrund erstellt, bevor der Kunde „Bestellen" klickt.

**Was passiert:**
- Nichts — rein technischer Zwischenstatus, keine Hooks, keine E-Mails

---

## Phorest-Zusammenfassung

| Statuswechsel | Phorest-Aktion | Datei |
|---|---|---|
| → `completed` | **DEDUCT** (Lager abziehen) | `includes/phorest/stock-sync.php:186` |
| → `cancelled` (nach completed) | **INCREASE** (Lager zurück) | `includes/phorest/stock-sync.php:197` |
| → `refunded` (nach completed) | **INCREASE** (Lager zurück) | `includes/phorest/stock-sync.php:208` |

> Phorest-Aktionen laufen nur, wenn das Produkt eine `_phorest_product_id` hat und ein zugehöriges Lagerbestandsprodukt in Phorest existiert.
