<?php

if (!defined('MAGE_ROOT')) {
	define('MAGE_ROOT', getcwd());
}

date_default_timezone_set('asia/ho_chi_minh');

include MAGE_ROOT . '/shell.php';

/**
 * Class Token
 */
class Token extends Shell
{
	public $dataPath = MAGE_ROOT . '/../_data';
	public $tokenPath = MAGE_ROOT . '/_source/tokens';
	public $tokenListPath = MAGE_ROOT . '/_source/tokens.json';
	public $tokenMetaDataPath = MAGE_ROOT . '/_source/tokens_metadata.json';
	public $holdersStatsDataFilePath = 'latest.json';
	public $tokenUrl = 'https://etherscan.io/tokens';
	public $currentSym = null;
	public $currentToken = null;
	public $etherscanApiKey = 'YourApiKeyToken';
	public $etherscanEndpoint = 'https://api.etherscan.io/api';
	public $etheplorerEndpoint = 'https://api.ethplorer.io';
	public $etheplorerApiKey = 'freekey';

	public function initToken($symbol)
	{

		$this->currentSym   = $symbol;
		$this->currentToken = $this->getTokenBySymbol($symbol);
	}

	/**
	 * @return array|mixed
	 */
	public function getTokenList()
	{
		$list     = $this->getDataFromJsonFile($this->tokenListPath);
		$listMeta = $this->getDataFromJsonFile($this->tokenMetaDataPath);
		$list     = array_merge($list, $listMeta);

		return $list['tokens'];
	}

	/**
	 * get token by symbol
	 * @param $sym
	 * @return array|mixed
	 */
	public function getTokenBySymbol($sym)
	{
		$list = $this->getTokenList();
		foreach ($list as $_token) {
			if ($_token['symbol'] == $sym) {
				return $_token;
			}
		}

		return [];

	}

	/**
	 * get token dât by key
	 * @param $key
	 * @return null
	 */
	public function getTokenDataByKey($key)
	{
		if (isset($this->currentToken[$key])) {
			return $this->currentToken[$key];
		} else {
			return null;
		}
	}

	/**
	 * @return null
	 */
	public function getCurrentContractAddress()
	{
		return $this->getTokenDataByKey('address');
	}

	/**
	 * get total supply tokens
	 * @return mixed
	 */
	public function getCurrentTotalSupplyToken($liveData = false)
	{
		if ($liveData == true) {
			$request = [
				'module'          => 'stats',
				'action'          => 'tokensupply',
				'contractaddress' => $this->getCurrentContractAddress(),
			];

			return $this->etherscanApiCall($request, true);
		} else {
			return $this->getTokenDataByKey('totalSupply');
		}


	}

	/**
	 * @return string
	 */
	public function getCurrentHoldersFilePath()
	{
		return $this->tokenPath . '/' . $this->currentSym . '/holders.txt';
	}

	/**
	 * @return string
	 */
	public function getCurrentTokenDataPath()
	{
		return $this->tokenPath . '/' . $this->currentSym;
	}

	/**
	 * @return array|mixed
	 */
	public function getAllHolders()
	{
		if (is_file($this->getCurrentHoldersFilePath())) {
			$content   = file_get_contents($this->getCurrentHoldersFilePath());
			$addresses = explode("\n", $content);

			return $addresses;
		} else {

			try {
				file_put_contents($this->getCurrentHoldersFilePath(), null);
			} catch (Exception $e) {
			}

			return [];
		}
	}


	/**
	 * Etherscan.io api call
	 * @param $request
	 * @param bool $returnResult
	 * @return int|mixed
	 */
	public function etherscanApiCall($request, $returnResult = false)
	{
		$url      = $this->etherscanEndpoint . '?' . http_build_query($request) . '&apikey=' . $this->etherscanApiKey;
		$response = $this->getContentByCRUL($url);
		$data     = $this->jsondecode($response);

		if ($returnResult == true) {
			if ($data['status'] == 0) {
				echo $data['result'];

				return null;
			} else {
				return $data['result'];
			}
		} else {
			return $data;

		}
	}

	/**
	 *call etheploer api
	 * @param $action
	 * @param $value
	 * @param $request
	 * @return int|mixed
	 */
	public function etheplorerApiCall($action, $value, $request)
	{
		//https://api.ethplorer.io/getAddressTransactions/0xb287a379e6caca6732e50b88d23c290aa990a892/?apiKey=freekey&limit=50
		$url = $this->etheplorerEndpoint . '/' . $action . '/' . $value . '/?apiKey=' . $this->etheplorerApiKey . http_build_query($request);

		$response = $this->getContentByCRUL($url);
		$data     = $this->jsondecode($response);

		if (isset($data['error'])) {
			echo $data['error']['message'];

			return 0;
		} else {
			return $data;
		}
	}


