<?php

include_once 'coin.php';


$general = new Coin();
// $general->downloadBasicFiles();
// $general->saveAllCurrencies();
$general->isTestMode = false;
$general->isNewOnly = false;
$general->currentLanguage = 'en';

$general->generateCryptoCurrencies();



