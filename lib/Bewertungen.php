<?php
/**
 * Kundenbewertungen.
 *
 * Bewertungen laufen bewusst NICHT über die veröffentlichte Fassung. Sie
 * kommen laufend von Kunden herein, wie Bestellungen und Bestände – eine
 * freigegebene Bewertung soll sofort im Shop stehen und nicht darauf warten,
 * dass jemand den ganzen Katalog neu veröffentlicht.
 *
 * Rechtlicher Rahmen, der den Aufbau bestimmt:
 *
 *   § 5b Abs. 3 UWG  Wer Bewertungen zugänglich macht, muss darüber
 *                    informieren, ob und wie sichergestellt wird, dass sie
 *                    von tatsächlichen Käufern stammen. Deshalb sucht
 *                    kaufNachweis() zu jeder Bewertung eine bezahlte
 *                    Bestellung derselben E-Mail über denselben Artikel und
 *                    hält sie in bestellung_id fest.
 *   Anhang zu § 3    Gefälschte Bewertungen und das gezielte Aussortieren
 *   Abs. 3 Nr. 23b/c negativer Bewertungen sind verboten. Das System kennt
 *                    deshalb kein "nur gute veröffentlichen": Freigeben ist
 *                    eine Moderation gegen Beleidigung und Spam, keine
 *                    Auswahl nach Sternen. Das Backend sagt das auch so.
 *
 * Die Durchschnittsnote wird immer zusammen mit der Anzahl gezeigt. Eine
 * einzelne Fünf-Sterne-Bewertung ist keine 5,0 – sie ist eine Meinung.
 */
final class Bewertungen
{
    /** Wie eine Bewertung im Shop erscheint. */
    public const STATUS = [
        'neu'       => 'Wartet auf Freigabe',
        'frei'      => 'Veröffentlicht',
        'versteckt' => 'Nicht veröffentlicht',
    ];

    /** Höchstens so viele Bewertungen aus derselben Quelle pro Stunde. */
    private const PRO_STUNDE = 3;

    /* ------------------------------------------------------------- Anlegen */

    /**
     * Nimmt eine Bewertung aus dem Shop entgegen.
     *
     * @param array<string,mixed> $daten artikel_id, name, email, sterne, titel, text
     * @return array{0:bool,1:string} Erfolg und Meldung für den Kunden
     */
    public static function abgeben(array $daten): array
    {
        $artikelId = (int) ($daten['artikel_id'] ?? 0);
        $sterne    = (int) ($daten['sterne'] ?? 0);
        $name      = trim((string) ($daten['name'] ?? ''));
        $email     = Util::normalizeEmail((string) ($daten['email'] ?? ''));
        $titel     = trim((string) ($daten['titel'] ?? ''));
        $text      = trim((string) ($daten['text'] ?? ''));

        if ($artikelId <= 0 || DB::value('SELECT COUNT(*) FROM artikel WHERE id = ?', [$artikelId], 0) < 1) {
            return [false, 'Zu diesem Artikel lässt sich nichts bewerten.'];
        }
        if ($sterne < 1 || $sterne > 5) {
            return [false, 'Bitte eine Bewertung von einem bis fünf Sternen wählen.'];
        }
        if ($name === '') {
            return [false, 'Bitte einen Namen angeben – er steht später an der Bewertung.'];
        }
        if (!Util::isEmail($email)) {
            return [false, 'Bitte eine gültige E-Mail-Adresse angeben. Sie wird nicht veröffentlicht.'];
        }
        if (mb_strlen($text, 'UTF-8') < 10) {
            return [false, 'Bitte ein paar Sätze schreiben – zwei Worte helfen niemandem weiter.'];
        }

        /*
         * Bremse gegen Massenabgaben. Absichtlich über die E-Mail und nicht
         * über die IP-Adresse: hinter einer IP sitzt bei Mobilfunk halb
         * Deutschland, und die Adresse zu speichern wäre ein personenbezogenes
         * Datum, das für den Zweck nicht nötig ist.
         */
        $quelle = substr(hash_hmac('sha256', $email, Config::secret()), 0, 64);
        $zuletzt = (int) DB::value(
            'SELECT COUNT(*) FROM bewertungen WHERE quelle = ? AND erstellt > ?',
            [$quelle, date('Y-m-d H:i:s', time() - 3600)],
            0
        );
        if ($zuletzt >= self::PRO_STUNDE) {
            return [false, 'Es sind gerade sehr viele Bewertungen von dir eingegangen. Bitte später noch einmal.'];
        }

        // Zweimal derselbe Artikel von derselben Adresse ist keine zweite
        // Meinung, sondern ein zweiter Versuch.
        $schonDa = DB::row(
            'SELECT id FROM bewertungen WHERE artikel_id = ? AND email = ?',
            [$artikelId, $email]
        );
        if ($schonDa !== null) {
            return [false, 'Zu diesem Artikel liegt von dir bereits eine Bewertung vor.'];
        }

        $bestellung = self::kaufNachweis($artikelId, $email);
        $kunde      = Kunden::nachEmail($email);
        $sofort     = Settings::get('bewertungen_freigabe', 'manuell') === 'sofort';

        $id = DB::insert('bewertungen', [
            'artikel_id'    => $artikelId,
            'bestellung_id' => $bestellung,
            'kunde_id'      => $kunde !== null ? (int) $kunde['id'] : null,
            'name'          => mb_substr($name, 0, 120, 'UTF-8'),
            'email'         => mb_substr($email, 0, 190, 'UTF-8'),
            'sterne'        => $sterne,
            'titel'         => mb_substr($titel, 0, 200, 'UTF-8'),
            'text'          => mb_substr(Util::nurText($text), 0, 4000, 'UTF-8'),
            'antwort'       => '',
            'status'        => $sofort ? 'frei' : 'neu',
            'quelle'        => $quelle,
            'erstellt'      => Util::now(),
            'geaendert'     => Util::now(),
        ]);
        Log::info('bewertung', 'Neue Bewertung (' . $sterne . ' Sterne) zu Artikel ' . $artikelId . '.');

        return [true, $sofort
            ? 'Danke! Deine Bewertung steht jetzt beim Artikel.'
            : 'Danke! Deine Bewertung wird gelesen und erscheint in der Regel binnen eines Werktages.'];
    }

