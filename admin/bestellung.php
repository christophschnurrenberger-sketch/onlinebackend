<?php
/**
 * Eine Bestellung bearbeiten.
 *
 * Als Arbeitsfläche gedacht: bezahlt markieren, versenden, erstatten,
 * stornieren – jeweils ein Klick, mit dem Ereignisverlauf daneben, damit
 * jederzeit klar ist, was passiert ist.
 */

$seitentitel = 'Bestellung';
require __DIR__ . '/partials/header.php';

$id = Util::getInt('id');

if (Util::isPost() && Auth::darf('pflegen')) {
    Auth::csrfPruefen();
    $benutzerId = (int) $benutzer['id'];
    try {
        switch (Util::post('aktion')) {
            case 'bezahlt':
                Bestellungen::alsBezahltMarkieren($id, Util::post('referenz'), $benutzerId);
                $rueckmeldung = 'Als bezahlt markiert.';
                break;

            case 'versenden':
                Bestellungen::versenden(
                    $id, [],
                    Util::post('dienstleister'),
                    Util::post('sendungsnummer'),
                    Util::post('sendung_url'),
                    $benutzerId
                );
                $rueckmeldung = 'Sendung angelegt.';
                break;

            case 'erstatten':
                $betrag = Util::centAus(Util::post('betrag'));
                $referenz = '';
                // Bei Stripe und PayPal die Erstattung dort auslösen; die
                // Dokumentation im Shop passiert in jedem Fall.
                try {
                    $bestellung = Bestellungen::holen($id);
                    if ($bestellung !== null && (string) $bestellung['zahlstatus'] === 'bezahlt') {
                        $referenz = Zahlung::erstatten($bestellung, $betrag);
                    }
                } catch (Throwable $e) {
                    Bestellungen::ereignis($id, 'erstattungsfehler',
                        'Erstattung beim Anbieter fehlgeschlagen: ' . $e->getMessage(), $benutzerId);
                }
                Bestellungen::erstatten($id, $betrag, Util::post('grund'), $referenz, $benutzerId);
                if (Util::postBool('zurueck_ins_lager')) {
                    $voll = Bestellungen::holen($id);
                    Bestand::freigeben($voll['zeilen'], $id, 'retoure', $benutzerId);
                }
                $rueckmeldung = 'Erstattung erfasst.';
                break;

            case 'stornieren':
                Bestellungen::stornieren($id, Util::post('grund'), Util::postBool('zurueck_ins_lager'), $benutzerId);
                $rueckmeldung = 'Bestellung storniert.';
                break;

            case 'archivieren':
                Bestellungen::archivieren($id, Util::post('wert') === '1', $benutzerId);
                $rueckmeldung = 'Status geändert.';
                break;

            case 'notiz':
                Bestellungen::notizSetzen($id, Util::postRaw('notiz'), $benutzerId);
                $rueckmeldung = 'Notiz gespeichert.';
                break;

            default:
                $rueckmeldung = '';
        }
        Util::redirect('bestellung.php?id=' . $id . ($rueckmeldung !== '' ? '&meldung=' . rawurlencode($rueckmeldung) : ''));
    } catch (Throwable $e) {
        echo '<div class="ad-hinweis ad-hinweis-fehler">' . Util::e($e->getMessage()) . '</div>';
    }
}

$bestellung = Bestellungen::holen($id);
if ($bestellung === null) {
    Util::redirect('bestellungen.php?meldung=' . rawurlencode('Bestellung nicht gefunden.') . '&art=fehler');
}

$liefer   = $bestellung['lieferadresse_daten'];
$rechnung = $bestellung['rechnungsadresse_daten'];
$offen    = (int) $bestellung['gesamt'] - (int) $bestellung['erstattet'];
$hatOffeneZeilen = false;
foreach ($bestellung['zeilen'] as $zeile) {
    if ((int) $zeile['versandpflicht'] === 1 && (int) $zeile['versendet'] < (int) $zeile['menge']) {
        $hatOffeneZeilen = true;
        break;
    }
}
$storniert = (string) $bestellung['status'] === 'storniert';
?>

