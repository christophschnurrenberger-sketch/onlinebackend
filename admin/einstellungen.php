<?php
/**
 * Einstellungen.
 *
 * In Reiter geteilt, weil die Bereiche unterschiedliche Leute betreffen:
 * Stammdaten und Rechtliches den Inhaber, Zahlungen und Versand die
 * Buchhaltung.
 */

$seitentitel = 'Einstellungen';
$benoetigtesRecht = 'einstellen';
require __DIR__ . '/partials/header.php';

$reiter = [
    'shop'      => 'Shop-Daten',
    'kasse'     => 'Kasse',
    'zahlungen' => 'Zahlungen',
    'versand'   => 'Versand',
    'steuern'   => 'Steuern',
    'recht'     => 'Rechtliches',
    'mail'      => 'E-Mail',
];
$aktiv = Util::einesVon(Util::get('reiter', 'shop'), array_keys($reiter), 'shop');

/* --- Speichern ------------------------------------------------------------- */

if (Util::isPost()) {
    Auth::csrfPruefen();
    try {
        switch (Util::post('bereich')) {
            case 'shop':
                Settings::setMany([
                    'shop_name' => Util::post('shop_name'), 'shop_slogan' => Util::post('shop_slogan'),
                    'shop_beschreibung' => Util::post('shop_beschreibung'),
                    'shop_email' => Util::post('shop_email'), 'shop_telefon' => Util::post('shop_telefon'),
                    'waehrung' => Util::post('waehrung'), 'land' => mb_strtoupper(Util::post('land'), 'UTF-8'),
                    'logo_url' => Util::post('logo_url'), 'favicon_url' => Util::post('favicon_url'),
                    'firma' => Util::post('firma'), 'strasse' => Util::post('strasse'),
                    'plz' => Util::post('plz'), 'ort' => Util::post('ort'),
                    'social_instagram' => Util::post('social_instagram'),
                    'social_facebook' => Util::post('social_facebook'),
                    'lieferzeit' => Util::post('lieferzeit'),
                    // Unter 14 Tagen gibt es im Fernabsatz kein Widerrufsrecht.
                    'widerruf_tage' => (string) max(14, Util::postInt('widerruf_tage', 14)),
                    'siegel_bild' => Util::post('siegel_bild'),
                    'siegel_url' => Util::post('siegel_url'),
                    'siegel_text' => Util::post('siegel_text'),
                ]);
                break;

            case 'kasse':
                Settings::setMany([
                    'agb_pflicht' => Util::postBool('agb_pflicht') ? '1' : '0',
                    'telefon_pflicht' => Util::postBool('telefon_pflicht') ? '1' : '0',
                    'bestellnummer_start' => (string) Util::postInt('bestellnummer_start', 1000),
                    'mindestbestellwert' => (string) Util::centAus(Util::post('mindestbestellwert')),
                    'danke_text' => Util::postRaw('danke_text'),
                ]);
                break;

            case 'zahlungen':
                $aktive = [];
                foreach (array_keys(Zahlung::ARTEN) as $art) {
                    if (Util::postBool('an_' . $art) && Zahlung::einsatzbereit($art)) {
                        $aktive[] = $art;
                    }
                }
                $werte = ['zahlarten' => implode(',', $aktive), 'bankverbindung' => Util::postRaw('bankverbindung')];
                foreach (array_keys(Zahlung::ARTEN) as $art) {
                    $werte['zahlart_' . $art . '_name'] = Util::post('name_' . $art);
                    if (in_array($art, ['rechnung', 'vorkasse', 'nachnahme', 'test'], true)) {
                        $werte['zahlart_' . $art . '_text'] = Util::postRaw('text_' . $art);
                    }
                }
                // Geheimnisse nur überschreiben, wenn etwas eingegeben wurde –
                // die Felder zeigen den gespeicherten Wert nie an.
                foreach (['stripe_secret', 'stripe_public', 'stripe_webhook',
                          'paypal_id', 'paypal_secret', 'paypal_webhook'] as $feld) {
                    $eingabe = Util::postRaw($feld);
                    if ($eingabe !== '') {
                        $werte[$feld] = $eingabe;
                    }
                }
                $werte['paypal_modus'] = Util::einesVon(Util::post('paypal_modus'), ['sandbox', 'live'], 'sandbox');
                Settings::setMany($werte);
                break;

            case 'zone-neu':
                Versand::zoneAnlegen(Util::post('zone_name'), explode(',', Util::post('zone_laender')), [
                    ['name' => Util::post('art_name'), 'preis' => Util::post('art_preis'),
                     'lieferzeit' => Util::post('art_lieferzeit'), 'frei_ab' => Util::post('art_frei_ab')],
                ]);
                break;

            case 'zone-speichern':
                $zoneId = Util::postInt('zone_id');
                $arten  = [];
                foreach (Util::postArray('art_name') as $nr => $name) {
                    if (trim((string) $name) === '') {
                        continue;
                    }
                    $arten[] = [
                        'name'       => (string) $name,
                        'preis'      => (string) (Util::postArray('art_preis')[$nr] ?? '0'),
                        'lieferzeit' => (string) (Util::postArray('art_lieferzeit')[$nr] ?? ''),
                        'frei_ab'    => (string) (Util::postArray('art_frei_ab')[$nr] ?? ''),
                        'position'   => $nr,
                    ];
                }
                Versand::zoneSpeichern($zoneId, Util::post('zone_name'), explode(',', Util::post('zone_laender')), $arten);
                break;

            case 'zone-loeschen':
                Versand::zoneLoeschen(Util::postInt('zone_id'));
                break;

            case 'steuer-neu':
                Steuern::anlegen(Util::post('steuer_name'), Util::post('steuer_satz'),
                    Util::post('steuer_land', 'DE'), Util::postBool('steuer_standard'));
                break;

            case 'steuer-loeschen':
                Steuern::loeschen(Util::postInt('steuer_id'));
                break;

            case 'recht':
                Settings::setMany([
                    'seite_impressum' => Util::post('seite_impressum'),
                    'seite_datenschutz' => Util::post('seite_datenschutz'),
                    'seite_agb' => Util::post('seite_agb'),
                    'seite_widerruf' => Util::post('seite_widerruf'),
                    'seite_versand' => Util::post('seite_versand'),
                    'ust_id' => Util::post('ust_id'),
                    'handelsregister' => Util::post('handelsregister'),
                    'geschaeftsfuehrung' => Util::post('geschaeftsfuehrung'),
                ]);
                break;

            case 'mail':
                $werte = [
                    'mail_absender' => Util::post('mail_absender'),
                    'mail_absender_name' => Util::post('mail_absender_name'),
                    'mail_methode' => Util::einesVon(Util::post('mail_methode'), ['mail', 'smtp'], 'mail'),
                    'smtp_host' => Util::post('smtp_host'),
                    'smtp_port' => (string) Util::postInt('smtp_port', 587),
                    'smtp_user' => Util::post('smtp_user'),
                    'smtp_sicherheit' => Util::einesVon(Util::post('smtp_sicherheit'), ['tls', 'ssl', 'keine'], 'tls'),
                    'mail_bestellung_an_betreiber' => Util::postBool('mail_bestellung_an_betreiber') ? '1' : '0',
                ];
                if (Util::postRaw('smtp_pass') !== '') {
                    $werte['smtp_pass'] = Util::postRaw('smtp_pass');
                }
                Settings::setMany($werte);
                break;

            case 'mail-test':
                $erfolg = Mail::test(Util::post('test_an'));
                Util::redirect('einstellungen.php?reiter=mail&art=' . ($erfolg ? 'erfolg' : 'fehler')
                    . '&meldung=' . rawurlencode($erfolg
                        ? 'Testmail wurde versendet. Bitte den Posteingang prüfen.'
                        : 'Die Testmail konnte nicht gesendet werden – Einzelheiten stehen im Protokoll.'));
        }
        Util::redirect('einstellungen.php?reiter=' . rawurlencode(Util::post('reiter', $aktiv))
            . '&meldung=' . rawurlencode('Einstellungen gespeichert.'));
    } catch (Throwable $e) {
        echo '<div class="bk-hinweis bk-hinweis-fehler">' . Util::e($e->getMessage()) . '</div>';
    }
}

$e = static fn(string $k): string => Settings::get($k);
?>

<div class="bk-seitenkopf">
  <div class="bk-titel"><h1>Einstellungen</h1></div>
</div>

<section class="bk-karte">
  <div class="bk-reiter">
    <?php foreach ($reiter as $wert => $label): ?>
      <a href="einstellungen.php?reiter=<?= Util::e($wert) ?>" class="<?= $aktiv === $wert ? 'aktiv' : '' ?>">
        <?= Util::e($label) ?>
      </a>
    <?php endforeach; ?>
  </div>
  <div class="bk-karte-inhalt">
    <?php require __DIR__ . '/partials/einstellungen-' . $aktiv . '.php'; ?>
  </div>
</section>

<?php require __DIR__ . '/partials/footer.php'; ?>
