<?php
/**
 * Created by PhpStorm.
 * User: ezyuskin
 * Date: 30.03.16
 * Time: 17:45
 */

namespace It2k\AtolHubClient;

use Exception;
use Symfony\Component\DomCrawler\Crawler;
use veqryn\Curl\Curl;
use veqryn\Curl\CurlException;
use veqryn\Curl\CurlResponse;

/**
 * Class Client
 * @package It2k\AtolHubClient
 */
class Client extends Curl
{
    /**
     * @var
     */
    private $host;

    /**
     * @var
     */
    private $username;

    /**
     * @var
     */
    private $password;

    /**
     * @var string
     */
    private $factoryNumber;

    /**
     * @var string
     */
    private $imageVersion;

    /**
     * @var string
     */
    private $lastUpdateDate;

    /**
     * @var string
     */
    private $lastCheckUpdateDate;

    /**
     * @var string
     */
    private $serviceAKVersion;

    /**
     * @var array
     */
    private $installedApplications = array();

    /**
     * @var string
     */
    private $utmDataBaseVesion;

    /**
     * @var string
     */
    private $utmSoftwareVersion;

    /**
     * @var string
     */
    private $utmVersion;

    /**
     * @var bool
     */
    private $utmIsDeleteDocuments;

    /**
     * @var int
     */
    private $rsaCertExpiredDaysCount;

    /**
     * @var int
     */
    private $gostCertExpiredDaysCount;

    /**
     * @var int
     */
    private $checkBuffer;

    /**
     * @var int
     */
    private $checkAge;

    /**
     * @var int
     */
    private $documentsCount;

    /**
     * @var string
     */
    private $internetState;

    /**
     * @var string
     */
    private $internetStateTime;

    /**
     * @var string
     */
    private $utmState;

    /**
     * @var string
     */
    private $utmStateTime;

    /**
     * @param string $host
     * @param string $username
     * @param string $password
     * @param int    $timeout
     */
    public function __construct($host, $username, $password, $timeout = 5)
    {
        parent::__construct();

        $this->host = $host;
        $this->username = $username;
        $this->password = $password;

        $this->options['CURLOPT_TIMEOUT'] = $timeout;

        $this->cookie_file = sys_get_temp_dir().'/atol-hub-cookie-'.$host.'.txt';
        $this->user_agent = 'atol-hub-client';
    }


    /**
     * @param string $url
     * @param array  $vars
     * @param array  $enctype
     * @return CurlResponse
     */
    public function post($url, $vars = array(), $enctype = null)
    {
        if (empty($vars)) {
            $vars = array('ping' => 1);
        }

        return $this->securityRequest('POST', $url, $vars);
    }

    /**
     * @param string $url
     * @param array  $vars
     * @return CurlResponse
     * @throws Exception
     */
    public function get($url, $vars = array())
    {
        return $this->securityRequest('GET', $url, $vars);
    }

    /**
     * @return string
     */
    public function getFactoryNumber()
    {
        if (!$this->factoryNumber) {
            $this->getBaseInfo();
        }

        return $this->factoryNumber;
    }

    /**
     * @return string
     */
    public function getImageVersion()
    {
        if (!$this->imageVersion) {
            $this->getBaseInfo();
        }

        return $this->imageVersion;
    }

    /**
     * @return string
     */
    public function getLastUpdateDate()
    {
        if (!$this->lastUpdateDate) {
            $this->getBaseInfo();
        }

        return $this->lastUpdateDate;
    }

    /**
     * @return string
     */
    public function getServiceAKVersion()
    {
        if (!$this->serviceAKVersion) {
            $this->getBaseInfo();
        }

        return $this->serviceAKVersion;
    }

    /**
     * @return array
     */
    public function getInstalledApplications()
    {
        if (!$this->installedApplications) {
            $this->getUpdateInfo();
        }

        return $this->installedApplications;
    }

