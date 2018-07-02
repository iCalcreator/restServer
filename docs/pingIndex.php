<?php
/**
 * pingIndex.php
 *
 * @author      Kjell-Inge Gustafsson <ical@kigkonsult.se>
 *
 * Simply checking service is up
 * Open in a web browser http://<host>/<path>/pingIndex.php/ping
 *  method 'GET'
 *  uri    '/ping'
 * Return (array) classname, version and time
 */

namespace Kigkonsult\RestServer;

include '../vendor/autoload.php';

(new RestServer())->attachRestService( restServer::getPingServiceDef())->run();