    /**
     * Sucht eine bezahlte Bestellung derselben E-Mail über diesen Artikel.
     *
     * Nur das ist ein Nachweis im Sinne von § 5b Abs. 3 UWG. Eine Bestellung,
     * die storniert wurde, zählt nicht.
     */
    public static function kaufNachweis(int $artikelId, string $email): ?int
    {
        $zeile = DB::row(
            'SELECT b.id
               FROM bestellungen b
               JOIN bestellzeilen z ON z.bestellung_id = b.id
              WHERE b.email = ? AND z.artikel_id = ?
                AND b.status != ? AND b.bezahlt_am IS NOT NULL
              ORDER BY b.id DESC LIMIT 1',
            [Util::normalizeEmail($email), $artikelId, 'storniert']
        );
        return $zeile !== null ? (int) $zeile['id'] : null;
    }

    /* --------------------------------------------------------------- Lesen */

    /**
     * Die veröffentlichten Bewertungen eines Artikels, neueste zuerst.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function zuArtikel(int $artikelId, int $limit = 50): array
    {
        return DB::all(
            'SELECT * FROM bewertungen WHERE artikel_id = ? AND status = ?
              ORDER BY erstellt DESC LIMIT ' . max(1, min(200, $limit)),
            [$artikelId, 'frei']
        );
    }

    /**
     * Kennzahlen je Artikel: Anzahl, Schnitt und die Verteilung auf 1–5 Sterne.
     *
     * In einem Rutsch für viele Artikel, damit ein Raster mit zwanzig Kacheln
     * nicht zwanzig Abfragen auslöst.
     *
     * @param array<int,int> $artikelIds
     * @return array<int,array{anzahl:int,schnitt:float,verteilung:array<int,int>}>
     */
    public static function kennzahlen(array $artikelIds): array
    {
        $ids = array_values(array_unique(array_map('intval', $artikelIds)));
        if ($ids === []) {
            return [];
        }
        $platzhalter = implode(',', array_fill(0, count($ids), '?'));
        $zeilen = DB::all(
            'SELECT artikel_id, sterne, COUNT(*) AS anzahl
               FROM bewertungen
              WHERE status = ? AND artikel_id IN (' . $platzhalter . ')
              GROUP BY artikel_id, sterne',
            array_merge(['frei'], $ids)
        );

        $aus = [];
        foreach ($zeilen as $zeile) {
            $artikel = (int) $zeile['artikel_id'];
            $stern   = (int) $zeile['sterne'];
            $anzahl  = (int) $zeile['anzahl'];
            $aus[$artikel] ??= ['anzahl' => 0, 'schnitt' => 0.0, 'verteilung' => [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0]];
            $aus[$artikel]['verteilung'][$stern] = $anzahl;
            $aus[$artikel]['anzahl']  += $anzahl;
            $aus[$artikel]['schnitt'] += $stern * $anzahl;
        }
        foreach ($aus as $artikel => $werte) {
            $aus[$artikel]['schnitt'] = $werte['anzahl'] > 0
                ? round($werte['schnitt'] / $werte['anzahl'], 1)
                : 0.0;
        }
        return $aus;
    }