<div class="ad-seitenkopf">
  <div class="ad-titel">
    <a class="ad-zurueck" href="bestellungen.php">← Alle Bestellungen</a>
    <h1>Bestellung #<?= (int) $bestellung['nummer'] ?></h1>
    <div class="ad-untertitel">
      <?= Util::e(Util::dt((string) $bestellung['erstellt'])) ?> · <?= Util::e((string) $bestellung['email']) ?>
    </div>
    <div style="margin-top:8px;display:flex;gap:6px;flex-wrap:wrap">
      <?php require __DIR__ . '/partials/zahlmarke.php'; ?>
      <span class="ad-marke <?= (string) $bestellung['versandstatus'] === 'versendet' ? 'ad-marke-gruen' : '' ?>">
        <i></i><?= Util::e(Bestellungen::VERSANDSTATUS[(string) $bestellung['versandstatus']] ?? '') ?>
      </span>
      <?php if ($storniert): ?><span class="ad-marke ad-marke-rot"><i></i>Storniert</span><?php endif; ?>
      <?php if ((string) $bestellung['status'] === 'archiviert'): ?><span class="ad-marke"><i></i>Archiviert</span><?php endif; ?>
    </div>
  </div>
  <div class="ad-aktionen">
    <a class="ad-knopf" target="_blank" rel="noopener"
       href="<?= Util::e(Config::url('bestellung.php?t=' . rawurlencode((string) $bestellung['token']))) ?>">Kundenansicht ↗</a>
    <button class="ad-knopf" type="button" onclick="window.print()">Drucken</button>
    <?php if (!$storniert && Auth::darf('pflegen')): ?>
      <form method="post" style="display:inline">
        <?= Auth::csrfFeld() ?>
        <input type="hidden" name="aktion" value="archivieren">
        <input type="hidden" name="wert" value="<?= (string) $bestellung['status'] === 'archiviert' ? '0' : '1' ?>">
        <button class="ad-knopf" type="submit">
          <?= (string) $bestellung['status'] === 'archiviert' ? 'Wieder öffnen' : 'Archivieren' ?>
        </button>
      </form>
    <?php endif; ?>
  </div>
</div>

