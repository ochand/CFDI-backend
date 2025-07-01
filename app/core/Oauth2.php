<?php
//ini_set('display_errors',1);
//error_reporting(E_ALL);
require_once('core/OAuth2/Autoloader.php');
require_once 'core/config.php';
require_once 'core/sPDO.php';
OAuth2\Autoloader::register();

$storage = new OAuth2\Storage\Pdo( sPDO::singleton() );

// Pass a storage object or array of storage objects to the OAuth2 server class
$server = new OAuth2\Server($storage);

// Add the "Client Credentials" grant type (it is the simplest of the grant types)
$server->addGrantType(new OAuth2\GrantType\ClientCredentials($storage));

// Add the "Authorization Code" grant type (this is where the oauth magic happens)
$server->addGrantType(new OAuth2\GrantType\AuthorizationCode($storage));