<?php
    /**
     * restServer, a PSR HTTP Message rest server implementation
     *
     * This file is a part of restServer.
     *
     * Copyright 2018 Kjell-Inge Gustafsson, kigkonsult, All rights reserved
     * Link      http://kigkonsult.se/restServer/index.php
     * Version   0.9.123
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

// use Kigkonsult\RestServer\Handlers\RequestMethodHandler;
// use Kigkonsult\RestServer\Handlers\CorsHandler;
// use Kigkonsult\RestServer\Handlers\AuthenticationHandler;

    /**
     * Configuration for RestServer
     *
     * @author      Kjell-Inge Gustafsson <ical@kigkonsult.se>
     *
     * A strong recommendation is to place config outside webserver document root.
     *
     * Use all RestServer configs as templates and concatenate into your own one.
     */
$config = [];

    /**
     * Most config keys have only test or default values set,
     * include ONLY on changes!!
     */

    /** ************************************************************************
     * correlation-id
     *
     * unique session id, if NOT set here is it automatically generated
     *
     * value type : string
     */
$config[RestServer::CORRELATIONID] = RestServer::getGuid();

    /** ************************************************************************
     * baseUri
     *
     * Part of request Uri to ELIMINATE to match service uri
     *
     * ex1. server is invoked using
     * 'http://localhost/www/index.php'
     * and
     * your service uri (routes) is like '/', then
     * baseUri = 'index.php'
     *
     *
     * ex2. server is invoked using
     * 'http://localhost/www/index.php/user'
     * and
     * your service uri (routes) is like '/user', then
     * baseUri = 'index.php'
     *
     * ex3. server is invoked using
     * 'http://192.168.0.1/user'
     * and
     * your service routes has an request URI like '/user', then
     * baseUri = ''
     *
     * Default : the file(/script) the RestServer class is invoked from
     * Will ease up going from test to production environment
     *
     * value type : string
     */
// $config[RestServer::BASEURI] = 'index.php';

    /** ************************************************************************
     * Default are all request methods allowed
     *
     * If you do NOT want to allow some (ex HEAD and OPTIONS methods), activate below.
     * Otherwice (default) allowed are
     * request method HEAD only if any service method GET exists
     *   i.e. no HEAD service required, will pick GET service
     * request method OPTIONS
     *   note, an OPTIONS (non-CORS/preflight) request will automatically
     *   create a response with
     *      header (Allow) containing all service definition request-methods
     *      a response body (json string) with ALL (attached) request methods and targets,
     *        (as for now) regardless of current request target!
     *
     *value type : array
     */
    /*
$config[RestServer::DISALLOW] = [
    RequestMethodHandler::METHOD_OPTIONS,
    RequestMethodHandler::METHOD_HEAD
];
     */

    /** ************************************************************************
     *
     * Configuration for the (optional) builtin IpHandler
     * See cfg.1.ip.php for more details.
     *
     * value type : array
     * Note, NO $config[IpHandler::IPHEADER] means no IP mgnt,
     */
// $config[IpHandler::IPHEADER] = include 'cfg/cfg.1.ip.php';

    /** ************************************************************************
     *
     * Configuration for the (optional) builtin CorsHandler
     * Note, OPTIONS (above) must be allowed to manage preflights requests
     * See cfg.2.cors.php for more details.
     *
     * value type : array
     * Note, NO $config[CorsHandler::CORS] means no cors mgnt,
     */
// $config[CorsHandler::CORS] = include 'cfg/cfg.2.cors.php';

    /** ************************************************************************
     *
     * Configuration for the (optional) builtin AuthenticationHandler
     * See cfg.3.auth.php for more details.
     *
     * value type : array
     *
     * Note, NO $config[AuthenticationHandler::AUTHORIZATION] means no auth mgnt,
     */
// $config[AuthenticationHandler::AUTHORIZATION] = include 'cfg/cfg.3.auth.php';

    /** ************************************************************************
     *
     * Opt. debug logging
     *
     * value type : bool
     *
     * Require a logger class instance is set (RestServer::setLogger())
     */
// config[RestServer::DEBUG] = true;

    /** ************************************************************************
     *
     * Opt. configuration
     * for the builtin ContentTypeHandler/EncodingHandler
     * See cfg.56.cte.php for more details.
     *
     * value type : array
     */
// $config += include './cfg.56.cte.php';

    /** ************************************************************************
     *
     * Add a rest service definition
     * see docs/TemplateService.php for implementation
     *
     * value type : array  [ string|string[], string, callable ]
     */
    /*
$config[restServer::SERVICES][] = [
    RestServer::METHOD   => <method>
    RestServer::URI      => <uri>,
    RestServer::CALLBACK => <callable>
];
     */

    /** ************************************************************************
     *
     * Add a custom handler
     * see docs/TemplateHandler.php for implementation
     *
     * value type : callable
     */
// $config[restServer::HANDLERS][] = <callable>;

    /** ************************************************************************
     *
     * Add a final custom handler
     * see docs/TemplateHandler.php for implementation
     *
     * value type : callable
     */
// $config[restServer::FINALHANDLER] = <callable>;

    /** ************************************************************************
     * Return config
     */
return $config;
