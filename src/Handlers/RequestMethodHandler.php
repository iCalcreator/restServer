<?php
/**
 * restServer, a PSR HTTP Message rest server implementation
 *
 * This file is a part of restServer.
 *
 * Copyright 2018 Kjell-Inge Gustafsson, kigkonsult, All rights reserved
 * Link      http://kigkonsult.se/restServer/index.php
 * Version   0.9.23
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

use Fig\Http\Message\RequestMethodInterface;
use Psr\Http\Message\ServerRequestInterface;
use Kigkonsult\RestServer\ResponseInterface;
use Kigkonsult\RestServer\RestServer;
use Kigkonsult\RestServer\StreamFactory;
use RuntimeException;

/**
 * RequestMethodHandler manages Request-Method
 * http methods in RequestMethodInterface
 * @see https://tools.ietf.org/html/rfc7231#page-24
 */
class RequestMethodHandler extends AbstractHandler implements RequestMethodInterface
{
    /**
     * http methods in RequestMethodInterface
     * @see https://tools.ietf.org/html/rfc7231#page-24
     */

    /**
     * Request methods
     */
    private static $requestMethods = [
        self::METHOD_OPTIONS,
        self::METHOD_HEAD,
        self::METHOD_GET,
        self::METHOD_POST,
        self::METHOD_PUT,
        self::METHOD_PATCH,
        self::METHOD_DELETE,
        self::METHOD_PURGE,
        self::METHOD_OPTIONS,
        self::METHOD_TRACE,
        self::METHOD_CONNECT,
    ];

    /**
     * Request header
     */
    const REQUESTMETHOD = 'Request-Method';

    /**
     * Request headers
     */
    private static $requestMethodHeaders = [
        'REQUEST-METHOD',
        'X-HTTP-METHOD-OVERRIDE',
        'HTTP-X-HTTP-METHOD-OVERRIDE',
    ];

    /**
     * Return request method
     *
     * @param array $server
     * @return string
     * @static
     * @todo error mgnt (not found?)
     */
    public static function getRequestMethod(
        array $server
    ) {
        static $D      = '-';
        static $UL     = '_';
        $requestMethod = null;
        foreach ( $server as $headerKey => $headerValue ) {
            $headerKey = \str_replace( $UL, $D, $headerKey );
            if ( 0 == \strcasecmp( self::$requestMethodHeaders[0], $headerKey )) {
                $requestMethod = \strtoupper( $headerValue );
                break ;
            } // end if
        } // end foreach
        if ( 0 != \strcasecmp( self::METHOD_POST, $requestMethod )) {
            return $requestMethod;
        }
        foreach ( $server as $headerKey => $headerValue ) {
            $headerKey = \str_replace( $UL, $D, $headerKey );
            foreach ( self::$requestMethodHeaders as $x => $cmpHdr ) {
                if ( empty( $x )) {
                    continue;
                }
                if ( 0 == \strcasecmp( $cmpHdr, $headerKey )) {
                    $requestMethod = \strtoupper( $headerValue );
                    break 2;
                }
            } // end foreach
        } // end foreach
        return $requestMethod;
    }

    /**
     * Return bool true if method is valid
     *
     * @param string|string[] $method
     * @return bool
     * @static
     */
    public static function isValidRequestMethod(
        $method
    ) {
        foreach ((array) $method as $mthd ) {
            if ( ! \in_array( $mthd, self::$requestMethods )) {
                return false;
            }
        }
        return true;
    }

    /**
     * Handler callback validating request method, if config set unvalid method returns 405
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface      $response
     * @return array [ ServerRequestInterface, ResponseInterface ]
     */
    public static function validateRequestMethod(
        ServerRequestInterface $request,
        ResponseInterface      $response
    ) {
        static $ERRORFMT2                           = 'No support for %s: %s';
        $method                                     = $request->getMethod();
        list( $allowedMethods, $disallowedMethods ) = self::getAccurateMethods( $request );
        if( self::isMethodAccepted( $method, $disallowedMethods, $allowedMethods )) {
            return [
                $request,
                $response,
            ];
        }
        return self::doLogReturn(
            $request->withAttribute( RestServer::ERROR, true ),
            self::setStatusMethodNotAllowed(
                 $response,
                 $allowedMethods
            ),
            new RuntimeException( \sprintf(
                $ERRORFMT2,
                self::REQUESTMETHOD,
                $method
            )),
            RestServer::WARNING
        );
    }

