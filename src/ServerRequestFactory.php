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

use Zend\Diactoros\ServerRequestFactory as master;
use Kigkonsult\RestServer\Handlers\AuthenticationHandler;
use Zend\Diactoros\ServerRequest;
use Kigkonsult\RestServer\Handlers\RequestMethodHandler;
use Kigkonsult\RestServer\Handlers\ContentTypeHandler;
use Kigkonsult\RestServer\Handlers\IpHandler;
use Kigkonsult\RestServer\Handlers\CorsHandler;
use UnexpectedValueException;

/**
 * Extends Zend\Diactoros\ServerRequestFactory
 *
 * @author      Kjell-Inge Gustafsson <ical@kigkonsult.se>
 */
class ServerRequestFactory extends master
{
    /**
     * @var string stream aids
     */
    private static $PHPINPUT        = 'php://input';
    private static $PHPMEMORY       = 'php://memory';
    private static $TXTSTREAMERROR1 = 'Can\'t open input stream';
    private static $TXTSTREAMERROR2 = 'Can\'t copy input stream';
    private static $TXTREADERROR3   = 'Can\'t read input stream';

    /**
     * {@inheritdoc}
     *
     * Extends parent,
     *   body from $_POST if content-type any of  application/x-www-form-urlencoded or multipart/form-data
     */
    public static function fromGlobals(
        array $server  = null,
        array $query   = null,
        array $body    = null,
        array $cookies = null,
        array $files   = null
    ) {
        static $COOKIE = 'cookie';
        static $ERROR  = 'error';
        $server  = static::normalizeServer($server ?: $_SERVER);
        $files   = static::normalizeFiles($files ?: $_FILES);
        $headers = static::marshalHeaders($server);

        $uri = static::marshalUriFromServer($server, $headers);

        $method = RequestMethodHandler::getRequestMethod( $server );

        if (null === $cookies && \array_key_exists($COOKIE, $headers)) {
            $cookies = self::parseCookieHeader($headers[$COOKIE]);
        }

        $isNoBodyRequest = RequestMethodHandler::isNoBodyRequest( $method );
        // ?? TRACE    (return 200)
        //    If the request is valid, the response SHOULD contain the entire request message
        //    in the entity-body, with a Content-Type of "message/http".
        $stream = self::$PHPINPUT;
        $error  = null;
        switch ( true ) {
            case( null != $body ):
                break;
            case $isNoBodyRequest:
                $body = $_GET;
//              if ( false !== ( $pos = \strpos( $uri, '?' ))) {
//                  \parse_str( \substr( $uri, ( $pos +1 )), $body );
//              }
                break;
            case (( RequestMethodHandler::METHOD_POST == $method ) &&
                    ContentTypeHandler::hasFormHeader( $headers )):
                $body   = $_POST;
                break;
            case ( ContentTypeHandler::hasUrlEncodedBody( $headers )) :
                $body   = self::getInputBodyFromPhpInput($error );
                break;
            default:
                $stream = self::getInputStreamFromPhpInput( $error );
                break;
        } // end switch
        $ServerRequest  = new ServerRequest(
            $server,
            $files,
            $uri,
            $method,
            $stream,              // body
            $headers,
            $cookies ?: $_COOKIE,
            $query ?: $_GET,
            $body,                // parsedBody
            static::marshalProtocolVersion( $server )
        );

        return ( null !== $error )
               ? $ServerRequest->withAttribute( $ERROR, $error )
               : $ServerRequest;
    }

    /**
     * Return input stream from php://input (if POST/PUT/PATCH)
     *
     * @param string $error
     * @return resource|string
     */
    private static function getInputStreamFromPhpInput(
        & $error = null
    ) {
        static $R  = 'r';
        static $RW = 'rw';
        $error  = null;
        \set_error_handler( function( $e ) use ( & $error ) {
            $error = $e;
        }, E_WARNING );
        $input  = \fopen( self::$PHPINPUT, $R );
        $stream = \fopen( self::$PHPMEMORY, $RW );
        \restore_error_handler();
        if ( null !== $error ) {
            $error = self::$TXTSTREAMERROR1;
        } elseif ( ! \stream_copy_to_stream( $input, $stream )) {
            $error = self::$TXTSTREAMERROR2;
        }
        \fclose( $input );
        return ( null !== $error ) ? self::$PHPINPUT : $stream;
    }

