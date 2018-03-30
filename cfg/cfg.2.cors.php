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

namespace Kigkonsult\RestServer;

use Kigkonsult\RestServer\Handlers\CorsHandler;

/**
 * Configuration for the builtin CorsHandler
 * CorsHandler provides simple cors,
 * Cross-Origin Resource Sharing,
 * but on server level, NOT on each specific request target level
 * The handler is optional.
 *
 * @see https://www.html5rocks.com/static/images/cors_server_flowchart.png
 *
 * Note, Request-Method OPTIONS must be allowed to manage preflights requests
 * (see cfg.RestServer.php, disallow)
 * Note, NO $config[CorsHandler::CORS] means no cors mgnt
 *
 * Response header Access-Control-Allow-Methods will contain
 *  - all attached service methods
 *  - NON-disallowed methods (ex HEAD/OPTIONS)
 *
 * This config is included in the RestServer config with key CorsHandler::CORS
 *
 * NOTE, most config keys here have only test values set,
 * include ONLY on after update!!
 *
 * ex
 * $config[CorsHandler::CORS] = include 'cfg/cfg.2.cors.php';
 */
$corscfg = [];

    /**
     * errorCode1
     *
     * value type : int
     *
     * statusCode for response if origin is expected but not found,
     * default 400, 'Bad request', set only here if other !!
     *
     */
$corscfg[CorsHandler::ERRORCODE1] = 400;

    /**
     * errorCode2
     *
     * value type : int
     *
     * statusCode for response
     *   if origin is found but NO match,
     *   or origin found but not expected (NOT alterable)
     * default 403, 'Forbidden', set only here if other !!
     */
$corscfg[CorsHandler::ERRORCODE2] = 403;

    /**
     * errorCode3
     *
     * value type : int
     *
     * statusCode for response
     *   if contents in request header Access-Control-Request-Method,
     *     is NOT accepted by rest services definitions (method)
     * default 406, 'Not Acceptable', set only here if other !!
     */
$corscfg[CorsHandler::ERRORCODE3] = 406;

    /**
     * errorCode4
     *
     * value type : int
     *
     * statusCode for response
     *   if contents in request header Access-Control-Request-Header
     *     is NOT in Access-Control-Allow-Headers, below
     * default 406, 'Not Acceptable', set only here if other !!
     */
$corscfg[CorsHandler::ERRORCODE4] = 406;

    /**
     * allow
     *
     * value type : string[]
     *
     * Will match request header 'Origin'
     * Cfg contains accepted origins, (uri-scheme), uri-host, (uri-port)
     * ['*'] accepts all
     * request 'Origin' value will be used in response
     */
$corscfg[RestServer::ALLOW] = ['*'];

    /**
     * Access-Control-Allow-Headers
     *
     * value type : string[]
     *
     * allowed (non-simple) headers
     * optional
     * Checked in checking preflight request header Access-Control-Request-Header
     * Used in (preflight request) response header Access-Control-Allow-Headers
     */
$corscfg[CorsHandler::ACCESSCONTROLALLOWHEADERS] = ['x-header'];

    /**
     * Access-Control-Max-Age
     *
     * value type : int ()
     *
     * in preflight request response only
     * optional
     * Used in response header Access-Control-Max-Age
     */
$corscfg[CorsHandler::ACCESSCONTROLMAXAGE] = 200;

    /**
     * Access-Control-Expose-Headers
     *
     * value type : null|string[]
     *
     * headers to expose (in the NON-preflight response)
     * optional, see also Access-Control-Allow-Headers above
     * Used in response header Access-Control-Expose-Headers
     * Note, empty value will result in an empty response header
     */
$corscfg[CorsHandler::ACCESSCONTROLEXPOSEHEADERS] = ['x-header'];

    /**
     * Access-Control-Allow-Credentials
     *
     * value type : bool
     *
     * true  : cookies are allowed, response header is sent
     * false : (or missing), cookies are not allowed, no response header
     * optional
     * Used in response header Access-Control-Allow-Credentials
     */
$corscfg[CorsHandler::ACCESSCONTROLALLOWCREDENTIALS] = true;

return $corscfg;
