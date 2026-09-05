<?php
/**
 * Zahlung – alle Zahlarten hinter einer gemeinsamen Schnittstelle.
 *
 * starten() gibt entweder 'fertig' (Bestellung kann sofort abgeschlossen
 * werden) oder 'weiterleiten' (Kunde geht zum Anbieter) zurück. Mehr Fälle
 * braucht es nicht, und die Kasse bleibt dadurch geradlinig.
 *
 * Zwei Regeln gelten für jeden Webhook:
 *   1. Signatur prüfen, bevor irgendetwas gebucht wird.
 *   2. Jedes Ereignis nur einmal verarbeiten – Anbieter liefern doppelt aus.
 */
final class Zahlung
{
    /** Alle Zahlarten mit ihrer Vorgabe-Beschriftung. */
    public const ARTEN = [
        'rechnung'  => 'Kauf auf Rechnung',
        'vorkasse'  => 'Vorkasse / Überweisung',
        'nachnahme' => 'Nachnahme',
        'stripe'    => 'Kredit- / Debitkarte',
        'paypal'    => 'PayPal',
        'test'      => 'Testzahlung',
    ];

    /** Zahlarten ohne Online-Abwicklung: Bestellung bleibt offen. */
    public const OFFLINE = ['rechnung', 'vorkasse', 'nachnahme'];

    public static function name(string $art): string
    {
        $eigener = Settings::get('zahlart_' . $art . '_name', '');
        return $eigener !== '' ? $eigener : (self::ARTEN[$art] ?? $art);
    }

    public static function hinweis(string $art): string
    {
        return Settings::get('zahlart_' . $art . '_text', '');
    }

    /** Ist die Zahlart technisch einsatzbereit (Zugangsdaten hinterlegt)? */
    public static function einsatzbereit(string $art): bool
    {
        return match ($art) {
            'stripe' => Settings::get('stripe_secret') !== '',
            'paypal' => Settings::get('paypal_id') !== '' && Settings::get('paypal_secret') !== '',
            default  => true,
        };
    }

    /**
     * Die an der Kasse anzubietenden Zahlarten: in den Einstellungen aktiviert
     * UND technisch einsatzbereit. Beides muss stimmen, sonst landet der Kunde
     * in einem Ablauf, den der Shop gar nicht bedienen kann.
     *
     * @return array<int,array{id:string,name:string,hinweis:string,weiterleitung:bool}>
     */
    public static function verfuegbare(): array
    {
        $out = [];
        foreach (Settings::liste('zahlarten') as $art) {
            if (!isset(self::ARTEN[$art]) || !self::einsatzbereit($art)) {
                continue;
            }
            $out[] = [
                'id'            => $art,
                'name'          => self::name($art),
                'hinweis'       => self::hinweis($art),
                'weiterleitung' => in_array($art, ['stripe', 'paypal'], true),
            ];
        }
        return $out;
    }

    public static function istVerfuegbar(string $art): bool
    {
        foreach (self::verfuegbare() as $zahlart) {
            if ($zahlart['id'] === $art) {
                return true;
            }
        }
        return false;
    }

    /* --------------------------------------------------------------- Starten */

    /**
     * Startet die Zahlung für eine Bestellung.
     *
     * @return array{aktion:string,url:string,status:string,referenz:string,text:string}
     */
    public static function starten(array $bestellung, string $art): array
    {
        $zurueck  = Config::url('zahlung.php?token=' . rawurlencode((string) $bestellung['token']));
        $abbruch  = Config::url('zahlung.php?abbruch=1&token=' . rawurlencode((string) $bestellung['token']));

        return match ($art) {
            'test'   => ['aktion' => 'fertig', 'url' => '', 'status' => 'bezahlt', 'referenz' => 'test_' . Util::token(8), 'text' => 'Testzahlung erfolgreich.'],
            'stripe' => self::stripeStarten($bestellung, $zurueck, $abbruch),
            'paypal' => self::paypalStarten($bestellung, $zurueck, $abbruch),
            default  => ['aktion' => 'fertig', 'url' => '', 'status' => 'offen', 'referenz' => '', 'text' => self::hinweis($art)],
        };
    }

