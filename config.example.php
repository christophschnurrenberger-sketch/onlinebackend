<?php
/**
 * Beispiel-Konfiguration.
 *
 * Normalerweise wird config.php automatisch von install.php erzeugt.
 * Diese Datei zeigt nur, welche Werte darin stehen – etwa zum Umziehen auf
 * einen anderen Server oder für eine Sicherung.
 *
 * Alle weiteren Einstellungen (Shopname, Zahlarten, Versandkosten, Design,
 * Texte) liegen in der Datenbank und werden im Backend gepflegt.
 */

return [
    // Adresse des Shop-Ordners, ohne Schrägstrich am Ende.
    // Darauf bauen alle Links, Rückleitungen der Zahlungsanbieter und die
    // Adressen in den Bestellmails auf.
    'base_url' => 'https://www.mein-shop.de/shop',

    // Zufälliger Schlüssel für Signaturen und die Verschlüsselung der
    // Zugangsdaten (Stripe, PayPal, SMTP). NIEMALS ändern, solange Daten im
    // System sind – sonst werden gespeicherte Zugangsdaten unlesbar.
    // Erzeugen mit:  php -r "echo bin2hex(random_bytes(32));"
    'secret' => 'HIER_EINEN_ZUFALLSWERT_EINSETZEN',

    // Datenbank – entweder SQLite (eine Datei, nichts einzurichten) …
    'db' => [
        'driver' => 'sqlite',
        'path'   => __DIR__ . '/data/shop.sqlite',
    ],

    // … oder MySQL/MariaDB:
    // 'db' => [
    //     'driver' => 'mysql',
    //     'host'   => 'localhost',
    //     'port'   => 3306,
    //     'name'   => 'db1234567',
    //     'user'   => 'dbo1234567',
    //     'pass'   => 'geheim',
    // ],
];
