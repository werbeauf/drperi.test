# Phorest Integration — Technische Dokumentation
**Plugin:** `werbeauf-customs`
**Projekt:** Dr. Peri Skincare — WooCommerce × Phorest
**Stand:** April 2026 (Pause-Snapshot · 30. April 2026)
**Status:** Produktionsbereit

---

## 1. Übersicht

Das Plugin verbindet den WooCommerce-Shop von Dr. Peri Skincare mit dem Salon-Management-System **Phorest**. Es synchronisiert Produktdaten automatisch von Phorest nach WooCommerce und meldet Lageränderungen bei Bestellungsereignissen zurück an Phorest.

### Datenfluss

```
Phorest API
    │
    ├─► Produktdaten (Name, Preis, SKU, Barcode, Lagerbestand)
    │       └─► WooCommerce Produkte  [stündlich + manuell]
    │
    └◄─ Lageranpassungen (DEDUCT / INCREASE)
            └◄─ WooCommerce Bestellungsereignisse  [automatisch]
```

> **Bestellungen werden NICHT an Phorest gesendet.**
> WooCommerce übernimmt die gesamte Auftragsverwaltung.

---

## 2. Server & Zugangsdaten

| | |
|---|---|
| **Server** | svr.werbeauf.com |
| **SSH Port** | 2222 |
| **SSH Key** | `~/.ssh/id_rsa` |
| **SSH User** | `flamboyant-mahavira_hlevjin4uzo` |
| **WordPress Root** | `/var/www/vhosts/flamboyant-mahavira.88-198-51-214.plesk.page/httpdocs/` |
| **Plugin Pfad** | `…/httpdocs/wp-content/plugins/werbeauf-customs/` |
| **WP Admin** | https://flamboyant-mahavira.88-198-51-214.plesk.page/wp-admin/ |
| **DB Präfix** | `lmAZXbT_` |

---

## 3. Phorest API

| | |
|---|---|
| **Base URL** | `https://api-gateway-eu.phorest.com/third-party-api-server` |
| **Auth** | HTTP Basic — `Authorization: Basic {base64(user:password)}` |
| **Business ID** | `FVQJ_AcSEF6JPRyvmbjhEg` |
| **Branch ID** | `z_B_42Tkq4shoEIve6Uxzw` |

### 3.1 Verwendete Endpoints

#### Produkte abrufen
```
GET /api/business/{businessId}/branch/{branchId}/product?pageSize=100&page=0
```
- Paginiert — Schleife bis `page.totalPages`
- Antwort: HAL-Format unter `_embedded.products`
- **Filter:** nur `brandName === "Dr. Peri Skincare"` UND `categoryName !== "1 Kabinettware"`

**Genutzte Felder:**

| Feld | Bedeutung | Ziel WooCommerce |
|------|-----------|-----------------|
| `productId` * | Eindeutige Phorest-ID | SKU |
| `name` * | Produktname | Titel |
| `price` * | Bruttopreis | Regulärer Preis |
| `barcode` * | EAN / GTIN | `_wc_gtin` |
| `quantityInStock` * | Lagerbestand | `_stock` |
| `categoryName` | Kategorie | nur Filter |
| `brandName` | Marke | nur Filter |
| `updatedAt` | Zuletzt geändert | nur Anzeige |

`*` = für unser System erforderlich

#### Lagerkorrektur
```
POST /api/business/{businessId}/branch/{branchId}/stock/adjustment
```
```json
{
  "stocks": [
    {
      "barcode": "1234567891234",
      "quantity": 2,
      "operationType": "DEDUCT"
    }
  ]
}
```
- `operationType`: `DEDUCT` (abziehen) oder `INCREASE` (erhöhen)
- Erfolg: **HTTP 204 No Content** (kein Body)
- Fehler: HTTP 400 mit `{ "detail": "Fehlermeldung" }`

#### Verbindungstest (nur Settings-Seite)
```
GET /api/business/{businessId}/branch
```

---

## 4. Plugin-Dateistruktur