    /**
     * Fragt den Zahlungsstatus beim Anbieter nach. Wird nach der Rückkehr des
     * Kunden aufgerufen – die bloße Rückkehr gilt nicht als Zahlungsnachweis,
     * sonst könnte jeder die Rückkehr-Adresse aufrufen und so tun, als hätte
     * er bezahlt.
     *
     * @return array{status:string,referenz:string}
     */
    public static function bestaetigen(array $bestellung): array
    {
        return match ((string) $bestellung['zahlart']) {
            'stripe' => self::stripeBestaetigen($bestellung),
            'paypal' => self::paypalBestaetigen($bestellung),
            'test'   => ['status' => 'bezahlt', 'referenz' => (string) $bestellung['zahlreferenz']],
            default  => ['status' => (string) $bestellung['zahlstatus'], 'referenz' => (string) $bestellung['zahlreferenz']],
        };
    }

    /** Erstattung beim Anbieter auslösen, sofern er das kann. */
    public static function erstatten(array $bestellung, int $betrag): string
    {
        return match ((string) $bestellung['zahlart']) {
            'stripe' => self::stripeErstatten($bestellung, $betrag),
            'paypal' => self::paypalErstatten($bestellung, $betrag),
            default  => '',
        };
    }

    public static function zahlungNotieren(int $bestellungId, string $art, string $referenz, int $betrag, string $status, array $rohdaten = []): void
    {
        DB::insert('zahlungen', [
            'bestellung_id' => $bestellungId,
            'anbieter'      => $art,
            'referenz'      => mb_substr($referenz, 0, 190, 'UTF-8'),
            'betrag'        => $betrag,
            'waehrung'      => Settings::get('waehrung', 'EUR'),
            'status'        => $status,
            'rohdaten'      => json_encode($rohdaten, JSON_UNESCAPED_UNICODE),
            'erstellt'      => Util::now(),
            'geaendert'     => Util::now(),
        ]);
    }

    /* ---------------------------------------------------------------- Stripe */

    private static function stripeStarten(array $bestellung, string $zurueck, string $abbruch): array
    {
        $positionen = DB::all('SELECT titel, menge FROM bestellzeilen WHERE bestellung_id = ?', [(int) $bestellung['id']]);
        $beschreibung = implode(', ', array_map(
            static fn($z) => $z['menge'] . '× ' . $z['titel'],
            array_slice($positionen, 0, 10)
        ));

        // Eine einzige Position mit dem Gesamtbetrag: Rabatte, Versand und
        // Steuern sind bereits verrechnet, und so kann die Summe bei Stripe
        // nicht um Rundungscent von unserer abweichen.
        $antwort = self::stripeAufruf('/checkout/sessions', [
            'mode'                 => 'payment',
            'client_reference_id'  => (string) $bestellung['id'],
            'customer_email'       => (string) $bestellung['email'],
            'success_url'          => $zurueck,
            'cancel_url'           => $abbruch,
            'line_items' => [[
                'quantity'   => 1,
                'price_data' => [
                    'currency'     => mb_strtolower((string) $bestellung['waehrung'], 'UTF-8'),
                    'unit_amount'  => (int) $bestellung['gesamt'],
                    'product_data' => [
                        'name'        => 'Bestellung ' . $bestellung['nummer'],
                        'description' => mb_substr($beschreibung, 0, 400, 'UTF-8') ?: 'Bestellung',
                    ],
                ],
            ]],
            'metadata' => ['bestellung_id' => (string) $bestellung['id']],
        ]);

        return [
            'aktion'   => 'weiterleiten',
            'url'      => (string) ($antwort['url'] ?? ''),
            'status'   => 'offen',
            'referenz' => (string) ($antwort['id'] ?? ''),
            'text'     => '',
        ];
    }