	/**
	 * download all tokens
	 */
	public function downloadAllTokenList()
	{
		//https://api.ethplorer.io/getTop?apiKey=freekey&criteria=cap
		$list = [

			//Daily
			'tokens.json' => 'https://api.ethplorer.io/getTop?apiKey=freekey&criteria=cap',
		];

		foreach ($list as $fileName => $url) {
			try {
				$path    = $this->basicFileDataPath . '/' . $fileName;
				$content = file_get_contents($url);
				$content = json_encode(json_decode($content), JSON_PRETTY_PRINT);
				file_put_contents($path, $content);
			} catch (Exception $e) {

			}
		}

		foreach ($this->getTokenList() as $_token) {
			//Create token folder: store token data
			$tokenPath = $this->tokenPath . '/' . $_token['symbol'];
			if (!is_dir($tokenPath)) {
				$this->createFolder($tokenPath);
				try {
					file_put_contents($tokenPath . '/holders.txt', '');
				} catch (Exception $e) {
				}
			}
			//Create token holders folder: store holder statistic
			$tokenPath = $this->tokenPath . '/' . $_token['symbol'] . '/holders';
			if (!is_dir($tokenPath)) {
				$this->createFolder($tokenPath);
				file_put_contents($tokenPath . '/.keep', '');

			}

		}
	}


	/**
	 * @param $address
	 * @param null $contractAddress
	 * @return int|mixed
	 */
	public function getAddressQuantityTokens($address, $contractAddress = null)
	{
		if (empty($address)) return null;

		$request = [
			'module'          => 'account',
			'action'          => 'tokenbalance',
			'contractaddress' => $contractAddress ? $contractAddress : $this->getCurrentContractAddress(),
			'address'         => $address,
			'tag'             => 'latest'
		];

		return $this->etherscanApiCall($request, true);

	}


	/**
	 * get address percent
	 * @param $address
	 * @return float
	 */
	public function getAddressPercentage($address)
	{
		if (empty($address)) return null;

		$percentage = ($this->getAddressQuantityTokens($address) / $this->getCurrentTotalSupplyToken($liveData = false)) * 100;

		return $percentage;
	}

	/**
	 * get data from token folder
	 * @param null $file
	 * @param string $fileType
	 * @return array|mixed|string
	 */
	public function getTokenDataFile($file = null, $fileType = 'json')
	{
		$dataFilePath = $this->getCurrentTokenDataPath() . '/' . $file;
		switch ($fileType) {
			case 'txt':
				if (!is_file($dataFilePath)) {
					echo "File does not exist!";
				} else {
					$content = file_get_contents($dataFilePath);
					$data    = explode("\n", $content);
				}
				break;

			default:
				$data = $this->getDataFromJsonFile($dataFilePath);
				break;
		}

		return $data;
	}


	/**
	 * @param null $contractAddress
	 * @return array
	 */
	public function downloadHolderStats($contractAddress = null)
	{


		$dataFilePath = $this->getCurrentTokenDataPath() . '/' . $this->holdersStatsDataFilePath;
		$holders      = [];
		foreach ($this->getAllHolders() as $_holderAddress) {
			$_holderAddress = trim($_holderAddress);

			if (empty($_holderAddress)) continue;

			echo $this->currentSym . ": " . $_holderAddress . "\n";

			$_data = [
				'q' => $this->getAddressQuantityTokens($_holderAddress, $contractAddress),
				'%' => number_format($this->getAddressPercentage($_holderAddress), 4),
			];


			$diff = [];

			//All the time stats
			$diff['all'] = $this->saveStatsToAddress($_holderAddress,
				[
					date('Y-m-d', time()) => $_data
				],
				'all');

			//7d
			$diff['7d'] = $this->saveStatsToAddress($_holderAddress,
				[
					date('w', time()) => $_data
				],
				'7d');

			//30d
			$diff['30d'] = $this->saveStatsToAddress($_holderAddress,
				[
					date('m', time()) => $_data
				],
				'30d');

			//Save 24h: hourly
			$diff['24h'] = $this->saveStatsToAddress($_holderAddress,
				[
					date('H', time()) => $_data //19
				],
				$day = '24h');

			//All holders
			$holders[$_holderAddress] = array_merge($_data, $diff);
		}

		//Rank holder by qty
		$rankedHolders = [];
		foreach ($holders as $_address => $_holder) {
			$rankedHolders[$_address] = $_holder['q'];
		}
		//Sort from high to low
		ksort($rankedHolders);

		$updatedHolders = [];
		$i              = 1;
		foreach ($rankedHolders as $_address => $_holder) {

			$_holderData               = array_merge(
				[
					'rank' => $i
				],
				$holders[$_address]
			);

			$updatedHolders[$_address] = $_holderData;
			$i++;
		}

		//Save to latest data
		$this->saveDataToJsonFile($updatedHolders, $dataFilePath);

		//Save to media folder
		$path = MAGE_ROOT . '/../media/tokens/' . $this->currentSym . '.json';
		$json = $this->jsonencode($updatedHolders);
		$json = 'var holderStats = ' . $json;
		$this->saveDataToJsonFile($json, $path);


		return $updatedHolders;
	}

