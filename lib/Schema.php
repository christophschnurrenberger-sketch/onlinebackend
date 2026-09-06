<?php
/**
 * Schema – legt die Tabellen an und hält sie aktuell.
 *
 * Die DDL ist einmal generisch formuliert; Platzhalter werden je nach
 * Datenbank übersetzt:
 *   %PK%        Auto-Increment-Primärschlüssel
 *   %INT%       Ganzzahl
 *   %STR(n)%    kurzer Text, indizierbar (VARCHAR bei MySQL)
 *   %TEXT%      Langtext
 *   %DT%        Zeitstempel (als Text 'Y-m-d H:i:s')
 *   %ENGINE%    Tabellen-Suffix (nur MySQL)
 *
 * migrate() ist idempotent: mehrfaches Aufrufen ist unschädlich und ergänzt
 * fehlende Tabellen, Indizes und Spalten. Deshalb genügt es nach einem Update,
 * die Dateien zu überschreiben – die Datenbank zieht beim nächsten Aufruf nach.
 *
 * Grundsätze im Datenmodell:
 *   * Alle Geldbeträge sind Ganzzahlen in Cent.
 *   * Die kaufbare Einheit ist die Variante, nicht der Artikel. Auch ein
 *     Artikel ohne Optionen hat genau eine Variante – das erspart im
 *     Warenkorb und in der Bestellung jede Sonderbehandlung.
 *   * Bestellungen sind Dokumente: Titel und Preise werden hineinkopiert,
 *     damit spätere Katalogänderungen alte Belege nicht verändern.
 */
final class Schema
{
    /** Version des Schemas – wird in den Einstellungen gespeichert. */
    public const VERSION = 3;

    public static function migrate(): void
    {
        foreach (self::tables() as $sql) {
            DB::pdo()->exec(self::translate($sql));
        }
        /*
         * Spalten, die in späteren Fassungen dazugekommen sind. Bestehende
         * Tabellen ändert "CREATE TABLE IF NOT EXISTS" nicht – deshalb hier.
         */
        foreach ([
            ['varianten', 'inhalt_menge', '%INT% NOT NULL DEFAULT 0'],
            ['varianten', 'inhalt_einheit', '%STR(8)% NOT NULL DEFAULT ""'],
        ] as [$tabelle, $spalte, $definition]) {
            self::ensureColumn($tabelle, $spalte, $definition);
        }

        foreach (self::indexes() as $sql) {
            // MySQL kennt kein "CREATE INDEX IF NOT EXISTS".
            if (!DB::isSqlite()) {
                $sql = str_replace('CREATE INDEX IF NOT EXISTS', 'CREATE INDEX', $sql);
            }
            try {
                DB::pdo()->exec($sql);
            } catch (Throwable $e) {
                $msg = strtolower($e->getMessage());
                if (!str_contains($msg, 'duplicate') && !str_contains($msg, 'exist')) {
                    throw $e;
                }
            }
        }
        Settings::set('schema_version', (string) self::VERSION);
    }

