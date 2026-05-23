# Zahlungsmethoden

> **Status:** Entwurf — vor Veröffentlichung anwaltlich prüfen lassen.
> Dieses Dokument ergänzt die [Allgemeinen Geschäftsbedingungen](./AGB-DRAFT.md) und ist Bestandteil jedes über den Online-Shop https://www.drperi.at geschlossenen Vertrags.
> Stand: [PLATZHALTER: stand_datum]

---

## § 1. Akzeptierte Zahlungsmittel

(1) Im Online-Shop https://www.drperi.at akzeptieren wir die folgenden Zahlungsmittel:

[PLATZHALTER: aktive_zahlungsarten]

> **Hinweis Anwalt / Klient (R8):** Bitte ersetzen Sie [PLATZHALTER: aktive_zahlungsarten] durch eine vollständige und richtige Aufzählung der zum Zeitpunkt der Veröffentlichung in WooCommerce → WooPayments → Payment Methods tatsächlich aktivierten Zahlungsarten (z.B. "Kredit- und Debitkarten (Visa, Mastercard, American Express)", "Apple Pay", "Google Pay", "SEPA-Lastschrift", "Klarna", etc.). Eine vom Online-Shop tatsächlich abweichende Aufzählung wäre nach UWG § 2 als irreführende Geschäftspraxis angreifbar.

(2) Welche Zahlungsmittel im konkreten Fall verfügbar sind, wird Ihnen vor Abschluss des Bestellvorgangs an der Bestellabschlussseite angezeigt. Aus technischen oder bonitätsbedingten Gründen kann die Verfügbarkeit einzelner Zahlungsmittel im Einzelfall eingeschränkt sein.

---

## § 2. Zahlungsabwicklung über WooPayments

(1) Die Abwicklung der Online-Zahlungen erfolgt über den Zahlungsdienst **WooPayments** der [PLATZHALTER: woopayments_anbieter]. Als zugrundeliegender Zahlungsdienstleister kommt **Stripe Payments Europe Limited**, 1 Grand Canal Street Lower, Grand Canal Dock, Dublin, D02 H210, Irland, zum Einsatz (im Folgenden "Stripe").

(2) Mit Auswahl eines kartenbasierten oder eines anderen über WooPayments abgewickelten Zahlungsmittels willigen Sie ein, dass Ihre für die Zahlungsabwicklung erforderlichen Daten (insbesondere Karten- bzw. Kontodaten, Name, Bestellbetrag, Bestellnummer) an WooPayments und Stripe zur Durchführung der Transaktion übermittelt werden.

(3) Einzelheiten zur Datenverarbeitung durch WooPayments und Stripe entnehmen Sie bitte unserer **Datenschutzerklärung** unter https://www.drperi.at/datenschutz/ sowie den Datenschutzbestimmungen von Stripe (https://stripe.com/at/privacy).

> **Hinweis Anwalt:** Die Konzernstruktur von WooPayments und Stripe kann sich ändern (Acquirer, Issuer, EU-Tochtergesellschaften). Bitte verifizieren Sie [PLATZHALTER: woopayments_anbieter] sowie die Stripe-EU-Entität zum Stand der Veröffentlichung dieser AGB.

---

## § 3. Sicherheit, PCI-DSS und Starke Kundenauthentifizierung

(1) Sämtliche im Rahmen der Zahlungsabwicklung erhobenen Karten- und Kontodaten werden ausschließlich vom Zahlungsdienstleister verarbeitet und zu keinem Zeitpunkt auf Servern von [PLATZHALTER: firma] gespeichert.

(2) Die Übertragung sensibler Zahlungsdaten erfolgt durchgehend SSL-/TLS-verschlüsselt. WooPayments und Stripe sind nach dem Payment Card Industry Data Security Standard (PCI-DSS) zertifiziert.

