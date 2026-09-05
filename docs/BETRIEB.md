# Betrieb

Von der Installation zum laufenden Shop.

## 1. Nach der Installation

Der Installer legt Steuersätze, drei Versandzonen, Rechtsseiten-Entwürfe und
deinen Zugang an. Diese Handgriffe fehlen noch:

1. **Einstellungen → Shop-Daten** — Anschrift, E-Mail, Währung. Die Anschrift
   landet im Impressum und auf Belegen.
2. **Einstellungen → Rechtliches** und **Seiten** — Impressum, AGB, Datenschutz
   und Widerruf **müssen durch geprüfte Texte ersetzt werden.** Die
   mitgelieferten sind Platzhalter und im deutschsprachigen Handel abmahnfähig.
3. **Einstellungen → Versand** — Zonen und Tarife prüfen. Eine Zone **ohne**
   Länderliste ist die Auffangzone für alle übrigen Länder; ohne sie kann aus
   nicht aufgeführten Ländern niemand bestellen.
4. **Einstellungen → Zahlungen** — Zahlarten aktivieren (siehe unten).
5. **Einstellungen → E-Mail** — Absenderadresse eintragen, sonst gehen keine
   Bestellbestätigungen raus. Mit „Testmail senden“ direkt prüfen.
6. **Artikel** anlegen, **Design** einstellen, **Veröffentlichen**.

## 2. Zahlungen

Alle Zugangsdaten trägst du im Backend unter **Einstellungen → Zahlungen** ein.
Sie werden verschlüsselt gespeichert und nie wieder angezeigt — ein leeres Feld
lässt den gespeicherten Wert unverändert.

### Rechnung, Vorkasse, Nachnahme

Brauchen nichts weiter. Die Bestellung bleibt auf *offen*, bis du sie unter
**Bestellungen → Als bezahlt markieren** buchst. Bei Vorkasse trägst du die
Bankverbindung ein; sie erscheint dann in der Bestätigungsmail und auf der
Statusseite des Kunden.

### Stripe (Karte, Apple Pay, Google Pay)

Aus dem Stripe-Dashboard unter *Entwickler → API-Schlüssel*:

- **Geheimer Schlüssel** (`sk_live_…`)
- **Öffentlicher Schlüssel** (`pk_live_…`)

Dann unter *Entwickler → Webhooks* einen Endpunkt anlegen:

- Adresse: die im Backend angezeigte Webhook-Adresse
- Ereignisse: `checkout.session.completed`, `checkout.session.expired`,
  `checkout.session.async_payment_succeeded`,
  `checkout.session.async_payment_failed`, `charge.refunded`

Das dabei erzeugte **Webhook-Geheimnis** (`whsec_…`) gehört ebenfalls ins
Backend. Ohne dieses Geheimnis werden eingehende Zahlungsmeldungen **abgelehnt**
— das ist Absicht: eine unsignierte Bestätigung darf keine Bestellung als
bezahlt markieren.

### PayPal

Aus dem PayPal-Entwicklerportal: **Client-ID** und **Secret**. Modus zuerst auf
*Sandbox* stellen und einen Testkauf machen, dann auf *Live* umschalten.
Webhook-Adresse ebenfalls im Backend angezeigt; Ereignisse
`CHECKOUT.ORDER.APPROVED`, `PAYMENT.CAPTURE.COMPLETED`,
`PAYMENT.CAPTURE.DENIED`, `PAYMENT.CAPTURE.REFUNDED`. Die **Webhook-ID**
gehört ins Backend.

### Testzahlung

Schließt Bestellungen sofort ab, ohne dass Geld fließt. Damit lässt sich der
gesamte Ablauf bis zur Bestellverwaltung durchspielen. **Vor dem Livegang
abschalten.**

### Warum Webhooks wichtig sind

Nach der Zahlung wird der Kunde in den Shop zurückgeleitet, und dabei fragen wir
den Zahlungsstatus direkt beim Anbieter nach. Schließt der Kunde vorher den
Browser, fällt dieser Weg aus — dann ist der Webhook der einzige Weg, vom
Geldeingang zu erfahren. Er ist deshalb kein Extra, sondern der verlässliche
Pfad; die Rückkehr im Browser ist nur der schnelle.

Jede Meldung wird gespeichert und genau einmal verarbeitet. Doppelte
Zustellungen — die es bei jedem Anbieter gibt — verändern nichts.

## 3. E-Mail

Zwei Wege, einstellbar unter **Einstellungen → E-Mail**:

- **PHP `mail()`** — auf den meisten Webhostern eingerichtet, braucht keine
  Zugangsdaten. Die Mails kommen aber vom Server des Hosters und landen
  häufiger im Spam.
- **SMTP** — du hinterlegst einen Postfach-Zugang (meist dasselbe Postfach wie
  die Absenderadresse). Zuverlässiger, weil die Mails vom richtigen Server
  kommen.

In beiden Fällen sollte die **Absenderadresse zur Domain des Shops gehören** —
sonst weisen viele Empfänger sie ab. Die Schaltfläche „Testmail senden“ prüft
die Einstellung sofort; schlägt sie fehl, steht der Grund im **Protokoll**.

## 4. Livegang

Vor dem Scharfschalten:

- [ ] **HTTPS aktiv.** Bei fast allen Hostern lässt sich ein kostenloses
      Zertifikat mit einem Klick aktivieren. Ohne HTTPS wandern Kundenadressen
      und Anmeldedaten im Klartext durchs Netz, und Stripe wie PayPal
      verweigern unverschlüsselte Rückleitungen.