    /**
     * @return bool
     */
    public function isNeedUpdate()
    {
        $apps = $this->getInstalledApplications();

        if (count($apps) > 0) {
            foreach ($apps as $name => $app) {
                if ($app['new_version'] != '-') {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return string
     */
    public function getLastCheckUpdateDate()
    {
        if (!$this->lastCheckUpdateDate) {
            $this->getUpdateInfo();
        }

        return $this->lastCheckUpdateDate;
    }

    /**
     * @return bool
     */
    public function reboot()
    {
        $response = $this->post('settings/reboot');
        if (strpos($response->body, 'Устройство будет перезагружено')) {
            return true;
        }

        return false;
    }

    /**
     * @return string
     */
    public function getUtmLog()
    {
        $response = $this->get('settings/log-utm');

        $crawler = $this->createCrawlerFromContent($response->body);

        try {
            $log = $crawler->filter('pre');

            return $log->html();
        } catch (Exception $e) {
            return '';
        }
    }

    /**
     * @return bool
     */
    public function clearUtmLog()
    {
        $response = $this->post('settings/log-utm-delete');

        if (strpos($response->body, 'Очистить (всего 0B)') > 0) {
            return true;
        }

        return false;
    }

    /**
     * @return string
     */
    public function getUtmDatabaseVersion()
    {
        if (!$this->utmDataBaseVesion) {
            $this->getUtmSettings();
        }

        return $this->utmDataBaseVesion;
    }

    /**
     * @return string
     */
    public function getUtmSoftwareVersion()
    {
        if (!$this->utmSoftwareVersion) {
            $this->getUtmSettings();
        }

        return $this->utmSoftwareVersion;
    }

    /**
     * @return bool
     */
    public function isUtmDeleteDocuments()
    {
        if (!$this->utmSoftwareVersion) {
            $this->getUtmSettings();
        }

        return $this->utmIsDeleteDocuments;
    }

    /**
     * @return int
     */
    public function getRsaCertExpiredDaysCount()
    {
        if (!$this->rsaCertExpiredDaysCount) {
            $this->getUtmStatistic();
        }

        return $this->rsaCertExpiredDaysCount;
    }

    /**
     * @return int
     */
    public function getGostCertExpiredDaysCount()
    {
        if (!$this->gostCertExpiredDaysCount) {
            $this->getUtmStatistic();
        }

        return $this->gostCertExpiredDaysCount;
    }

    /**
     * @return int
     */
    public function getCheckBuffer()
    {
        if (!$this->checkBuffer) {
            $this->getUtmStatistic();
        }

        return $this->checkBuffer;
    }

    /**
     * @return int
     */
    public function getCheckAge()
    {
        if (!$this->checkAge) {
            $this->getUtmStatistic();
        }

        return $this->checkAge;
    }

    /**
     * @return int
     */
    public function getDocumentsCount()
    {
        if (!$this->documentsCount) {
            $this->getUtmStatistic();
        }

        return $this->documentsCount;
    }

    /**
     * @return string
     */
    public function getInternetState()
    {
        if (!$this->internetState) {
            $this->getUtmStatistic();
        }

        return $this->internetState;
    }

    /**
     * @return string
     */
    public function getInternetStateTime()
    {
        if (!$this->internetStateTime) {
            $this->getUtmStatistic();
        }

        return $this->internetStateTime;
    }

    /**
     * @return string
     */
    public function getUtmState()
    {
        if (!$this->utmState) {
            $this->getUtmStatistic();
        }

        return $this->utmState;
    }

    /**
     * @return string
     */
    public function getUtmStateTime()
    {
        if (!$this->utmStateTime) {
            $this->getUtmStatistic();
        }

        return $this->utmStateTime;
    }

    /**
     * @return string
     */
    public function getUtmVersion()
    {
        if (is_null($this->utmVersion)) {
            $this->getUtmStatistic();
        }

        return $this->utmVersion;
    }

    private function getUtmSettings()
    {
        $response = $this->get('docs/Settings.html');

        $crawler = $this->createCrawlerFromContent($response->body);

        try {
            $deleteDocumentsInfo = $crawler->filter('input[name="del_flag"]');
            $node = $deleteDocumentsInfo->getNode(0);
            $this->utmIsDeleteDocuments = ($node && $deleteDocumentsInfo->getNode(0)->getAttribute('checked') == 'checked') ? false : true;

            $totalInfo = $crawler->filter('#settings-form > div > div > div > div > table:nth-child(2) td.act-data');
            $this->utmDataBaseVesion = (isset($totalInfo->getNode(0)->nodeValue)) ? $totalInfo->getNode(0)->nodeValue : '';
            $this->utmSoftwareVersion = (isset($totalInfo->getNode(1)->nodeValue)) ? $totalInfo->getNode(1)->nodeValue : '';
        } catch (Exception $e) {
            return;
        }
    }

    public function getUtmStatistic()
    {
        $response = $this->get('docs/');

        $crawler = $this->createCrawlerFromContent($response->body);

        try {
            $items = $crawler->filter('li.list-group-item > p');
            foreach ($items as $item) {
                $this->setVarByTitle($item->lastChild->nodeValue, $item->firstChild->nodeValue);
            }
        } catch (Exception $e) {
            return;
        }
    }

    private function getUpdateInfo()
    {
        $response = $this->get('settings/applications');

        $crawler = $this->createCrawlerFromContent($response->body);
        try {
            $text = $crawler->filter('div.row > div.col-md-12 > div.form-group > div.form-group > div.alert-success')->last()->text();
            $this->lastCheckUpdateDate = substr($text, strpos($text, ':')+2, 22);

            $text = $crawler->filter('div.box-installed > form > table')->text();
            $text = explode("\n", $text);

            for ($i = 3; $i < count($text)-3; $i++) {
                $this->installedApplications[trim($text[$i])] = array(
                    'current_version' => trim($text[$i+1]),
                    'new_version' => trim($text[$i+2]),
                );
                $i = $i+2;
            }
        } catch (Exception $e) {
            return;
        }
    }

    private function getBaseInfo()
    {
        $response = $this->get('settings');

        $crawler = $this->createCrawlerFromContent($response->body);
        try {
            $text = $crawler->filter('pre')->text();
            $text = explode("\n", $text);

            for ($i = 0; $i < count($text); $i++) {
                switch (trim($text[$i])) {
                    case 'Заводской номер:':
                        $this->factoryNumber = trim($text[$i+1]);
                        break;
                    case 'Версия образа:':
                        $this->imageVersion = trim($text[$i+1]);
                        break;
                    case 'Последнее обновление ПО:':
                        $this->lastUpdateDate = trim($text[$i+1]);
                        break;
                    case 'Версия сервиса АК:':
                        $this->serviceAKVersion = trim($text[$i+1]);
                        break;
                }
            }

        } catch (Exception $e) {
            return;
        }
    }

    /**
     * @param string $method
     * @param string $url
     * @param array $vars
     * @return CurlResponse
     * @throws CurlException
     * @throws Exception
     */
    private function securityRequest($method, $url, array $vars)
    {
        try {
            $response = $this->request($method, $this->getUrl($url), $vars);

            if (strpos($response->body, 'Вход на сервер настроек') > 0) {
                if ($this->loginIn()) {
                    $response = $this->request($method, $this->getUrl($url), $vars);
                } else {
                    throw new Exception('Auth failure');
                }
            }

            return $response;
        } catch (\Exception $e) {
            return new CurlResponse(null);
        }
    }

    /**
     * @param string $title
     * @param string $value
     */
    private function setVarByTitle($title, $value)
    {
        switch (trim($title)) {
            case 'Дней до истечения сертификата RSA:':
                $this->rsaCertExpiredDaysCount = $value;
                break;
            case 'Дней до истечения сертификата ГОСТ:':
                $this->gostCertExpiredDaysCount = $value;
                break;
            case 'Возраст буфера чеков:':
                $this->checkAge = $value;
                break;
            case 'Размер буфера чеков:':
                $this->checkBuffer = $value;
                break;
            case 'Общее количество документов:':
                $this->documentsCount = $value;
                break;
            case 'Статус интернета:':
                $this->internetState = $value;
                break;
            case 'Статус интернета от:':
                $this->internetStateTime = $value;
                break;
            case 'Статус УТМ:':
                $this->utmState = $value;
                break;
            case 'Статус УТМ от:':
                $this->utmStateTime = $value;
                break;
            case 'Версия УТМ:':
                $this->utmVersion = $value;
                break;
            default:
                //echo $title."-".$value."\n";
                break;
        }
    }

    /**
     * @return bool
     * @throws CurlException
     */
    private function loginIn()
    {
        try {
            $response = $this->request('POST', $this->getUrl('settings/login'), array(
                'username' => $this->username,
                'password' => $this->password,
            ));

            if (strpos($response->body, 'Имя пользователя и/или пароль указаны неверно') > 0) {
                return false;
            }
        } catch (\Exception $e) {
            return false;
        }

        return true;
    }

    /**
     * @param string $suffix
     * @return string
     */
    private function getUrl($suffix)
    {
        return 'http://'.$this->host.'/'.$suffix;
    }

    /**
     * @param string $content
     * @return Crawler
     */
    private function createCrawlerFromContent($content)
    {
        $crawler = new Crawler();
        $crawler->addHtmlContent($content);

        return $crawler;
    }
}
