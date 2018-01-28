<?php

if (!defined('MAGE_ROOT')) {
    define('MAGE_ROOT', getcwd());
}

include MAGE_ROOT . '/shell.php';

class Coin extends Shell
{

    public $dataPath = MAGE_ROOT . '/../_data';
    public $dataCoinPath = MAGE_ROOT . '/../_data/coins';
    public $coinPath = MAGE_ROOT . '/_source/coins';
    public $coinListPath = MAGE_ROOT . '/_source/coinlist.json';
    public $coinListCMCPath = MAGE_ROOT . '/_source/coinlist_coinmarketcap.json';
    public $fullDataCoinPath = MAGE_ROOT . '/_source/coinlist_full.json';
    public $coinRankPath = MAGE_ROOT . '/_source/coinsrank.json';
    public $currentCoin = null;
    public $currentSym = null;

    public $defaultCurrency = 'USD';
    public $defaultToCurrencies = 'USD,BTC,EUR,ETH,USDT,BNB';

    public $currentLanguage = 'en';
    public $currentCoinSnapshot = [];
    public $currentCoinSnapshotFull = [];
    public $btcPrice = 0;
    public $limit = 100;


    public function __construct()
    {
        $this->btcPrice = $this->getBtcPrice();

    }

    /**
     * download basic files
     */
    public function downloadBasicFiles()
    {
        $list = [

            //Daily
            'coinlist.json' => 'https://min-api.cryptocompare.com/data/all/coinlist',

            'coinlist_coinmarketcap.json' => 'https://api.coinmarketcap.com/v1/ticker/?limit=0',

            //3 days
            'exchanges.json' => 'https://min-api.cryptocompare.com/data/all/exchanges',

            //7 days
            'currencies.json' => 'https://openexchangerates.org/api/currencies.json',


            //Minutes: Stream
            // 'price.json' => 'https://min-api.cryptocompare.com/data/price?fsym=ETH&tsyms=BTC,USD,EUR',
            // 'pricehistorical.json' => 'https://min-api.cryptocompare.com/data/pricehistorical?fsym=ETH&tsyms=BTC,USD,EUR',

        ];

        foreach ($list as $fileName => $url) {
            try {
                $path = $this->basicFileDataPath . '/' . $fileName;
                $content = file_get_contents($url);
                $content = json_encode(json_decode($content), JSON_PRETTY_PRINT);
                file_put_contents($path, $content);
            } catch (Exception $e) {

            }

        }
    }


    /**
     * get all coin list
     * @return array
     */
    public function getCoinList()
    {
        $coinList = $this->getDataFromJsonFile($this->coinListPath);

        return $coinList['Data'];
    }

    public function getCoinCMCList()
    {
        $coinList = $this->getDataFromJsonFile($this->coinListCMCPath);
        return $coinList;
    }

    /**
     * Run download all coin snapshot
     */
    public function downloadAllCoinsData()
    {
        $coinList = $this->getDataFromJsonFile($this->coinListPath);
        echo 'Coins: ' . count($coinList['Data']) . "\n";

        foreach ($coinList['Data'] as $sym => $_coin) {

            //check allow
            if ($this->isTestMode() && !in_array($sym, $this->testCoinList())) continue;

            $this->currentCoin = $_coin;
            $this->currentSym = $sym;

            //set: new only + not exist
            if ($this->isNewOnly && !$this->isNewCoin()) continue;


            // 1. make dir
            echo 'sym: ' . $sym . "\n";
            $this->createFolder($this->getCoinPath());
            $this->createFolder($this->getDataCoinPath());

            //2. download media
            if ($this->isNewCoin()) {

                // image url
                $imageUrl = 'https://www.cryptocompare.com' . $this->currentCoin['ImageUrl'];

                // todo: download
            }


            //3.2. history 30 days
            $this->getHistoday(30);
            //3.1. history today
            //Gop thanh lifetime
            //3.2. history 7 days
            //3.2. history all time


            // 4. coinsnapshotfullbyid
            $this->downloadCoinSnapshotFullInfo();

            //5. coinsnapshot
            $this->downloadCoinSnapshort();


            //6. socials statistic
            $this->downloadCoinSocialStats();

            //7. rewrite intro (content marketing)


        }


    }


    /**
     * download all coin snapshot
     */
    public function downloadAllCoinsSnapshot()
    {
        $coinList = $this->getDataFromJsonFile($this->coinListPath);
        echo 'Coins: ' . count($coinList['Data']) . "\n";

        foreach ($coinList['Data'] as $sym => $_coin) {

            //check allow
            if ($this->isTestMode() && !in_array($sym, $this->testCoinList())) continue;

            $this->currentCoin = $_coin;
            $this->currentSym = $sym;


            //set: new only + not exist
            if ($this->isNewOnly && !$this->isNewCoin()) continue;

            // 1. make dir
            echo 'sym: ' . $sym . "\n";
            $this->createFolder($this->getCoinPath());
            $this->createFolder($this->getDataCoinPath());

            $this->downloadCoinSnapshot();


        }


    }


    /**
     * download coin snapshot
     */
    public function downloadCoinSnapshot()
    {
        //Old URL: https://www.cryptocompare.com/api/data/coinsnapshot/?fsym=BTC&tsym=USD
        switch ($this->getCurrentSym()) {
            case 'BTC':
                $coinSnapshotUrl = $this->oldEndpoint . '/data/coinsnapshot/?fsym=' . $this->getCurrentSym() . '&tsym=USD';
                echo $coinSnapshotUrl . "\n";
                $path = $this->getCoinPath() . '/coinsnapshot.json';
                $this->saveCoinSnapshot($coinSnapshotUrl, $path, true, true);

                break;
            default:
                $coinSnapshotUrl = $this->oldEndpoint . '/data/coinsnapshot/?fsym=' . $this->getCurrentSym() . '&tsym=BTC';
                echo $coinSnapshotUrl . "\n";
                $path = $this->getCoinPath() . '/coinsnapshot.json';
                $this->saveCoinSnapshot($coinSnapshotUrl, $path, true, true);

                break;

        }

    }

    /**
     * downlaod coin snapshot full information
     */
    public function downloadCoinSnapshotFullInfo()
    {
        ////https://www.cryptocompare.com/api/data/coinsnapshotfullbyid/?id=1182
        $coinsnapshotfullinfourl = $this->oldEndpoint . '/data/coinsnapshotfullbyid/?id=' . $this->currentCoin['Id'];
        $path = $this->getCoinPath() . '/coinSnapshotFullInfo.json';
        $this->saveUrlTo($coinsnapshotfullinfourl, $path, true, true);

        //ADD NEW: override meta data: description, features
        if (!is_file($this->getCoinPath() . '/coinSnapshotFullInfo_MetaData.json')) {
            $path = $this->getCoinPath() . '/coinSnapshotFullInfo_MetaData.json';
            $this->saveDataToJsonFile($this->getCoinDefaultMetaData(), $path);
        }
    }

    /**
     * downlaod coin's social statistic
     */
    public function downloadCoinSocialStats()
    {
        //old url: https://www.cryptocompare.com/api/data/socialstats/?id=1182
        $socialStatsUrl = $this->oldEndpoint . '/data/socialstats/?id=' . $this->currentCoin['Id'];
        $path = $this->getCoinPath() . '/socialstats.json';
        $this->saveUrlTo($socialStatsUrl, $path, true, true);
    }

