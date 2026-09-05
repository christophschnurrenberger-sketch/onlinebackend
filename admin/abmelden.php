<?php
/** Abmelden – beendet die Sitzung sofort und serverseitig. */
require dirname(__DIR__) . '/lib/bootstrap.php';
Auth::logout();
Util::redirect('login.php');
