<?php
/**
 * Created by PhpStorm.
 * User: ezyuskin
 * Date: 30.03.16
 * Time: 18:00
 */

use It2k\AtolHubClient\Client;

include __DIR__.'/../vendor/autoload.php';

$config = include __DIR__.'/config.php';

$client = new Client($config['host'], $config['username'], $config['password']);
echo $client->getUtmStatistic();