```
werbeauf-customs/
├── werbeauf-customs.php              Haupt-Plugin-Datei
├── PHOREST-DOKUMENTATION.md         Diese Dokumentation
├── admin/
│   ├── admin-menu.php                Admin-Unterseiten registrieren
│   ├── phorest-api.php               API-Einstellungen & Test
│   ├── phorest-data.php              Produktbrowser + Sync
│   └── phorest-stocks.php            Lagerverlauf + manuelle Anpassung
└── includes/
    ├── phorest-woo-sync.php          Produkt-Sync Phorest → WooCommerce
    └── phorest-stock-sync.php        Lager-Sync + API-Helper + DB-Tabelle
```

### Ladereihenfolge (`werbeauf-customs.php`)

```
admin/phorest-api.php        AJAX-Handler Einstellungen + Verbindungstest
admin/phorest-data.php       AJAX-Handler Produktsync + Cache
admin/phorest-stocks.php     AJAX-Handler manuelle Lageranpassung
includes/phorest-woo-sync.php     Konstanten, Meta-Box, Cron-Hook
includes/phorest-stock-sync.php   wa_phorest_api(), DB-Tabelle, Bestell-Hooks
admin/admin-menu.php         Menü-Registrierung (immer zuletzt)
```

> `phorest-stock-sync.php` definiert `wa_phorest_api()` mit `if ( ! function_exists(...) )` — damit steht die Funktion für alle Module zur Verfügung.

---

## 5. Admin-Seiten

| Menüpunkt | URL | Datei |
|-----------|-----|-------|
| Phorest API | `?page=wa-phorest-api` | `admin/phorest-api.php` |
| Phorest Produkte | `?page=wa-phorest-data` | `admin/phorest-data.php` |
| Phorest Lager | `?page=wa-phorest-stocks` | `admin/phorest-stocks.php` |

Alle Seiten sind Unterseiten des ACF-Elternmenüs `dr-peri`.

---

## 6. Modul: API-Einstellungen (`admin/phorest-api.php`)

Speichert alle Zugangsdaten als WP Options. Bietet einen AJAX-Verbindungstest.

### WP Options

| Option | Beschreibung |
|--------|-------------|
| `wa_phorest_active` | Integration aktiv: `1` / `0` |
| `wa_phorest_business_id` | Phorest Business ID |
| `wa_phorest_branch_id` | Phorest Branch ID |
| `wa_phorest_api_url` | API Base URL |
| `wa_phorest_api_token` | Base64-Auth-Token |

### AJAX

| Action | Nonce | Beschreibung |
|--------|-------|-------------|
| `wa_phorest_test_connection` | `wa_phorest_test` | GET /branch — prüft HTTP 200 |

---

## 7. Modul: Produktbrowser (`admin/phorest-data.php`)

Zeigt alle gefilterten Phorest-Produkte als Tabelle. Buttons: **Sync now** und **Clear Cache**.

### Tabellenspalten (fixe Reihenfolge)

`Marke` — `Name *` — `Produkt-ID *` — `Kategorie` — `Preis *` — `Barcode *` — `Lagerbestand *` — `Aktualisiert`

### Cache

| | |
|---|---|
| Transient | `wa_phorest_products_cache` |
| Gültigkeit | 6 Stunden |
| Letzter Sync | WP Option `wa_phorest_last_sync` |

### AJAX

| Action | Nonce | Beschreibung |
|--------|-------|-------------|
| `wa_phorest_sync_products` | `wa_phorest_data` | API abrufen → Cache speichern → `wa_phorest_after_sync` feuern |
| `wa_phorest_clear_cache` | `wa_phorest_data` | Transient + `wa_phorest_last_sync` löschen |

### Produktfilter
```php
brandName === "Dr. Peri Skincare"
AND categoryName !== "1 Kabinettware"
```

---

## 8. Modul: Produkt-Sync (`includes/phorest-woo-sync.php`)

Verbindet einzelne WooCommerce-Produkte mit Phorest-Produkten und hält sie synchron.

### Konstanten

