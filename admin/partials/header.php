<?php
/**
 * Kopf aller Backend-Seiten: Anmeldung erzwingen, Navigation, Meldungen.
 *
 * Vor dem Einbinden setzen:
 *   $seitentitel  – erscheint im Browser-Tab
 *   $benoetigtesRecht – 'lesen' (Vorgabe), 'pflegen' oder 'einstellen'
 */

require_once dirname(__DIR__, 2) . '/lib/bootstrap.php';

$benutzer     = Auth::verlangen($benoetigtesRecht ?? 'lesen');
$seitentitel  = $seitentitel ?? 'Übersicht';
$aktuelleDatei = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));

/* Zahlen für die Navigation und die Kopfzeile. */
$offeneBestellungen = (int) DB::value("SELECT COUNT(*) FROM bestellungen WHERE status = 'offen' AND versandstatus != 'versendet'", [], 0);
$offeneAenderungen  = Veroeffentlichung::offeneAenderungen();
$zuVeroeffentlichen = $offeneAenderungen['nie'] ? 1 : $offeneAenderungen['anzahl'];

/**
 * Ein Icon je Navigationspunkt – schlanke Strichzeichnungen als Inline-SVG,
 * ganz ohne Fremdpaket. currentColor lässt sie die Farbe des Eintrags erben.
 */
function admin_icon(string $datei): string
{
    $pfade = [
        'index.php'            => '<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/>',
        'bestellungen.php'     => '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 9h18M8 13h8M8 16.5h5"/>',
        'artikel.php'          => '<rect x="3" y="3" width="7.5" height="7.5" rx="1.5"/><rect x="13.5" y="3" width="7.5" height="7.5" rx="1.5"/><rect x="3" y="13.5" width="7.5" height="7.5" rx="1.5"/><rect x="13.5" y="13.5" width="7.5" height="7.5" rx="1.5"/>',
        'kunden.php'           => '<circle cx="9" cy="8" r="3.2"/><path d="M3.5 20a5.5 5.5 0 0 1 11 0"/><path d="M16 6.2a3 3 0 0 1 0 5.6M20.5 20a5 5 0 0 0-4-4.9"/>',
        'rabatte.php'          => '<path d="m19 5-14 14"/><circle cx="7.5" cy="7.5" r="2"/><circle cx="16.5" cy="16.5" r="2"/>',
        'kategorien.php'       => '<path d="M8 6h13M8 12h13M8 18h13"/><path d="M3.5 6h.01M3.5 12h.01M3.5 18h.01"/>',
        'bestand.php'          => '<path d="M3 7.5 12 3l9 4.5v9L12 21l-9-4.5v-9Z"/><path d="m3 7.5 9 4.5 9-4.5M12 12v9"/>',
        'design.php'           => '<circle cx="12" cy="12" r="9"/><path d="M12 3a9 9 0 0 0 0 18c1.5 0 2-1 2-2s-.8-1.5-.8-2.5S14 13 15.5 13H18a3 3 0 0 0 3-3"/>',
        'seiten.php'           => '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8Z"/><path d="M14 3v5h5M9 13h6M9 17h4"/>',
        'journal.php'          => '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/>',
        'navigation.php'       => '<path d="M4 6h16M4 12h10M4 18h13"/>',
        'veroeffentlichen.php' => '<path d="M12 19V5"/><path d="m5 12 7-7 7 7"/>',
        'einstellungen.php'    => '<circle cx="12" cy="12" r="3.2"/><path d="M19.5 12a7.5 7.5 0 0 0-.1-1.2l2-1.6-2-3.4-2.4 1a7.5 7.5 0 0 0-2-1.2L14.6 3H9.4L9 5.6a7.5 7.5 0 0 0-2 1.2l-2.4-1-2 3.4 2 1.6a7.5 7.5 0 0 0 0 2.4l-2 1.6 2 3.4 2.4-1a7.5 7.5 0 0 0 2 1.2l.4 2.6h5.2l.4-2.6a7.5 7.5 0 0 0 2-1.2l2.4 1 2-3.4-2-1.6c.07-.4.1-.8.1-1.2Z"/>',
        'benutzer.php'         => '<circle cx="12" cy="8" r="3.4"/><path d="M5.5 20a6.5 6.5 0 0 1 13 0"/>',
        'protokoll.php'        => '<path d="M3 12h4l2.5 7 5-16L17 12h4"/>',
    ];
    $d = $pfade[$datei] ?? '<circle cx="12" cy="12" r="9"/>';
    return '<svg class="ad-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" '
         . 'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" width="18" height="18">' . $d . '</svg>';
}

function admin_link(string $datei, string $beschriftung, int $zaehler = 0): void
{
    $aktiv = basename((string) ($_SERVER['SCRIPT_NAME'] ?? '')) === $datei;
    echo '<a class="ad-link' . ($aktiv ? ' aktiv' : '') . '" href="' . Util::e($datei) . '">'
       . admin_icon($datei) . Util::e($beschriftung)
       . ($zaehler > 0 ? '<span class="ad-zaehler">' . $zaehler . '</span>' : '')
       . '</a>';
}