    /**
     * get current coin path
     * @return string
     */
    public function getCoinPath()
    {
        return $this->coinPath . '/' . $this->getCurrentSym(true);
    }

    /**
     * get current coin path in jekyll _data folder
     * @return string
     */
    public function getDataCoinPath()
    {
        return $this->dataCoinPath . '/' . $this->getCurrentSym(true);
    }


    /**
     * get coin file data
     * @param $fileName
     * @return array|mixed
     */
    public function getCoinFileData($fileName)
    {
        $path = $this->getCoinPath() . '/' . $fileName . '.json';
        $data = $this->getDataFromJsonFile($path);

        return $data;
    }


    /**
     * get current Coin symbol
     * @param bool $filter
     * @return mixed
     */
    public function getCurrentSym($filter = false)
    {
        $sym = $this->currentSym;
        if ($filter) {
            $sym = $this->filterSymbol($sym);
        }

        return $sym;
    }

    /**
     * fixed duplicate symbol.
     * Mot so coin ra sau bi trung Symbol voi nhau: 92 coin duplicated symbol
     * @param $sym
     * @return mixed
     */
    public function filterSymbol($sym)
    {
        $sym = str_replace(['*'], ['-'], $sym);

        return $sym;
    }

    /**
     * get coin data by key
     * @param $key
     * @return null
     */
    public function getCoinData($key)
    {
        //can co ham get data
        return isset($this->currentCoin[$key]) ? $this->currentCoin[$key] : null;
    }


    /**
     * Check is new coin by getting coin folder
     * @return bool
     */
    public function isNewCoin()
    {
        if (is_dir($this->getCoinPath())) {
            return true;
        } else {
            return false;
        }
    }


    /**
     * get coin historcal data by x day
     * @param int $day
     */
    public function getHistoday($day = 7)
    {
        $histoDayUrl = $this->endpoint . '/data/histoday?fsym=' . $this->getCurrentSym() . '&tsym=' . $this->defaultCurrency . '&limit=' . $day . '&aggregate=3&e=CCCAGG';

        //Save to _source folder, PHP will process this later
        $path = $this->getCoinPath() . '/history' . $day . 'd.json';
        $this->saveUrlTo($histoDayUrl, $path, true, true);

        //Save to Jekyll _data folder
        $path = $this->getDataCoinPath() . '/history' . $day . 'd.json';
//		$this->saveUrlTo($histoDayUrl, $path, true, true);
    }


    /**
     * genreate coin default meta data
     * @return array
     */
    public function getCoinDefaultMetaData()
    {
        $data = [
            'is_approved' => "false",
            'SEO' => [],
            'General' => [
                'Description' => '',
                'Features' => '',
                'Technology' => '',
                'Sponsor' => '',
            ],
            'ICO' => [
                'Description' => ''
            ],
        ];

        return $data;
    }


    /**
     * get world currencies data
     * @return array|mixed
     */
    public function getCurrenciesData()
    {
        return $this->getDataFromJsonFile($this->basicFileDataPath . '/currencies.json');
    }

    public function getMainCurrenciesData()
    {
        $list = $this->getDataFromJsonFile($this->basicFileDataPath . '/currencies_main.json');
        $currencies = $this->getCurrenciesData();
        $mainCurrencies = [];
        foreach ($list as $sym) {
            $mainCurrencies[$sym] = $currencies[$sym];
        }
        return $mainCurrencies;
    }


    /**
     * get world currencies meta data
     * @return array|mixed
     */
    public function getCurrenciesMetaData()
    {
        return $this->getDataFromJsonFile($this->basicFileDataPath . '/currencies_metadata.json');
    }


    /**
     * get coin list
     * @return array
     */
    public function testCoinList()
    {
        return ['BTC', 'ETH', 'TRO', 'BNB'];
    }

    public function testCurrenciesList()
    {
        return ['USD', 'EUR', 'KRW', 'VND'];
    }


    /**
     * generate Coin intro for COntent marketing team
     */
    public function generateIntroductions()
    {


        $this->templateFile = '_templates/backend_coin_intro.html';
        $this->outputFolder = '../_introductions';
        $i = 1;
        foreach ($this->getCoinCMCList() as $key => $coin) {
            // var_dump($coin['symbol']);die;
            $sym = $coin['symbol'];
            $this->initCoin($sym);

            //test mode
            if ($this->isTestMode()) {
                if (!in_array($sym, $this->testCoinList())) continue;
            }
            if (empty($this->currentCoin)) continue;

            $coinSnapshotFullInfo = $this->getCoinSnapshotFull();
            $_coin = $this->currentCoin;
            $_coin['Description'] = isset($coinSnapshotFullInfo['Data']['General']['Description']) ? $coinSnapshotFullInfo['Data']['General']['Description'] : null;
            $_coin['Features'] = isset($coinSnapshotFullInfo['Data']['General']['Features']) ? $coinSnapshotFullInfo['Data']['General']['Features'] : null;
            $_coin['Technology'] = isset($coinSnapshotFullInfo['Data']['General']['Technology']) ? $coinSnapshotFullInfo['Data']['General']['Technology'] : null;
            $_coin['AffiliateUrl'] = isset($coinSnapshotFullInfo['Data']['General']['AffiliateUrl']) ? $coinSnapshotFullInfo['Data']['General']['AffiliateUrl'] : null;
            $_coin['Url'] = isset($coinSnapshotFullInfo['Data']['General']['Url']) ? $coinSnapshotFullInfo['Data']['General']['Url'] : null;
            $_coin['sitemap'] = "false";
            $_coin['sort_order'] = $i;

            $_coin['tag'] = $this->slug($this->currentCoin['Name']);
            $_coin['url'] = '/backend/' . $this->slug($this->getCurrentSym(true)) . '-' . $this->currentCoin['Id'] . '.html';
            $this->setVars($_coin);
            $this->tag = $_coin['tag'];
            $file = '2018-01-01-' . $this->slug($this->getCurrentSym(true)) . '-' . $this->currentCoin['Id'] . '.md';
            $this->generateFile($file);
            $i++;
        }
    }



