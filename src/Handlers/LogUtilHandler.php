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

namespace Kigkonsult\RestServer\Handlers;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Kigkonsult\RestServer\RestServer;
use Exception;

/**
 * LogUtilHandler manages restServer handlers logging
 */
class LogUtilHandler implements HandlerInterface
{
    /**
     * Class constants, headers, cfg keys etc
     */

    /**
     * Return stringified object
     *
     * @param mixed $msg
     * @return mixed
     * @static
     */
    public static function getStringifiedObject(
        $msg
    ) {
        switch ( true ) {
            case ( \is_scalar( $msg )) :
                return $msg;
                break;
            case ( $msg instanceof ServerRequestInterface ) :
                return self::getRequestToString( $msg );
                break;
            case ( $msg instanceof ResponseInterface ) :
                return self::getResponseToString( $msg );
                break;
            case ( $msg instanceof Exception ) :
                return self::jTraceEx( $msg );
                break;
            case ( \is_object( $msg ) && \method_exists( $msg, 'toString' )) :
                return $msg->toString();
                break;
            default :
                break;
        } // end switch
        return $msg;
    }

    /**
     * Return stringified request, method, target, headers and attributes
     *
     * @param ServerRequestInterface $request
     * @return string
     * @static
     */
    public static function getRequestToString(
        ServerRequestInterface $request
    ) {
        static $FMT1 = 'REQUEST method/uri: %s %s';
        static $FMT2 = 'headers: %s';
        static $FMT3 = 'attributes: %s';
        static $FMT4 = 'queryParams: %s';

        $string      = [];
        $requestUri  = $request->getAttribute( RestServer::REQUESTTARGET, $request->getRequestTarget());
        $string[]    = \sprintf( $FMT1, $request->getMethod(), $requestUri );
        $string[]    = \sprintf( $FMT2, \var_export( $request->getHeaders(), true ));
        $string[]    = \sprintf( $FMT3, \var_export( $request->getAttributes(), true ));
        $queryParams = $request->getQueryParams();
        if ( ! empty( $queryParams )) {
            $string[] = \sprintf( $FMT4, \var_export( $queryParams, true ));
        }
        return \implode( PHP_EOL, $string );
    }

    /**
     * Return stringified request, method, target, headers and attributes
     *
     * @param ResponseInterface $response
     * @return string
     * @static
     */
    public static function getResponseToString(
        ResponseInterface $response
    ) {
        static $FMT4 = 'RESPONSE status: %s %s';
        static $FMT5 = 'headers: %s';
        $string      = [];
        $string[]    = \sprintf( $FMT4, $response->getStatusCode(), $response->getReasonPhrase());
        $string[]    = \sprintf( $FMT5, \var_export( $response->getHeaders(), true ));
        return \implode( PHP_EOL, $string );
    }

    /**
     * Handler callback debug logging request method, target, headers and attributes, testing
     *
     * Requires global $RestServerLogger
     * @param ServerRequestInterface $request
     * @param ResponseInterface      $response
     * @return array [ ServerRequestInterface, ResponseInterface ]
     */
    public static function logRequest(
        ServerRequestInterface $request,
        ResponseInterface      $response
    ) {
        $logger = RestServer::getLogger();
        if ( ! empty( $logger ) && \method_exists( $logger, RestServer::DEBUG )) {
            $logger->{RestServer::DEBUG}( self::getRequestToString( $request ));
        }
        return [
            $request,
            $response,
        ];
    }

    /**
     * Handler callback debug logging response status and headers, testing
     *
     * Requires global $RestServerLogger
     * @param ServerRequestInterface $request
     * @param ResponseInterface      $response
     * @return array [ ServerRequestInterface, ResponseInterface ]
     */
    public static function logResponse(
        ServerRequestInterface $request,
        ResponseInterface      $response
    ) {
        $logger = RestServer::getLogger();
        if ( ! empty( $logger ) && \method_exists( $logger, RestServer::DEBUG )) {
            $logger->{RestServer::DEBUG}( self::getResponseToString( $response ));
        }
        return [
            $request,
            $response,
        ];
    }

    /**
     * jTraceEx() - provide a Java style exception trace
     * @param Exception $e
     * @param $seen      - array passed to recursive calls to accumulate trace lines already seen
     *                     leave as NULL when calling this function
     * @return string
     * @link http://php.net/manual/en/exception.gettraceasstring.php#114980
     * @static
     */
    public static function jTraceEx(
        Exception $e,
                  $seen = null
    ) {
        $starter = ( null == $seen ) ? 'Caused by: ' . PHP_EOL : '';
        $result  = array();
        if ( ! $seen ) {
            $seen = array();
        }
        $trace    = $e->getTrace();
        $prev     = $e->getPrevious();
        $code     = ( false != ( $c = $e->getCode())) ? \sprintf( '#%s ', $c ) : '';
        $result[] = \sprintf('%s%s: %s%s', $starter, \get_class($e), $code, $e->getMessage());
        $file     = $e->getFile();
        $line     = $e->getLine();
        while (true) {
            $current = "$file:$line";
            if ( \is_array( $seen ) && \in_array( $current, $seen )) {
                $result[] = \sprintf(' ... %d more', \count($trace) + 1);
                break;
            }
            $result[] = \sprintf(
                ' at %s%s%s(%s%s%s)',
                                 \count( $trace ) && \array_key_exists( 'class', $trace[0] ) ? \str_replace( '\\', '.', $trace[0]['class'] ) : '',
                                 \count( $trace ) && \array_key_exists(' class', $trace[0] ) && \array_key_exists( 'function', $trace[0] ) ? '.' : '',
                                 \count( $trace ) && \array_key_exists(' function', $trace[0] ) ? \str_replace( '\\', '.', $trace[0]['function'] ) : '(main)',
                                 $line === null ? $file : \basename( $file ),
                                 $line === null ? '' : ':',
                                 $line === null ? '' : $line
            );
            if ( \is_array( $seen )) {
                $seen[] = "$file:$line";
            }
            if ( 1 > \count( $trace )) {
                break;
            }
            $file = \array_key_exists( 'file', $trace[0] ) ? $trace[0]['file'] : 'Unknown Source';
            $line = \array_key_exists( 'file', $trace[0] ) && \array_key_exists( 'line', $trace[0] ) && $trace[0]['line'] ? $trace[0]['line'] : null;
            \array_shift($trace);
        } // end while
        $result = \join( PHP_EOL, $result );
        if ( $prev ) {
            $result .= PHP_EOL . self::jTraceEx( $prev, $seen );
        }
        return $result;
    }
}
