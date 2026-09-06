<?php
/** Anmeldung am Backend. */

require dirname(__DIR__) . '/lib/bootstrap.php';

if (Auth::angemeldet()) {
    Util::redirect('index.php');
}

$fehler = '';
$email  = '';

if (Util::isPost()) {
    $email  = Util::post('email');
    $fehler = Auth::login($email, Util::postRaw('passwort'));
    if ($fehler === '') {
        $weiter = Util::post('weiter');
        // Nur eigene Pfade als Ziel zulassen – sonst wäre die Anmeldung ein
        // offener Weiterleiter auf fremde Seiten.
        $ziel = (str_starts_with($weiter, '/') && !str_starts_with($weiter, '//')) ? $weiter : 'index.php';
        Util::redirect($ziel);
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Anmelden – Backend</title>
<link rel="stylesheet" href="assets/admin.css">
</head>
<body>
<div class="bk-login-wrap">
  <form class="bk-login" method="post">
    <h1><?= Util::e(Settings::get('shop_name', 'Shop')) ?></h1>
    <p class="bk-sub">Melde dich an, um Artikel, Bestellungen und Inhalte zu pflegen.</p>

    <?php if ($fehler !== ''): ?>
      <div class="bk-hinweis bk-hinweis-fehler"><?= Util::e($fehler) ?></div>
    <?php endif; ?>

    <input type="hidden" name="weiter" value="<?= Util::e(Util::get('weiter')) ?>">
    <div class="bk-feld">
      <label for="email">E-Mail-Adresse</label>
      <input type="email" id="email" name="email" required autocomplete="username" autofocus
             value="<?= Util::e($email) ?>">
    </div>
    <div class="bk-feld">
      <label for="passwort">Passwort</label>
      <input type="password" id="passwort" name="passwort" required autocomplete="current-password">
    </div>
    <button class="bk-knopf bk-knopf-voll" type="submit" style="width:100%">Anmelden</button>
  </form>
</div>
</body>
</html>
