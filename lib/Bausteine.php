<?php
/**
 * Bausteine – der Seitenbaukasten.
 *
 * Eine Seite ist hier kein Klumpen HTML, sondern eine Reihe von Bausteinen:
 * Bühne, Kartenreihe, Artikelraster, Kräuterbuch. Jeder Baustein kennt seine
 * Felder selbst (TYPEN), das Backend baut daraus die Eingabemaske und der
 * Shop daraus die Seite. Ein neuer Bausteintyp braucht deshalb keine
 * Datenbankänderung – nur einen Eintrag in TYPEN und einen Zweig in rendern().
 *
 * Die Werte liegen als JSON in der Spalte "daten". Das ist bewusst kein
 * eigenes Feld je Eigenschaft: Bausteine unterscheiden sich zu stark, und eine
 * Tabelle mit sechzig Spalten, von denen jede Zeile fünf benutzt, ist keine.
 */
final class Bausteine
{
    /**
     * Die Bausteintypen.
     *
     * felder: Schlüssel => [Art, Beschriftung, Hinweis]
     *   Arten: text, mehrzeilig, html, bild, zahl
     * liste: ein wiederholbarer Teil (Karten, Einträge, Spalten)
     */
    public const TYPEN = [
        'buehne' => [
            'name' => 'Bühne mit Bild',
            'text' => 'Bild im Bogen, daneben Überschrift, Text und Knopf. Der Seitenanfang.',
            'felder' => [
                'ueberschrift' => ['text', 'Überschrift'],
                'akzent'       => ['text', 'Zeile in Signalfarbe', 'Steht kursiv mitten in der Überschrift.'],
                'nachsatz'     => ['text', 'Zeile danach'],
                'text'         => ['mehrzeilig', 'Fließtext'],
                'notiz'        => ['text', 'Handschriftliche Notiz', 'Steht unter dem Text, wie mit der Hand ergänzt.'],
                'knopf'        => ['text', 'Knopf'],
                'knopf_ziel'   => ['text', 'Knopfziel', 'z. B. kategorie.php?h=alle'],
                'link'         => ['text', 'Nebenlink'],
                'link_ziel'    => ['text', 'Ziel des Nebenlinks'],
                'bild'         => ['bild', 'Bild'],
                'etikett'      => ['text', 'Etikett auf dem Bild', 'Liegt schräg auf der oberen Ecke.'],
                'stempel'      => ['text', 'Stempel'],
                'stempel_ort'  => ['text', 'Stempel: zweite Zeile'],
            ],
        ],

        'gruen' => [
            'name' => 'Grüner Kasten',
            'text' => 'Zitat oder Haltung auf grünem Grund, rechts ein Bild. Für das, was einmal gesagt gehört.',
            'felder' => [
                'kleinzeile' => ['text', 'Kleinzeile', 'Handschrift über dem Zitat, z. B. „Ein Wort vorweg“.'],
                'zitat'      => ['mehrzeilig', 'Zitat'],
                'text'       => ['mehrzeilig', 'Text darunter'],
                'name'       => ['text', 'Name'],
                'rolle'      => ['text', 'Rolle', 'z. B. „Tierheilpraktikerin, mischt seit 1998“'],
                'bild'       => ['bild', 'Bild'],
            ],
        ],

        'hilfe' => [
            'name' => 'Wobei darf ich helfen?',
            'text' => 'Kartenreihe zum Anklicken. Jede Karte führt auf eine Seite mit Empfehlungen.',
            'felder' => [
                'ueberschrift' => ['text', 'Überschrift'],
                'notiz'        => ['text', 'Handschriftlicher Zusatz', 'Steht neben der Überschrift.'],
            ],
            'liste' => [
                'schluessel' => 'karten',
                'name'       => 'Karten',
                'max'        => 12,
                'felder'     => [
                    'titel' => ['text', 'Titel', 'z. B. „Steife Gelenke“'],
                    'text'  => ['text', 'Unterzeile', 'z. B. „Teufelskralle, Weidenrinde“'],
                    'ziel'  => ['text', 'Ziel', 'z. B. seite.php?h=steife-gelenke'],
                ],
            ],
        ],

        'artikel' => [
            'name' => 'Artikelraster',
            'text' => 'Artikel aus einer Kategorie, im Bogen-Design der Seite.',
            'felder' => [
                'ueberschrift' => ['text', 'Überschrift'],
                'notiz'        => ['text', 'Handschriftlicher Zusatz'],
                'kategorie'    => ['text', 'Kategorie (Kürzel)', 'Leer lassen für die neuesten Artikel.'],
                'anzahl'       => ['zahl', 'Wie viele Artikel', 'Vorgabe 6.'],
                'link'         => ['text', 'Link rechts oben'],
                'link_ziel'    => ['text', 'Ziel des Links'],
            ],
        ],

        'kraeuterbuch' => [
            'name' => 'Aus dem Kräuterbuch',
            'text' => 'Schräg liegende Kästen mit Einträgen aus dem Kräuterbuch.',
            'felder' => [
                'ueberschrift' => ['text', 'Überschrift'],
                'notiz'        => ['text', 'Handschriftlicher Zusatz', 'z. B. „142 Einträge, von Hand geschrieben“'],
            ],
            'liste' => [
                'schluessel' => 'eintraege',
                'name'       => 'Einträge',
                'max'        => 9,
                'felder'     => [
                    'nummer' => ['text', 'Nummer', 'z. B. „Eintrag 041“'],
                    'titel'  => ['text', 'Titel'],
                    'latein' => ['text', 'Botanischer Name'],
                    'text'   => ['mehrzeilig', 'Anriss'],
                    'ziel'   => ['text', 'Ziel', 'Seite mit dem vollen Eintrag.'],
                ],
            ],
        ],

        'eintrag' => [
            'name' => 'Kräuterbuch-Kopf',
            'text' => 'Der Anfang einer Kräuterbuchseite: Nummer, Titel, botanischer Name, Vorspann.',
            'felder' => [
                'nummer'   => ['text', 'Nummer', 'z. B. „Eintrag 041“'],
                'titel'    => ['text', 'Titel'],
                'latein'   => ['text', 'Botanischer Name'],
                'vorspann' => ['mehrzeilig', 'Vorspann'],
                'bild'     => ['bild', 'Bild'],
                'notiz'    => ['text', 'Handschriftliche Notiz'],
            ],
        ],

        'werte' => [
            'name' => 'Spalten',
            'text' => 'Drei bis vier kurze Absätze nebeneinander. Für Haltung, Ablauf, Zusagen.',
            'felder' => [
                'ueberschrift' => ['text', 'Überschrift (darf leer bleiben)'],
            ],
            'liste' => [
                'schluessel' => 'spalten',
                'name'       => 'Spalten',
                'max'        => 4,
                'felder'     => [
                    'titel' => ['text', 'Titel'],
                    'text'  => ['mehrzeilig', 'Text'],
                ],
            ],
        ],

        'text' => [
            'name' => 'Fließtext',
            'text' => 'Überschrift und Text. Für alles, was einfach gelesen werden soll.',
            'felder' => [
                'ueberschrift' => ['text', 'Überschrift'],
                'text'         => ['html', 'Text'],
                'schmal'       => ['zahl', 'Schmale Spalte (1 = ja)', 'Schmal liest sich besser, breit wirkt luftiger.'],
            ],
        ],
    ];