    // domain.com/coinA/USD/
    // domain.com/BTC/USD
    /**
     * Generate Coin to Currency converter tool
     */
    public function generateCoinToWorldCurrencyConvert()
    {


        $this->templateFile = '_templates/calculator.html';
        $this->outputFolder = '../conversations';
        $i = 0;
        foreach ($this->getCoinCMCList() as $_coinA) {

            $sym = $_coinA['symbol'];
            $this->currentSym = $sym;
            $_coin = $this->loadCoinBySymbol($sym);
            if(!$_coin) continue;

            $_coin = array_merge($_coin, $_coinA);

            //World currencies
            foreach ($this->getMainWorldCurrencies() as $symB => $_coinB) {

                if ($sym == $symB) continue; //check if it is SAME coin

                //test mode
                if ($this->isTestMode() && !in_array($sym, $this->testCoinList())) continue;
                if ($this->isTestMode() && !in_array($symB, $this->testCurrenciesList())) continue;

                $this->currentCoin = $_coin;

                $coinSnapshotFullInfo = $this->getCoinFileData('coinSnapshotFullInfo');

                $_coin['Description'] = isset($coinSnapshotFullInfo['Data']['General']['Description']) ? $coinSnapshotFullInfo['Data']['General']['Description'] : null;
                $_coin['Features'] = isset($coinSnapshotFullInfo['Data']['General']['Features']) ? $coinSnapshotFullInfo['Data']['General']['Features'] : null;
                $_coin['Technology'] = isset($coinSnapshotFullInfo['Data']['General']['Technology']) ? $coinSnapshotFullInfo['Data']['General']['Technology'] : null;
                $_coin['AffiliateUrl'] = isset($coinSnapshotFullInfo['Data']['General']['AffiliateUrl']) ? $coinSnapshotFullInfo['Data']['General']['AffiliateUrl'] : null;
                $_coin['Url'] = isset($coinSnapshotFullInfo['Data']['General']['Url']) ? $coinSnapshotFullInfo['Data']['General']['Url'] : null;
                $_coin['TotalCoinSupply'] = isset($coinSnapshotFullInfo['Data']['General']['TotalCoinSupply']) ? $coinSnapshotFullInfo['Data']['General']['TotalCoinSupply'] : null;

                $coinSnapshotFullInfoMetaData = $this->getCoinFileData('coinSnapshotFullInfo_MetaData');

                //Check allow display
                $_coin['allow_search_engine'] = isset($coinSnapshotFullInfoMetaData['is_approved']) ? $coinSnapshotFullInfoMetaData['is_approved'] : "false";
                $_coin['sitemap'] = isset($coinSnapshotFullInfoMetaData['is_approved']) ? $coinSnapshotFullInfoMetaData['is_approved'] : "false";
                $_coin['enable_comment'] = "false";


                $symB = $this->filterSymbol($symB);

                $_coin['Name'] = $sym;
                $_coin['coin_a_lower_case'] = $this->slug($sym);
                $_coin['coin_b_symbol'] = $symB;
                $_coin['coin_b_name'] = $_coinB;
                $_coin['coin_a_b_rate'] = $_coin['price_usd'];
                $_coin['coin_b_a_rate'] = (1/$_coin['price_usd']);

                $_coin['title'] = $_coin['name'] . ' to ' .  $symB;
                $_coin['meta-title'] = '';
                $_coin['meta-description'] = '';
                $_coin['h1-title'] = '';

//                $_coin['all_currencies_options_a'] = $this->generateCurrenciesOptions($sym, $this->getCryptoCurrencies());
//                $_coin['all_currencies_options_b'] = $this->generateCurrenciesOptions($symB, $this->getWorldCurrencies());

                $_coin['parent-url'] = '/' . $this->currentLanguage . '/' .  $this->slug($sym) . '/';

                $_coin['url'] = '/' . $this->currentLanguage . '/' . $this->slug($sym) . '/' . $this->slug($this->filterSymbol($symB)) . '.html';

                $this->setVars($_coin);
                $file = '2018-01-01-' . $this->currentLanguage . '-' . $this->getCurrentSym(true) . '-' . $symB . '.md';
                $this->generateFile($file);
                echo $sym . " vs " . $symB . "\n";

            }


        }


    }


    public function generateCryptoCurrencies(){
        
        $this->templateFile = '_templates/overview.html';
        $this->outputFolder = '../_currencies';
        $i = 0;
        foreach ($this->getCoinCMCList() as $_coinA) {

            $sym = $_coinA['symbol'];
            $this->currentSym = $sym;
            $_coin = $this->loadCoinBySymbol($sym);
            if(!$_coin) continue;

                $_coin = array_merge($_coin, $_coinA);

                //test mode
                if ($this->isTestMode() && !in_array($sym, $this->testCoinList())) continue;

                $this->currentCoin = $_coin;

                $coinSnapshotFullInfo = $this->getCoinFileData('coinSnapshotFullInfo');

                
                $_coin['Description'] = isset($coinSnapshotFullInfo['Data']['General']['Description']) ? $coinSnapshotFullInfo['Data']['General']['Description'] : null;
                $_coin['Features'] = isset($coinSnapshotFullInfo['Data']['General']['Features']) ? $coinSnapshotFullInfo['Data']['General']['Features'] : null;
                $_coin['Technology'] = isset($coinSnapshotFullInfo['Data']['General']['Technology']) ? $coinSnapshotFullInfo['Data']['General']['Technology'] : null;
                $_coin['AffiliateUrl'] = isset($coinSnapshotFullInfo['Data']['General']['AffiliateUrl']) ? $coinSnapshotFullInfo['Data']['General']['AffiliateUrl'] : null;
                $_coin['Url'] = isset($coinSnapshotFullInfo['Data']['General']['Url']) ? $coinSnapshotFullInfo['Data']['General']['Url'] : null;
                $_coin['TotalCoinSupply'] = isset($coinSnapshotFullInfo['Data']['General']['TotalCoinSupply']) ? $coinSnapshotFullInfo['Data']['General']['TotalCoinSupply'] : null;

                $coinSnapshotFullInfoMetaData = $this->getCoinFileData('coinSnapshotFullInfo_MetaData');

                //Check allow display
                $_coin['allow_search_engine'] = isset($coinSnapshotFullInfoMetaData['is_approved']) ? $coinSnapshotFullInfoMetaData['is_approved'] : "false";
                $_coin['sitemap'] = isset($coinSnapshotFullInfoMetaData['is_approved']) ? $coinSnapshotFullInfoMetaData['is_approved'] : "false";
                $_coin['enable_comment'] = "false";
                $_coin['description'] = $coinSnapshotFullInfoMetaData['General']['Description'];
                $_coin['features'] = $coinSnapshotFullInfoMetaData['General']['Features'];
                $_coin['technology'] = $coinSnapshotFullInfoMetaData['General']['Technology'];



                $_coin['Name'] = $sym;
                $_coin['coin_a_lower_case'] = $this->slug($sym);

                $_coin['title'] = $_coin['name'] ;
                $_coin['meta-title'] = '';
                $_coin['meta-description'] = '';
                $_coin['h1-title'] = '';

                $_coin['parent-url'] = '/' . $this->currentLanguage . '/' .  $this->slug($sym) . '/';
                $_coin['url'] = '/' . $this->currentLanguage . '/' . $this->slug($sym) . '/';

                $this->setVars($_coin);
                $file = '2018-01-01-' . $this->currentLanguage . '-' . $this->getCurrentSym(true) . '.md';
                $this->generateFile($file);
                echo $sym . " generated \n";
            }
    }

