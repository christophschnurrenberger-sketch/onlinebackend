<?php
/** Benutzerverwaltung. */

$seitentitel = 'Benutzer';
$benoetigtesRecht = 'einstellen';
require __DIR__ . '/partials/header.php';

if (Util::isPost()) {
    Auth::csrfPruefen();
    try {
        switch (Util::post('aktion')) {
            case 'anlegen':
                Auth::anlegen(
                    Util::post('email'),
                    Util::postRaw('passwort'),
                    Util::post('name'),
                    Util::post('rolle')
                );
                Util::redirect('benutzer.php?meldung=' . rawurlencode('Benutzer angelegt.'));
                // Ende durch redirect

            case 'passwort':
                $zielId = Util::postInt('id');
                Auth::passwortSetzen($zielId, Util::postRaw('passwort'));
                Util::redirect('benutzer.php?meldung=' . rawurlencode('Passwort geändert.'));

            case 'loeschen':
                $zielId = Util::postInt('id');
                if ($zielId === (int) $benutzer['id']) {
                    throw new RuntimeException('Der eigene Zugang kann nicht gelöscht werden.');
                }
                if ((int) DB::value('SELECT COUNT(*) FROM benutzer WHERE aktiv = 1 AND id != ?', [$zielId], 0) === 0) {
                    throw new RuntimeException('Der letzte Zugang kann nicht gelöscht werden.');
                }
                DB::run('DELETE FROM sitzungen WHERE benutzer_id = ?', [$zielId]);
                DB::delete('benutzer', $zielId);
                Log::info('auth', 'Benutzer ' . $zielId . ' gelöscht.');
                Util::redirect('benutzer.php?meldung=' . rawurlencode('Benutzer gelöscht.'));

            case 'rolle':
                $zielId = Util::postInt('id');
                if ($zielId === (int) $benutzer['id']) {
                    throw new RuntimeException('Die eigene Rolle lässt sich nicht ändern.');
                }
                DB::update('benutzer', $zielId, [
                    'rolle'     => Util::einesVon(Util::post('rolle'), array_keys(Auth::ROLLEN), 'mitarbeiter'),
                    'geaendert' => Util::now(),
                ]);
                Util::redirect('benutzer.php?meldung=' . rawurlencode('Rolle geändert.'));
        }
    } catch (Throwable $e) {
        echo '<div class="ad-hinweis ad-hinweis-fehler">' . Util::e($e->getMessage()) . '</div>';
    }
}

$alle = DB::all('SELECT id, email, name, rolle, aktiv, letzter_login, erstellt FROM benutzer ORDER BY id');
?>

<div class="ad-seitenkopf">
  <div class="ad-titel">
    <h1>Benutzer</h1>
    <div class="ad-untertitel"><?= count($alle) ?> Zugänge</div>
  </div>
</div>

<div class="ad-hinweis ad-hinweis-info">
  <strong>Rollen</strong>
  Inhaber und Administrator dürfen alles, auch Einstellungen und Benutzer ändern.
  Mitarbeiter pflegen Artikel, Bestellungen, Kunden und Inhalte, kommen aber nicht an
  Einstellungen, Versandzonen, Steuersätze oder das Zurücksetzen von Fassungen.
</div>