    /* ------------------------------------------------------------- Lesen */

    /** @return array<int,array<string,mixed>> */
    public static function zurSeite(int $seiteId): array
    {
        $zeilen = DB::all('SELECT * FROM bausteine WHERE seite_id = ? ORDER BY position, id', [$seiteId]);
        return array_map([self::class, 'aufbereiten'], $zeilen);
    }

    /** @param array<string,mixed> $zeile */
    private static function aufbereiten(array $zeile): array
    {
        $daten = json_decode((string) ($zeile['daten'] ?? ''), true);
        return [
            'id'    => (int) $zeile['id'],
            'typ'   => (string) $zeile['typ'],
            'daten' => is_array($daten) ? $daten : [],
        ];
    }

    /* ---------------------------------------------------------- Speichern */

    /**
     * Ersetzt alle Bausteine einer Seite durch die übergebene Reihe.
     *
     * Bewusst „alles neu“ statt einzelner Änderungen: das Formular schickt
     * ohnehin den kompletten Stand, und so kann keine Zeile übrig bleiben,
     * die im Backend längst gelöscht ist.
     *
     * @param array<int,array{typ:string,daten:array<string,mixed>}> $bausteine
     */
    public static function setzen(int $seiteId, array $bausteine): void
    {
        DB::run('DELETE FROM bausteine WHERE seite_id = ?', [$seiteId]);
        $position = 0;
        foreach ($bausteine as $baustein) {
            $typ = (string) ($baustein['typ'] ?? '');
            if (!isset(self::TYPEN[$typ])) {
                continue;
            }
            DB::insert('bausteine', [
                'seite_id'  => $seiteId,
                'typ'       => $typ,
                'position'  => $position++,
                'daten'     => json_encode(self::saeubern($typ, (array) ($baustein['daten'] ?? [])), JSON_UNESCAPED_UNICODE),
                'erstellt'  => Util::now(),
                'geaendert' => Util::now(),
            ]);
        }
    }