- [ ] **`install.php` gelöscht.** Das Backend erinnert dich daran.
- [ ] **Rechtstexte ersetzt** (Impressum, AGB, Datenschutz, Widerruf).
- [ ] **Testzahlung abgeschaltet.**
- [ ] **Absenderadresse eingetragen** und Testmail erfolgreich.
- [ ] **Eine echte Testbestellung** durchgespielt — inklusive Bezahlen,
      Versenden und Erstatten.
- [ ] **Systemcheck** ohne offene Punkte.

Wechselt die Adresse des Shops (etwa von einer Testdomain auf die echte), muss
`base_url` in der `config.php` angepasst werden — darauf bauen alle Links und
die Rückleitungen der Zahlungsanbieter auf.

## 5. Wenn dein Hoster nginx statt Apache einsetzt

Die mitgelieferte `.htaccess` schützt `config.php`, den Datenordner und die
Programmbibliothek — aber **nur auf Apache-Servern**. nginx ignoriert sie.
Ohne Gegenmaßnahme wäre die Datenbank dort schlicht herunterladbar, und damit
lägen alle Kunden- und Bestelldaten offen.

Der **Systemcheck prüft das aktiv** und schlägt Alarm, wenn die Datei erreichbar
ist. Zwei Wege, es zu beheben:

**Regel in der nginx-Konfiguration** (der Hoster muss sie eintragen):

```nginx
location ~ ^/(data|lib)/ { deny all; }
location = /config.php    { deny all; }
location ^~ /uploads/ {
    # Hochgeladene Dateien nie als Programmcode ausführen
    location ~ \.php$ { deny all; }
}
```

**Oder die Datenbank aus dem Web-Verzeichnis holen** — geht ohne Hoster und ist
sogar der sicherere Weg. In `config.php` einen Pfad oberhalb des öffentlichen
Ordners eintragen:

```php
'db' => [
    'driver' => 'sqlite',
    'path'   => dirname(__DIR__) . '/shop-daten/shop.sqlite',
],
```

Den Ordner vorher per FTP anlegen und die vorhandene Datenbankdatei dorthin
verschieben. Bei MySQL stellt sich die Frage nicht — dort liegen die Daten
ohnehin außerhalb.

## 6. Sicherung

Zu sichern sind zwei Dinge: die **Datenbank** und der Ordner **`uploads`**.

**Bei SQLite** ist die gesamte Datenbank eine Datei: `data/shop.sqlite`.
Sie lässt sich per FTP herunterladen. Am saubersten geht es so, weil dabei kein
halb geschriebener Zustand erwischt wird:

```
data/shop.sqlite   +   data/shop.sqlite-wal   +   data/shop.sqlite-shm
```

Alle drei zusammen kopieren, oder den Shop kurz in den Wartungsmodus nehmen.
Wer Shell-Zugang hat, nimmt besser:

```bash
sqlite3 data/shop.sqlite ".backup '/pfad/zur/sicherung.sqlite'"
```

**Bei MySQL** übernimmt das der Hoster meist automatisch; im Zweifel über
phpMyAdmin exportieren.

Viele Hoster bieten automatische Sicherungen an — prüfe, ob deine im Tarif
enthalten sind und wie weit sie zurückreichen.

## 7. Umziehen auf einen anderen Server

1. Alle Dateien kopieren, einschließlich `config.php`, `data/` und `uploads/`.
2. In `config.php` die `base_url` auf die neue Adresse ändern.
3. Bei MySQL zusätzlich die Zugangsdaten in `config.php` anpassen.
4. `systemcheck.php` aufrufen und prüfen.

Den Wert `secret` in der `config.php` **nicht ändern** — sonst werden die
gespeicherten Zugangsdaten für Stripe, PayPal und SMTP unlesbar.

## 8. Aktualisieren

Neue Dateien hochladen und die alten überschreiben — `config.php`, `data/` und
`uploads/` dabei auslassen. Fehlende Tabellen oder Spalten ergänzt das System
beim nächsten Aufruf von selbst. Vorher eine Sicherung ziehen.

## 9. Wenn etwas klemmt

| Symptom | Ursache |
|---|---|
| Weiße Seite | PHP-Version zu alt oder ein Fehler. `systemcheck.php` aufrufen; die Fehlermeldung steht im Fehlerprotokoll des Hosters. |
| „Der Shop ist noch nicht eingerichtet“ | `config.php` fehlt — `install.php` aufrufen. |
| „Noch nichts veröffentlicht“ | Im Backend auf **Veröffentlichen** klicken. |
| Artikel fehlt im Shop | Status ist *Entwurf*, oder seit der Änderung wurde nicht veröffentlicht. |
| „In dieses Land liefern wir nicht“ | Keine Versandzone für das Land und keine Auffangzone. |
| Zahlart fehlt an der Kasse | Zugangsdaten fehlen oder Zahlart nicht aktiviert. |
| Zahlungen kommen nicht an | Webhook-Adresse und -Geheimnis prüfen. Eingegangene Meldungen samt Fehler stehen in der Tabelle `webhooks`. |
| Keine Bestellmails | Absenderadresse fehlt, oder der Hoster blockt `mail()`. Testmail senden, dann ins **Protokoll** schauen. |
| Upload schlägt fehl | Ordner `uploads` nicht beschreibbar (Rechte 755), oder Datei über 8 MB. Der Systemcheck zeigt die Grenze des Servers. |
| Nach dem Login wieder auf der Anmeldeseite | Cookies blockiert, oder `base_url` in `config.php` passt nicht zur aufgerufenen Adresse. |
| Bestand stimmt nicht | Unter **Bestand → ≡** steht der vollständige Verlauf jeder Variante mit Grund und Bestellung. |
