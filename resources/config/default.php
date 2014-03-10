<?php

$config->timeZone = 'US/Central';

$config->CM_Mail->send = true;
$config->CM_Mail->mailDeliveryAgent = null;

$config->CM_Site_Abstract->class = null;

$config->CM_Tracking_Abstract->class = 'CM_Tracking';
$config->CM_Tracking_Abstract->enabled = false;
$config->CM_Tracking_Abstract->code = '';

$config->CM_Splittesting_Abstract->enabled = false;

$config->CM_Search->enabled = true;
$config->CM_Search->servers = array(
    array('host' => 'localhost', 'port' => 9200),
);

$config->CM_Cache_Local->storage = 'CM_Cache_Storage_Apc';
$config->CM_Cache_Local->lifetime = 86400;

$config->CM_Cache_Shared->storage = 'CM_Cache_Storage_Memcache';
$config->CM_Cache_Shared->lifetime = 3600;

$config->CM_Memcache_Client->servers = array(
    array('host' => 'localhost', 'port' => 11211),
);

$config->CM_Redis_Client->server = array('host' => 'localhost', 'port' => 6379);

$config->classConfigCacheEnabled = true;

$config->CM_Stream_Message->enabled = true;
$config->CM_Stream_Message->adapter = 'CM_Stream_Adapter_Message_SocketRedis';

$config->CM_Stream_Adapter_Message_SocketRedis->servers = array(
    array('httpHost' => 'localhost', 'httpPort' => 8085, 'sockjsUrls' => array(
        'http://localhost:8090',
    )),
);

$config->CM_Db_Db->db = 'cm';
$config->CM_Db_Db->username = 'root';
$config->CM_Db_Db->password = '';
$config->CM_Db_Db->server = array('host' => 'localhost', 'port' => 3306);
$config->CM_Db_Db->serversRead = array();
$config->CM_Db_Db->serversReadEnabled = true;
$config->CM_Db_Db->delayedEnabled = true;
$config->CM_Db_Db->reconnectTimeout = 300;

$config->CM_Model_User->class = 'CM_Model_User';

$config->CM_Params->class = 'CM_Params';

$config->CM_Usertext_Usertext->class = 'CM_Usertext_Usertext';

$config->CM_Response_Page->catch = array(
    'CM_Exception_Nonexistent'  => '/error/not-found',
    'CM_Exception_InvalidParam' => '/error/not-found',
    'CM_Exception_AuthRequired' => '/error/auth-required',
    'CM_Exception_NotAllowed'   => '/error/not-allowed',
);

$config->CM_Response_View_Abstract->catch = array(
    'CM_Exception_AuthRequired',
    'CM_Exception_Blocked',
    'CM_Exception_ActionLimit',
    'CM_Exception_Nonexistent',
);

$config->CM_Response_RPC->catch = array(
    'CM_Exception_AuthRequired',
    'CM_Exception_NotAllowed',
);

$config->CM_Stream_Video->adapter = 'CM_Stream_Adapter_Video_Wowza';
$config->CM_Stream_Video->servers = array(
    array('publicHost' => 'localhost', 'publicIp' => '127.0.0.1', 'privateIp' => '127.0.0.1'),
);

$config->CM_Stream_Adapter_Video_Wowza->httpPort = '8086';
$config->CM_Stream_Adapter_Video_Wowza->wowzaPort = '1935';

$config->CM_KissTracking->enabled = false;
$config->CM_KissTracking->awsBucketName = '';
$config->CM_KissTracking->awsFilePrefix = '';

$config->CM_Adprovider->enabled = true;
$config->CM_Adprovider->zones = array();

$config->CM_AdproviderAdapter_Abstract->class = 'CM_AdproviderAdapter_Openx';
$config->CM_AdproviderAdapter_Openx->host = 'www.example.dev';

$config->CM_Jobdistribution_JobWorker->servers = array(array('host' => 'localhost', 'port' => 4730));

$config->CM_Jobdistribution_Job_Abstract->gearmanEnabled = true;
$config->CM_Jobdistribution_Job_Abstract->servers = array(array('host' => 'localhost', 'port' => 4730));

$config->CMService_Amazon_Abstract->accessKey = '';
$config->CMService_Amazon_Abstract->secretKey = '';

$config->CMService_Newrelic->enabled = false;
$config->CMService_Newrelic->appName = 'CM Application';
