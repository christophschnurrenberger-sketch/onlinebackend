<?php
/**
 * Mail – Bestellbestätigungen und Benachrichtigungen.
 *
 * Zwei Wege: die PHP-Funktion mail() (auf den meisten Webhostern eingerichtet)
 * oder SMTP über einen eigenen Zugang. SMTP ist zuverlässiger, weil die Mails
 * dann vom richtigen Absenderserver kommen und seltener im Spam landen.
 *
 * Der Versand darf eine Bestellung nie scheitern lassen: Fehler landen im
 * Protokoll, die Bestellung bleibt bestehen.
 */
final class Mail
{
    /* ------------------------------------------------------------- Versand */

    public static function senden(string $an, string $betreff, string $text, string $html = ''): bool
    {
        $absender     = Settings::get('mail_absender');
        $absenderName = Settings::get('mail_absender_name') ?: Settings::get('shop_name');

        if ($absender === '' || !Util::isEmail($absender)) {
            Log::warn('mail', 'Kein Absender hinterlegt – Mail an ' . $an . ' nicht gesendet.');
            return false;
        }
        if (!Util::isEmail($an)) {
            return false;
        }

        try {
            $erfolg = Settings::get('mail_methode', 'mail') === 'smtp'
                ? self::perSmtp($an, $betreff, $text, $html, $absender, $absenderName)
                : self::perMailFunktion($an, $betreff, $text, $html, $absender, $absenderName);

            if (!$erfolg) {
                Log::warn('mail', 'Versand an ' . $an . ' fehlgeschlagen: ' . $betreff);
            }
            return $erfolg;
        } catch (Throwable $e) {
            Log::error('mail', 'Versand an ' . $an . ' fehlgeschlagen: ' . $e->getMessage());
            return false;
        }
    }

    private static function perMailFunktion(string $an, string $betreff, string $text, string $html, string $absender, string $name): bool
    {
        $grenze   = '=_' . Util::token(12);
        $kopf = [
            'From: ' . self::adresse($name, $absender),
            'Reply-To: ' . self::adresse($name, Settings::get('shop_email') ?: $absender),
            'MIME-Version: 1.0',
            'X-Mailer: Shop-System',
        ];

        if ($html !== '') {
            $kopf[] = 'Content-Type: multipart/alternative; boundary="' . $grenze . '"';
            $koerper = "--$grenze\r\nContent-Type: text/plain; charset=UTF-8\r\n"
                . "Content-Transfer-Encoding: 8bit\r\n\r\n" . $text . "\r\n\r\n"
                . "--$grenze\r\nContent-Type: text/html; charset=UTF-8\r\n"
                . "Content-Transfer-Encoding: 8bit\r\n\r\n" . $html . "\r\n\r\n"
                . "--$grenze--";
        } else {
            $kopf[] = 'Content-Type: text/plain; charset=UTF-8';
            $koerper = $text;
        }

        return @mail(
            $an,
            self::betreffKodieren($betreff),
            $koerper,
            implode("\r\n", $kopf),
            '-f' . $absender
        );
    }