    public static function isInstalled(): bool
    {
        try {
            DB::value('SELECT COUNT(*) FROM einstellungen');
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    /** Ergänzt eine Spalte, falls sie fehlt (für Updates aus älteren Fassungen). */
    public static function ensureColumn(string $table, string $column, string $definition): bool
    {
        if (in_array($column, self::columns($table), true)) {
            return false;
        }
        DB::pdo()->exec('ALTER TABLE ' . $table . ' ADD COLUMN ' . $column . ' ' . self::translate($definition));
        Log::info('schema', 'Spalte ' . $table . '.' . $column . ' ergänzt.');
        return true;
    }

    /** @return array<int,string> */
    public static function columns(string $table): array
    {
        try {
            if (DB::isSqlite()) {
                return array_map(static fn($r) => (string) $r['name'], DB::all('PRAGMA table_info(' . $table . ')'));
            }
            return array_map(static fn($r) => (string) ($r['Field'] ?? ''), DB::all('SHOW COLUMNS FROM ' . $table));
        } catch (Throwable $e) {
            return [];
        }
    }

    /* ------------------------------------------------------------- Tabellen */

    /** @return array<int,string> */
    private static function tables(): array
    {
        return [

            /* --- Zugänge ------------------------------------------------- */

            'CREATE TABLE IF NOT EXISTS benutzer (
                id            %PK%,
                email         %STR(190)% NOT NULL,
                passwort      %STR(255)% NOT NULL,
                name          %STR(120)% NOT NULL DEFAULT "",
                rolle         %STR(20)%  NOT NULL DEFAULT "mitarbeiter",
                aktiv         %INT%      NOT NULL DEFAULT 1,
                letzter_login %DT%,
                erstellt      %DT%       NOT NULL,
                geaendert     %DT%       NOT NULL
            )%ENGINE%',

            'CREATE TABLE IF NOT EXISTS sitzungen (
                token      %STR(64)% NOT NULL PRIMARY KEY,
                benutzer_id %INT%    NOT NULL,
                laeuft_ab  %DT%      NOT NULL,
                browser    %STR(255)% NOT NULL DEFAULT "",
                erstellt   %DT%      NOT NULL
            )%ENGINE%',

            /* --- Einstellungen und Protokoll ----------------------------- */

            'CREATE TABLE IF NOT EXISTS einstellungen (
                schluessel %STR(100)% NOT NULL PRIMARY KEY,
                wert       %TEXT%,
                geaendert  %DT%
            )%ENGINE%',

            'CREATE TABLE IF NOT EXISTS protokoll (
                id       %PK%,
                ebene    %STR(10)%  NOT NULL DEFAULT "info",
                bereich  %STR(40)%  NOT NULL DEFAULT "app",
                text     %TEXT%,
                erstellt %DT%       NOT NULL
            )%ENGINE%',

            /* --- Medien --------------------------------------------------- */