    /**
     * Return input body from php://input (if POST/PUT/PATCH)
     *
     * @param string $error
     * @return resource|string
     */
    private static function getInputBodyFromPhpInput(
        & $error = null
    ) {
        static $MB_PARSE_STR = 'mb_parse_str';
        static $R            = 'r';
        $error  = null;
        $body   = [];
        \set_error_handler( function( $e ) use ( & $error ) {
            $error = $e;
        }, E_WARNING );
        $input     = \fopen( self::$PHPINPUT, $R );
        \restore_error_handler();
        while( true ) {
            if ( null !== $error ) {
                $error = self::$TXTSTREAMERROR1;
                break;
            }
            $body1 = \stream_get_contents( $input );
            if ( false === $body1 ) {
                $body  = [];
                $error = self::$TXTREADERROR3;
                break;
            }
            if( function_exists( $MB_PARSE_STR ))
                \mb_parse_str( $body1, $body );
            else
                \parse_str(    $body1, $body );
            break;
        }
        \fclose( $input );
        return $body;
    }

    /**
     * {@inheritdoc}
     *
     * Extends parent with all X-*, IP headers, Cors and Auth
     */
    public static function marshalHeaders(array $server)
    {
        static $REDIRECTUC = 'REDIRECT_';
        static $HTTPUC     = 'HTTP_';
        static $CONTENTUC  = 'CONTENT_';
        static $XUC        = 'X_';
        static $XD         = 'X-';
        $headers = [];
        foreach ($server as $key => $value) {
            // Apache prefixes environment variables with REDIRECT_
            // if they are added by rewrite rules
            if ( 0 === \strpos( $key, $REDIRECTUC )) {
                $key = \substr( $key, 9);

                // We will not overwrite existing variables with the
                // prefixed versions, though
                if (\array_key_exists($key, $server)) {
                    continue;
                }
            }

            if ( $value && ( 0 === \strpos( $key, $HTTPUC ))) {
                $name           = self::lowerCaseAndUcReplByDashStr( \substr( $key, 5 ));
                $headers[$name] = $value;
                continue;
            }

            if ( $value && ( 0 === \strpos( $key, $CONTENTUC ))) {
                $name           = self::lowerCaseAndUcReplByDashStr( $key );
                $headers[$name] = $value;
                continue;
            }

            if ( $value && IpHandler::isIpHeader( $key )) {
                $name           = self::lowerCaseAndUcReplByDashStr( $key );
                $headers[$name] = $value;
                continue;
            }
            if ( $value && CorsHandler::isCorsHeader( $key )) {
                $name           = self::lowerCaseAndUcReplByDashStr( $key );
                $headers[$name] = $value;
                continue;
            }
            if ( $value && AuthenticationHandler::isAuthHeader( $key )) {
                $name           = self::lowerCaseAndUcReplByDashStr( $key );
                $headers[$name] = $value;
                continue;
            }
            if ( $value && ( 0 === \strpos( $key, $XUC ))) { // accept X_* headers
                $name           = self::lowerCaseAndUcReplByDashStr( $key );
                $headers[$name] = $value;
                continue;
            }
            if ( $value && ( 0 === \strpos( $key, $XD ))) {  // accept X-* headers
                $name           = \strtolower( $key );
                $headers[$name] = $value;
                continue;
            }
        } // end foreach

        return $headers;
    }

    /**
     * Return lower case string with '_' replaced by '-'
     *
     * @param string $string
     * @return string
     * @access private
     * @static
     */
    private static function lowerCaseAndUcReplByDashStr(
        $string
    ) {
        static $UC         = '_';
        static $D          = '-';
        return \strtolower( \str_replace( $UC, $D, $string ));
    }

    /**
     * {@inheritdoc}
     *
     * private parent...
     */
    private static function marshalProtocolVersion(
        array $server
    ) {
        if ( ! isset($server['SERVER_PROTOCOL'])) {
            return '1.1';
        }

        if ( ! \preg_match('#^(HTTP/)?(?P<version>[1-9]\d*(?:\.\d)?)$#', $server['SERVER_PROTOCOL'], $matches)) {
            throw new UnexpectedValueException(\sprintf(
                'Unrecognized protocol version (%s)',
                $server['SERVER_PROTOCOL']
            ));
        }

        return $matches['version'];
    }

    /**
     * {@inheritdoc}
     *
     * private parent...
     */
    private static function parseCookieHeader($cookieHeader)
    {
        \preg_match_all('(
            (?:^\\n?[ \t]*|;[ ])
            (?P<name>[!#$%&\'*+-.0-9A-Z^_`a-z|~]+)
            =
            (?P<DQUOTE>"?)
                (?P<value>[\x21\x23-\x2b\x2d-\x3a\x3c-\x5b\x5d-\x7e]*)
            (?P=DQUOTE)
            (?=\\n?[ \t]*$|;[ ])
        )x', $cookieHeader, $matches, PREG_SET_ORDER);

        $cookies = [];

        foreach ($matches as $match) {
            $cookies[$match['name']] = \urldecode($match['value']);
        }

        return $cookies;
    }
}