| Konstante | Wert |
|-----------|------|
| `WA_PHOREST_LINK_META` | `_phorest_product_id` |
| `WA_PHOREST_CRON_HOOK` | `wa_phorest_auto_sync` |

### Felder-Mapping Phorest → WooCommerce

| Phorest | WooCommerce | Methode |
|---------|------------|---------|
| `productId` | SKU | `set_sku()` |
| `name` | Titel | `wp_update_post()` |
| `price` | Regulärer Preis | `set_regular_price()` |
| `barcode` | `_wc_gtin` | `update_post_meta()` |
| `quantityInStock` | Lagerbestand | `set_stock_quantity()` ¹ |

¹ Wird bei externen Produkten übersprungen (`is_type('external')`) — WooCommerce würde sonst eine `WC_Data_Exception` werfen.

### WP Meta pro Produkt

| Meta-Key | Inhalt |
|----------|--------|
| `_phorest_product_id` | Phorest `productId` (Verknüpfungs-ID) |
| `_phorest_last_sync` | Timestamp letzter erfolgreicher Sync |

### Sidebar-Meta-Box

Jedes WC-Produkt hat eine Sidebar-Box **„Phorest API Verknüpfung"**:
- Dropdown: alle Phorest-Produkte aus dem Cache
- Nach Verknüpfung: „Jetzt synchronisieren"-Button + letzter Sync-Zeitstempel

### Sync-Auslöser

| Trigger | Beschreibung |
|---------|-------------|
| `woocommerce_process_product_meta` | Sofort beim Speichern des Produkts |
| AJAX `wa_phorest_sync_single_product` | Manueller Button in der Sidebar |
| Hook `wa_phorest_after_sync` | Nach jedem Phorest-Datensync (Sync Now) |
| WP Cron `wa_phorest_auto_sync` | Stündlich automatisch |

### WP Cron

Der stündliche Cron-Job:
1. Ruft alle Produktseiten von der Phorest API ab
2. Erneuert den Cache (6 Stunden)
3. Synchronisiert alle verknüpften WC-Produkte

Wird bei Plugin-Deaktivierung via `register_deactivation_hook` automatisch entfernt.

### Rekursionsschutz

`wa_phorest_apply_to_woo()` enthält einen `static $running[]`-Guard, der verhindert, dass dasselbe Produkt innerhalb eines Aufrufzyklus doppelt synchronisiert wird.

### AJAX

| Action | Nonce | Beschreibung |
|--------|-------|-------------|
| `wa_phorest_sync_single_product` | `wa_phorest_sync_single` | Einzelnes WC-Produkt synchronisieren |

### Admin-Spalte (Produktliste)

Spalte **„Phorest Produkt"** zeigt verknüpften Namen (blau) + letzten Sync-Timestamp. Nicht verknüpfte Produkte zeigen `—`.

---

## 9. Modul: Lager-Sync (`includes/phorest-stock-sync.php`)

Sendet Lageranpassungen an Phorest bei Bestellungsereignissen und loggt alle Aktionen.

### Shared API Helper

```php
wa_phorest_api( string $method, string $path, array|null $body ) : true|array|WP_Error
```
- Liest URL und Token aus WP Options
- Gibt `true` zurück bei HTTP 204
- Gibt `WP_Error` zurück bei HTTP 4xx/5xx
- Gibt JSON-Array zurück bei HTTP 200/201

### Datenbank-Tabelle `lmAZXbT_wa_phorest_stock_log`

Wird automatisch beim ersten `init`-Hook via `dbDelta()` erstellt.
Version: `wa_phorest_stock_db_version` = `1.0`

| Spalte | Typ | Beschreibung |
|--------|-----|-------------|
| `id` | BIGINT UNSIGNED AI | Primärschlüssel |
| `order_id` | BIGINT UNSIGNED | WC-Bestellungs-ID (`0` = manuelle Anpassung) |
| `product_id` | BIGINT UNSIGNED | WC-Produkt-ID |
| `product_name` | VARCHAR(255) | Produktname zum Zeitpunkt der Anpassung |
| `barcode` | VARCHAR(100) | EAN/GTIN |
| `quantity` | INT | Angepasste Menge |
| `operation` | VARCHAR(10) | `DEDUCT` oder `INCREASE` |
| `status` | VARCHAR(10) | `success` oder `error` |
| `error_msg` | TEXT | Fehlermeldung (leer bei Erfolg) |
| `created_at` | DATETIME | Zeitstempel |