    public function generateDiscusstion(){
        
        $this->templateFile = '_templates/discussion.html';
        $this->outputFolder = '../_discussion';
        $i = 0;
        foreach ($this->getCoinCMCList() as $_coinA) {

            $sym = $_coinA['symbol'];
            $this->currentSym = $sym;
            $_coin = $this->loadCoinBySymbol($sym);
            if(!$_coin) continue;

                $_coin = array_merge($_coin, $_coinA);

                //test mode
                if ($this->isTestMode() && !in_array($sym, $this->testCoinList())) continue;

                $this->currentCoin = $_coin;

                $coinSnapshotFullInfo = $this->getCoinFileData('coinSnapshotFullInfo');

                
                $_coin['Description'] = isset($coinSnapshotFullInfo['Data']['General']['Description']) ? $coinSnapshotFullInfo['Data']['General']['Description'] : null;
                $_coin['Features'] = isset($coinSnapshotFullInfo['Data']['General']['Features']) ? $coinSnapshotFullInfo['Data']['General']['Features'] : null;
                $_coin['Technology'] = isset($coinSnapshotFullInfo['Data']['General']['Technology']) ? $coinSnapshotFullInfo['Data']['General']['Technology'] : null;
                $_coin['AffiliateUrl'] = isset($coinSnapshotFullInfo['Data']['General']['AffiliateUrl']) ? $coinSnapshotFullInfo['Data']['General']['AffiliateUrl'] : null;
                $_coin['Url'] = isset($coinSnapshotFullInfo['Data']['General']['Url']) ? $coinSnapshotFullInfo['Data']['General']['Url'] : null;
                $_coin['TotalCoinSupply'] = isset($coinSnapshotFullInfo['Data']['General']['TotalCoinSupply']) ? $coinSnapshotFullInfo['Data']['General']['TotalCoinSupply'] : null;

                $coinSnapshotFullInfoMetaData = $this->getCoinFileData('coinSnapshotFullInfo_MetaData');

                //Check allow display
                $_coin['allow_search_engine'] = isset($coinSnapshotFullInfoMetaData['is_approved']) ? $coinSnapshotFullInfoMetaData['is_approved'] : "false";
                $_coin['sitemap'] = isset($coinSnapshotFullInfoMetaData['is_approved']) ? $coinSnapshotFullInfoMetaData['is_approved'] : "false";
                $_coin['enable_comment'] = "false";
                $_coin['description'] = $coinSnapshotFullInfoMetaData['General']['Description'];
                $_coin['features'] = $coinSnapshotFullInfoMetaData['General']['Features'];
                $_coin['technology'] = $coinSnapshotFullInfoMetaData['General']['Technology'];



                $_coin['Name'] = $sym;
                $_coin['coin_a_lower_case'] = $this->slug($sym);

                $_coin['title'] = $_coin['name'] ;
                $_coin['meta-title'] = '';
                $_coin['meta-description'] = '';
                $_coin['h1-title'] = '';

                $_coin['parent-url'] = '/' . $this->currentLanguage . '/' .  $this->slug($sym) . '/';

                $_coin['url'] = '/' . $this->currentLanguage . '/' . $this->slug($sym) . '/discussion.html';

                $this->setVars($_coin);
                $file = '2018-01-01-' . $this->currentLanguage . '-' . $this->getCurrentSym(true) . '-discussion.md';
                $this->generateFile($file);
                echo $sym . " discussion generated \n";
            }
    }


    public function generateHistorical(){
        
        $this->templateFile = '_templates/historical.html';
        $this->outputFolder = '../_historical';
        $i = 0;
        foreach ($this->getCoinCMCList() as $_coinA) {

            $sym = $_coinA['symbol'];
            $this->currentSym = $sym;
            $_coin = $this->loadCoinBySymbol($sym);
            if(!$_coin) continue;

                $_coin = array_merge($_coin, $_coinA);

                //test mode
                if ($this->isTestMode() && !in_array($sym, $this->testCoinList())) continue;

                $this->currentCoin = $_coin;

                $coinSnapshotFullInfo = $this->getCoinFileData('coinSnapshotFullInfo');

                
                $_coin['Description'] = isset($coinSnapshotFullInfo['Data']['General']['Description']) ? $coinSnapshotFullInfo['Data']['General']['Description'] : null;
                $_coin['Features'] = isset($coinSnapshotFullInfo['Data']['General']['Features']) ? $coinSnapshotFullInfo['Data']['General']['Features'] : null;
                $_coin['Technology'] = isset($coinSnapshotFullInfo['Data']['General']['Technology']) ? $coinSnapshotFullInfo['Data']['General']['Technology'] : null;
                $_coin['AffiliateUrl'] = isset($coinSnapshotFullInfo['Data']['General']['AffiliateUrl']) ? $coinSnapshotFullInfo['Data']['General']['AffiliateUrl'] : null;
                $_coin['Url'] = isset($coinSnapshotFullInfo['Data']['General']['Url']) ? $coinSnapshotFullInfo['Data']['General']['Url'] : null;
                $_coin['TotalCoinSupply'] = isset($coinSnapshotFullInfo['Data']['General']['TotalCoinSupply']) ? $coinSnapshotFullInfo['Data']['General']['TotalCoinSupply'] : null;

                $coinSnapshotFullInfoMetaData = $this->getCoinFileData('coinSnapshotFullInfo_MetaData');

                //Check allow display
                $_coin['allow_search_engine'] = isset($coinSnapshotFullInfoMetaData['is_approved']) ? $coinSnapshotFullInfoMetaData['is_approved'] : "false";
                $_coin['sitemap'] = isset($coinSnapshotFullInfoMetaData['is_approved']) ? $coinSnapshotFullInfoMetaData['is_approved'] : "false";
                $_coin['enable_comment'] = "false";


                $_coin['Name'] = $sym;
                $_coin['coin_a_lower_case'] = $this->slug($sym);

                $_coin['title'] = $_coin['name'] ;
                $_coin['meta-title'] = '';
                $_coin['meta-description'] = '';
                $_coin['h1-title'] = '';

                $_coin['parent-url'] = '/' . $this->currentLanguage . '/' .  $this->slug($sym) . '/';

                $_coin['url'] = '/' . $this->currentLanguage . '/' . $this->slug($sym) . '/discussion.html';

                $this->setVars($_coin);
                $file = '2018-01-01-' . $this->currentLanguage . '-' . $this->getCurrentSym(true) . '-discussion.md';
                $this->generateFile($file);
                echo $sym . " discussion generated \n";
            }
    }