            'CREATE TABLE IF NOT EXISTS medien (
                id       %PK%,
                datei    %STR(255)% NOT NULL,
                url      %STR(255)% NOT NULL,
                typ      %STR(80)%  NOT NULL DEFAULT "",
                groesse  %INT%      NOT NULL DEFAULT 0,
                alt      %STR(255)% NOT NULL DEFAULT "",
                erstellt %DT%       NOT NULL
            )%ENGINE%',

            /* --- Katalog -------------------------------------------------- */

            'CREATE TABLE IF NOT EXISTS artikel (
                id            %PK%,
                handle        %STR(190)% NOT NULL,
                titel         %STR(200)% NOT NULL,
                untertitel    %STR(250)% NOT NULL DEFAULT "",
                beschreibung  %TEXT%,
                hersteller    %STR(120)% NOT NULL DEFAULT "",
                typ           %STR(120)% NOT NULL DEFAULT "",
                schlagworte   %STR(500)% NOT NULL DEFAULT "",
                status        %STR(20)%  NOT NULL DEFAULT "entwurf",
                seo_titel     %STR(200)% NOT NULL DEFAULT "",
                seo_text      %STR(400)% NOT NULL DEFAULT "",
                position      %INT%      NOT NULL DEFAULT 0,
                aktiv_seit    %DT%,
                erstellt      %DT%       NOT NULL,
                geaendert     %DT%       NOT NULL
            )%ENGINE%',

            'CREATE TABLE IF NOT EXISTS artikel_bilder (
                id         %PK%,
                artikel_id %INT%      NOT NULL,
                url        %STR(255)% NOT NULL,
                alt        %STR(255)% NOT NULL DEFAULT "",
                position   %INT%      NOT NULL DEFAULT 0
            )%ENGINE%',

            // Optionen sind die Achsen der Variantenmatrix, z. B. Größe und Farbe.
            'CREATE TABLE IF NOT EXISTS artikel_optionen (
                id         %PK%,
                artikel_id %INT%      NOT NULL,
                name       %STR(80)%  NOT NULL,
                werte      %TEXT%,
                position   %INT%      NOT NULL DEFAULT 1
            )%ENGINE%',

            // Die Variante ist die kaufbare Einheit: Preis und Bestand hängen hier.
            'CREATE TABLE IF NOT EXISTS varianten (
                id             %PK%,
                artikel_id     %INT%      NOT NULL,
                titel          %STR(150)% NOT NULL DEFAULT "Standard",
                artikelnummer  %STR(80)%  NOT NULL DEFAULT "",
                ean            %STR(80)%  NOT NULL DEFAULT "",
                preis          %INT%      NOT NULL DEFAULT 0,
                streichpreis   %INT%,
                einkaufspreis  %INT%,
                option1        %STR(80)%  NOT NULL DEFAULT "",
                option2        %STR(80)%  NOT NULL DEFAULT "",
                option3        %STR(80)%  NOT NULL DEFAULT "",
                bestand        %INT%      NOT NULL DEFAULT 0,
                bestand_fuehren %INT%     NOT NULL DEFAULT 1,
                ueberverkauf   %INT%      NOT NULL DEFAULT 0,
                gewicht_g      %INT%      NOT NULL DEFAULT 0,
                versandpflicht %INT%      NOT NULL DEFAULT 1,
                /* Füllmenge für den Grundpreis nach Preisangabenverordnung.
                   In Tausendsteln der Einheit, damit 0,75 l ganzzahlig bleibt. */
                inhalt_menge   %INT%      NOT NULL DEFAULT 0,
                inhalt_einheit %STR(8)%   NOT NULL DEFAULT "",
                steuer_id      %INT%,
                bild_url       %STR(255)% NOT NULL DEFAULT "",
                position       %INT%      NOT NULL DEFAULT 0,
                erstellt       %DT%       NOT NULL,
                geaendert      %DT%       NOT NULL
            )%ENGINE%',

            // Jede Bestandsänderung hinterlässt eine Zeile. Ohne dieses Journal
            // bliebe die Frage "warum stehen hier drei?" unbeantwortbar.
            'CREATE TABLE IF NOT EXISTS bestandsbewegungen (
                id          %PK%,
                varianten_id %INT%     NOT NULL,
                menge       %INT%      NOT NULL,
                grund       %STR(40)%  NOT NULL DEFAULT "korrektur",
                bestellung_id %INT%,
                benutzer_id %INT%,
                erstellt    %DT%       NOT NULL
            )%ENGINE%',

            /* --- Kategorien ----------------------------------------------- */

            'CREATE TABLE IF NOT EXISTS kategorien (
                id            %PK%,
                handle        %STR(190)% NOT NULL,
                titel         %STR(200)% NOT NULL,
                beschreibung  %TEXT%,
                bild_url      %STR(255)% NOT NULL DEFAULT "",
                art           %STR(20)%  NOT NULL DEFAULT "manuell",
                regeln        %TEXT%,
                regel_modus   %STR(10)%  NOT NULL DEFAULT "alle",
                sortierung    %STR(20)%  NOT NULL DEFAULT "manuell",
                sichtbar      %INT%      NOT NULL DEFAULT 0,
                position      %INT%      NOT NULL DEFAULT 0,
                seo_titel     %STR(200)% NOT NULL DEFAULT "",
                seo_text      %STR(400)% NOT NULL DEFAULT "",
                erstellt      %DT%       NOT NULL,
                geaendert     %DT%       NOT NULL
            )%ENGINE%',

            'CREATE TABLE IF NOT EXISTS kategorie_artikel (
                kategorie_id %INT% NOT NULL,
                artikel_id   %INT% NOT NULL,
                position     %INT% NOT NULL DEFAULT 0,
                PRIMARY KEY (kategorie_id, artikel_id)
            )%ENGINE%',

            /* --- Inhalte -------------------------------------------------- */

            'CREATE TABLE IF NOT EXISTS seiten (
                id        %PK%,
                handle    %STR(190)% NOT NULL,
                titel     %STR(200)% NOT NULL,
                inhalt    %TEXT%,
                sichtbar  %INT%      NOT NULL DEFAULT 0,
                seo_titel %STR(200)% NOT NULL DEFAULT "",
                seo_text  %STR(400)% NOT NULL DEFAULT "",
                erstellt  %DT%       NOT NULL,
                geaendert %DT%       NOT NULL
            )%ENGINE%',

            /*
             * Bausteine einer Seite.
             *
             * Statt einer Seite mit einem Klumpen HTML besteht eine Seite aus
             * einer Reihe von Bausteinen: Bühne, Kartenreihe, Artikelraster,
             * Kräuterbuch. Jeder Baustein hat einen Typ und einen Sack voll
             * Feldern, die als JSON danebenliegen – so kommt ein neuer
             * Bausteintyp ohne Datenbankänderung aus.
             */
            'CREATE TABLE IF NOT EXISTS bausteine (
                id        %PK%,
                seite_id  %INT%     NOT NULL,
                typ       %STR(40)% NOT NULL,
                position  %INT%     NOT NULL DEFAULT 0,
                daten     %TEXT%,
                erstellt  %DT%      NOT NULL,
                geaendert %DT%      NOT NULL
            )%ENGINE%',

            'CREATE TABLE IF NOT EXISTS beitraege (
                id          %PK%,
                handle      %STR(190)% NOT NULL,
                titel       %STR(200)% NOT NULL,
                anriss      %STR(500)% NOT NULL DEFAULT "",
                inhalt      %TEXT%,
                bild_url    %STR(255)% NOT NULL DEFAULT "",
                autor       %STR(120)% NOT NULL DEFAULT "",
                sichtbar    %INT%      NOT NULL DEFAULT 0,
                sichtbar_seit %DT%,
                seo_titel   %STR(200)% NOT NULL DEFAULT "",
                seo_text    %STR(400)% NOT NULL DEFAULT "",
                erstellt    %DT%       NOT NULL,
                geaendert   %DT%       NOT NULL
            )%ENGINE%',

            'CREATE TABLE IF NOT EXISTS menuepunkte (
                id       %PK%,
                menue    %STR(20)%  NOT NULL DEFAULT "haupt",
                eltern_id %INT%,
                label    %STR(120)% NOT NULL,
                url      %STR(255)% NOT NULL DEFAULT "/",
                position %INT%      NOT NULL DEFAULT 0
            )%ENGINE%',

            /* --- Kunden --------------------------------------------------- */

            'CREATE TABLE IF NOT EXISTS kunden (
                id            %PK%,
                email         %STR(190)% NOT NULL,
                vorname       %STR(120)% NOT NULL DEFAULT "",
                nachname      %STR(120)% NOT NULL DEFAULT "",
                telefon       %STR(60)%  NOT NULL DEFAULT "",
                firma         %STR(150)% NOT NULL DEFAULT "",
                newsletter    %INT%      NOT NULL DEFAULT 0,
                notiz         %TEXT%,
                bestellungen  %INT%      NOT NULL DEFAULT 0,
                umsatz        %INT%      NOT NULL DEFAULT 0,
                erstellt      %DT%       NOT NULL,
                geaendert     %DT%       NOT NULL
            )%ENGINE%',

            'CREATE TABLE IF NOT EXISTS adressen (
                id        %PK%,
                kunde_id  %INT%      NOT NULL,
                vorname   %STR(120)% NOT NULL DEFAULT "",
                nachname  %STR(120)% NOT NULL DEFAULT "",
                firma     %STR(150)% NOT NULL DEFAULT "",
                strasse   %STR(200)% NOT NULL DEFAULT "",
                zusatz    %STR(200)% NOT NULL DEFAULT "",
                plz       %STR(20)%  NOT NULL DEFAULT "",
                ort       %STR(120)% NOT NULL DEFAULT "",
                land      %STR(2)%   NOT NULL DEFAULT "DE",
                telefon   %STR(60)%  NOT NULL DEFAULT "",
                standard  %INT%      NOT NULL DEFAULT 0
            )%ENGINE%',

            /* --- Steuern und Versand -------------------------------------- */

            'CREATE TABLE IF NOT EXISTS steuersaetze (
                id       %PK%,
                name     %STR(120)% NOT NULL,
                satz_bp  %INT%      NOT NULL,
                land     %STR(2)%   NOT NULL DEFAULT "DE",
                standard %INT%      NOT NULL DEFAULT 0
            )%ENGINE%',

            'CREATE TABLE IF NOT EXISTS versandzonen (
                id       %PK%,
                name     %STR(120)% NOT NULL,
                laender  %STR(500)% NOT NULL DEFAULT "",
                position %INT%      NOT NULL DEFAULT 0
            )%ENGINE%',

            'CREATE TABLE IF NOT EXISTS versandarten (
                id          %PK%,
                zone_id     %INT%      NOT NULL,
                name        %STR(120)% NOT NULL,
                hinweis     %STR(250)% NOT NULL DEFAULT "",
                preis       %INT%      NOT NULL DEFAULT 0,
                ab_wert     %INT%,
                bis_wert    %INT%,
                frei_ab     %INT%,
                lieferzeit  %STR(80)%  NOT NULL DEFAULT "",
                position    %INT%      NOT NULL DEFAULT 0
            )%ENGINE%',

            /* --- Rabatte -------------------------------------------------- */

            'CREATE TABLE IF NOT EXISTS rabatte (
                id            %PK%,
                code          %STR(60)%  NOT NULL,
                name          %STR(200)% NOT NULL DEFAULT "",
                art           %STR(20)%  NOT NULL DEFAULT "prozent",
                wert          %INT%      NOT NULL DEFAULT 0,
                gilt_fuer     %STR(20)%  NOT NULL DEFAULT "alles",
                ziele         %TEXT%,
                mindestwert   %INT%      NOT NULL DEFAULT 0,
                max_nutzungen %INT%,
                einmal_pro_kunde %INT%   NOT NULL DEFAULT 0,
                genutzt       %INT%      NOT NULL DEFAULT 0,
                gilt_ab       %DT%,
                gilt_bis      %DT%,
                aktiv         %INT%      NOT NULL DEFAULT 1,
                erstellt      %DT%       NOT NULL,
                geaendert     %DT%       NOT NULL
            )%ENGINE%',

            /* --- Warenkörbe ----------------------------------------------- */

            'CREATE TABLE IF NOT EXISTS warenkoerbe (
                id           %PK%,
                token        %STR(64)%  NOT NULL,
                kunde_id     %INT%,
                email        %STR(190)% NOT NULL DEFAULT "",
                rabattcode   %STR(60)%  NOT NULL DEFAULT "",
                land         %STR(2)%   NOT NULL DEFAULT "DE",
                versandart_id %INT%,
                notiz        %STR(500)% NOT NULL DEFAULT "",
                erstellt     %DT%       NOT NULL,
                geaendert    %DT%       NOT NULL
            )%ENGINE%',

            'CREATE TABLE IF NOT EXISTS warenkorb_zeilen (
                id            %PK%,
                warenkorb_id  %INT% NOT NULL,
                varianten_id  %INT% NOT NULL,
                menge         %INT% NOT NULL DEFAULT 1
            )%ENGINE%',

            /* --- Bestellungen --------------------------------------------- */

            'CREATE TABLE IF NOT EXISTS bestellungen (
                id             %PK%,
                nummer         %INT%      NOT NULL,
                token          %STR(64)%  NOT NULL,
                kunde_id       %INT%,
                email          %STR(190)% NOT NULL DEFAULT "",
                telefon        %STR(60)%  NOT NULL DEFAULT "",
                status         %STR(20)%  NOT NULL DEFAULT "offen",
                zahlstatus     %STR(30)%  NOT NULL DEFAULT "offen",
                versandstatus  %STR(30)%  NOT NULL DEFAULT "offen",
                waehrung       %STR(3)%   NOT NULL DEFAULT "EUR",
                zwischensumme  %INT%      NOT NULL DEFAULT 0,
                rabatt         %INT%      NOT NULL DEFAULT 0,
                versandkosten  %INT%      NOT NULL DEFAULT 0,
                steuer         %INT%      NOT NULL DEFAULT 0,
                gesamt         %INT%      NOT NULL DEFAULT 0,
                erstattet      %INT%      NOT NULL DEFAULT 0,
                rabattcode     %STR(60)%  NOT NULL DEFAULT "",
                versandart     %STR(120)% NOT NULL DEFAULT "",
                lieferadresse  %TEXT%,
                rechnungsadresse %TEXT%,
                zahlart        %STR(40)%  NOT NULL DEFAULT "",
                zahlreferenz   %STR(190)% NOT NULL DEFAULT "",
                notiz          %TEXT%,
                kundennotiz    %TEXT%,
                storniert_am   %DT%,
                bezahlt_am     %DT%,
                erstellt       %DT%       NOT NULL,
                geaendert      %DT%       NOT NULL
            )%ENGINE%',

            'CREATE TABLE IF NOT EXISTS bestellzeilen (
                id             %PK%,
                bestellung_id  %INT%      NOT NULL,
                varianten_id   %INT%,
                artikel_id     %INT%,
                titel          %STR(200)% NOT NULL,
                variante       %STR(150)% NOT NULL DEFAULT "",
                artikelnummer  %STR(80)%  NOT NULL DEFAULT "",
                bild_url       %STR(255)% NOT NULL DEFAULT "",
                menge          %INT%      NOT NULL DEFAULT 1,
                preis          %INT%      NOT NULL DEFAULT 0,
                rabatt         %INT%      NOT NULL DEFAULT 0,
                gesamt         %INT%      NOT NULL DEFAULT 0,
                steuersatz_bp  %INT%      NOT NULL DEFAULT 0,
                steuer         %INT%      NOT NULL DEFAULT 0,
                versendet      %INT%      NOT NULL DEFAULT 0,
                versandpflicht %INT%      NOT NULL DEFAULT 1
            )%ENGINE%',

            'CREATE TABLE IF NOT EXISTS bestellereignisse (
                id            %PK%,
                bestellung_id %INT%      NOT NULL,
                art           %STR(40)%  NOT NULL,
                text          %TEXT%,
                benutzer_id   %INT%,
                erstellt      %DT%       NOT NULL
            )%ENGINE%',

            'CREATE TABLE IF NOT EXISTS sendungen (
                id             %PK%,
                bestellung_id  %INT%      NOT NULL,
                dienstleister  %STR(80)%  NOT NULL DEFAULT "",
                sendungsnummer %STR(120)% NOT NULL DEFAULT "",
                sendung_url    %STR(255)% NOT NULL DEFAULT "",
                zeilen         %TEXT%,
                erstellt       %DT%       NOT NULL
            )%ENGINE%',

            'CREATE TABLE IF NOT EXISTS erstattungen (
                id            %PK%,
                bestellung_id %INT%      NOT NULL,
                betrag        %INT%      NOT NULL,
                grund         %STR(500)% NOT NULL DEFAULT "",
                referenz      %STR(190)% NOT NULL DEFAULT "",
                benutzer_id   %INT%,
                erstellt      %DT%       NOT NULL
            )%ENGINE%',

            'CREATE TABLE IF NOT EXISTS zahlungen (
                id            %PK%,
                bestellung_id %INT%      NOT NULL,
                anbieter      %STR(40)%  NOT NULL,
                referenz      %STR(190)% NOT NULL DEFAULT "",
                betrag        %INT%      NOT NULL DEFAULT 0,
                waehrung      %STR(3)%   NOT NULL DEFAULT "EUR",
                status        %STR(30)%  NOT NULL DEFAULT "offen",
                rohdaten      %TEXT%,
                erstellt      %DT%       NOT NULL,
                geaendert     %DT%       NOT NULL
            )%ENGINE%',

            // Der Eintrag entsteht vor der Verarbeitung: schlägt sie fehl, ist
            // das Ereignis trotzdem dokumentiert. Die Eindeutigkeit macht die
            // Verarbeitung idempotent – Anbieter liefern Ereignisse doppelt aus.
            'CREATE TABLE IF NOT EXISTS webhooks (
                id           %PK%,
                anbieter     %STR(40)%  NOT NULL,
                ereignis_id  %STR(190)% NOT NULL,
                art          %STR(80)%  NOT NULL DEFAULT "",
                rohdaten     %TEXT%,
                verarbeitet  %DT%,
                fehler       %STR(500)% NOT NULL DEFAULT "",
                erstellt     %DT%       NOT NULL
            )%ENGINE%',

            /* --- Veröffentlichungen --------------------------------------- */

            // Der Kern des Systems: ein Veröffentlichen friert den kompletten
            // Katalog als JSON ein. Der Shop liest nur daraus, nie aus den
            // Arbeitstabellen – Entwürfe können deshalb nie versehentlich
            // sichtbar werden, und ein Rollback ist ein einziges UPDATE.
            'CREATE TABLE IF NOT EXISTS veroeffentlichungen (
                id          %PK%,
                version     %INT%      NOT NULL,
                notiz       %STR(500)% NOT NULL DEFAULT "",
                inhalt      %TEXT%,
                kennzahlen  %TEXT%,
                live        %INT%      NOT NULL DEFAULT 0,
                benutzer_id %INT%,
                erstellt    %DT%       NOT NULL
            )%ENGINE%',
        ];
    }

    /** @return array<int,string> */
    private static function indexes(): array
    {
        return [
            'CREATE UNIQUE INDEX IF NOT EXISTS ux_benutzer_email ON benutzer (email)',
            'CREATE INDEX IF NOT EXISTS ix_sitzungen_benutzer ON sitzungen (benutzer_id)',
            'CREATE UNIQUE INDEX IF NOT EXISTS ux_artikel_handle ON artikel (handle)',
            'CREATE INDEX IF NOT EXISTS ix_artikel_status ON artikel (status)',
            'CREATE INDEX IF NOT EXISTS ix_bilder_artikel ON artikel_bilder (artikel_id)',
            'CREATE INDEX IF NOT EXISTS ix_optionen_artikel ON artikel_optionen (artikel_id)',
            'CREATE INDEX IF NOT EXISTS ix_varianten_artikel ON varianten (artikel_id)',
            'CREATE INDEX IF NOT EXISTS ix_varianten_nummer ON varianten (artikelnummer)',
            'CREATE INDEX IF NOT EXISTS ix_bewegungen_variante ON bestandsbewegungen (varianten_id)',
            'CREATE UNIQUE INDEX IF NOT EXISTS ux_kategorien_handle ON kategorien (handle)',
            'CREATE UNIQUE INDEX IF NOT EXISTS ux_seiten_handle ON seiten (handle)',
            'CREATE UNIQUE INDEX IF NOT EXISTS ux_beitraege_handle ON beitraege (handle)',
            'CREATE INDEX IF NOT EXISTS ix_menue ON menuepunkte (menue, position)',
            'CREATE INDEX IF NOT EXISTS ix_bausteine ON bausteine (seite_id, position)',
            'CREATE UNIQUE INDEX IF NOT EXISTS ux_kunden_email ON kunden (email)',
            'CREATE INDEX IF NOT EXISTS ix_adressen_kunde ON adressen (kunde_id)',
            'CREATE INDEX IF NOT EXISTS ix_versandarten_zone ON versandarten (zone_id)',
            'CREATE UNIQUE INDEX IF NOT EXISTS ux_rabatte_code ON rabatte (code)',
            'CREATE UNIQUE INDEX IF NOT EXISTS ux_warenkoerbe_token ON warenkoerbe (token)',
            'CREATE UNIQUE INDEX IF NOT EXISTS ux_warenkorb_zeile ON warenkorb_zeilen (warenkorb_id, varianten_id)',
            'CREATE UNIQUE INDEX IF NOT EXISTS ux_bestellungen_nummer ON bestellungen (nummer)',
            'CREATE UNIQUE INDEX IF NOT EXISTS ux_bestellungen_token ON bestellungen (token)',
            'CREATE INDEX IF NOT EXISTS ix_bestellungen_kunde ON bestellungen (kunde_id)',
            'CREATE INDEX IF NOT EXISTS ix_bestellungen_erstellt ON bestellungen (erstellt)',
            'CREATE INDEX IF NOT EXISTS ix_bestellzeilen ON bestellzeilen (bestellung_id)',
            'CREATE INDEX IF NOT EXISTS ix_bestellereignisse ON bestellereignisse (bestellung_id)',
            'CREATE INDEX IF NOT EXISTS ix_zahlungen_bestellung ON zahlungen (bestellung_id)',
            'CREATE UNIQUE INDEX IF NOT EXISTS ux_webhooks ON webhooks (anbieter, ereignis_id)',
            'CREATE UNIQUE INDEX IF NOT EXISTS ux_veroeffentlichungen ON veroeffentlichungen (version)',
            'CREATE INDEX IF NOT EXISTS ix_protokoll_erstellt ON protokoll (erstellt)',
        ];
    }

    private static function translate(string $sql): string
    {
        // Kommentarzeilen aus der DDL entfernen – sie dienen nur der Lesbarkeit.
        $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;

        if (DB::isSqlite()) {
            $map = [
                '%PK%'     => 'INTEGER PRIMARY KEY AUTOINCREMENT',
                '%INT%'    => 'INTEGER',
                '%TEXT%'   => 'TEXT',
                '%DT%'     => 'TEXT',
                '%ENGINE%' => '',
            ];
            $sql = preg_replace('/%STR\((\d+)\)%/', 'TEXT', $sql) ?? $sql;
        } else {
            $map = [
                '%PK%'     => 'INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY',
                '%INT%'    => 'INT',
                '%TEXT%'   => 'LONGTEXT',
                '%DT%'     => 'VARCHAR(19)',
                '%ENGINE%' => ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            ];
            $sql = preg_replace('/%STR\((\d+)\)%/', 'VARCHAR($1)', $sql) ?? $sql;
        }
        // Doppelte Anführungszeichen in der DDL sind Standard-SQL für
        // Bezeichner; als Vorgabewerte braucht MySQL einfache.
        if (!DB::isSqlite()) {
            $sql = preg_replace('/DEFAULT "([^"]*)"/', "DEFAULT '$1'", $sql) ?? $sql;
        }
        return strtr($sql, $map);
    }
}
