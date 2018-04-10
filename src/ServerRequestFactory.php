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

namespace Kigkonsult\RestServer;

use Zend\Diactoros\ServerRequestFactory as master;
use Zend\Diactoros\ServerRequest;
use Kigkonsult\RestServer\Handlers\RequestMethodHandler;
use Kigkonsult\RestServer\Handlers\ContentTypeHandler;
use Kigkonsult\RestServer\Handlers\CorsHandler;
use UnexpectedValueException;

class ServerRequestFactory extends master
{
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
        $server  = static::normalizeServer($server ?: $_SERVER);
        $files   = static::normalizeFiles($files ?: $_FILES);
        $headers = static::marshalHeaders($server);

        $uri = static::marshalUriFromServer($server, $headers);

        $method = RequestMethodHandler::getRequestMethod( $server );

        if (null === $cookies && \array_key_exists('cookie', $headers)) {
            $cookies = self::parseCookieHeader($headers['cookie']);
        }

        $isNoBodyRequest = ( \in_array( $method, [
            RequestMethodHandler::METHOD_DELETE,
            RequestMethodHandler::METHOD_GET,
            RequestMethodHandler::METHOD_HEAD,
            RequestMethodHandler::METHOD_OPTIONS,
        ]));
        // ?? TRACE    (return 200)
        //    If the request is valid, the response SHOULD contain the entire request message
        //    in the entity-body, with a Content-Type of "message/http".
        $stream = 'php://input';
        $error  = null;
        switch ( true ) {
            case( null != $body ):
                break;
            case $isNoBodyRequest:
                $body = $_GET;
//              if ( false !== ( $pos = strpos( $uri, '?' ))) {
//                  parse_str( substr( $uri, ( $pos +1 )), $body );
//              }
                break;
            case (( RequestMethodHandler::METHOD_POST == $method ) &&
                    ContentTypeHandler::hasFormHeader( $headers )):
                $body = $_POST;
                break;
            default:
                $stream = self::getInputFromPhpInput( $error );
                break;
        } // end switch
        $ServerRequest = new ServerRequest(
            $server,
            $files,
            $uri,
            $method,
            $stream,              // body
            $headers,
            $cookies ?: $_COOKIE,
            $query ?: $_GET,
            $body,                // parsedBody
            static::marshalProtocolVersion($server)
        );

        return ( null !== $error )
               ? $ServerRequest->withAttribute( 'error', $error )
               : $ServerRequest;
    }

    /**
     * Return input from php://input (if POST/PUT/PATCH)
     *
     * @param string $error
     * @return resource|string
     */
    private static function getInputFromPhpInput(
        & $error = null
    ) {
        $error  = null;
        \set_error_handler( function( $e ) use ( & $error ) {
            $error = $e;
        }, E_WARNING );
        $input  = \fopen( 'php://input', 'r' );
        $stream = \fopen( 'php://memory', 'rw' );
        \restore_error_handler();
        if ( null !== $error ) {
            $error = 'Can\'t open input stream';
        } elseif ( ! \stream_copy_to_stream( $input, $stream )) {
            $error = 'Can\'t copy input stream';
        }
        \fclose( $input );

        return ( null !== $error ) ? 'php://input' : $stream;
    }

    /**
     * {@inheritdoc}
     *
     * Extends parent with all X-*, cors and IP headers
     */
    public static function marshalHeaders(array $server)
    {
        $headers = [];
        foreach ($server as $key => $value) {
            // Apache prefixes environment variables with REDIRECT_
            // if they are added by rewrite rules
            if (\strpos($key, 'REDIRECT_') === 0) {
                $key = \substr($key, 9);

                // We will not overwrite existing variables with the
                // prefixed versions, though
                if (\array_key_exists($key, $server)) {
                    continue;
                }
            }

            if ($value && \strpos($key, 'HTTP_') === 0) {
                $name           = \strtr(\strtolower(\substr($key, 5)), '_', '-');
                $headers[$name] = $value;
                continue;
            }

            if ($value && \strpos($key, 'CONTENT_') === 0) {
                $name           = 'content-' . \strtolower(\substr($key, 8));
                $headers[$name] = $value;
                continue;
            }

            if ($value && \strpos($key, 'X_') === 0) {      // extended from here
                // accept X_* headers
                $name           = \strtolower( \str_replace( '_', '-', $key ));
                $headers[$name] = $value;
                continue;
            }
            if ($value && \strpos($key, 'X-') === 0) {
                // accept X-* headers
                $name           = \strtolower( $key );
                $headers[$name] = $value;
                continue;
            }
            if ( $value ) {
                // accept CORS-headers (if not already accepted)
                $name = \strtr( \strtolower( $key ), '_', '-' );
                if ( 0 == \strcasecmp( CorsHandler::ORIGIN, $name )) {
                    $headers[$name] = $value;
                } elseif ( 0 == \strcasecmp(
                    CorsHandler::CORSHEADERPRSFIX,
                                             \substr( $name, 0, \strlen( CorsHandler::CORSHEADERPRSFIX ))
                )) {
                    $headers[$name] = $value;
                }
                continue;
            }
        } // end foreach

        return $headers;
    }

    /**
     * {@inheritdoc}
     *
     * private parent...
     */
    private static function marshalProtocolVersion(array $server)
    {
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