    public function generateMarket(){
        
        $this->templateFile = '_templates/market.html';
        $this->outputFolder = '../_market';
        $i = 0;
        foreach ($this->getCoinCMCList() as $_coinA) {

            $sym = $_coinA['symbol'];
            $this->currentSym = $sym;
            $_coin = $this->loadCoinBySymbol($sym);
            if(!$_coin) continue;

                $_coin = array_merge($_coin, $_coinA);

                //test mode
                if ($this->isTestMode() && !in_array($sym, $this->testCoinList())) continue;

                $this->currentCoin = $_coin;

                $coinSnapshotFullInfo = $this->getCoinFileData('coinSnapshotFullInfo');

                
                $_coin['Description'] = isset($coinSnapshotFullInfo['Data']['General']['Description']) ? $coinSnapshotFullInfo['Data']['General']['Description'] : null;
                $_coin['Features'] = isset($coinSnapshotFullInfo['Data']['General']['Features']) ? $coinSnapshotFullInfo['Data']['General']['Features'] : null;
                $_coin['Technology'] = isset($coinSnapshotFullInfo['Data']['General']['Technology']) ? $coinSnapshotFullInfo['Data']['General']['Technology'] : null;
                $_coin['AffiliateUrl'] = isset($coinSnapshotFullInfo['Data']['General']['AffiliateUrl']) ? $coinSnapshotFullInfo['Data']['General']['AffiliateUrl'] : null;
                $_coin['Url'] = isset($coinSnapshotFullInfo['Data']['General']['Url']) ? $coinSnapshotFullInfo['Data']['General']['Url'] : null;
                $_coin['TotalCoinSupply'] = isset($coinSnapshotFullInfo['Data']['General']['TotalCoinSupply']) ? $coinSnapshotFullInfo['Data']['General']['TotalCoinSupply'] : null;

                $coinSnapshotFullInfoMetaData = $this->getCoinFileData('coinSnapshotFullInfo_MetaData');

                //Check allow display
                $_coin['allow_search_engine'] = isset($coinSnapshotFullInfoMetaData['is_approved']) ? $coinSnapshotFullInfoMetaData['is_approved'] : "false";
                $_coin['sitemap'] = isset($coinSnapshotFullInfoMetaData['is_approved']) ? $coinSnapshotFullInfoMetaData['is_approved'] : "false";
                $_coin['enable_comment'] = "false";


                $_coin['Name'] = $sym;
                $_coin['coin_a_lower_case'] = $this->slug($sym);

                $_coin['title'] = $_coin['name'] ;
                $_coin['meta-title'] = '';
                $_coin['meta-description'] = '';
                $_coin['h1-title'] = '';

                $_coin['parent-url'] = '/' . $this->currentLanguage . '/' .  $this->slug($sym) . '/';

                $_coin['url'] = '/' . $this->currentLanguage . '/' . $this->slug($sym) . '/market.html';

                $this->setVars($_coin);
                $file = '2018-01-01-' . $this->currentLanguage . '-' . $this->getCurrentSym(true) . '-market.md';
                $this->generateFile($file);
                echo $sym . " market generated \n";
            }
    }

    public function generateMining(){
        
        $this->templateFile = '_templates/mining.html';
        $this->outputFolder = '../_mining';
        $i = 0;
        foreach ($this->getCoinCMCList() as $_coinA) {

            $sym = $_coinA['symbol'];
            $this->currentSym = $sym;
            $_coin = $this->loadCoinBySymbol($sym);
            if(!$_coin) continue;

                $_coin = array_merge($_coin, $_coinA);

                //test mode
                if ($this->isTestMode() && !in_array($sym, $this->testCoinList())) continue;

                $this->currentCoin = $_coin;

                $coinSnapshotFullInfo = $this->getCoinFileData('coinSnapshotFullInfo');

                
                $_coin['Description'] = isset($coinSnapshotFullInfo['Data']['General']['Description']) ? $coinSnapshotFullInfo['Data']['General']['Description'] : null;
                $_coin['Features'] = isset($coinSnapshotFullInfo['Data']['General']['Features']) ? $coinSnapshotFullInfo['Data']['General']['Features'] : null;
                $_coin['Technology'] = isset($coinSnapshotFullInfo['Data']['General']['Technology']) ? $coinSnapshotFullInfo['Data']['General']['Technology'] : null;
                $_coin['AffiliateUrl'] = isset($coinSnapshotFullInfo['Data']['General']['AffiliateUrl']) ? $coinSnapshotFullInfo['Data']['General']['AffiliateUrl'] : null;
                $_coin['Url'] = isset($coinSnapshotFullInfo['Data']['General']['Url']) ? $coinSnapshotFullInfo['Data']['General']['Url'] : null;
                $_coin['TotalCoinSupply'] = isset($coinSnapshotFullInfo['Data']['General']['TotalCoinSupply']) ? $coinSnapshotFullInfo['Data']['General']['TotalCoinSupply'] : null;

                $coinSnapshotFullInfoMetaData = $this->getCoinFileData('coinSnapshotFullInfo_MetaData');

                //Check allow display
                $_coin['allow_search_engine'] = isset($coinSnapshotFullInfoMetaData['is_approved']) ? $coinSnapshotFullInfoMetaData['is_approved'] : "false";
                $_coin['sitemap'] = isset($coinSnapshotFullInfoMetaData['is_approved']) ? $coinSnapshotFullInfoMetaData['is_approved'] : "false";
                $_coin['enable_comment'] = "false";
                $_coin['description'] = $coinSnapshotFullInfoMetaData['General']['Description'];



                $_coin['Name'] = $sym;
                $_coin['coin_a_lower_case'] = $this->slug($sym);

                $_coin['title'] = $_coin['name'] ;
                $_coin['meta-title'] = '';
                $_coin['meta-description'] = '';
                $_coin['h1-title'] = '';

                $_coin['parent-url'] = '/' . $this->currentLanguage . '/' .  $this->slug($sym) . '/';

                $_coin['url'] = '/' . $this->currentLanguage . '/' . $this->slug($sym) . '/mining.html';

                $this->setVars($_coin);
                $file = '2018-01-01-' . $this->currentLanguage . '-' . $this->getCurrentSym(true) . '-mining.md';
                $this->generateFile($file);
                echo $sym . " mining generated \n";
            }
    }


    public function generateNews(){
        
        $this->templateFile = '_templates/news.html';
        $this->outputFolder = '../_news';
        $i = 0;
        foreach ($this->getCoinCMCList() as $_coinA) {

            $sym = $_coinA['symbol'];
            $this->currentSym = $sym;
            $_coin = $this->loadCoinBySymbol($sym);
            if(!$_coin) continue;

                $_coin = array_merge($_coin, $_coinA);

                //test mode
                if ($this->isTestMode() && !in_array($sym, $this->testCoinList())) continue;

                $this->currentCoin = $_coin;

                $coinSnapshotFullInfo = $this->getCoinFileData('coinSnapshotFullInfo');

                
                $_coin['Description'] = isset($coinSnapshotFullInfo['Data']['General']['Description']) ? $coinSnapshotFullInfo['Data']['General']['Description'] : null;
                $_coin['Features'] = isset($coinSnapshotFullInfo['Data']['General']['Features']) ? $coinSnapshotFullInfo['Data']['General']['Features'] : null;
                $_coin['Technology'] = isset($coinSnapshotFullInfo['Data']['General']['Technology']) ? $coinSnapshotFullInfo['Data']['General']['Technology'] : null;
                $_coin['AffiliateUrl'] = isset($coinSnapshotFullInfo['Data']['General']['AffiliateUrl']) ? $coinSnapshotFullInfo['Data']['General']['AffiliateUrl'] : null;
                $_coin['Url'] = isset($coinSnapshotFullInfo['Data']['General']['Url']) ? $coinSnapshotFullInfo['Data']['General']['Url'] : null;
                $_coin['TotalCoinSupply'] = isset($coinSnapshotFullInfo['Data']['General']['TotalCoinSupply']) ? $coinSnapshotFullInfo['Data']['General']['TotalCoinSupply'] : null;

                $coinSnapshotFullInfoMetaData = $this->getCoinFileData('coinSnapshotFullInfo_MetaData');

                //Check allow display
                $_coin['allow_search_engine'] = isset($coinSnapshotFullInfoMetaData['is_approved']) ? $coinSnapshotFullInfoMetaData['is_approved'] : "false";
                $_coin['sitemap'] = isset($coinSnapshotFullInfoMetaData['is_approved']) ? $coinSnapshotFullInfoMetaData['is_approved'] : "false";
                $_coin['enable_comment'] = "false";
                $_coin['description'] = $coinSnapshotFullInfoMetaData['General']['Description'];



                $_coin['Name'] = $sym;
                $_coin['coin_a_lower_case'] = $this->slug($sym);

                $_coin['title'] = $_coin['name'] ;
                $_coin['meta-title'] = '';
                $_coin['meta-description'] = '';
                $_coin['h1-title'] = '';

                $_coin['parent-url'] = '/' . $this->currentLanguage . '/' .  $this->slug($sym) . '/';

                $_coin['url'] = '/' . $this->currentLanguage . '/' . $this->slug($sym) . '/news.html';

                $this->setVars($_coin);
                $file = '2018-01-01-' . $this->currentLanguage . '-' . $this->getCurrentSym(true) . '-news.md';
                $this->generateFile($file);
                echo $sym . " news generated \n";
            }
    }


