<?php
/**
 * Settings – alle inhaltlichen Einstellungen, gespeichert in der Datenbank.
 *
 * In config.php stehen nur die technischen Grunddaten. Alles, was der
 * Betreiber im Backend ändern kann – Shopname, Design, Zahlarten, Texte –
 * liegt hier und braucht keinen Dateizugriff.
 *
 * Werte werden beim ersten Zugriff einmal komplett geladen; eine Shopseite
 * fragt Dutzende Einstellungen ab, und das soll keine Dutzend Abfragen sein.
 */
final class Settings
{
    /** @var array<string,string>|null */
    private static ?array $cache = null;

    /**
     * Vorgaben. Ein hier neu ergänzter Schlüssel ist sofort verfügbar, ohne
     * dass die Datenbank angefasst werden muss.
     *
     * @var array<string,string>
     */
    public const VORGABEN = [
        // Shop
        'shop_name'        => 'Mein Shop',
        'shop_slogan'      => 'Willkommen im Shop',
        'shop_beschreibung' => 'Ein Shop, betrieben mit dem eigenen Shop-System.',
        'shop_email'       => '',
        'shop_telefon'     => '',
        'waehrung'         => 'EUR',
        'land'             => 'DE',
        'logo_url'         => '',
        'favicon_url'      => '',
        'firma'            => '',
        'strasse'          => '',
        'plz'              => '',
        'ort'              => '',
        'ust_id'           => '',
        'handelsregister'  => '',
        'geschaeftsfuehrung' => '',
        'social_instagram' => '',
        'social_facebook'  => '',

        // Design – wird als CSS-Variablen in jede Shopseite geschrieben
        'design_vorlage'   => 'basis',
        'farbe_hintergrund' => '#ffffff',
        'farbe_flaeche'    => '#f7f7f8',
        'farbe_text'       => '#16181d',
        'farbe_nebentext'  => '#6b7280',
        'farbe_rahmen'     => '#e5e7eb',
        'farbe_knopf'      => '#16181d',
        'farbe_knopf_text' => '#ffffff',
        'farbe_akzent'     => '#2f6f4f',
        'farbe_sale'       => '#c0392b',
        'schrift_titel'    => "'Helvetica Neue', Helvetica, Arial, sans-serif",
        'schrift_text'     => "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif",
        'ecken'            => '10px',
        'inhaltsbreite'    => '1200px',
        'artikel_pro_reihe' => '4',
        'hersteller_zeigen' => '0',
        'streichpreis_zeigen' => '1',
        'hinweisleiste'    => '',
        'hinweisleiste_an' => '0',
        // Servicezeile und Vorteilsleiste – im Fachhandel Standard über bzw.
        // unter dem Kopf. Die Texte stehen öffentlich im Shop: bitte nur
        // hineinschreiben, was auch stimmt.
        'servicezeile_an'  => '0',
        'servicezeile'     => 'Kundenservice Mo–Fr 9–17 Uhr',
        'vorteile_an'      => '1',
        'vorteil_1'        => 'Sichere Bezahlung',
        'vorteil_2'        => 'Schneller Versand',
        'vorteil_3'        => 'Rückgabe innerhalb der gesetzlichen Frist',
        'vorteil_4'        => '',
        'start_titel'      => 'Neu im Shop',
        'start_text'       => 'Handverlesen, sofort lieferbar.',
        'start_bild'       => '',
        'start_knopf'      => 'Jetzt entdecken',
        'start_knopf_url'  => 'kategorie.php?h=alle',
        'fusszeile_text'   => '',
        'eigenes_css'      => '',

        // Kasse
        'agb_pflicht'      => '1',
        'telefon_pflicht'  => '0',
        'preise_brutto'    => '1',
        'steuer_standard_bp' => '1900',
        'bestellnummer_start' => '1000',
        'mindestbestellwert' => '0',
        'danke_text'       => 'Du erhältst gleich eine Bestätigung per E-Mail. Wir melden uns, sobald die Sendung unterwegs ist.',

        // Zahlarten – aktiv sind die kommagetrennt aufgeführten
        'zahlarten'        => 'rechnung,vorkasse',
        'zahlart_rechnung_name' => 'Kauf auf Rechnung',
        'zahlart_rechnung_text' => 'Die Rechnung liegt der Sendung bei und ist innerhalb von 14 Tagen zahlbar.',
        'zahlart_vorkasse_name' => 'Vorkasse / Überweisung',
        'zahlart_vorkasse_text' => 'Wir senden dir die Bankverbindung per E-Mail. Die Ware geht nach Zahlungseingang raus.',
        'zahlart_nachnahme_name' => 'Nachnahme',
        'zahlart_nachnahme_text' => 'Du zahlst bei Lieferung an den Zusteller, zzgl. Nachnahmegebühr.',
        'zahlart_test_name'     => 'Testzahlung',
        'zahlart_test_text'     => 'Nur zum Ausprobieren – es wird kein Geld bewegt.',
        'zahlart_stripe_name'   => 'Kredit- / Debitkarte',
        'zahlart_paypal_name'   => 'PayPal',
        'bankverbindung'   => '',

        // Zugangsdaten der Zahlungsanbieter (verschlüsselt gespeichert)
        'stripe_secret'    => '',
        'stripe_public'    => '',
        'stripe_webhook'   => '',
        'paypal_id'        => '',
        'paypal_secret'    => '',
        'paypal_modus'     => 'sandbox',
        'paypal_webhook'   => '',

        // E-Mail-Versand
        'mail_absender_name' => '',
        'mail_absender'    => '',
        'mail_methode'     => 'mail',
        'smtp_host'        => '',
        'smtp_port'        => '587',
        'smtp_user'        => '',
        'smtp_pass'        => '',
        'smtp_sicherheit'  => 'tls',
        'mail_bestellung_an_betreiber' => '1',

        // Rechtstexte – Handles der zugehörigen Seiten
        'seite_impressum'  => 'impressum',
        'seite_datenschutz' => 'datenschutz',
        'seite_agb'        => 'agb',
        'seite_widerruf'   => 'widerruf',
        'seite_versand'    => 'versand',

        // Betriebsdaten
        'schema_version'   => '0',
        'zuletzt_veroeffentlicht' => '',
        'live_version'     => '0',
    ];

