<?php
/**
 * Created by PhpStorm.
 * User: ezyuskin
 * Date: 30.03.16
 * Time: 17:45
 */

namespace It2k\AtolHubClient;

use Exception;
use HttpException;
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
     * @param string $host
     * @param string $username
     * @param string $password
     */
    public function __construct($host, $username, $password)
    {
        $this->host = $host;
        $this->username = $username;
        $this->password = $password;

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
     * @throws HttpException
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
     * @throws HttpException
     */
    private function securityRequest($method, $url, array $vars)
    {
        $response = $this->request($method, $this->getUrl($url), $vars);

        if (strpos($response->body, 'Вход на сервер настроек') > 0) {
            if ($this->loginIn()) {
                $response = $this->request($method, $this->getUrl($url), $vars);
            } else {
                throw new HttpException('Auth failure');
            }
        }

        return $response;
    }

    /**
     * @return bool
     * @throws CurlException
     */
    private function loginIn()
    {
        $response = $this->request('POST', $this->getUrl('settings/login'), array(
            'username' => $this->username,
            'password' => $this->password,
        ));

        if (strpos($response->body, 'Имя пользователя и/или пароль указаны неверно') > 0) {
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