Indizes auf `order_id` und `created_at`.

### Bestell-Hooks

| WC-Hook | Operation | Bedingung |
|---------|-----------|-----------|
| `woocommerce_order_status_completed` | `DEDUCT` | Nur wenn `_phorest_stock_deducted` noch nicht gesetzt |
| `woocommerce_order_status_cancelled` | `INCREASE` | Nur wenn `_phorest_stock_deducted` vorhanden |
| `woocommerce_order_status_refunded` | `INCREASE` | Nur wenn `_phorest_stock_deducted` vorhanden |

### Guard-Meta pro Bestellung

| Meta-Key | Wert | Bedeutung |
|----------|------|-----------|
| `_phorest_stock_deducted` | Timestamp | Lager wurde abgezogen — schützt vor Doppel-DEDUCT |

### Produktbedingungen

Ein Produkt wird beim Lager-Sync nur berücksichtigt wenn:
1. `_phorest_product_id` gesetzt (Phorest-verknüpft)
2. `_wc_gtin` gesetzt und nicht leer (hat Barcode)

---

## 10. Modul: Lagerverlauf (`admin/phorest-stocks.php`)

### Manuelle Anpassung (oben auf der Seite)

- Produkt-Dropdown: nur Produkte mit Phorest-Verknüpfung **und** Barcode
- Menge + Operation (DEDUCT / INCREASE) wählen
- Sendet direkt an Phorest API, loggt mit `order_id = 0`

**AJAX:** `wa_phorest_manual_stock` (Nonce: `wa_phorest_manual_stock`)

### Verlaufstabelle

| Spalte | Beschreibung |
|--------|-------------|
| Datum | `d.m.Y H:i` |
| Quelle | `#order_id` Link oder „Manuell"-Badge |
| Produkt | Name zum Zeitpunkt der Anpassung |
| Barcode | EAN/GTIN als `<code>` |
| Menge | Stückzahl |
| Operation | DEDUCT (rot) / INCREASE (grün) |
| Status | Grün = OK, Rot = Fehler mit Tooltip |

### Filter & Pagination

- Freitext (Produkt, Barcode, Bestellungs-ID)
- Operation / Status Dropdown
- 50 Einträge pro Seite

---

## 11. WooCommerce Meta-Keys (Vollständige Übersicht)

### Pro Produkt

| Meta-Key | Gesetzt von | Inhalt |
|----------|------------|--------|
| `_phorest_product_id` | Admin Sidebar | Phorest `productId` |
| `_phorest_last_sync` | `wa_phorest_apply_to_woo()` | Timestamp letzter Sync |
| `_wc_gtin` | Phorest-Sync | EAN/GTIN (WC 9.2+ natives Feld) |

### Pro Bestellung

| Meta-Key | Gesetzt von | Inhalt |
|----------|------------|--------|
| `_phorest_stock_deducted` | `status_completed` Hook | Timestamp — Lager abgezogen |

---

## 12. WP Options (Vollständige Übersicht)

| Option | Beschreibung |
|--------|-------------|
| `wa_phorest_active` | Integration aktiv (0/1) |
| `wa_phorest_business_id` | Phorest Business ID |
| `wa_phorest_branch_id` | Phorest Branch ID |
| `wa_phorest_api_url` | API Base URL |
| `wa_phorest_api_token` | Base64-Auth-Token |
| `wa_phorest_last_sync` | Letzter Produkt-Sync Timestamp |
| `wa_phorest_stock_db_version` | DB-Schema-Version (`1.0`) |

---

## 13. Deployment-Checkliste

### Erstmalige Einrichtung (neue Umgebung)

