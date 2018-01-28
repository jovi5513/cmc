<?php
if (!defined('MAGE_ROOT')) {
    define('MAGE_ROOT', getcwd());
}

ini_set('memory_limit','512M');
ini_set('max_execution_time',0);

class Shell{

	public $_file;
	public $_header;
	public $_body;
	public $_debug;
	public $_rootPath;
	public $_name;
    public $_slug;
    public $_templateFile = ''; 
    public $outputFolder = ''; 
    public $vars = []; 

    public $basicFileDataPath = MAGE_ROOT . '/_source';
    public $isTestMode = true;
    public $isNewOnly = true;
    public $endpoint = 'https://min-api.cryptocompare.com';
    public $oldEndpoint = 'https://www.cryptocompare.com/api';

    
    /**
     * translate
     * @param $str
     * @return mixed
     */
    public function __($str)
    {
        return $str;
    }

	public function setFile($file){
		$this->_file = $file;
	}


	public function setHeader($header){
		$this->_header = $header;
	}

	public function setBody($body){
		$this->_body = $body;
	}

	public function run($debug = false){
		$this->_debug = $debug;

		$content = $this->_header . "\n" . $this->_body;
		try {
			$file = file_put_contents($this->_file, $content);
			if($this->_debug){
				var_dump($file);
			}
		} catch (Exception $e) {
			
		}
	}

	/**
     * Get  Root path (with last directory separator)
     *
     * @return string
     */
    public function _getRootPath()
    {
        return MAGE_ROOT;
    }



 //Get content URL by CRUL
    public function getContentByCRUL($url) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/49.0.2623.87 Safari/537.36');
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $content = curl_exec($ch);
        if($content === false)
        {
            echo "Error Number:".curl_errno($ch)."<br>";
            echo "Error String:".curl_error($ch);
        }
        
        curl_close($ch);
        return $content;
    }

    
    public static function slug($string, $seperator = '-', $allowANSIOnly = true) {
        $pattern = array(
            "a" => "á|à|ạ|ả|ã|Á|À|Ạ|Ả|Ã|ă|ắ|ằ|ặ|ẳ|ẵ|Ă|Ắ|Ằ|Ặ|Ẳ|Ẵ|â|ấ|ầ|ậ|ẩ|ẫ|Â|Ấ|Ầ|Ậ|Ẩ|Ẫ",
            "o" => "ó|ò|ọ|ỏ|õ|Ó|Ò|Ọ|Ỏ|Õ|ô|ố|ồ|ộ|ổ|ỗ|Ô|Ố|Ồ|Ộ|Ổ|Ỗ|ơ|ớ|ờ|ợ|ở|ỡ|Ơ|Ớ|Ờ|Ợ|Ở|Ỡ",
            "e" => "é|è|ẹ|ẻ|ẽ|É|È|Ẹ|Ẻ|Ẽ|ê|ế|ề|ệ|ể|ễ|Ê|Ế|Ề|Ệ|Ể|Ễ",
            "u" => "ú|ù|ụ|ủ|ũ|Ú|Ù|Ụ|Ủ|Ũ|ư|ứ|ừ|ự|ử|ữ|Ư|Ứ|Ừ|Ự|Ử|Ữ",
            "i" => "í|ì|ị|ỉ|ĩ|Í|Ì|Ị|Ỉ|Ĩ",
            "y" => "ý|ỳ|ỵ|ỷ|ỹ|Ý|Ỳ|Ỵ|Ỷ|Ỹ",
            "d" => "đ|Đ",
            "c" => "ç",
        );
        while (list($key, $value) = each($pattern)) {
            $string = preg_replace('/' . $value . '/i', $key, $string);
        }
        if ($allowANSIOnly) {
            $string = strtolower($string);
            $string = preg_replace(array('/[^a-zA-Z0-9 -]/', '/[ -]+/', '/^-|-$/'), array('', $seperator, ''), $string);
        }
        return $string;
    }


    public function getTemplateFile($file = null)
    {
        if (empty($file)) {
            return null;
        }

        $path = MAGE_ROOT . '/' . $file;
        if ( ! is_file($path)) {
            //Error
            return null;
        }
        $content = '';

        try {
            $content = file_get_contents($path);
        } catch (Exception $e) {
        }

        return $content;

    }
    
    /**
    * return content of template
    */
    public function getTemplate()
    {
        return $this->getTemplateFile($this->templateFile);
    }


    public function generateFileFromTemplate($vars, $template)
    {
        $content = $template;
        foreach ($vars as $k => $v) {
            $content = str_replace('{{' . $k . '}}', $v, $content);
        }

        return $content;
    }


    public function generateFile($fileName)
    {
        $content = $this->getTemplateFile($this->templateFile);
        $content = $this->generateFileFromTemplate($this->getVars(), $content);

        $path = $this->outputFolder . '/' . $fileName;
        try {
            file_put_contents($path, $content);
        } catch (Exception $e) {
        }
    }

    public function createFolder($path)
    {
        if (is_dir($path)) {
            return true;
        }

        try {
            mkdir($path, 0777, true);
        } catch (Exception $e) {
        }

    }

    public function getVars(){
        return $this->vars;
    }

    public function setVars($vars){
        $this->vars = $vars;
    }


    public function getDirContents($dir, &$results = array()){
        $files = scandir($dir);

        foreach($files as $key => $value){
            $path = realpath($dir.DIRECTORY_SEPARATOR.$value);
            if(is_file($path)) {
                $results[] = $path;
            } else if($value != "." && $value != ".." AND !in_array($value, $this->excludeFolders)) {
                $this->getDirContents($path, $results);
                $results[] = $path;
            }
        }

        return $results;
    }

    public function saveUrlTo($url, $to, $isJson = false, $pretty = false){
        $content = $this->getContentByCRUL($url);
        if(!empty($content)){
            try {
                if($isJson && $pretty){
                    $content = $this->jsondecode($content);
                    $content = $this->jsonencode($content);
                }
                file_put_contents($to, $content); 
            } catch (Exception $e) {
                
            }
        }
    }

    public function jsondecode($json){
        return json_decode($json,true);
    }

    public function jsonencode($data){
        return json_encode($data, JSON_PRETTY_PRINT);
    }
    
    public function getDataFromJsonFile($path){
        if(!is_file($path)) return [
            'response'=> 'error',
            'message'=> 'File is not exist'
        ];

        $json = file_get_contents($path);
        return $this->jsondecode($json);
    }

    public function saveDataToJsonFile($data, $path){
        $json = $this->jsonencode($data);
        try {
            file_put_contents($path, $json);
        } catch (Exception $e) {
            
        }
    }



}