    public function generateTrend(){
        
        $this->templateFile = '_templates/trend.html';
        $this->outputFolder = '../_trend';
        $i = 0;
        foreach ($this->getCoinCMCList() as $_coinA) {

            $sym = $_coinA['symbol'];
            $this->currentSym = $sym;
            $_coin = $this->loadCoinBySymbol($sym);
            if(!$_coin) continue;

                $_coin = array_merge($_coin, $_coinA);

                //test mode
                if ($this->isTestMode() && !in_array($sym, $this->testCoinList())) continue;

                $this->currentCoin = $_coin;

                $coinSnapshotFullInfo = $this->getCoinFileData('coinSnapshotFullInfo');

                
                $_coin['Description'] = isset($coinSnapshotFullInfo['Data']['General']['Description']) ? $coinSnapshotFullInfo['Data']['General']['Description'] : null;
                $_coin['Features'] = isset($coinSnapshotFullInfo['Data']['General']['Features']) ? $coinSnapshotFullInfo['Data']['General']['Features'] : null;
                $_coin['Technology'] = isset($coinSnapshotFullInfo['Data']['General']['Technology']) ? $coinSnapshotFullInfo['Data']['General']['Technology'] : null;
                $_coin['AffiliateUrl'] = isset($coinSnapshotFullInfo['Data']['General']['AffiliateUrl']) ? $coinSnapshotFullInfo['Data']['General']['AffiliateUrl'] : null;
                $_coin['Url'] = isset($coinSnapshotFullInfo['Data']['General']['Url']) ? $coinSnapshotFullInfo['Data']['General']['Url'] : null;
                $_coin['TotalCoinSupply'] = isset($coinSnapshotFullInfo['Data']['General']['TotalCoinSupply']) ? $coinSnapshotFullInfo['Data']['General']['TotalCoinSupply'] : null;

                $coinSnapshotFullInfoMetaData = $this->getCoinFileData('coinSnapshotFullInfo_MetaData');

                //Check allow display
                $_coin['allow_search_engine'] = isset($coinSnapshotFullInfoMetaData['is_approved']) ? $coinSnapshotFullInfoMetaData['is_approved'] : "false";
                $_coin['sitemap'] = isset($coinSnapshotFullInfoMetaData['is_approved']) ? $coinSnapshotFullInfoMetaData['is_approved'] : "false";
                $_coin['enable_comment'] = "false";
                $_coin['description'] = $coinSnapshotFullInfoMetaData['General']['Description'];



                $_coin['Name'] = $sym;
                $_coin['coin_a_lower_case'] = $this->slug($sym);

                $_coin['title'] = $_coin['name'] ;
                $_coin['meta-title'] = '';
                $_coin['meta-description'] = '';
                $_coin['h1-title'] = '';

                $_coin['parent-url'] = '/' . $this->currentLanguage . '/' .  $this->slug($sym) . '/';

                $_coin['url'] = '/' . $this->currentLanguage . '/' . $this->slug($sym) . '/trend.html';

                $this->setVars($_coin);
                $file = '2018-01-01-' . $this->currentLanguage . '-' . $this->getCurrentSym(true) . '-trend.md';
                $this->generateFile($file);
                echo $sym . " trend generated \n";
            }
    }


    public function loadCoinBasicData(){

    }
    /**
     * generate coin comparision file
     */
    public function generateCoinComparisions()
    {


        $this->templateFile = '_templates/coin_comparision.html';
        $this->outputFolder = '../_coincomparisions';


        foreach ($this->getCoinList() as $sym => $_coin) {

            //Crypto curriencies
            foreach ($this->getCoinList() as $symB => $_coinB) {

                if ($sym == $symB) continue; //check if it is SAME coin

                //test mode
                if ($this->isTestMode() && !in_array($sym, $this->testCoinList())) continue;
                if ($this->isTestMode() && !in_array($symB, $this->testCoinList())) continue;

                $this->currentCoin = $_coin;

                $coinSnapshotFullInfo = $this->getCoinFileData('coinSnapshotFullInfo');

                $_coin['Description'] = isset($coinSnapshotFullInfo['Data']['General']['Description']) ? $coinSnapshotFullInfo['Data']['General']['Description'] : null;
                $_coin['Features'] = isset($coinSnapshotFullInfo['Data']['General']['Features']) ? $coinSnapshotFullInfo['Data']['General']['Features'] : null;
                $_coin['Technology'] = isset($coinSnapshotFullInfo['Data']['General']['Technology']) ? $coinSnapshotFullInfo['Data']['General']['Technology'] : null;
                $_coin['AffiliateUrl'] = isset($coinSnapshotFullInfo['Data']['General']['AffiliateUrl']) ? $coinSnapshotFullInfo['Data']['General']['AffiliateUrl'] : null;
                $_coin['Url'] = isset($coinSnapshotFullInfo['Data']['General']['Url']) ? $coinSnapshotFullInfo['Data']['General']['Url'] : null;
                $_coin['TotalCoinSupply'] = isset($coinSnapshotFullInfo['Data']['General']['TotalCoinSupply']) ? $coinSnapshotFullInfo['Data']['General']['TotalCoinSupply'] : null;

                $coinSnapshotFullInfoMetaData = $this->getCoinFileData('coinSnapshotFullInfo_MetaData');

                //Check allow display
                $_coin['allow_search_engine'] = isset($coinSnapshotFullInfoMetaData['is_approved']) ? $coinSnapshotFullInfoMetaData['is_approved'] : "false";
                $_coin['sitemap'] = isset($coinSnapshotFullInfoMetaData['is_approved']) ? $coinSnapshotFullInfoMetaData['is_approved'] : "false";
                $_coin['enable_comment'] = "false";


                $symB = $this->filterSymbol($symB);

                $_coin['coin_b_symbol'] = $symB;
                $_coin['coin_b_name'] = $_coinB['CoinName'];
                $_coin['coin_a_b_rate'] = 1000; //TODO

                $_coin['all_currencies_options_a'] = $this->generateCurrenciesOptions($sym, $this->getCryptoCurrencies());
                $_coin['all_currencies_options_b'] = $this->generateCurrenciesOptions($symB, $this->getWorldCurrencies());

                $url = '/' . $this->currentLanguage . '/' . $this->slug($this->getCurrentSym(true)) . '/' . $this->slug($this->filterSymbol($symB)) . '/';
                $_coin['url'] = $url;

                $this->setVars($_coin);
                $file = '2018-01-01-' . $this->currentLanguage . '-' . $this->getCurrentSym(true) . '-' . $symB . '.md';
                $this->generateFile($file);
                echo $sym . " vs " . $symB . "\n";
            }//end crypto


        }


    }

