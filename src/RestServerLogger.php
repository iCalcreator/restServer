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

/**
 * RestServer log class
 *
 *           serializing/encoding errors etc    with RestServer::ERROR   (response status 500)
 *           Capturing unaccepted requests with prio RestServer::WARNING (response status 4xx)
 *           (test) info                        with RestServer::INFO
 *           debug                              with RestServer::DEBUG
 *
 * RestServerLogger is a error_log() wrapper.
 * RestServerLogger may be replaced by any logger supporting Psr\Log\LogLevel and Psr\Log\LoggerInterface
 */
class RestServerLogger
{
    /**
     * RestServerLogger constructor
     *
     */
    public function __construct()
    {
    }

    /**
     * Log messages with prio RestServer::WARNING
     *
     * @param string|object $message
     */
    public function error(
        $message
    ) {
        $this->log( $message, RestServer::ERROR );
    }

    /**
     * Log messages with RestServer::ERROR
     *
     * @param string|object $message
     */
    public function warning(
        $message
    ) {
        $this->log( $message, RestServer::WARNING );
    }

    /**
     * Log messages  with RestServer::INFO
     *
     * @param string|object $message
     */
    public function info(
        $message
    ) {
        $this->log( $message, RestServer::INFO );
    }

    /**
     * Log messages  with RestServer::DEBUG
     *
     * @param string|object $message
     */
    public function debug(
        $message
    ) {
        $this->log( $message, RestServer::DEBUG );
    }

    /**
     * Log messages with prio
     *
     * @param string|object $message
     * @param string        $prio
     */
    public function log(
        $message,
        $prio
    ) {
        static $FMT = '[%s] %s';
        \error_log( \sprintf( $FMT, $prio, $message ));
    }
}
