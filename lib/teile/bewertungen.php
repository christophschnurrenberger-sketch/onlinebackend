<?php
/**
 * Der Bewertungsabschnitt auf der Artikelseite.
 *
 * Eingebunden von artikel.php. Erwartet:
 *   $artikel   Artikel aus der Fassung
 *   $noten     Kennzahlen aus Bewertungen::zahlen()
 *   $stimmen   freigegebene Bewertungen
 *
 * Aufbau wie im großen Handel: links die Note mit der Verteilung auf fünf
 * Balken, rechts die einzelnen Stimmen. Die Verteilung steht dabei bewusst
 * vorn – sie zeigt, ob eine 4,6 aus lauter Vieren oder aus Fünfen und Einsen
 * entstanden ist. Genau das lesen Kunden heraus, bevor sie den Texten
 * überhaupt Beachtung schenken.
 */

$gesamt   = (int) $noten['anzahl'];
$meldung  = Util::get('bmeldung');
$fehler   = Util::get('bfehler');
$formOffen = $fehler !== '' || Util::get('bewerten') !== '';
?>
<section class="abschnitt bewertungen" id="bewertungen">
  <div class="abschnitt-kopf"><h2>Bewertungen</h2></div>

  <?php if ($meldung !== ''): ?>
    <?php Theme::meldung($meldung, 'erfolg'); ?>
  <?php endif; ?>
  <?php if ($fehler !== ''): ?>
    <?php Theme::meldung($fehler, 'fehler'); ?>
  <?php endif; ?>

  <div class="bw-raster">
    <div class="bw-uebersicht">
      <?php if ($gesamt > 0): ?>
        <div class="bw-note"><?= Util::e(number_format((float) $noten['schnitt'], 1, ',', '.')) ?></div>
        <?= Theme::sterne((float) $noten['schnitt'], $gesamt, 20) ?>
        <p class="klein nebentext"><?= $gesamt ?> Bewertung<?= $gesamt === 1 ? '' : 'en' ?></p>

        <ul class="bw-verteilung">
          <?php foreach ([5, 4, 3, 2, 1] as $stufe): ?>
            <?php
            $anzahl = (int) ($noten['verteilung'][$stufe] ?? 0);
            $anteil = $gesamt > 0 ? round($anzahl / $gesamt * 100) : 0;
            ?>
            <li>
              <span class="bw-stufe"><?= $stufe ?> <?= $stufe === 1 ? 'Stern' : 'Sterne' ?></span>
              <span class="bw-balken"><i style="width:<?= $anteil ?>%"></i></span>
              <span class="bw-anzahl"><?= $anzahl ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?>
        <p>Zu diesem Artikel gibt es noch keine Bewertung.</p>
        <p class="klein nebentext">Wenn du ihn gekauft hast: Deine Erfahrung hilft dem Nächsten weiter.</p>
      <?php endif; ?>

      <a class="knopf knopf-leer knopf-breit" href="#bewertung-schreiben">Bewertung schreiben</a>
    </div>

    <div class="bw-liste">
      <?php if ($stimmen === []): ?>
        <p class="nebentext">Sobald die erste Bewertung freigegeben ist, steht sie hier.</p>
      <?php endif; ?>

      <?php foreach ($stimmen as $stimme): ?>
        <article class="bw-stimme">
          <div class="bw-kopf">
            <?= Theme::sterne((float) (int) $stimme['sterne'], -1, 15) ?>
            <?php if ((string) $stimme['titel'] !== ''): ?>
              <strong class="bw-titel"><?= Util::e((string) $stimme['titel']) ?></strong>
            <?php endif; ?>
          </div>
          <p class="bw-wer">
            <?= Util::e((string) $stimme['name']) ?>
            <span class="nebentext">· <?= Util::e(Util::dt((string) $stimme['erstellt'], 'd.m.Y')) ?></span>
            <?php if ($stimme['bestellung_id'] !== null): ?>
              <?php /* Kennzeichnung nach § 5b Abs. 3 UWG: nur wenn zu dieser
                       E-Mail eine bezahlte Bestellung des Artikels vorliegt. */ ?>
              <span class="bw-echt">Verifizierter Kauf</span>
            <?php endif; ?>
          </p>
          <p class="bw-text"><?= nl2br(Util::e((string) $stimme['text'])) ?></p>

          <?php if (trim((string) $stimme['antwort']) !== ''): ?>
            <div class="bw-antwort">
              <strong><?= Util::e(Theme::e('shop_name', 'Wir')) ?> antwortet:</strong>
              <p><?= nl2br(Util::e((string) $stimme['antwort'])) ?></p>
            </div>
          <?php endif; ?>
        </article>
      <?php endforeach; ?>

      <?php
      /*
       * Pflichtangabe nach § 5b Abs. 3 UWG. Sie steht sichtbar bei den
       * Bewertungen und nicht im Kleingedruckten – anders erfüllt sie den
       * Zweck nicht, und im Zweifel auch nicht das Gesetz.
       */
      ?>
      <p class="bw-hinweis"><?= Util::e(Bewertungen::HINWEIS) ?></p>

      <form class="bw-form" id="bewertung-schreiben" method="post"
            action="<?= Util::e(Config::url('bewertung.php')) ?>">
        <h3>Eigene Bewertung schreiben</h3>
        <input type="hidden" name="artikel_id" value="<?= (int) $artikel['id'] ?>">
        <input type="hidden" name="handle" value="<?= Util::e((string) $artikel['handle']) ?>">

        <fieldset class="bw-wahl">
          <legend>Wie viele Sterne?</legend>
          <?php foreach ([5, 4, 3, 2, 1] as $stufe): ?>
            <label>
              <input type="radio" name="sterne" value="<?= $stufe ?>" required
                     <?= (int) Util::get('bsterne') === $stufe ? 'checked' : '' ?>>
              <span><?= $stufe ?> <?= $stufe === 1 ? 'Stern' : 'Sterne' ?></span>
            </label>
          <?php endforeach; ?>
        </fieldset>

        <div class="bw-zwei">
          <label>Name
            <input type="text" name="name" maxlength="120" required
                   value="<?= Util::e(Util::get('bname')) ?>"
                   placeholder="Vorname oder Kürzel">
          </label>
          <label>E-Mail-Adresse
            <input type="email" name="email" maxlength="190" required
                   value="<?= Util::e(Util::get('bemail')) ?>"
                   placeholder="wird nicht veröffentlicht">
          </label>
        </div>
        <label>Überschrift (freiwillig)
          <input type="text" name="titel" maxlength="200" value="<?= Util::e(Util::get('btitel')) ?>">
        </label>
        <label>Deine Erfahrung
          <textarea name="text" rows="5" required
                    placeholder="Was hat gepasst, was nicht? Wofür hast du es benutzt?"></textarea>
        </label>

        <p class="klein nebentext">
          Die E-Mail-Adresse dient nur dazu, deine Bewertung einer Bestellung zuzuordnen
          und bei Rückfragen zu antworten. Sie wird nicht veröffentlicht.
          <?php if (Theme::e('seite_datenschutz') !== ''): ?>
            Mehr dazu in der
            <a href="<?= Util::e(Config::url('seite.php?h=' . rawurlencode(Theme::e('seite_datenschutz')))) ?>">Datenschutzerklärung</a>.
          <?php endif; ?>
        </p>
        <button class="knopf" type="submit">Bewertung absenden</button>
      </form>
    </div>
  </div>