    /**
     * get all currencies: World + crypto
     * @return array
     */
    public function getAllCurrencies()
    {
        return array_merge($this->getWorldCurrencies(), $this->getCryptoCurrencies());
    }

    /**
     * get world  curriencies
     * @return array
     */
    public function getWorldCurrencies()
    {
        $list = [];
        foreach ($this->getCurrenciesData() as $symbol => $name) {
            $list[$symbol] = $name;
        }

        return $list;
    }


    public function getMainWorldCurrencies()
    {
        $list = [];
        foreach ($this->getMainCurrenciesData() as $symbol => $name) {
            $list[$symbol] = $name;
        }

        return $list;
    }

    /**
     * get Crypto currencies
     * @return array
     */
    public function getCryptoCurrencies()
    {
        $list = [];
        foreach ($this->getCoinList() as $_coin) {
            $list[$_coin['Symbol']] = $_coin['CoinName'];
        }

        return $list;
    }

    /**
     * save all currencies to a file
     */
    public function saveAllCurrencies()
    {
        $this->saveDataToJsonFile($this->getAllCurrencies(), $this->basicFileDataPath . '/all_currencies.json');
    }

    /**
     * render currencies options in HTML
     * @param $default
     * @param $list
     * @return string
     */
    public function generateCurrenciesOptions($default, $list)
    {
        $html = '<select><option value="" disabled >' . $this->__('Choose') . '</option>';
        foreach ($list as $symbol => $label) {
            if ($symbol == $default) {
                $html .= '<option value="' . $default . '" selected>' . $default . '</option>';
            } else {
                $html .= '<option value="' . $symbol . '">' . $symbol . '</option>';
            }
        }
        $html .= '</select>';

        return $html;
    }

    /**
     * check is test mode
     * @return bool
     */
    public function isTestMode()
    {
        return $this->isTestMode;
    }

    /**
     * save coin snapshot
     * @param $url
     * @param $to
     * @param bool $isJson
     * @param bool $pretty
     */
    public function saveCoinSnapshot($url, $to, $isJson = false, $pretty = false)
    {
        $content = $this->getContentByCRUL($url);
        if (!empty($content)) {
            try {
                if ($isJson && $pretty) {
                    $array = $this->jsondecode($content);
//					if (isset($array['Data']['ProofType'])) {
//						$array['Data']['ProofType'] = str_replace(
//							['\/'],
//							['/'],
//							$array['Data']['ProofType']
//						);
//					}

                    $content = $this->jsonencode($array);
                }

                switch ($this->getCurrentSym()) {
                    case 'BTC':
                        $content = $this->jsondecode($content);
                        $content['Data']['AggregatedData']['PRICE'] = 1;
                        $content['Data']['AggregatedData']['LASTVOLUME'] = (float)$content['Data']['AggregatedData']['LASTVOLUME'] / $this->getBtcPrice();
                        $content['Data']['AggregatedData']['LASTVOLUMETO'] = (float)$content['Data']['AggregatedData']['LASTVOLUMETO'] / $this->getBtcPrice();
                        $content['Data']['AggregatedData']['VOLUMEDAY'] = (float)$content['Data']['AggregatedData']['VOLUMEDAY'] / $this->getBtcPrice();
                        $content['Data']['AggregatedData']['VOLUMEDAYTO'] = (float)$content['Data']['AggregatedData']['VOLUMEDAYTO'] / $this->getBtcPrice();
                        $content['Data']['AggregatedData']['VOLUME24HOUR'] = (float)$content['Data']['AggregatedData']['VOLUME24HOUR'] / $this->getBtcPrice();
                        $content['Data']['AggregatedData']['VOLUME24HOURTO'] = (float)$content['Data']['AggregatedData']['VOLUME24HOURTO'] / $this->getBtcPrice();
                        $content['Data']['AggregatedData']['VOLUMEDAYTO'] = (float)$content['Data']['AggregatedData']['VOLUMEDAYTO'] / $this->getBtcPrice();
                        $content['Data']['AggregatedData']['OPENDAY'] = (float)$content['Data']['AggregatedData']['OPENDAY'] / $this->getBtcPrice();
                        $content['Data']['AggregatedData']['HIGHDAY'] = (float)$content['Data']['AggregatedData']['HIGHDAY'] / $this->getBtcPrice();
                        $content['Data']['AggregatedData']['OPEN24HOUR'] = (float)$content['Data']['AggregatedData']['OPEN24HOUR'] / $this->getBtcPrice();
                        $content['Data']['AggregatedData']['HIGH24HOUR'] = (float)$content['Data']['AggregatedData']['HIGH24HOUR'] / $this->getBtcPrice();
                        $content['Data']['AggregatedData']['LOW24HOUR'] = (float)$content['Data']['AggregatedData']['LOW24HOUR'] / $this->getBtcPrice();

                        //Encode again
                        $content = $this->jsonencode($content);
                        break;
                    default:


                        break;


                }

                file_put_contents($to, $content);
            } catch (Exception $e) {

            }
        }

    }

    public function getCoinSnapshot()
    {
        $coinSnapshot = $this->getCoinFileData('coinsnapshot');

        return $coinSnapshot;
    }

    public function getCoinSnapshotFull()
    {
        $coinSnapshot = array_merge($this->getCoinFileData('coinSnapshotFullInfo'), $this->getCoinFileData('coinSnapshotFullInfo_MetaData'));
        return $coinSnapshot;
    }

    public function getCurrentCoinPrice()
    {
        return isset($this->currentCoinSnapshot['Data']['AggregatedData']['PRICE']) ? $this->currentCoinSnapshot['Data']['AggregatedData']['PRICE'] : null;
    }

    public function getCurrentCoinTotalCoinMined()
    {
        return isset($this->currentCoinSnapshotFull['Data']['General']['TotalCoinsMined']) ? $this->currentCoinSnapshotFull['Data']['General']['TotalCoinsMined'] : null;
    }

    public function getCurrentTotalCoinSupply()
    {
        return isset($this->currentCoinSnapshotFull['Data']['General']['TotalCoinSupply']) ? $this->currentCoinSnapshotFull['Data']['General']['TotalCoinSupply'] : null;
    }

    public function getCurrentCoinMarketCap($currency = 'BTC')
    {
        $marketCap = 0;
        switch ($currency) {
            case 'USD':
                // btc to USD
                break;

            default:
                $marketCap = (float)$this->getCurrentCoinPrice() * (float)$this->getCurrentCoinTotalCoinMined();
                break;
        }

        return $marketCap;
    }


    /**
     * generate exchange meta data
     */
    public function generateExchangeMetaData()
    {
        $path = $this->basicFileDataPath . '/exchanges.json';
        $exchanges = $this->getDataFromJsonFile($path);

        foreach ($exchanges as $_name => $_pairs) {
            $exchanges[$_name] = [
                'introduce' => '',
                'website' => '',
                'trade_url' => '', //https://www.binance.com/trade.html?symbol={{coin_a}}_{{coin_b}}
                'refer_id' => '',
                'refer_path' => '',
                'refer_url' => '',
                'rating' => '',
                'icon' => '',
                'country' => '',
            ];
        }

        $this->saveDataToJsonFile($exchanges, $this->basicFileDataPath . '/exchanges_metadata.json');
    }


