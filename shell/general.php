<?php

include_once 'coin.php';


$general = new Coin();
// $general->downloadBasicFiles();
// $general->saveAllCurrencies();
$general->isTestMode = false;
$general->isNewOnly = false;

$general->generateCoinToWorldCurrencyConvert();
// $general->generateIntroductions();
$general->generateCryptoCurrencies();
$general->generateHistorical();
$general->generateDiscusstion();
$general->generateMarket();
$general->generateMining();
$general->generateNews();
$general->generateTrend();

