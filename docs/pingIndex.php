<?php
/**
 * restServer, a PSR HTTP Message rest server implementation
 *
 * This file is a part of restServer.
 *
 * Copyright 2018 Kjell-Inge Gustafsson, kigkonsult, All rights reserved
 * Link      http://kigkonsult.se/restServer/index.php
 * Version   0.8.0
 * License   Subject matter of licence is the software restServer.
 *           The above copyright, link, package and version notices and
 *           this licence notice shall be included in all copies or
 *           substantial portions of the restServer.
 *           restServer can be used either under the terms of
 *           a proprietary license, available at <https://kigkonsult.se/>
 *           or the GNU Affero General Public License, version 3:
 *           restServer is free software: you can redistribute it and/or
 *           modify it under the terms of the GNU Affero General Public License
 *           as published by the Free Software Foundation, either version 3 of
 *           the License, or (at your option) any later version.
 *           restServer is distributed in the hope that it will be useful,
 *           but WITHOUT ANY WARRANTY; without even the implied warranty of
 *           MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *           GNU Affero General Public License for more details.
 *           You should have received a copy of the GNU Affero General Public
 *           License along with this program.
 *           If not, see <http://www.gnu.org/licenses/>.
 */
/**
 * pingIndex.php
 *
 * Simply checking service is up
 * Test it from a web browser http://<host>/<path>/pingIndex.php/ping
 *  method 'GET'
 *  uri    '/ping'
 * Return (array) classname, version, copyright and time,
 * content-type (auto) whatever your browser expects
 */

namespace Kigkonsult\RestServer;

//require '/path/to/vendor/autoload.php';
  // PSR-7 HTTP message interfaces
  // PSR HTTP message Util interfaces
  // zend-diactoros
  // FastRoute

(new RestServer())->attachRestService( restServer::getPingServiceDef())->run();
