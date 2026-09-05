<?php
/** Sitemap für Suchmaschinen – aus der veröffentlichten Fassung. */

require __DIR__ . '/lib/bootstrap.php';

$fassung = Veroeffentlichung::live();
if ($fassung === null) {
    http_response_code(503);
    exit;
}

header('Content-Type: application/xml; charset=utf-8');

$adressen = [Config::url()];
$adressen[] = Config::url('kategorien.php');
$adressen[] = Config::url('journal.php');
foreach ($fassung['kategorien'] as $k) {
    $adressen[] = Config::url('kategorie.php?h=' . rawurlencode((string) $k['handle']));
}
foreach ($fassung['artikel'] as $a) {
    $adressen[] = Config::url('artikel.php?h=' . rawurlencode((string) $a['handle']));
}
foreach ($fassung['seiten'] as $s) {
    $adressen[] = Config::url('seite.php?h=' . rawurlencode((string) $s['handle']));
}
foreach ($fassung['beitraege'] as $b) {
    $adressen[] = Config::url('journal.php?h=' . rawurlencode((string) $b['handle']));
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($adressen as $adresse) {
    echo '  <url><loc>' . Util::e($adresse) . '</loc></url>' . "\n";
}
echo '</urlset>';