	/**
	 * save stats to holder address
	 * @param $address
	 * @param $newData
	 */
	public function saveStatsToAddress($address, $newData, $day = null)
	{


		switch ($day) {
			case 'all':
				$path = $this->getCurrentTokenDataPath() . '/holders/' . $address . '.json';

				break;
			default:
				$path = $this->getCurrentTokenDataPath() . '/holders/' . $address . '_' . $day . '.json';

				break;
		}

		$data = $this->getDataFromJsonFile($path);

		unset($data['response']);
		unset($data['message']);

		//First item
		$first = array_shift($data);
		$last  = end($newData);

		//compare
		$diff = [
			'q' => $last['q'] - $first['q'],
			'%' => $last['%'] - $first['%'],
		];

		$data['change'] = $diff['%'];
		//Copy to JS file
		$data['diff'] = $diff;

		echo 'Change: ' . $diff['%'] . "\n";


		$data = array_merge($data, $newData);

		unset($data['response']);
		unset($data['message']);

		$this->saveDataToJsonFile($data, $path);

		return $diff;
	}


	/**
	 * @param null $contactAddress
	 * @return array|mixed
	 */
	public function getHolderStatistic($contactAddress = null)
	{
		$dataFilePath = $this->getCurrentTokenDataPath() . '/' . $this->holdersStatsDataFilePath;

		return $this->getDataFromJsonFile($dataFilePath);
	}

	/**
	 * @param $address
	 * @param int $limit
	 * @return int|mixed
	 */
	public function getAddressTransactions($address, $limit = 50)
	{
		$request = [
			'limit' => $limit
		];

		return $this->etheplorerApiCall($action = 'getAddressTransactions', $address, $request);

	}

	/**
	 *
	 */
	public function downloadAllTokenAddressesStats()
	{
		foreach ($this->getTokenList() as $_token) {
			$this->initToken($_token['symbol']);
			if ($_token['status'] == 1) {
				$this->downloadHolderStats($_token['address']);
			}
		}
	}


}

$token = new Token();
$token->downloadAllTokenList();
//$token->currentSym = 'TRX';
//$address           = $token->initToken($token->currentSym);

//var_dump($token->getHolderStatistic());
$token->downloadHolderStats('0xf230b790e05390fc8295f4d3f60332c93bed42e2');

$token->downloadAllTokenAddressesStats();

// Get total supply tokens: https://api.etherscan.io/api?module=stats&action=tokensupply&contractaddress=0xf230b790e05390fc8295f4d3f60332c93bed42e2&apikey=YourApiKeyToken
// Get Address's quantity token:
//  	- https://api.etherscan.io/api?module=account&action=tokenbalance&contractaddress=0xf230b790e05390fc8295f4d3f60332c93bed42e2&address=0xa18ff761a52ce1cb71ab9a19bf4e4b707b388b83&tag=latest&apikey=YourApiKeyToken
//		- https://api.ethplorer.io/getAddressInfo/0x02918e31f684602864f1df5fcd3175d84c6137a7/?apiKey=freekey
//=> Get % quatity / total
//Get all top tokens: https://api.ethplorer.io/getTop?apiKey=freekey&criteria=cap
//Get token contact info: https://api.ethplorer.io/getTokenInfo/0xf230b790e05390fc8295f4d3f60332c93bed42e2/?apiKey=freekey
//Get address transactions:
// 	- 10 transaction: https://api.ethplorer.io/getAddressHistory/0xb287a379e6caca6732e50b88d23c290aa990a892/?apiKey=freekey&limit=50
//	- 50: https://api.ethplorer.io/getAddressTransactions/0xb287a379e6caca6732e50b88d23c290aa990a892/?apiKey=freekey&limit=50
/**
 *
 * jQuery('#maintable tr td span a').each(function(index,data){
 * console.log(data.innerText);
 * });
 */

//Get Top holders: https://ethplorer.io/
//var html = '';
//jQuery('#address-token-holders table tbody tr td:nth-child(2) a').each(function(index,data){
//	html = html + data.innerText + "\n";
//});
//html;