    private static function stripeBestaetigen(array $bestellung): array
    {
        $referenz = (string) $bestellung['zahlreferenz'];
        if ($referenz === '') {
            return ['status' => 'offen', 'referenz' => ''];
        }
        $sitzung = self::stripeAufruf('/checkout/sessions/' . rawurlencode($referenz), null, 'GET');
        $bezahlt = ($sitzung['payment_status'] ?? '') === 'paid';

        return [
            'status'   => $bezahlt ? 'bezahlt' : ((($sitzung['status'] ?? '') === 'expired') ? 'verfallen' : 'offen'),
            'referenz' => (string) ($sitzung['payment_intent'] ?? $referenz),
        ];
    }

    private static function stripeErstatten(array $bestellung, int $betrag): string
    {
        $referenz = (string) $bestellung['zahlreferenz'];
        if ($referenz === '') {
            throw new RuntimeException('An dieser Bestellung hängt keine Stripe-Referenz.');
        }
        // Kommt die Referenz noch von der Sitzung, holen wir den PaymentIntent nach.
        if (str_starts_with($referenz, 'cs_')) {
            $sitzung  = self::stripeAufruf('/checkout/sessions/' . rawurlencode($referenz), null, 'GET');
            $referenz = (string) ($sitzung['payment_intent'] ?? '');
        }
        $erstattung = self::stripeAufruf('/refunds', ['payment_intent' => $referenz, 'amount' => $betrag]);
        return (string) ($erstattung['id'] ?? '');
    }

    /** @param array<string,mixed>|null $daten */
    private static function stripeAufruf(string $pfad, ?array $daten = null, string $methode = 'POST'): array
    {
        $schluessel = Settings::get('stripe_secret');
        if ($schluessel === '') {
            throw new RuntimeException('Es ist kein Stripe-Schlüssel hinterlegt.');
        }
        $antwort = self::http(
            'https://api.stripe.com/v1' . $pfad,
            $methode,
            $daten === null ? null : http_build_query(self::flach($daten)),
            [
                'Authorization: Bearer ' . $schluessel,
                'Content-Type: application/x-www-form-urlencoded',
            ]
        );
        $json = json_decode($antwort['body'], true) ?: [];
        if ($antwort['status'] >= 400) {
            throw new RuntimeException('Stripe: ' . (string) ($json['error']['message'] ?? ('Fehler ' . $antwort['status'])));
        }
        return $json;
    }

    /** Verschachtelte Arrays in Stripes Klammer-Schreibweise bringen. */
    private static function flach(array $daten, string $praefix = ''): array
    {
        $out = [];
        foreach ($daten as $key => $wert) {
            $name = $praefix === '' ? (string) $key : $praefix . '[' . $key . ']';
            if (is_array($wert)) {
                $out += self::flach($wert, $name);
            } elseif ($wert !== null && $wert !== '') {
                $out[$name] = is_bool($wert) ? ($wert ? 'true' : 'false') : (string) $wert;
            }
        }
        return $out;
    }

    /**
     * Prüft die Stripe-Signatur. Ohne diese Prüfung könnte jeder mit einem POST
     * auf die Webhook-Adresse Bestellungen als bezahlt markieren.
     */
    public static function stripeSignaturPruefen(string $rohdaten, string $kopfzeile): bool
    {
        $geheim = Settings::get('stripe_webhook');
        if ($geheim === '' || $kopfzeile === '') {
            return false;
        }
        $teile = [];
        foreach (explode(',', $kopfzeile) as $stueck) {
            $paar = explode('=', trim($stueck), 2);
            if (count($paar) === 2) {
                $teile[$paar[0]] = $paar[1];
            }
        }
        $zeit      = (int) ($teile['t'] ?? 0);
        $signatur  = (string) ($teile['v1'] ?? '');
        if ($zeit === 0 || $signatur === '') {
            return false;
        }
        // Ereignisse älter als fünf Minuten sind Wiedereinspielungen.
        if (abs(time() - $zeit) > 300) {
            return false;
        }
        return hash_equals(hash_hmac('sha256', $zeit . '.' . $rohdaten, $geheim), $signatur);
    }

    /* ---------------------------------------------------------------- PayPal */

    private static function paypalHost(): string
    {
        return Settings::get('paypal_modus', 'sandbox') === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
    }

