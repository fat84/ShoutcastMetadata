<?php

/**
 * Class ShoutcastMetadata
 */
class ShoutcastMetadata {

    const MAX_CHUNK_SIZE = 8192;

    /**
     * @var string
     */
    public $url;

    /**
     * @var string
     */
    public $hostname;

    /**
     * @var int
     */
    public $port;

    /**
     * @var string
     */
    public $path;

    /**
     * @var int
     */
    public $timeout = 30;

    /**
     * @var int
     */
    public $chunkSize = 128;

    /**
     * StreamMetaData constructor.
     *
     * @param $url
     */
    public function __construct($url) {
        $this->url = $url;
        $this->hostname = parse_url($url,PHP_URL_HOST);
        $this->port = parse_url($url, PHP_URL_PORT) ? parse_url($url, PHP_URL_PORT) : 80;
        $this->path = parse_url($url,PHP_URL_PATH) ? parse_url($url, PHP_URL_PATH) : '/';
    }

    /**
     * @return string
     */
    public function readTitle($default='') {
        $fp = fsockopen($this->hostname, $this->port, $errno, $errstr, $this->timeout);
        if (!$fp) {
            echo "$errstr ($errno)<br />\n";
        } else {
            $out = "GET " . $this->path . " HTTP/1.1\r\n";
            $out .= "Host: " . $this->hostname . "\r\n";
            $out .= "Icy-MetaData:1\r\n";
            //$out .= "Content-Type: charset=utf-8\r\n";
            $out .= "Connection: Close\r\n\r\n";
            fwrite($fp, $out);
            $resultHeaders = '';
            while (!feof($fp)) {
                $resultHeaders .= fgets($fp, $this->chunkSize);
                $resArr = $this->_parseStringData("\r\n", ':', $resultHeaders);
                if (array_key_exists('icy-metaint', $resArr)) {
                    break;
                }
            }

            fgets($fp);

            $count = intdiv($resArr['icy-metaint'], $this->chunkSize);
            $last = $resArr['icy-metaint'] % $this->chunkSize;
            for ($i = 0; $i < $count; $i++) {
                fread($fp, $this->chunkSize);
            }
            if ($last) {
                fread($fp, $last);
            }

            $byte = fread($fp, 1);
            $byte = ord($byte);
            $byte *= 16;

            $metaString = fread($fp, $byte);
            $metadata = $this->_parseStringData(';', '=', $metaString);

            fclose($fp);
        }

        return isset($metadata['StreamTitle']) ? $metadata['StreamTitle'] : $default;
    }

    /**
     * @param $delimiter1
     * @param $delimiter2
     * @param $string
     *
     * @return array
     */
    protected function _parseStringData($delimiter1, $delimiter2, $string) {
        $result = [];
        $arr = explode($delimiter1, $string);
        foreach ($arr as $key=>$value) {
            if (empty($value)) continue;
            $item = explode($delimiter2, $value);
            if (count($item) == 2) {
                $result[$item[0]] = trim($item[1]);
            }
        }
        return $result;
    }
}