    /** Minimaler SMTP-Client – reicht für Bestätigungsmails eines Shops. */
    private static function perSmtp(string $an, string $betreff, string $text, string $html, string $absender, string $name): bool
    {
        $host       = Settings::get('smtp_host');
        $port       = Settings::int('smtp_port', 587);
        $benutzer   = Settings::get('smtp_user');
        $passwort   = Settings::get('smtp_pass');
        $sicherheit = Settings::get('smtp_sicherheit', 'tls');

        if ($host === '') {
            throw new RuntimeException('Es ist kein SMTP-Server hinterlegt.');
        }

        $ziel = ($sicherheit === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
        $sock = @stream_socket_client($ziel, $fehlerNr, $fehlerText, 20);
        if ($sock === false) {
            throw new RuntimeException('Verbindung zu ' . $host . ' fehlgeschlagen: ' . $fehlerText);
        }
        stream_set_timeout($sock, 20);

        $lesen = static function () use ($sock): string {
            $antwort = '';
            while (($zeile = fgets($sock, 515)) !== false) {
                $antwort .= $zeile;
                if (strlen($zeile) < 4 || $zeile[3] !== '-') {
                    break;
                }
            }
            return $antwort;
        };
        $schreiben = static function (string $befehl) use ($sock, $lesen): string {
            fwrite($sock, $befehl . "\r\n");
            return $lesen();
        };

        $lesen();
        $eigen = (string) ($_SERVER['SERVER_NAME'] ?? 'localhost');
        $schreiben('EHLO ' . $eigen);

        if ($sicherheit === 'tls') {
            $schreiben('STARTTLS');
            if (!@stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                fclose($sock);
                throw new RuntimeException('TLS-Verschlüsselung zum Mailserver fehlgeschlagen.');
            }
            $schreiben('EHLO ' . $eigen);
        }

        if ($benutzer !== '') {
            $schreiben('AUTH LOGIN');
            $schreiben(base64_encode($benutzer));
            $antwort = $schreiben(base64_encode($passwort));
            if (!str_starts_with($antwort, '235')) {
                fclose($sock);
                throw new RuntimeException('SMTP-Anmeldung abgelehnt. Bitte Benutzername und Passwort prüfen.');
            }
        }

        $schreiben('MAIL FROM:<' . $absender . '>');
        $antwort = $schreiben('RCPT TO:<' . $an . '>');
        if (!str_starts_with($antwort, '25')) {
            fclose($sock);
            throw new RuntimeException('Der Mailserver hat den Empfänger abgelehnt.');
        }
        $schreiben('DATA');

        $grenze = '=_' . Util::token(12);
        $kopf = [
            'From: ' . self::adresse($name, $absender),
            'To: <' . $an . '>',
            'Subject: ' . self::betreffKodieren($betreff),
            'Date: ' . date('r'),
            'MIME-Version: 1.0',
        ];
        if ($html !== '') {
            $kopf[] = 'Content-Type: multipart/alternative; boundary="' . $grenze . '"';
            $koerper = "--$grenze\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\n" . $text
                . "\r\n\r\n--$grenze\r\nContent-Type: text/html; charset=UTF-8\r\n\r\n" . $html
                . "\r\n\r\n--$grenze--";
        } else {
            $kopf[] = 'Content-Type: text/plain; charset=UTF-8';
            $koerper = $text;
        }

        // Punkte am Zeilenanfang verdoppeln – ein einzelner beendet sonst die Nachricht.
        $daten = implode("\r\n", $kopf) . "\r\n\r\n" . str_replace("\n.", "\n..", $koerper);
        $antwort = $schreiben($daten . "\r\n.");
        $schreiben('QUIT');
        fclose($sock);

        return str_starts_with($antwort, '250');
    }

    private static function adresse(string $name, string $email): string
    {
        return $name !== '' ? '=?UTF-8?B?' . base64_encode($name) . '?= <' . $email . '>' : '<' . $email . '>';
    }

    private static function betreffKodieren(string $betreff): string
    {
        return preg_match('/[\x80-\xFF]/', $betreff) === 1
            ? '=?UTF-8?B?' . base64_encode($betreff) . '?='
            : $betreff;
    }

    /* --------------------------------------------------------- Nachrichten */

    public static function bestellbestaetigung(int $bestellungId): void
    {
        $bestellung = Bestellungen::holen($bestellungId);
        if ($bestellung === null) {
            return;
        }
        $shop = Settings::get('shop_name');
        $text = "Vielen Dank für deine Bestellung bei " . $shop . "!\n\n"
              . "Bestellnummer: " . $bestellung['nummer'] . "\n"
              . "Datum: " . Util::dt((string) $bestellung['erstellt']) . "\n\n"
              . self::positionenText($bestellung)
              . "\nZahlart: " . Zahlung::name((string) $bestellung['zahlart']) . "\n";

        $hinweis = Zahlung::hinweis((string) $bestellung['zahlart']);
        if ($hinweis !== '') {
            $text .= $hinweis . "\n";
        }
        if ((string) $bestellung['zahlart'] === 'vorkasse' && Settings::get('bankverbindung') !== '') {
            $text .= "\nBankverbindung:\n" . Settings::get('bankverbindung')
                   . "\nVerwendungszweck: Bestellung " . $bestellung['nummer'] . "\n";
        }

        $text .= "\nLieferadresse:\n" . Kunden::adresseText($bestellung['lieferadresse_daten'])
              . "\n\nDeine Bestellung ansehen:\n" . Config::url('bestellung.php?t=' . $bestellung['token'])
              . "\n\n" . $shop . "\n";

        self::senden((string) $bestellung['email'], 'Deine Bestellung ' . $bestellung['nummer'] . ' bei ' . $shop, $text);

        if (Settings::bool('mail_bestellung_an_betreiber')) {
            $an = Settings::get('shop_email') ?: Settings::get('mail_absender');
            if ($an !== '') {
                self::senden($an, 'Neue Bestellung ' . $bestellung['nummer'] . ' (' . Util::geld((int) $bestellung['gesamt']) . ')',
                    "Es ist eine neue Bestellung eingegangen.\n\n" . $text
                    . "\nIm Backend öffnen:\n" . Config::url('admin/bestellung.php?id=' . $bestellung['id']) . "\n");
            }
        }
    }

    public static function bestellungBezahlt(int $bestellungId): void
    {
        $bestellung = Bestellungen::holen($bestellungId);
        if ($bestellung === null || in_array((string) $bestellung['zahlart'], ['rechnung', 'nachnahme'], true)) {
            return;
        }
        self::senden(
            (string) $bestellung['email'],
            'Zahlungseingang zu Bestellung ' . $bestellung['nummer'],
            "Wir haben deine Zahlung erhalten – vielen Dank!\n\n"
            . "Bestellnummer: " . $bestellung['nummer'] . "\n"
            . "Betrag: " . Util::geld((int) $bestellung['gesamt']) . "\n\n"
            . "Wir bereiten deine Bestellung jetzt für den Versand vor.\n\n"
            . Settings::get('shop_name') . "\n"
        );
    }

    public static function bestellungVersendet(int $bestellungId, string $dienstleister = '', string $nummer = '', string $url = ''): void
    {
        $bestellung = Bestellungen::holen($bestellungId);
        if ($bestellung === null) {
            return;
        }
        $text = "Deine Bestellung " . $bestellung['nummer'] . " ist unterwegs.\n\n";
        if ($nummer !== '') {
            $text .= "Versanddienstleister: " . $dienstleister . "\n"
                   . "Sendungsnummer: " . $nummer . "\n";
            if ($url !== '') {
                $text .= "Sendung verfolgen: " . $url . "\n";
            }
            $text .= "\n";
        }
        $text .= "Lieferadresse:\n" . Kunden::adresseText($bestellung['lieferadresse_daten']) . "\n\n"
               . Settings::get('shop_name') . "\n";

        self::senden((string) $bestellung['email'], 'Deine Bestellung ' . $bestellung['nummer'] . ' ist unterwegs', $text);
    }

    private static function positionenText(array $bestellung): string
    {
        $zeilen = '';
        foreach ($bestellung['zeilen'] as $zeile) {
            $name = (string) $zeile['titel'];
            if ((string) $zeile['variante'] !== '' && (string) $zeile['variante'] !== 'Standard') {
                $name .= ' (' . $zeile['variante'] . ')';
            }
            $zeilen .= '  ' . $zeile['menge'] . '× ' . $name
                     . ' – ' . Util::geld((int) $zeile['gesamt']) . "\n";
        }

        $zeilen .= "\n  Zwischensumme: " . Util::geld((int) $bestellung['zwischensumme']) . "\n";
        if ((int) $bestellung['rabatt'] > 0) {
            $zeilen .= '  Rabatt' . ((string) $bestellung['rabattcode'] !== '' ? ' (' . $bestellung['rabattcode'] . ')' : '')
                     . ': -' . Util::geld((int) $bestellung['rabatt']) . "\n";
        }
        $zeilen .= '  Versand: ' . ((int) $bestellung['versandkosten'] === 0 ? 'kostenlos' : Util::geld((int) $bestellung['versandkosten'])) . "\n"
                 . '  Gesamt: ' . Util::geld((int) $bestellung['gesamt']) . "\n"
                 . '  darin enthaltene MwSt.: ' . Util::geld((int) $bestellung['steuer']) . "\n";
        return $zeilen;
    }

    /** Testmail aus den Einstellungen heraus. */
    public static function test(string $an): bool
    {
        return self::senden(
            $an,
            'Testmail von ' . Settings::get('shop_name'),
            "Diese Testmail bestätigt, dass der Mailversand deines Shops funktioniert.\n\n"
            . 'Versandart: ' . (Settings::get('mail_methode') === 'smtp' ? 'SMTP' : 'PHP mail()') . "\n"
            . 'Gesendet am ' . Util::dt(Util::now()) . "\n"
        );
    }
}