    /** Schlüssel, deren Wert verschlüsselt in der Datenbank liegt. */
    private const GEHEIM = ['stripe_secret', 'stripe_webhook', 'paypal_secret', 'paypal_webhook', 'smtp_pass'];

    /** @return array<string,string> */
    public static function all(bool $frisch = false): array
    {
        if ($frisch || self::$cache === null) {
            $werte = self::VORGABEN;
            try {
                foreach (DB::all('SELECT schluessel, wert FROM einstellungen') as $row) {
                    $werte[(string) $row['schluessel']] = (string) ($row['wert'] ?? '');
                }
            } catch (Throwable $e) {
                // Vor der Einrichtung existiert die Tabelle noch nicht.
            }
            self::$cache = $werte;
        }
        return self::$cache;
    }

    public static function get(string $key, ?string $default = null): string
    {
        $wert = self::all()[$key] ?? $default ?? (self::VORGABEN[$key] ?? '');
        if (in_array($key, self::GEHEIM, true) && $wert !== '') {
            try {
                return Util::decrypt($wert);
            } catch (Throwable $e) {
                return '';
            }
        }
        return (string) $wert;
    }

    public static function int(string $key, int $default = 0): int
    {
        $wert = self::get($key, (string) $default);
        return is_numeric($wert) ? (int) $wert : $default;
    }

    public static function bool(string $key): bool
    {
        return in_array(self::get($key), ['1', 'true', 'ja', 'on'], true);
    }

    /** @return array<int,string> Kommagetrennte Liste als Array. */
    public static function liste(string $key): array
    {
        $wert = trim(self::get($key));
        if ($wert === '') {
            return [];
        }
        return array_values(array_filter(array_map('trim', explode(',', $wert))));
    }

    public static function set(string $key, string $value): void
    {
        if (in_array($key, self::GEHEIM, true) && $value !== '') {
            $value = Util::encrypt($value);
        }
        $vorhanden = DB::value('SELECT COUNT(*) FROM einstellungen WHERE schluessel = ?', [$key]);
        if ((int) $vorhanden > 0) {
            DB::run('UPDATE einstellungen SET wert = ?, geaendert = ? WHERE schluessel = ?', [$value, Util::now(), $key]);
        } else {
            DB::run('INSERT INTO einstellungen (schluessel, wert, geaendert) VALUES (?, ?, ?)', [$key, $value, Util::now()]);
        }
        self::$cache = null;
    }

    /** @param array<string,string> $werte */
    public static function setMany(array $werte): void
    {
        DB::transaction(static function () use ($werte): void {
            foreach ($werte as $key => $value) {
                self::set($key, (string) $value);
            }
        });
    }

    /** Ist ein geheimer Wert hinterlegt, ohne ihn auszugeben? */
    public static function hatGeheimnis(string $key): bool
    {
        return trim((string) (self::all()[$key] ?? '')) !== '';
    }

    public static function cacheLeeren(): void
    {
        self::$cache = null;
    }
}