    /** Kennzahlen eines einzelnen Artikels; leer, wenn es keine Bewertung gibt. */
    public static function zahlen(int $artikelId): array
    {
        return self::kennzahlen([$artikelId])[$artikelId]
            ?? ['anzahl' => 0, 'schnitt' => 0.0, 'verteilung' => [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0]];
    }

    /**
     * Die neuesten freigegebenen Bewertungen über alle Artikel – für den
     * Baustein "Kundenstimmen".
     *
     * @return array<int,array<string,mixed>>
     */
    public static function neueste(int $limit = 3, int $mindestSterne = 1): array
    {
        return DB::all(
            'SELECT w.*, a.titel AS artikel_titel, a.handle AS artikel_handle
               FROM bewertungen w
               JOIN artikel a ON a.id = w.artikel_id
              WHERE w.status = ? AND w.sterne >= ? AND a.status = ?
              ORDER BY w.erstellt DESC LIMIT ' . max(1, min(24, $limit)),
            ['frei', max(1, min(5, $mindestSterne)), 'aktiv']
        );
    }

    /* ---------------------------------------------------------- Moderation */

    /**
     * Liste fürs Backend.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function liste(string $status = '', int $limit = 100): array
    {
        $wo = $status !== '' ? ' WHERE w.status = ?' : '';
        return DB::all(
            'SELECT w.*, a.titel AS artikel_titel, a.handle AS artikel_handle
               FROM bewertungen w
               LEFT JOIN artikel a ON a.id = w.artikel_id' . $wo . '
              ORDER BY w.erstellt DESC LIMIT ' . max(1, min(500, $limit)),
            $status !== '' ? [$status] : []
        );
    }

    public static function offene(): int
    {
        return (int) DB::value('SELECT COUNT(*) FROM bewertungen WHERE status = ?', ['neu'], 0);
    }

    public static function holen(int $id): ?array
    {
        return DB::row(
            'SELECT w.*, a.titel AS artikel_titel, a.handle AS artikel_handle
               FROM bewertungen w LEFT JOIN artikel a ON a.id = w.artikel_id
              WHERE w.id = ?',
            [$id]
        );
    }

    public static function statusSetzen(int $id, string $status): void
    {
        if (!isset(self::STATUS[$status])) {
            throw new InvalidArgumentException('Diesen Status gibt es nicht.');
        }
        DB::update('bewertungen', $id, ['status' => $status, 'geaendert' => Util::now()]);
        Log::info('bewertung', 'Bewertung ' . $id . ' auf "' . $status . '" gesetzt.');
    }

    /** Öffentliche Antwort des Betreibers. Leerer Text entfernt sie wieder. */
    public static function antworten(int $id, string $antwort): void
    {
        DB::update('bewertungen', $id, [
            'antwort'   => mb_substr(Util::nurText(trim($antwort)), 0, 2000, 'UTF-8'),
            'geaendert' => Util::now(),
        ]);
    }

    public static function loeschen(int $id): void
    {
        DB::delete('bewertungen', $id);
        Log::warn('bewertung', 'Bewertung ' . $id . ' gelöscht.');
    }

    /* --------------------------------------------------------- Rechtstexte */

    /**
     * Pflichtangabe nach § 5b Abs. 3 UWG, wörtlich so im Shop sichtbar.
     *
     * Der Text beschreibt, was das System wirklich tut – er ist deshalb
     * absichtlich hier und nicht in den Einstellungen: Was geprüft wird,
     * entscheidet der Programmcode, nicht der Betreiber.
     */
    public const HINWEIS =
        'Bewertungen kann jeder abgeben. Zu jeder Bewertung prüfen wir automatisch, '
      . 'ob unter derselben E-Mail-Adresse eine bezahlte Bestellung dieses Artikels vorliegt; '
      . 'nur dann steht "Verifizierter Kauf" daran. Vor der Veröffentlichung lesen wir mit – '
      . 'aussortiert wird ausschließlich Beleidigendes, Rechtswidriges und offensichtlicher Spam, '
      . 'niemals eine schlechte Note. Bewertungen werden weder gekauft noch selbst geschrieben.';
}