</section>

<?php
/*
 * Strukturierte Daten für Suchmaschinen. Nur echte, freigegebene Bewertungen
 * wandern hinein – ausgedachte Sterne im Quelltext sind der schnellste Weg,
 * bei Google und vor Gericht Ärger zu bekommen.
 */
if ($gesamt > 0):
    $daten = [
        '@context' => 'https://schema.org',
        '@type'    => 'Product',
        'name'     => (string) $artikel['titel'],
        'aggregateRating' => [
            '@type'       => 'AggregateRating',
            'ratingValue' => (float) $noten['schnitt'],
            'reviewCount' => $gesamt,
            'bestRating'  => 5,
            'worstRating' => 1,
        ],
        'review' => array_map(static fn(array $b): array => [
            '@type'         => 'Review',
            'author'        => ['@type' => 'Person', 'name' => (string) $b['name']],
            'datePublished' => substr((string) $b['erstellt'], 0, 10),
            'name'          => (string) $b['titel'],
            'reviewBody'    => (string) $b['text'],
            'reviewRating'  => ['@type' => 'Rating', 'ratingValue' => (int) $b['sterne'], 'bestRating' => 5, 'worstRating' => 1],
        ], array_slice($stimmen, 0, 10)),
    ];
    ?>
    <script type="application/ld+json"><?= json_encode($daten, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></script>
    <?php
endif;