(3) Bei Kartenzahlungen kommt — sofern erforderlich — eine **Starke Kundenauthentifizierung (SCA / 3D-Secure 2)** gemäß der zweiten europäischen Zahlungsdiensterichtlinie (PSD2) zum Einsatz. Sie werden in diesem Fall im Bestellvorgang automatisch zu Ihrer Bank weitergeleitet, um die Zahlung durch das von Ihrer Bank vorgegebene Verfahren (z.B. App-Bestätigung, Push-Nachricht, biometrische Authentifizierung) zu autorisieren.

---

## § 4. Fälligkeit und Voraussetzungen für den Versand

(1) Der Kaufpreis ist mit Absendung der Bestellung fällig.

(2) Der Versand der Ware erfolgt erst nach erfolgreichem Eingang der Zahlung bzw. nach erfolgreicher Autorisierung durch den Zahlungsdienstleister. Bei Zahlungsmethoden mit nachgelagertem Zahlungseingang (sofern verfügbar, z.B. SEPA-Lastschrift) gelten die jeweiligen Bedingungen des Zahlungsdienstleisters.

---

## § 5. Fehlgeschlagene Zahlungen, Rücklastschriften und Chargebacks

(1) Wird eine Zahlung durch den Zahlungsdienstleister, das kartenausgebende Institut oder die Bank des Kunden zurückgewiesen oder rückgebucht (Chargeback / Rücklastschrift), und ist diese Rückbuchung vom Kunden zu vertreten, sind wir berechtigt, die uns durch das Bank- oder Zahlungsdienstinstitut tatsächlich entstandenen Bearbeitungsentgelte gegenüber dem Kunden geltend zu machen.

(2) Pauschalierte Bearbeitungsgebühren werden nicht in Rechnung gestellt. Dem Kunden bleibt der Nachweis vorbehalten, dass uns kein oder ein wesentlich geringerer Schaden entstanden ist.

(3) Bei wiederholten erfolglosen Zahlungsversuchen behalten wir uns vor, die Bestellung zu stornieren und den Versand zu verweigern.

---

## § 6. Rückerstattungen

(1) Wir erstatten geleistete Zahlungen ausschließlich auf demselben Zahlungsweg, den Sie für die ursprüngliche Transaktion verwendet haben — es sei denn, mit Ihnen wird ausdrücklich etwas anderes vereinbart. In keinem Fall werden Ihnen wegen einer Rückzahlung Entgelte berechnet.

(2) Im Falle eines wirksam ausgeübten Widerrufs gelten ergänzend die Regelungen unserer [Widerrufsbelehrung](./WIDERRUFSBELEHRUNG-DRAFT.md), insbesondere die Vorgaben zu Frist und Höhe der Rückerstattung.

---

## § 7. Rechnungsstellung

(1) Sie erhalten zu jeder abgeschlossenen Bestellung eine elektronische Rechnung. Die Rechnung wird Ihnen entweder als Anhang zur Bestellbestätigungs- bzw. Versandbestätigungs-E-Mail übermittelt oder im Kundenkonto unter https://www.drperi.at zum Abruf bereitgestellt.

(2) Mit der Bestellung erklären Sie sich mit dem Empfang elektronischer Rechnungen gemäß § 11 UStG 1994 einverstanden.

(3) Die Rechnung enthält alle nach § 11 Abs 1 UStG erforderlichen Angaben. Eine zusätzliche Zusendung in Papierform erfolgt nicht; auf Anfrage erhalten Sie jederzeit eine Kopie unter [PLATZHALTER: email_kontakt].

---

**Verwandte Rechtstexte:** [Allgemeine Geschäftsbedingungen](./AGB-DRAFT.md) · [Versandbedingungen](./VERSAND-DRAFT.md) · [Widerrufsbelehrung](./WIDERRUFSBELEHRUNG-DRAFT.md) · [Impressum](./IMPRESSUM-DRAFT.md)

**Stand der letzten Aktualisierung:** [PLATZHALTER: stand_datum]