<div class="ad-zwei">
  <div>
    <section class="ad-karte">
      <div class="ad-karte-kopf"><h2>Positionen</h2></div>
      <div class="ad-karte-inhalt eng">
        <div class="ad-tabelle-rahmen"><table><tbody>
          <?php foreach ($bestellung['zeilen'] as $zeile): ?>
            <tr>
              <td class="ad-bild-zelle">
                <?php if ((string) $zeile['bild_url'] !== ''): ?>
                  <img class="ad-mini" src="<?= Util::e(Theme::url((string) $zeile['bild_url'])) ?>" alt="">
                <?php else: ?>
                  <div class="ad-mini ad-mini-leer">▦</div>
                <?php endif; ?>
              </td>
              <td>
                <div class="ad-haupt"><?= Util::e((string) $zeile['titel']) ?></div>
                <div class="ad-neben">
                  <?php
                  $teile = [];
                  if ((string) $zeile['variante'] !== '' && (string) $zeile['variante'] !== 'Standard') {
                      $teile[] = (string) $zeile['variante'];
                  }
                  if ((string) $zeile['artikelnummer'] !== '') {
                      $teile[] = (string) $zeile['artikelnummer'];
                  }
                  $teile[] = (int) $zeile['versendet'] > 0
                      ? (int) $zeile['versendet'] . ' von ' . (int) $zeile['menge'] . ' versendet'
                      : (int) $zeile['menge'] . ' Stück';
                  echo Util::e(implode(' · ', $teile));
                  ?>
                </div>
              </td>
              <td class="ad-zahl-rechts ad-neben">
                <?= Util::e(Util::geld((int) $zeile['preis'])) ?> × <?= (int) $zeile['menge'] ?>
              </td>
              <td class="ad-zahl-rechts">
                <strong><?= Util::e(Util::geld((int) $zeile['gesamt'])) ?></strong>
                <?php if ((int) $zeile['rabatt'] > 0): ?>
                  <div class="ad-neben">−<?= Util::e(Util::geld((int) $zeile['rabatt'])) ?></div>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody></table></div>
      </div>

      <div class="ad-karte-inhalt" style="border-top:1px solid var(--rahmen)">
        <?php
        $summen = [
            'Zwischensumme' => Util::geld((int) $bestellung['zwischensumme']),
        ];
        if ((int) $bestellung['rabatt'] > 0) {
            $summen['Rabatt ' . $bestellung['rabattcode']] = '−' . Util::geld((int) $bestellung['rabatt']);
        }
        $summen['Versand' . ((string) $bestellung['versandart'] !== '' ? ' (' . $bestellung['versandart'] . ')' : '')]
            = Util::geld((int) $bestellung['versandkosten']);
        foreach ($summen as $name => $wert): ?>
          <div style="display:flex;justify-content:space-between;padding:4px 0">
            <span><?= Util::e($name) ?></span><span><?= Util::e($wert) ?></span>
          </div>
        <?php endforeach; ?>
        <div style="display:flex;justify-content:space-between;padding:8px 0;margin-top:6px;
                    border-top:1px solid var(--rahmen);font-weight:700;font-size:16px">
          <span>Gesamt</span><span><?= Util::e(Util::geld((int) $bestellung['gesamt'])) ?></span>
        </div>
        <div style="display:flex;justify-content:space-between;padding:2px 0;color:var(--nebentext);font-size:13px">
          <span>darin enthaltene MwSt.</span><span><?= Util::e(Util::geld((int) $bestellung['steuer'])) ?></span>
        </div>
        <?php if ((int) $bestellung['erstattet'] > 0): ?>
          <div style="display:flex;justify-content:space-between;padding:2px 0;color:var(--rot);font-size:13px">
            <span>Erstattet</span><span>−<?= Util::e(Util::geld((int) $bestellung['erstattet'])) ?></span>
          </div>
        <?php endif; ?>
      </div>

      <?php if (!$storniert && Auth::darf('pflegen')): ?>
        <div class="ad-karte-fuss">
          <?php if ((string) $bestellung['zahlstatus'] === 'offen'): ?>
            <button class="ad-knopf ad-knopf-voll" type="button"
                    onclick="document.getElementById('bezahlt-form').hidden=false;this.hidden=true">
              Als bezahlt markieren
            </button>
          <?php endif; ?>
          <?php if ($hatOffeneZeilen): ?>
            <button class="ad-knopf ad-knopf-voll" type="button"
                    onclick="document.getElementById('versand-form').hidden=false;this.hidden=true">Versenden</button>
          <?php endif; ?>
          <?php if ($offen > 0 && (string) $bestellung['zahlstatus'] !== 'offen'): ?>
            <button class="ad-knopf" type="button"
                    onclick="document.getElementById('erstatten-form').hidden=false;this.hidden=true">Erstatten</button>
          <?php endif; ?>
          <button class="ad-knopf ad-knopf-rot" type="button"
                  onclick="document.getElementById('storno-form').hidden=false;this.hidden=true">Stornieren</button>
        </div>

        <div class="ad-karte-inhalt" id="bezahlt-form" hidden>
          <form method="post">
            <?= Auth::csrfFeld() ?>
            <input type="hidden" name="aktion" value="bezahlt">
            <p class="ad-tipp">Die Bestellung wird auf „bezahlt“ gesetzt. Beim Zahlungsanbieter
              passiert dabei nichts – das ist für Vorkasse, Rechnung und Überweisungen gedacht.</p>
            <div class="ad-feld">
              <label for="referenz">Referenz (optional)</label>
              <input type="text" id="referenz" name="referenz" placeholder="z. B. Verwendungszweck oder Beleg-Nr.">
            </div>
            <button class="ad-knopf ad-knopf-voll" type="submit">Als bezahlt markieren</button>
          </form>
        </div>

        <div class="ad-karte-inhalt" id="versand-form" hidden>
          <form method="post">
            <?= Auth::csrfFeld() ?>
            <input type="hidden" name="aktion" value="versenden">
            <div class="ad-feldzeile">
              <div class="ad-feld">
                <label for="dienstleister">Versanddienstleister</label>
                <select id="dienstleister" name="dienstleister">
                  <?php foreach (['DHL', 'DPD', 'Hermes', 'GLS', 'UPS', 'Deutsche Post', ''] as $d): ?>
                    <option value="<?= Util::e($d) ?>"><?= Util::e($d ?: 'Anderer / kein Tracking') ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="ad-feld">
                <label for="sendungsnummer">Sendungsnummer</label>
                <input type="text" id="sendungsnummer" name="sendungsnummer">
              </div>
            </div>
            <div class="ad-feld">
              <label for="sendung_url">Tracking-Link (optional)</label>
              <input type="url" id="sendung_url" name="sendung_url">
            </div>
            <p class="ad-tipp">Alle noch offenen Positionen werden als versendet markiert.
              Der Kunde bekommt eine E-Mail.</p>
            <button class="ad-knopf ad-knopf-voll" type="submit">Als versendet markieren</button>
          </form>
        </div>

        <div class="ad-karte-inhalt" id="erstatten-form" hidden>
          <form method="post">
            <?= Auth::csrfFeld() ?>
            <input type="hidden" name="aktion" value="erstatten">
            <p class="ad-tipp">Höchstens <?= Util::e(Util::geld($offen)) ?> erstattbar. Bei Stripe und
              PayPal wird die Erstattung dort ausgelöst; bei anderen Zahlarten nur dokumentiert.</p>
            <div class="ad-feld">
              <label for="betrag">Betrag</label>
              <div class="ad-euro"><input type="text" id="betrag" name="betrag"
                     value="<?= Util::e(Util::geldFeld($offen)) ?>" inputmode="decimal"></div>
            </div>
            <div class="ad-feld">
              <label for="grund_e">Grund</label>
              <input type="text" id="grund_e" name="grund">
            </div>
            <label class="ad-haken">
              <input type="checkbox" name="zurueck_ins_lager" value="1">
              <span>Artikel zurück in den Bestand buchen</span>
            </label>
            <button class="ad-knopf ad-knopf-rot" type="submit">Erstatten</button>
          </form>
        </div>

        <div class="ad-karte-inhalt" id="storno-form" hidden>
          <form method="post" data-frage="Diese Bestellung wirklich stornieren?">
            <?= Auth::csrfFeld() ?>
            <input type="hidden" name="aktion" value="stornieren">
            <div class="ad-feld">
              <label for="grund_s">Grund</label>
              <input type="text" id="grund_s" name="grund">
            </div>
            <label class="ad-haken">
              <input type="checkbox" name="zurueck_ins_lager" value="1" checked>
              <span>Artikel zurück in den Bestand buchen</span>
            </label>
            <p class="ad-tipp">Eine bereits erfolgte Zahlung wird dadurch nicht erstattet.</p>
            <button class="ad-knopf ad-knopf-rot" type="submit">Stornieren</button>
          </form>
        </div>
      <?php endif; ?>
    </section>

    <?php if ($bestellung['sendungen'] !== []): ?>
      <section class="ad-karte">
        <div class="ad-karte-kopf"><h2>Sendungen</h2></div>
        <div class="ad-karte-inhalt eng"><div class="ad-tabelle-rahmen"><table><tbody>
          <?php foreach ($bestellung['sendungen'] as $sendung): ?>
            <tr>
              <td><div class="ad-haupt"><?= Util::e((string) ($sendung['dienstleister'] ?: 'Versand')) ?></div>
                  <div class="ad-neben"><?= Util::e(Util::dt((string) $sendung['erstellt'])) ?></div></td>
              <td><?= Util::e((string) ($sendung['sendungsnummer'] ?: '—')) ?></td>
              <td class="ad-zahl-rechts"><?php if ((string) $sendung['sendung_url'] !== ''): ?>
                <a href="<?= Util::e((string) $sendung['sendung_url']) ?>" target="_blank" rel="noopener">Verfolgen ↗</a>
              <?php endif; ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody></table></div></div>
      </section>
    <?php endif; ?>

    <?php if ($bestellung['erstattungen'] !== []): ?>
      <section class="ad-karte">
        <div class="ad-karte-kopf"><h2>Erstattungen</h2></div>
        <div class="ad-karte-inhalt eng"><div class="ad-tabelle-rahmen"><table><tbody>
          <?php foreach ($bestellung['erstattungen'] as $erstattung): ?>
            <tr>
              <td class="ad-neben"><?= Util::e(Util::dt((string) $erstattung['erstellt'])) ?></td>
              <td><?= Util::e((string) ($erstattung['grund'] ?: '—')) ?></td>
              <td class="ad-zahl-rechts"><strong><?= Util::e(Util::geld((int) $erstattung['betrag'])) ?></strong></td>
            </tr>
          <?php endforeach; ?>
        </tbody></table></div></div>
      </section>
    <?php endif; ?>
  </div>

  <div>
    <section class="ad-karte">
      <div class="ad-karte-kopf"><h2>Kunde</h2></div>
      <div class="ad-karte-inhalt">
        <?php if ($bestellung['kunde_id'] !== null): ?>
          <a href="kunde.php?id=<?= (int) $bestellung['kunde_id'] ?>"><strong><?=
            Util::e(trim(($liefer['vorname'] ?? '') . ' ' . ($liefer['nachname'] ?? '')) ?: (string) $bestellung['email'])
          ?></strong></a>
        <?php else: ?>
          <strong><?= Util::e(trim(($liefer['vorname'] ?? '') . ' ' . ($liefer['nachname'] ?? '')) ?: 'Gast') ?></strong>
        <?php endif; ?>
        <div class="ad-neben" style="margin-top:4px"><?= Util::e((string) $bestellung['email']) ?></div>
        <?php if ((string) $bestellung['telefon'] !== ''): ?>
          <div class="ad-neben"><?= Util::e((string) $bestellung['telefon']) ?></div>
        <?php endif; ?>

        <h3 style="margin-top:14px;font-size:12px;text-transform:uppercase;color:var(--nebentext)">Lieferadresse</h3>
        <div class="ad-neben"><?= nl2br(Util::e(Kunden::adresseText($liefer))) ?></div>

        <?php if (json_encode($rechnung) !== json_encode($liefer)): ?>
          <h3 style="margin-top:12px;font-size:12px;text-transform:uppercase;color:var(--nebentext)">Rechnungsadresse</h3>
          <div class="ad-neben"><?= nl2br(Util::e(Kunden::adresseText($rechnung))) ?></div>
        <?php endif; ?>
      </div>
    </section>

    <section class="ad-karte">
      <div class="ad-karte-kopf"><h2>Zahlung</h2></div>
      <div class="ad-karte-inhalt">
        <div style="display:flex;justify-content:space-between;padding:3px 0">
          <span>Zahlart</span><strong><?= Util::e(Zahlung::name((string) $bestellung['zahlart'])) ?></strong>
        </div>
        <?php if ((string) $bestellung['zahlreferenz'] !== ''): ?>
          <div style="padding:6px 0">
            <div class="ad-neben">Referenz</div>
            <div class="ad-code-kasten" style="margin-top:4px"><?= Util::e((string) $bestellung['zahlreferenz']) ?></div>
          </div>
        <?php endif; ?>
        <?php if ((string) $bestellung['bezahlt_am'] !== ''): ?>
          <div style="display:flex;justify-content:space-between;padding:3px 0">
            <span>Bezahlt am</span><span class="ad-neben"><?= Util::e(Util::dt((string) $bestellung['bezahlt_am'])) ?></span>
          </div>
        <?php endif; ?>
      </div>
    </section>

    <section class="ad-karte">
      <div class="ad-karte-kopf"><h2>Interne Notiz</h2></div>
      <div class="ad-karte-inhalt">
        <form method="post">
          <?= Auth::csrfFeld() ?>
          <input type="hidden" name="aktion" value="notiz">
          <div class="ad-feld">
            <textarea name="notiz" rows="3" placeholder="Nur im Backend sichtbar"><?= Util::e((string) $bestellung['notiz']) ?></textarea>
          </div>
          <button class="ad-knopf ad-knopf-klein" type="submit">Notiz speichern</button>
        </form>
        <?php if ((string) $bestellung['kundennotiz'] !== ''): ?>
          <div class="ad-hinweis ad-hinweis-info" style="margin:12px 0 0">
            <strong>Anmerkung des Kunden</strong><?= Util::e((string) $bestellung['kundennotiz']) ?>
          </div>
        <?php endif; ?>
      </div>
    </section>

    <section class="ad-karte">
      <div class="ad-karte-kopf"><h2>Verlauf</h2></div>
      <div class="ad-karte-inhalt">
        <ul class="ad-verlauf">
          <?php foreach ($bestellung['ereignisse'] as $ereignis): ?>
            <li><?= Util::e((string) $ereignis['text']) ?>
              <time><?= Util::e(Util::dt((string) $ereignis['erstellt'])) ?><?php
                if ((string) ($ereignis['benutzer_name'] ?? '') !== '') {
                    echo ' · ' . Util::e((string) $ereignis['benutzer_name']);
                }
              ?></time>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    </section>
  </div>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