    private static function paypalToken(): string
    {
        static $cache = ['wert' => '', 'bis' => 0];
        if ($cache['wert'] !== '' && $cache['bis'] > time() + 60) {
            return $cache['wert'];
        }

        $id     = Settings::get('paypal_id');
        $secret = Settings::get('paypal_secret');
        if ($id === '' || $secret === '') {
            throw new RuntimeException('Es sind keine PayPal-Zugangsdaten hinterlegt.');
        }

        $antwort = self::http(
            self::paypalHost() . '/v1/oauth2/token',
            'POST',
            'grant_type=client_credentials',
            [
                'Authorization: Basic ' . base64_encode($id . ':' . $secret),
                'Content-Type: application/x-www-form-urlencoded',
            ]
        );
        $json = json_decode($antwort['body'], true) ?: [];
        if ($antwort['status'] >= 400 || empty($json['access_token'])) {
            throw new RuntimeException('PayPal-Anmeldung fehlgeschlagen: ' . (string) ($json['error_description'] ?? 'unbekannter Fehler'));
        }

        $cache = ['wert' => (string) $json['access_token'], 'bis' => time() + (int) ($json['expires_in'] ?? 3000)];
        return $cache['wert'];
    }

    private static function paypalAufruf(string $pfad, ?array $daten = null, string $methode = 'POST'): array
    {
        $antwort = self::http(
            self::paypalHost() . $pfad,
            $methode,
            $daten === null ? null : json_encode($daten, JSON_UNESCAPED_UNICODE),
            [
                'Authorization: Bearer ' . self::paypalToken(),
                'Content-Type: application/json',
            ]
        );
        $json = json_decode($antwort['body'], true) ?: [];
        if ($antwort['status'] >= 400) {
            throw new RuntimeException('PayPal: ' . (string) ($json['message'] ?? ('Fehler ' . $antwort['status'])));
        }
        return $json;
    }