    /**
     * Lässt nur durch, was der Typ kennt, und putzt jedes Feld nach seiner Art.
     *
     * Öffentlich, weil auch die Live-Vorschau (admin/vorschau.php) hier
     * durchgeht: Sie zeigt damit genau das, was beim Speichern herauskäme –
     * und rendert nie ungeprüftes HTML aus dem Formular.
     *
     * @param array<string,mixed> $daten
     * @return array<string,mixed>
     */
    public static function saeubern(string $typ, array $daten): array
    {
        $muster = self::TYPEN[$typ];
        $rein   = [];

        foreach ($muster['felder'] ?? [] as $schluessel => $feld) {
            $rein[$schluessel] = self::feldWert($feld[0], $daten[$schluessel] ?? '');
        }

        if (isset($muster['liste'])) {
            $liste  = $muster['liste'];
            $eintraege = [];
            foreach (array_slice((array) ($daten[$liste['schluessel']] ?? []), 0, (int) $liste['max']) as $roh) {
                if (!is_array($roh)) {
                    continue;
                }
                $eintrag = [];
                $leer    = true;
                foreach ($liste['felder'] as $schluessel => $feld) {
                    $eintrag[$schluessel] = self::feldWert($feld[0], $roh[$schluessel] ?? '');
                    if ($eintrag[$schluessel] !== '') {
                        $leer = false;
                    }
                }
                // Leere Zeilen sind keine Einträge, sondern übrig gebliebene Felder.
                if (!$leer) {
                    $eintraege[] = $eintrag;
                }
            }
            $rein[$liste['schluessel']] = $eintraege;
        }

        return $rein;
    }

    private static function feldWert(string $art, $wert): string
    {
        $text = is_string($wert) ? $wert : (is_scalar($wert) ? (string) $wert : '');
        return match ($art) {
            'html'       => Util::sauberesHtml($text),
            'mehrzeilig' => mb_substr(trim($text), 0, 4000, 'UTF-8'),
            'zahl'       => (string) (int) $text,
            default      => mb_substr(trim($text), 0, 500, 'UTF-8'),
        };
    }

    /* ----------------------------------------------------------- Ausgeben */

    /**
     * Gibt einen Baustein aus.
     *
     * @param array{typ:string,daten:array<string,mixed>} $baustein
     * @param array<string,mixed> $fassung Die veröffentlichte Fassung – für Artikel
     */
    public static function rendern(array $baustein, array $fassung): void
    {
        $d = static fn(string $schluessel, string $vorgabe = ''): string
            => (string) ($baustein['daten'][$schluessel] ?? $vorgabe);
        $liste = static fn(string $schluessel): array
            => (array) ($baustein['daten'][$schluessel] ?? []);

        match ($baustein['typ']) {
            'buehne'       => self::buehne($d),
            'gruen'        => self::gruen($d),
            'hilfe'        => self::hilfe($d, $liste('karten')),
            'artikel'      => self::artikel($d, $fassung),
            'kraeuterbuch' => self::kraeuterbuch($d, $liste('eintraege')),
            'eintrag'      => self::eintrag($d),
            'werte'        => self::werte($d, $liste('spalten')),
            'text'         => self::text($d),
            default        => null,
        };
    }

    /** Mehrzeiliger Text als Absätze – ohne HTML aus dem Eingabefeld zuzulassen. */
    private static function absaetze(string $text): string
    {
        $teile = preg_split('/\R{2,}/u', trim($text)) ?: [];
        $aus   = '';
        foreach ($teile as $absatz) {
            if (trim($absatz) !== '') {
                $aus .= '<p>' . nl2br(Util::e(trim($absatz))) . '</p>';
            }
        }
        return $aus;
    }

    private static function knopfZeile(callable $d): string
    {
        $aus = '';
        if ($d('knopf') !== '') {
            $aus .= '<a class="knopf" href="' . Util::e(Theme::url($d('knopf_ziel'))) . '">'
                  . Util::e($d('knopf')) . '</a>';
        }
        if ($d('link') !== '') {
            $aus .= '<a class="bs-nebenlink" href="' . Util::e(Theme::url($d('link_ziel'))) . '">'
                  . Util::e($d('link')) . '</a>';
        }
        return $aus === '' ? '' : '<div class="bs-knopfzeile">' . $aus . '</div>';
    }