    /**
     * generate coin full data
     */
    public function generateCoinFullData()
    {

        $coinsRank = [];
        foreach ($this->getCoinList() as $_sym => $_coin) {
            $this->initCoin($_sym);
            echo ($_sym . ": " . (float)$this->getCurrentCoinPrice() . " Mined: " . (float)$this->getCurrentCoinTotalCoinMined() . " Total " . (float)$this->getCurrentTotalCoinSupply()) . "\n";
            $coinsRank[$_sym] = (float)$this->getCurrentCoinPrice() * (float)$this->getCurrentCoinTotalCoinMined();
        }
        //Sort by market cap
        arsort($coinsRank);


        //get coin list order by market cap rank
        $coinList = [];
        $rank = 1;
        foreach ($coinsRank as $_sym => $marketCap) {
            $this->initCoin($_sym);
            /**
             * INFO:
             * - Symbol
             * - Name
             * - Market cap
             * - Sort order
             * - Price
             * - TotalCoinSupply
             * - TotalCoinsMined
             * - Algorithm
             * - ProofType
             */
            $_coinData = [];
            $_coinData['Symbol'] = $this->currentSym;
            $_coinData['CoinName'] = isset($this->currentCoinSnapshotFull['Data']['General']['Name']) ? $this->currentCoinSnapshotFull['Data']['General']['Name'] : $this->currentSym;
            $_coinData['Algorithm'] = isset($this->currentCoinSnapshotFull['Data']['General']['Algorithm']) ? $this->currentCoinSnapshotFull['Data']['General']['Algorithm'] : null;
            $_coinData['ProofType'] = isset($this->currentCoinSnapshotFull['Data']['General']['ProofType']) ? $this->currentCoinSnapshotFull['Data']['General']['ProofType'] : null;
            $_coinData['TotalCoinSupply'] = (float)$this->getCurrentTotalCoinSupply();
            $_coinData['TotalCoinsMined'] = (float)$this->getCurrentCoinTotalCoinMined();
            $_coinData['Price'] = (float)$this->getCurrentCoinPrice();
            $_coinData['MarketCap'] = $this->getCoinMarketCap('USD');
            $_coinData['MarketCapBTC'] = $this->getCoinMarketCap('BTC');
            $_coinData['Vol24h'] = isset($this->currentCoinSnapshot['Data']['AggregatedData']['VOLUME24HOUR']) ? $this->currentCoinSnapshot['Data']['AggregatedData']['VOLUME24HOUR'] * $this->btcPrice : 0;
            $_coinData['Change24h'] = $this->getChange24h();
            $_coinData['Rank'] = $rank;
            $_coinData['ImageUrl'] = $this->currentCoinSnapshotFull['Data']['General']['ImageUrl'];

            if (($_coinData['Symbol'] != 'BTC') AND ($_coinData['Vol24h'] == 0 OR $_coinData['Change24h'] == 0)) continue;

            $coinList[$_sym] = $_coinData;
            $rank++;
        }
        $this->saveDataToJsonFile($coinList, $this->fullDataCoinPath);




    }



    /**
     * init coin by sym
     * @param $sym
     */
    public function initCoin($sym)
    {
        $this->currentSym = $sym;
        $this->currentCoin = $this->getCoinBySymbol($sym);
        $this->currentCoinSnapshot = $this->getCoinSnapshot();
        $this->currentCoinSnapshotFull = $this->getCoinSnapshotFull();
    }

    /**
     * save coin rank
     * @param $coinRank
     */
    public function saveRankedCoinList($coinRank)
    {
        $this->saveDataToJsonFile($coinRank, $this->coinRankPath);
    }

    /**
     * get ranked coin list
     * @return array|mixed
     */
    public function getRankedCoinList()
    {
        return $this->getDataFromJsonFile($this->coinRankPath);
    }


    /**
     * get full & ranked coin list
     * @return array|mixed
     */
    public function getFullDataCoinList()
    {
        return $this->getDataFromJsonFile($this->fullDataCoinPath);
    }

    /**
     * get coin price
     * @param string $fromSymbol
     * @param string $toSymbol
     * @return int
     */
    public function getCoinPrice($fromSymbol = 'BTC', $toSymbol = 'USD')
    {
        $url = $this->endpoint . '/data/price?fsym=' . $fromSymbol . '&tsyms=' . $toSymbol;
        $json = $this->getContentByCRUL($url);

        $data = $this->jsondecode($json);

        if (isset($data[$toSymbol])) {
            return $data[$toSymbol];
        } else {
            return 0;
        }
    }

    /**
     * get bitcoin price
     * @param string $toSym
     * @return int
     */
    public function getBtcPrice($toSym = 'USD')
    {
        return $this->getCoinPrice('BTC', $toSym);
    }

    /**
     * get eth price
     * @param string $toSym
     * @return int
     */
    public function getEthPrice($toSym = 'USD')
    {
        return $this->getCoinPrice('ETH', $toSym);
    }

    public function getCoinMarketCap($toSym = 'USD')
    {
        $price = 0;
        if ($toSym == 'USD') {
            $price = (float)$this->getCurrentCoinPrice() * (float)$this->getCurrentCoinTotalCoinMined() * $this->btcPrice;
        } else {
            $price = (float)$this->getCurrentCoinPrice() * (float)$this->getCurrentCoinTotalCoinMined();
        }

        return $price;
    }

    public function getChange24h()
    {
        if (isset($this->currentCoinSnapshot['Data']['AggregatedData']['OPEN24HOUR']) && isset($this->currentCoinSnapshot['Data']['AggregatedData']['PRICE'])) {
            $change24h = (($this->currentCoinSnapshot['Data']['AggregatedData']['PRICE'] - $this->currentCoinSnapshot['Data']['AggregatedData']['OPEN24HOUR']) / $this->currentCoinSnapshot['Data']['AggregatedData']['OPEN24HOUR']);
        } else {
            $change24h = 0;
        }

        return $change24h;
    }

    /**
     * @param $sym
     * @return null
     */
    public function getCoinBySymbol($sym)
    {
        $coinlist = $this->getFullDataCoinList();
        if (isset($coinlist[$sym]))
            return $coinlist[$sym];
        else
            return null;
    }

    /**
     * @param $sym
     * @return null
     */
    public function loadCoinBySymbol($sym)
    {
        return $this->getCoinBySymbol($sym);
    }

}



//Converter
/**
 * 1. HistoDay:  https://min-api.cryptocompare.com/data/histoday?fsym=BTC&tsym=USD&limit=60&aggregate=3&e=CCCAGG
 * 2. Price: https://min-api.cryptocompare.com/data/price?fsym=ETH&tsyms=BTC,USD,EUR
 * https://min-api.cryptocompare.com/data/pricehistorical?fsym=BTC&tsyms=USD,EUR&e=Coinbase
 *
 * 3. Stream: https://cryptoqween.github.io/streamer/current/
 * 4. List currentcies: https://openexchangerates.org/api/currencies.json
 * 5. Coin market:
 * 6. exchanges: https://min-api.cryptocompare.com/data/top/exchanges?fsym=BTC&tsym=USD&limit=50
 */