<section class="ad-karte">
  <div class="ad-karte-kopf"><h2>Vorhandene Zugänge</h2></div>
  <div class="ad-karte-inhalt eng">
    <div class="ad-tabelle-rahmen"><table>
      <thead><tr><th>Name</th><th>E-Mail</th><th>Rolle</th><th>Zuletzt angemeldet</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($alle as $eintrag): ?>
          <tr>
            <td><?= Util::e((string) ($eintrag['name'] ?: '—')) ?>
              <?php if ((int) $eintrag['id'] === (int) $benutzer['id']): ?>
                <span class="ad-neben">(du)</span>
              <?php endif; ?>
            </td>
            <td class="ad-neben"><?= Util::e((string) $eintrag['email']) ?></td>
            <td>
              <?php if ((string) $eintrag['rolle'] === 'inhaber' || (int) $eintrag['id'] === (int) $benutzer['id']): ?>
                <span class="ad-marke <?= (string) $eintrag['rolle'] === 'inhaber' ? 'ad-marke-gruen' : '' ?>">
                  <i></i><?= Util::e(Auth::rolleName((string) $eintrag['rolle'])) ?>
                </span>
              <?php else: ?>
                <form method="post" style="display:inline">
                  <?= Auth::csrfFeld() ?>
                  <input type="hidden" name="aktion" value="rolle">
                  <input type="hidden" name="id" value="<?= (int) $eintrag['id'] ?>">
                  <select name="rolle" onchange="this.form.submit()" style="padding:4px 8px;font-size:12.5px;
                          border:1px solid var(--rahmen-kraeftig);border-radius:6px">
                    <?php foreach (Auth::ROLLEN as $wert => $label): ?>
                      <?php if ($wert === 'inhaber') { continue; } ?>
                      <option value="<?= Util::e($wert) ?>" <?= (string) $eintrag['rolle'] === $wert ? 'selected' : '' ?>>
                        <?= Util::e($label) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </form>
              <?php endif; ?>
            </td>
            <td class="ad-neben"><?= $eintrag['letzter_login'] !== null
                ? Util::e(Util::dt((string) $eintrag['letzter_login'])) : 'nie' ?></td>
            <td class="ad-zahl-rechts">
              <?php if ((int) $eintrag['id'] !== (int) $benutzer['id']): ?>
                <form method="post" style="display:inline"
                      data-frage="Zugang von <?= Util::e((string) $eintrag['email']) ?> löschen?">
                  <?= Auth::csrfFeld() ?>
                  <input type="hidden" name="aktion" value="loeschen">
                  <input type="hidden" name="id" value="<?= (int) $eintrag['id'] ?>">
                  <button class="ad-knopf ad-knopf-klein ad-knopf-leer ad-knopf-rot" type="submit">Löschen</button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>
  </div>
</section>

<div class="ad-zwei">
  <form method="post" class="ad-karte">
    <?= Auth::csrfFeld() ?>
    <input type="hidden" name="aktion" value="anlegen">
    <div class="ad-karte-kopf"><h2>Benutzer hinzufügen</h2></div>
    <div class="ad-karte-inhalt">
      <div class="ad-feld">
        <label for="name">Name</label>
        <input type="text" id="name" name="name">
      </div>
      <div class="ad-feld">
        <label for="email">E-Mail-Adresse</label>
        <input type="email" id="email" name="email" required>
      </div>
      <div class="ad-feld">
        <label for="passwort">Passwort</label>
        <input type="password" id="passwort" name="passwort" required autocomplete="new-password">
        <div class="ad-tipp">Mindestens 10 Zeichen.</div>
      </div>
      <div class="ad-feld" style="margin:0">
        <label for="rolle">Rolle</label>
        <select id="rolle" name="rolle">
          <option value="mitarbeiter">Mitarbeiter</option>
          <option value="admin">Administrator</option>
        </select>
      </div>
    </div>
    <div class="ad-karte-fuss">
      <button class="ad-knopf ad-knopf-voll" type="submit">Benutzer anlegen</button>
    </div>
  </form>

  <form method="post" class="ad-karte">
    <?= Auth::csrfFeld() ?>
    <input type="hidden" name="aktion" value="passwort">
    <div class="ad-karte-kopf"><h2>Eigenes Passwort ändern</h2></div>
    <div class="ad-karte-inhalt">
      <input type="hidden" name="id" value="<?= (int) $benutzer['id'] ?>">
      <div class="ad-feld" style="margin:0">
        <label for="neues_passwort">Neues Passwort</label>
        <input type="password" id="neues_passwort" name="passwort" required autocomplete="new-password">
        <div class="ad-tipp">Mindestens 10 Zeichen. Alle anderen Anmeldungen werden dabei beendet.</div>
      </div>
    </div>
    <div class="ad-karte-fuss">
      <button class="ad-knopf ad-knopf-voll" type="submit">Passwort ändern</button>
    </div>
  </form>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