    private static function bogenbild(string $bild, string $etikett = '', string $klasse = '', string $zusatz = ''): string
    {
        $aus = '<div class="bs-bogen ' . Util::e($klasse) . '">';
        if ($bild !== '') {
            $aus .= '<img src="' . Util::e(Theme::url($bild)) . '" alt="" loading="lazy">';
        }
        if ($etikett !== '') {
            $aus .= '<span class="bs-etikett">' . Util::e($etikett) . '</span>';
        }
        // Etikett und Stempel liegen bewusst im Bild und nicht daneben: sie
        // sollen aussehen, als wären sie daraufgeklebt.
        return $aus . $zusatz . '</div>';
    }

    private static function buehne(callable $d): void
    {
        $stempel = '';
        if ($d('stempel') !== '') {
            $stempel = '<div class="stempel"><span class="stempel-text">' . Util::e($d('stempel')) . '</span>'
                . ($d('stempel_ort') !== ''
                    ? '<span class="stempel-ort">' . Util::e($d('stempel_ort')) . '</span>' : '')
                . '</div>';
        }

        echo '<section class="bs bs-buehne"><div class="behaelter bs-buehne-innen">';
        echo self::bogenbild($d('bild'), $d('etikett'), '', $stempel);
        echo '<div class="bs-buehne-text"><h1>' . Util::e($d('ueberschrift'));
        if ($d('akzent') !== '') {
            echo '<span class="bs-akzent">' . Util::e($d('akzent')) . '</span>';
        }
        if ($d('nachsatz') !== '') {
            echo '<span class="bs-nachsatz">' . Util::e($d('nachsatz')) . '</span>';
        }
        echo '</h1>';
        echo self::absaetze($d('text'));
        if ($d('notiz') !== '') {
            echo '<p class="bs-notiz">' . Util::e($d('notiz')) . '</p>';
        }
        echo self::knopfZeile($d);
        echo '</div></div></section>';
    }

    private static function gruen(callable $d): void
    {
        echo '<section class="bs bs-gruen"><div class="behaelter"><div class="bs-gruen-kasten">';
        echo '<div class="bs-gruen-text">';
        if ($d('kleinzeile') !== '') {
            echo '<p class="bs-notiz">' . Util::e($d('kleinzeile')) . '</p>';
        }
        if ($d('zitat') !== '') {
            echo '<blockquote>' . self::absaetze($d('zitat')) . '</blockquote>';
        }
        echo self::absaetze($d('text'));
        if ($d('name') !== '') {
            echo '<p class="bs-name">' . Util::e($d('name')) . '</p>';
        }
        if ($d('rolle') !== '') {
            echo '<p class="bs-rolle">' . Util::e($d('rolle')) . '</p>';
        }
        echo '</div>';
        echo self::bogenbild($d('bild'), '', 'bs-bogen-klein');
        echo '</div></div></section>';
    }

    /** @param array<int,array<string,string>> $karten */
    private static function hilfe(callable $d, array $karten): void
    {
        echo '<section class="bs bs-hilfe"><div class="behaelter">';
        self::abschnittskopf($d);
        echo '<div class="bs-karten">';
        foreach ($karten as $karte) {
            $ziel = (string) ($karte['ziel'] ?? '');
            $tag  = $ziel !== '' ? 'a' : 'div';
            echo '<' . $tag . ' class="bs-karte"'
               . ($ziel !== '' ? ' href="' . Util::e(Theme::url($ziel)) . '"' : '') . '>';
            echo '<span class="bs-karte-titel">' . Util::e((string) ($karte['titel'] ?? '')) . '</span>';
            if ((string) ($karte['text'] ?? '') !== '') {
                echo '<span class="bs-karte-text">' . Util::e((string) $karte['text']) . '</span>';
            }
            echo '</' . $tag . '>';
        }
        echo '</div></div></section>';
    }

    /** @param array<string,mixed> $fassung */
    private static function artikel(callable $d, array $fassung): void
    {
        $anzahl = max(1, min(24, (int) ($d('anzahl') ?: 6)));
        $handle = $d('kategorie');

        $artikel = $fassung['artikel'] ?? [];
        if ($handle !== '') {
            foreach ($fassung['kategorien'] ?? [] as $kategorie) {
                if ((string) $kategorie['handle'] === $handle) {
                    $ids     = array_flip(array_map('intval', $kategorie['artikel_ids']));
                    $artikel = array_values(array_filter(
                        $artikel,
                        static fn(array $a): bool => isset($ids[(int) $a['id']])
                    ));
                    break;
                }
            }
        }
        $artikel = array_slice($artikel, 0, $anzahl);

        echo '<section class="bs bs-artikel"><div class="behaelter">';
        self::abschnittskopf($d);
        if ($artikel === []) {
            echo '<div class="leer"><p>Hier sind noch keine Artikel veröffentlicht.</p></div>';
        } else {
            $bestaende = Theme::bestaende(Theme::variantenIds($artikel));
            echo '<div class="raster">';
            foreach ($artikel as $eintrag) {
                Theme::kachel($eintrag, $bestaende);
            }
            echo '</div>';
        }
        echo '</div></section>';
    }

