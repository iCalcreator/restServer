<?php
/**
 * pingIndex.php
 *
 * Simply checking service is up
 * Open in a web browser http://<host>/<path>/pingIndex.php/ping
 *  method 'GET'
 *  uri    '/ping'
 * Return (array) classname, version and time,
 * content-type whatever your browser expects
 */

namespace Kigkonsult\RestServer;

//require '/path/to/vendor/autoload.php';
  // PSR-7 HTTP message interfaces
  // PSR HTTP message Util interfaces
  // zend-diactoros
  // FastRoute
include '../vendor/autoload.php';

(new RestServer())->attachRestService( restServer::getPingServiceDef())->run();
