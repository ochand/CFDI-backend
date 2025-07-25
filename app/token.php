<?php

// include our OAuth2 Server object
require_once 'core/Oauth2.php';

// Handle a request for an OAuth2.0 Access Token and send the response to the client
$server->handleTokenRequest(OAuth2\Request::createFromGlobals())->send();