    private static function paypalStarten(array $bestellung, string $zurueck, string $abbruch): array
    {
        $angelegt = self::paypalAufruf('/v2/checkout/orders', [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => (string) $bestellung['id'],
                'custom_id'    => (string) $bestellung['id'],
                'description'  => 'Bestellung ' . $bestellung['nummer'],
                'amount' => [
                    'currency_code' => (string) $bestellung['waehrung'],
                    'value'         => number_format((int) $bestellung['gesamt'] / 100, 2, '.', ''),
                ],
            ]],
            'payment_source' => ['paypal' => ['experience_context' => [
                'user_action' => 'PAY_NOW',
                'return_url'  => $zurueck,
                'cancel_url'  => $abbruch,
            ]]],
        ]);

        $url = '';
        foreach ((array) ($angelegt['links'] ?? []) as $link) {
            if (in_array((string) ($link['rel'] ?? ''), ['approve', 'payer-action'], true)) {
                $url = (string) $link['href'];
                break;
            }
        }
        if ($url === '') {
            throw new RuntimeException('PayPal hat keine Weiterleitungsadresse geliefert.');
        }

        return ['aktion' => 'weiterleiten', 'url' => $url, 'status' => 'offen', 'referenz' => (string) ($angelegt['id'] ?? ''), 'text' => ''];
    }

    private static function paypalBestaetigen(array $bestellung): array
    {
        $referenz = (string) $bestellung['zahlreferenz'];
        if ($referenz === '') {
            return ['status' => 'offen', 'referenz' => ''];
        }

        $aktuell = self::paypalAufruf('/v2/checkout/orders/' . rawurlencode($referenz), null, 'GET');
        $status  = (string) ($aktuell['status'] ?? '');

        if ($status === 'COMPLETED') {
            return ['status' => 'bezahlt', 'referenz' => $referenz];
        }
        if ($status !== 'APPROVED') {
            return ['status' => $status === 'VOIDED' ? 'verfallen' : 'offen', 'referenz' => $referenz];
        }

        // Erst der erfolgreiche Einzug macht die Bestellung bezahlt.
        $eingezogen = self::paypalAufruf('/v2/checkout/orders/' . rawurlencode($referenz) . '/capture');
        $capture    = $eingezogen['purchase_units'][0]['payments']['captures'][0]['id'] ?? $referenz;

        return [
            'status'   => (string) ($eingezogen['status'] ?? '') === 'COMPLETED' ? 'bezahlt' : 'offen',
            'referenz' => (string) $capture,
        ];
    }

    private static function paypalErstatten(array $bestellung, int $betrag): string
    {
        $referenz = (string) $bestellung['zahlreferenz'];
        if ($referenz === '') {
            throw new RuntimeException('An dieser Bestellung hängt keine PayPal-Referenz.');
        }
        $ergebnis = self::paypalAufruf('/v2/payments/captures/' . rawurlencode($referenz) . '/refund', [
            'amount' => [
                'currency_code' => (string) $bestellung['waehrung'],
                'value'         => number_format($betrag / 100, 2, '.', ''),
            ],
        ]);
        return (string) ($ergebnis['id'] ?? '');
    }

    /** PayPal signiert asymmetrisch; die Prüfung läuft über einen Rückruf zu PayPal. */
    public static function paypalSignaturPruefen(string $rohdaten, array $kopfzeilen): bool
    {
        $webhookId = Settings::get('paypal_webhook');
        if ($webhookId === '') {
            return false;
        }
        try {
            $ergebnis = self::paypalAufruf('/v1/notifications/verify-webhook-signature', [
                'auth_algo'         => $kopfzeilen['paypal-auth-algo'] ?? '',
                'cert_url'          => $kopfzeilen['paypal-cert-url'] ?? '',
                'transmission_id'   => $kopfzeilen['paypal-transmission-id'] ?? '',
                'transmission_sig'  => $kopfzeilen['paypal-transmission-sig'] ?? '',
                'transmission_time' => $kopfzeilen['paypal-transmission-time'] ?? '',
                'webhook_id'        => $webhookId,
                'webhook_event'     => json_decode($rohdaten, true),
            ]);
            return (string) ($ergebnis['verification_status'] ?? '') === 'SUCCESS';
        } catch (Throwable $e) {
            Log::warn('zahlung', 'PayPal-Signaturprüfung fehlgeschlagen: ' . $e->getMessage());
            return false;
        }
    }

    /* ----------------------------------------------------------------- HTTP */

    /**
     * Ein HTTP-Aufruf – bevorzugt über cURL, sonst über Streams. Manche
     * günstigen Hoster haben cURL nicht aktiviert; ohne den Rückfallweg wäre
     * dort keine Onlinezahlung möglich.
     *
     * @return array{status:int,body:string}
     */
    private static function http(string $url, string $methode, ?string $body, array $kopfzeilen): array
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST  => $methode,
                CURLOPT_HTTPHEADER     => $kopfzeilen,
                CURLOPT_TIMEOUT        => 30,
                CURLOPT_SSL_VERIFYPEER => true,
            ]);
            if ($body !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            }
            $antwort = curl_exec($ch);
            $status  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $fehler  = curl_error($ch);
            curl_close($ch);

            if ($antwort === false) {
                throw new RuntimeException('Verbindung fehlgeschlagen: ' . $fehler);
            }
            return ['status' => $status, 'body' => (string) $antwort];
        }

        $kontext = stream_context_create(['http' => [
            'method'        => $methode,
            'header'        => implode("\r\n", $kopfzeilen),
            'content'       => $body ?? '',
            'timeout'       => 30,
            'ignore_errors' => true,
        ]]);
        $antwort = @file_get_contents($url, false, $kontext);
        if ($antwort === false) {
            throw new RuntimeException('Verbindung zum Zahlungsanbieter fehlgeschlagen. '
                . 'Bitte prüfen, ob der Server ausgehende HTTPS-Verbindungen erlaubt.');
        }
        $status = 200;
        foreach ($http_response_header ?? [] as $kopf) {
            if (preg_match('#^HTTP/\S+\s+(\d+)#', $kopf, $treffer)) {
                $status = (int) $treffer[1];
            }
        }
        return ['status' => $status, 'body' => (string) $antwort];
    }
}