    /** @param array<int,array<string,string>> $eintraege */
    private static function kraeuterbuch(callable $d, array $eintraege): void
    {
        echo '<section class="bs bs-buch"><div class="behaelter">';
        self::abschnittskopf($d);
        echo '<div class="bs-zettel-raster">';
        foreach ($eintraege as $nr => $eintrag) {
            $ziel = (string) ($eintrag['ziel'] ?? '');
            $tag  = $ziel !== '' ? 'a' : 'div';
            // Wechselnde Neigung: gerade Zettel wirken gedruckt, schräge gelegt.
            echo '<' . $tag . ' class="bs-zettel bs-zettel-' . ($nr % 3) . '"'
               . ($ziel !== '' ? ' href="' . Util::e(Theme::url($ziel)) . '"' : '') . '>';
            if ((string) ($eintrag['nummer'] ?? '') !== '') {
                echo '<span class="bs-zettel-nr">' . Util::e((string) $eintrag['nummer']) . '</span>';
            }
            echo '<h3>' . Util::e((string) ($eintrag['titel'] ?? '')) . '</h3>';
            if ((string) ($eintrag['latein'] ?? '') !== '') {
                echo '<p class="bs-latein">' . Util::e((string) $eintrag['latein']) . '</p>';
            }
            echo self::absaetze((string) ($eintrag['text'] ?? ''));
            if ($ziel !== '') {
                echo '<span class="bs-weiter">weiterlesen</span>';
            }
            echo '</' . $tag . '>';
        }
        echo '</div></div></section>';
    }

    private static function eintrag(callable $d): void
    {
        echo '<section class="bs bs-eintrag"><div class="behaelter bs-eintrag-innen">';
        echo '<div class="bs-eintrag-text">';
        if ($d('nummer') !== '') {
            echo '<span class="bs-zettel-nr">' . Util::e($d('nummer')) . '</span>';
        }
        echo '<h1>' . Util::e($d('titel')) . '</h1>';
        if ($d('latein') !== '') {
            echo '<p class="bs-latein">' . Util::e($d('latein')) . '</p>';
        }
        echo self::absaetze($d('vorspann'));
        if ($d('notiz') !== '') {
            echo '<p class="bs-notiz">' . Util::e($d('notiz')) . '</p>';
        }
        echo '</div>';
        if ($d('bild') !== '') {
            echo self::bogenbild($d('bild'), '', 'bs-bogen-klein');
        }
        echo '</div></section>';
    }

    /** @param array<int,array<string,string>> $spalten */
    private static function werte(callable $d, array $spalten): void
    {
        echo '<section class="bs bs-werte"><div class="behaelter">';
        if ($d('ueberschrift') !== '') {
            echo '<h2>' . Util::e($d('ueberschrift')) . '</h2>';
        }
        echo '<div class="bs-spalten">';
        foreach ($spalten as $spalte) {
            echo '<div class="bs-spalte"><h3>' . Util::e((string) ($spalte['titel'] ?? '')) . '</h3>';
            echo self::absaetze((string) ($spalte['text'] ?? ''));
            echo '</div>';
        }
        echo '</div></div></section>';
    }

    private static function text(callable $d): void
    {
        $schmal = $d('schmal') === '1';
        echo '<section class="bs bs-text"><div class="behaelter">';
        echo '<div class="' . ($schmal ? 'schmal' : '') . '">';
        if ($d('ueberschrift') !== '') {
            echo '<h2>' . Util::e($d('ueberschrift')) . '</h2>';
        }
        echo '<div class="rte">' . $d('text') . '</div>';
        echo '</div></div></section>';
    }

    private static function abschnittskopf(callable $d): void
    {
        if ($d('ueberschrift') === '' && $d('notiz') === '' && $d('link') === '') {
            return;
        }
        echo '<div class="abschnitt-kopf">';
        echo '<h2>' . Util::e($d('ueberschrift')) . '</h2>';
        if ($d('notiz') !== '') {
            echo '<span class="bs-notiz">' . Util::e($d('notiz')) . '</span>';
        }
        if ($d('link') !== '') {
            echo '<a href="' . Util::e(Theme::url($d('link_ziel'))) . '">' . Util::e($d('link')) . '</a>';
        }
        echo '</div>';
    }
}
