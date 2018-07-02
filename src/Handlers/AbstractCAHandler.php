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

namespace Kigkonsult\RestServer\Handlers;

use Psr\Http\Message\ServerRequestInterface;
use Kigkonsult\RestServer\RestServer;

/**
 * Parent class for CorsHandler and AuthenticationHandler
 *
 * @author      Kjell-Inge Gustafsson <ical@kigkonsult.se>
 */
abstract class AbstractCAHandler extends AbstractHandler implements HandlerInterface
{
    /**
     * Default config return error status keys
     */
    const ERRORCODE1                    = 'errorCode1';
    const ERRORCODE2                    = 'errorCode2';
    const ERRORCODE3                    = 'errorCode3';
    const ERRORCODE4                    = 'errorCode4';
    const ERRORCODE5                    = 'errorCode5';

    /**
     * misc.
     */
    protected static $US = '_';
    protected static $D  = '-';

    /**
     * Return array config for key + opt. updated defaults + correlation-id
     *
     * @param ServerRequestInterface $request
     * @param string                 $key
     * @param array                  $defaults
     * @param array                  $statusCodesWithAltLogPrio
     * @return array
     * @access protected
     * @static
     */
    protected static function getConfig(
        ServerRequestInterface $request,
                               $key,
                         array $defaults,
                         array $statusCodesWithAltLogPrio
    ) {
        $config = $request->getAttribute( RestServer::CONFIG, [] );
        $cfg    = ( isset( $config[$key] )) ? $config[$key] : [];
        foreach ( $defaults as $defaultKey => $defaultValue ) {
            if ( ! isset( $cfg[$defaultKey] )) {
                $cfg[$defaultKey] = $defaultValue;
            }
        }
        if( ! empty( $statusCodesWithAltLogPrio )) {
            foreach( $statusCodesWithAltLogPrio as $altKey ) {
                if( ! is_array( $cfg[$altKey] )) {
                    $cfg[$altKey] = [
                        $cfg[$altKey],
                        RestServer::WARNING,
                    ];
                }
                if( ! is_int( $cfg[$altKey][0] )) {
                    $cfg[$altKey][0] = $defaults[$altKey];
                }
                if(( RestServer::WARNING != $cfg[$altKey][1] ) &&
                   ( RestServer::ERROR   != $cfg[$altKey][1] )) {
                    $cfg[$altKey][1] = RestServer::WARNING;
                }
            }
        }
        $cfg[RestServer::CORRELATIONID] = $config[RestServer::CORRELATIONID];
        return $cfg;
    }
}