    /**
     * Return allowed and disallowed Methods from config
     *
     * @param ServerRequestInterface $request
     * @return array
     * @access private
     * @static
     */
    private static function getAccurateMethods(
        ServerRequestInterface $request
    ) {
        $allowedMethods    = $request->getAttribute( RestServer::REQUESTMETHODURI, [] );
        $allowedMethods    = \array_keys( $allowedMethods );
        $disallowedMethods = self::getDisallowedMethodsFromConfig( $request );
        $allowedMethods    = self::extendAllowedMethods(
            $request,
            $allowedMethods,
            $disallowedMethods
        );
        return [$allowedMethods, $disallowedMethods];
    }

    /**
     * Return bool true if method is accepted
     *
     * @param string $method
     * @param array $disallowedMethods
     * @param array $allowedMethods
     * @return bool
     * @access private
     * @static
     */
    private static function isMethodAccepted(
        $method,
        array $disallowedMethods,
        array $allowedMethods
    ) {
        return (
            self::isValidRequestMethod( $method ) &&
            ! \in_array( $method, $disallowedMethods ) &&
            \in_array( $method, $allowedMethods )
        );
    }

    /**
     * Return disallowedMethods from config
     *
     * @param ServerRequestInterface $request
     * @return array
     * @access private
     * @static
     */
    private static function getDisallowedMethodsFromConfig(
        ServerRequestInterface $request
    ) {
        $config = $request->getAttribute( RestServer::CONFIG, [] );
        return ( isset( $config[RestServer::DISALLOW] )) ? $config[RestServer::DISALLOW] : [];
    }

    /**
     * Return extended allowedMethods with NOT (in config) rejected ones (HEAD/OPTIONS)
     *
     * @param ServerRequestInterface $request
     * @param array             $allowedMethods
     * @param array             $disallowedMethods
     * @return array
     * @static
     */
    public static function extendAllowedMethods(
        ServerRequestInterface $request,
        array                  $allowedMethods,
        array                  $disallowedMethods = null
    ) {
        if ( empty( $disallowedMethods )) {
            $disallowedMethods = self::getDisallowedMethodsFromConfig( $request );
        }
        if (  \in_array( self::METHOD_GET, $allowedMethods ) &&
            ! \in_array( self::METHOD_HEAD, $disallowedMethods )) {
            $allowedMethods[] = self::METHOD_HEAD;
        }
        if ( ! \in_array( self::METHOD_OPTIONS, $disallowedMethods )) {
            $allowedMethods[] = self::METHOD_OPTIONS;
        }
        return $allowedMethods;
    }

    /**
     * Return $response with status 405 and header allowed method(s)
     *
     * @param ResponseInterface $response
     * @param array             $allowedMethods
     * @return ResponseInterface
     * @static
     */
    public static function setStatusMethodNotAllowed(
        ResponseInterface $response,
        array             $allowedMethods
    ) {
        return self::setResponseHeaderAllowed(
            $response->withStatus( 405 ),
            $allowedMethods
        );
    }

    /**
     * Return $response with header allowed method(s)
     *
     * @param ResponseInterface $response
     * @param array             $allowedMethods
     * @return ResponseInterface
     * @static
     * @access private
     */
    private static function setResponseHeaderAllowed(
        ResponseInterface $response,
        array             $allowedMethods
    ) {
        return $response->withHeader(
            RestServer::ALLOW,
            \implode( self::COMMA . self::$SP, $allowedMethods )
        );
    }

    /**
     * Set response header 'Allow' as well as (body) payload for request method OPTIONS
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface      $response
     * @return array [ ServerRequestInterface, ResponseInterface ]
     * @throws RuntimeException on stream write error
     * @static
     * @todo use JsonHandler ??
     */
    public static function setOptionsResponsePayload(
        ServerRequestInterface $request,
        ResponseInterface      $response
    ) {
        list( $allowedMethods, $disallowedMethods ) = self::getAccurateMethods( $request );
        $response                                   = self::setResponseHeaderAllowed( $response, $allowedMethods );

        $allowMethodUri = $request->getAttribute( RestServer::REQUESTMETHODURI, [] );
        foreach ( $allowedMethods as $method ) {
            if (( self::METHOD_HEAD == $method ) &&
               isset( $allowMethodUri[self::METHOD_GET] )) {
                $allowMethodUri[$method] = $allowMethodUri[self::METHOD_GET];
                continue;
            } // end if
            if ( ! isset( $allowMethodUri[$method] )) {
                $allowMethodUri[$method] = [];
            }
        } // end foreach
        \json_encode( null );
        $jsonString = \json_encode( $allowMethodUri );
        if ( JSON_ERROR_NONE !== \json_last_error()) {
            throw new RuntimeException( \json_last_error_msg(), 500 );
        }
        $response = $response->withBody( StreamFactory::createStream( $jsonString ));
        return ContentTypeHandler::setContentLength( $request, $response, true );
    }
}