/* Meldungen zwischen zwei Seitenaufrufen – Muster "nach dem Speichern umleiten". */
$meldung    = Util::get('meldung');
$meldungArt = Util::einesVon(Util::get('art', 'erfolg'), ['erfolg', 'fehler', 'warnung', 'info'], 'erfolg');
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?= Util::e($seitentitel) ?> – Backend</title>
<link rel="stylesheet" href="assets/admin.css">
</head>
<body>
<div class="ad-layout">

  <aside class="ad-nav" id="seitenleiste">
    <div class="ad-shop">
      <div class="ad-shop-zeichen"><?= Util::e(mb_strtoupper(mb_substr(Settings::get('shop_name', 'S'), 0, 1, 'UTF-8'), 'UTF-8')) ?></div>
      <div style="min-width:0">
        <div class="ad-shop-name"><?= Util::e(Settings::get('shop_name')) ?></div>
        <div class="ad-shop-rolle"><?= Util::e((string) ($benutzer['name'] ?: $benutzer['email'])) ?></div>
      </div>
    </div>

    <div class="ad-gruppe">
      <?php
      admin_link('index.php', 'Übersicht');
      admin_link('bestellungen.php', 'Bestellungen', $offeneBestellungen);
      admin_link('artikel.php', 'Artikel');
      admin_link('kunden.php', 'Kunden');
      admin_link('rabatte.php', 'Rabatte');
      ?>
    </div>

    <div class="ad-gruppe">
      <div class="ad-gruppe-titel">Katalog</div>
      <?php
      admin_link('kategorien.php', 'Kategorien');
      admin_link('bestand.php', 'Bestand');
      ?>
    </div>

    <div class="ad-gruppe">
      <div class="ad-gruppe-titel">Onlineshop</div>
      <?php
      admin_link('design.php', 'Design');
      admin_link('seiten.php', 'Seiten');
      admin_link('journal.php', 'Journal');
      admin_link('navigation.php', 'Navigation');
      admin_link('veroeffentlichen.php', 'Veröffentlichen', $zuVeroeffentlichen);
      ?>
    </div>

    <div class="ad-gruppe">
      <?php
      admin_link('einstellungen.php', 'Einstellungen');
      if (Auth::darf('einstellen')) {
          admin_link('benutzer.php', 'Benutzer');
      }
      admin_link('protokoll.php', 'Protokoll');
      ?>
    </div>

    <div class="ad-fueller"></div>
    <div class="ad-nav-fuss">
      <a href="<?= Util::e(Config::baseUrl()) ?>/" target="_blank" rel="noopener">Shop ansehen ↗</a><br>
      <a href="abmelden.php">Abmelden</a><br>
      <span style="font-size:11px">Fassung <?= Util::e(SHOP_VERSION) ?></span>
    </div>
  </aside>

  <div>
    <header class="ad-kopf">
      <button class="ad-knopf ad-knopf-leer ad-knopf-klein ad-menue-schalter" type="button"
              onclick="document.getElementById('seitenleiste').classList.toggle('offen')" aria-label="Menü">☰</button>
      <form class="ad-suche" action="artikel.php">
        <input type="search" name="suche" placeholder="Artikel suchen …" value="<?= Util::e(Util::get('suche')) ?>">
      </form>
      <div class="ad-kopf-rechts">
        <div class="ad-stand">
          <?php if ($zuVeroeffentlichen > 0): ?>
            <strong><?= $zuVeroeffentlichen ?> Änderung<?= $zuVeroeffentlichen === 1 ? '' : 'en' ?> offen</strong>
          <?php else: ?>
            Alles veröffentlicht
          <?php endif; ?>
          <br><?php
            $zuletzt = Settings::get('zuletzt_veroeffentlicht');
            echo $zuletzt !== '' ? 'zuletzt ' . Util::e(Util::seit($zuletzt)) : 'noch nie veröffentlicht';
          ?>
        </div>
        <a class="ad-knopf ad-knopf-gruen" href="veroeffentlichen.php">Veröffentlichen</a>
      </div>
    </header>

    <main class="ad-inhalt">
      <?php if (is_file(dirname(__DIR__, 2) . '/install.php')): ?>
        <div class="ad-hinweis ad-hinweis-warnung">
          <strong>Bitte install.php vom Server löschen.</strong>
          Solange die Datei erreichbar ist, könnte jemand den Shop neu einrichten.
        </div>
      <?php endif; ?>

      <?php if ($meldung !== ''): ?>
        <div class="ad-hinweis ad-hinweis-<?= Util::e($meldungArt) ?>"><?= Util::e($meldung) ?></div>
      <?php endif; ?>