- [ ] Plugin in `wp-content/plugins/werbeauf-customs/` hochladen
- [ ] Plugin im WP-Admin aktivieren
- [ ] Unter **Phorest API** alle Felder ausfüllen:
  - Business ID: `FVQJ_AcSEF6JPRyvmbjhEg`
  - Branch ID: `z_B_42Tkq4shoEIve6Uxzw`
  - API-URL: `https://api-gateway-eu.phorest.com/third-party-api-server`
  - API-Token: Base64-kodierter `user:password` String
- [ ] **Verbindung testen** — muss HTTP 200 zurückgeben
- [ ] Integration **aktivieren** (Toggle auf AN)
- [ ] Unter **Phorest Produkte → Sync now** klicken — Produkte laden
- [ ] Für jedes WC-Produkt das entsprechende Phorest-Produkt in der Sidebar verknüpfen
- [ ] Sicherstellen dass jedes verknüpfte Produkt einen Barcode (`_wc_gtin`) hat
- [ ] DB-Tabelle `lmAZXbT_wa_phorest_stock_log` prüfen (wird automatisch angelegt)

### Update (bestehende Installation)

- [ ] Geänderte Dateien per SCP hochladen:
  ```bash
  scp -P 2222 -i ~/.ssh/id_rsa {datei} flamboyant-mahavira_hlevjin4uzo@svr.werbeauf.com:{pfad}
  ```
- [ ] PHP-Syntax prüfen:
  ```bash
  ssh -p 2222 ... "php -l /pfad/zur/datei.php"
  ```
- [ ] WP Cron läuft: unter **Tools → Scheduled Events** `wa_phorest_auto_sync` prüfen
- [ ] Testbestellung aufgeben → Status auf Abgeschlossen setzen → Bestellungsnotiz prüfen
- [ ] **Phorest Lager**-Seite prüfen — Eintrag muss erscheinen

### Smoke-Test nach Deployment

1. WP-Admin aufrufen — kein PHP Fatal Error
2. **Phorest API** → Verbindung testen → HTTP 200
3. **Phorest Produkte** → Sync now → Produkte erscheinen
4. Produkt öffnen → Sidebar zeigt Phorest-Dropdown
5. **Phorest Lager** → manuelle Anpassung für ein Produkt → Eintrag in Tabelle
6. Testbestellung abschließen → Bestellungsnotiz „Phorest Lager abgezogen"

---

## 14. Bekannte Einschränkungen

| Thema | Details |
|-------|---------|
| **Phorest Online Shop** | Käufe können nicht als Online-Shop-Verkäufe markiert werden — kein `salesChannel`-Feld in der API. Kontakt: api-requests@phorest.com |
| **Teilrückerstattungen** | Nur vollständiger `refunded`-Status löst INCREASE aus. WooCommerce-Teilrefunds werden nicht separat behandelt. |
| **Externe Produkte** | Lagerbestand wird nicht synchronisiert (WC-Einschränkung). Barcode wird trotzdem gesetzt. |
| **Cache-Abhängigkeit** | Manuelle Produkt-Sync Buttons in der Sidebar benötigen einen gefüllten Cache. Zuerst „Sync now" ausführen. |

---

## 15. Fehlerbehebung

| Symptom | Ursache | Lösung |
|---------|---------|--------|
| „Phorest API nicht konfiguriert" | Leere WP Options | Einstellungen speichern und testen |
| Sidebar zeigt „Keine Phorest-Produkte im Cache" | Cache leer | Phorest Produkte → Sync now |
| Lager-Sync kommt nicht an | Produkt hat keinen Barcode | `_wc_gtin` im Produkt setzen (via Phorest-Sync) |
| Doppelter DEDUCT | Guard-Meta fehlt | `_phorest_stock_deducted` manuell in DB prüfen |
| HTTP 400 bei Lageranpassung | Barcode stimmt nicht überein | Barcode in Phorest und WC abgleichen |
| Cron läuft nicht | WP Cron deaktiviert | `DISABLE_WP_CRON` in `wp-config.php` prüfen, ggf. Server-Cron einrichten |
