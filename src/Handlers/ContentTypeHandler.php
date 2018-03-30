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

use Kigkonsult\RestServer\RestServer;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Kigkonsult\RestServer\Handlers\Exceptions\simplexmlLoadErrorException;
use Kigkonsult\RestServer\Handlers\Exceptions\LIBXMLFatalErrorException;
use Kigkonsult\RestServer\Handlers\Exceptions\jsonErrorException;
use RuntimeException;
use Exception;

/**
 * ContentTypeHandler class
 *
 * Managing request headers Content-Type and Accept
 * and manage message serializing
 *
 * If header 'Accept' is missing in request headers,
 * config[Accept][default] is set (make sure there is a supported Handler for the default),
 * otherwise 'application/json' is set
 * ex
 * <code>
 * $config[ContentTypeHandler::ACCEPT][ContentTypeHandler::FALLBACK] = <default>;
 * </code>
 *
 * You can add a contentType Handler
 * ex
 * <code>
 * Kigkonsult\RestServer\Handlers\ContentTypeHandler::register( 'application/json', 'kigkonsult\\RestServer\\Handlers\\ContentTypeHandlers\\JsonHandler' );
 * </code>
 * The new Handler class MUST implement Kigkonsult\RestServer\Handlers\ContentTypeHandlers\ContentTypeInterface
 *
 * @link https://www.iana.org/assignments/media-types/media-types.xhtml
 */
class ContentTypeHandler extends AbstractCteHandler
{
    /**
     * Class constants, headers, cfg keys etc
     */
    const ACCEPT = 'Accept';

    const CONTENTTYPE = 'Content-Type';

    const CONTENTLENGTH = 'Content-Length';

    const UNSERIALIZEOPTIONS = 'unSerializeOptions';

    const SERIALIZEOPTIONS = 'serializeOptions';

    /**
     * @var string[] $types  array *(type => Handler)
     * @access protected
     * @static
     */
    protected static $types = [
        'application/json'                  => __NAMESPACE__ . '\\ContentTypeHandlers\\JsonHandler',
        'application/xml'                   => __NAMESPACE__ . '\\ContentTypeHandlers\\XMLHandler',
        'text/xml'                          => __NAMESPACE__ . '\\ContentTypeHandlers\\XMLHandler',
        'application/x-www-form-urlencoded' => null,
        'multipart/form-data'               => null,
        'text/plain'                        => null,
    ];

    /**
     * @var string $astChar "i'll accept whatever you have..."
     * @access protected
     * @static
     */
    protected static $astChar = '*/*';

    /**
     * @var string $OptionsContentType   Content-Type for Options response
     * @access private
     * @static
     */
    private static $OptionsContentType = 'httpd/unix-directory';

    /**
     * Return bool true if (POST=method and) content-type has form types
     *
     * @param array $headers
     * @return bool
     * @static
     */
    public static function hasFormHeader(
        array $headers
    ) {
        static $SC = ';';
        $headers   = \array_change_key_case( $headers );
        $cmpKey    = \strtolower( self::CONTENTTYPE );
        if ( ! \array_key_exists( $cmpKey, $headers )) {
            return false;
        }
        $content = $headers[$cmpKey];
        if ( false !== \strpos( $content, $SC )) {
            list( $content, $dummy ) = \explode( $SC, $content, 2 );
        }
        $accepted = \array_slice( \array_keys( self::$types ), 3, 2 );
        if ( \in_array( $content, $accepted )) {
            return true;
        }
        return false;
    }

    /**
     * Handler callback mgnt unserializing header value for request
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface      $response
     * @return array [ ServerRequestInterface, ResponseInterface ]
     * @static
     */
    public static function validateRequestHeader(
        ServerRequestInterface $request,
        ResponseInterface      $response
    ) {
        return self::validateHeader(
            $request,
            $response,
            self::CONTENTTYPE,
            null,
            415     // Unsupported Media Type
        );
    }

    /**
     * Handler callback mgnt serializing header value for response
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface      $response
     * @return array [ ServerRequestInterface, ResponseInterface ]
     * @static
     */
    public static function validateResponseHeader(
        ServerRequestInterface $request,
        ResponseInterface      $response
    ) {
        if ( RequestMethodHandler::METHOD_OPTIONS == $request->getMethod()) {
            return [
                $request,
                $response,
            ];
        }
        $config  = $request->getAttribute( RestServer::CONFIG, [] );
        $fallback = ( isset( $config[self::ACCEPT][self::FALLBACK] ))
                           ? $config[self::ACCEPT][self::FALLBACK]
                           : \array_keys( self::$types)[0];

        return self::validateHeader(
            $request,
            $response,
            self::ACCEPT,
            $fallback,
            406     // not acceptable
        );
    }

    /**
     * Handler callback unserializing request body
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface      $response
     * @return array [ ServerRequestInterface, ResponseInterface ]
     * @static
     */
    public static function unSerialize(
        ServerRequestInterface $request,
        ResponseInterface      $response
    ) {
        $contentType = $request->getAttribute( ContentTypeHandler::CONTENTTYPE, false );
        if ( false === $contentType ) {
            return [
                $request,
                $response,
            ];
        }

        $body = $request->getParsedBody();
        if ( ! empty( $body ) && ( \is_array( $body ) || \is_object( $body ))) {
            return [
                $request,
                $response,
            ];
        }

        $stream = $request->getBody();
        if ( empty( $stream->getSize())) {
            return [
                $request,
                $response,
            ];
        }

        $config  = $request->getAttribute( RestServer::CONFIG, [] );
        $options = ( isset( $config[$contentType][self::UNSERIALIZEOPTIONS] ))
                          ? $config[$contentType][self::UNSERIALIZEOPTIONS]
                          : null;
        $error = false;
        try {
            $stream->rewind();
            $body     = $stream->getContents();
            if ( ! empty( self::$types[$contentType] )) {
                $body = self::$types[$contentType]::unSerialize( $body, $options );
            }
            $request  = $request->withParsedBody( $body )
                                ->withBody( RestServer::getNewStream());
        } catch ( LIBXMLFatalErrorException $e ) {
            $error = $e;
        } catch ( simplexmlLoadErrorException $e ) {
            $error = $e;
        } catch ( jsonErrorException $e ) {
            $error = $e;
        } catch ( RuntimeException $e ) {
            $error = $e;
        } catch ( Exception $e ) {
            $error = $e;
        }
        if ( false !== $error ) {
            return self::doLogReturn(
                $request->withAttribute( RestServer::ERROR, true ),
                $response->withStatus( 500 ),
                $error,
                RestServer::WARNING
            );
        } // end if
        return [
            $request,
            $response,
        ];
    }

    /**
     * Handler callback serializing response body
     *
     * Will also (opt) set response content-type(/-length) and encoding headers
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface      $response
     * @return array [ ServerRequestInterface, ResponseInterface ]
     * @static
     */
    public static function serialize(
        ServerRequestInterface $request,
        ResponseInterface      $response
    ) {
        $contentType = $request->getAttribute( ContentTypeHandler::ACCEPT, false );

        $body = self::getResponseBody( $response, $error );
        if ( $error instanceof Exception ) {
            return self::doLogReturn(
                $request->withAttribute( RestServer::ERROR, true ),
                $response->withStatus( 500 )->withRawBody( null ),
                $error,
                RestServer::WARNING
            );
        } // end if

        if ( false === $contentType ) { // no request content-type
            $response = self::setResponseBody( $response, $body, $error );
            if ( $error instanceof Exception ) {
                return self::doLogReturn(
                    $request->withAttribute( RestServer::ERROR, true ),
                    $response->withStatus( 500 ),
                    $error,
                    RestServer::WARNING
                );
            }

            return [
                $request,
                $response,
            ];
        } // end if

        $response = $response->withHeader( self::CONTENTTYPE, $contentType );
        if ( empty( self::$types[$contentType] )) { // no serializing for Content-Type
            $response = self::setResponseBody( $response, $body, $error );
            if ( $error instanceof Exception ) {
                return self::doLogReturn(
                    $request->withAttribute( RestServer::ERROR, true ),
                    $response->withStatus( 500 ),
                    $error,
                    RestServer::WARNING
                );
            }
            return [
                $request,
                $response,
            ];
        } // end if

        $config  = $request->getAttribute( RestServer::CONFIG, [] );
        $options = ( isset( $config[$contentType][self::SERIALIZEOPTIONS] ))
                          ? $config[$contentType][self::SERIALIZEOPTIONS]
                          : null;
        $error = false;
        try {
            if (empty($body)) {
                $body = null;
            } elseif (0 !== $body) {
                $body = self::$types[$contentType]::serialize($body, $options);
            }
            $response = $response->withRawBody(null)
                ->withBody(RestServer::getNewStream($body));
        } catch ( LIBXMLFatalErrorException $e ) {
            $error = $e;
        } catch ( simplexmlLoadErrorException $e ) {
            $error = $e;
        } catch ( jsonErrorException $e ) {
            $error = $e;
        } catch ( RuntimeException $e ) {
            $error = $e;
        } catch ( Exception $e ) {
            $error = $e;
        }
        if ( $error instanceof Exception ) {
            return self::doLogReturn(
                $request->withAttribute( RestServer::ERROR, true ),
                $response->withStatus( 500 ),
                $error,
                RestServer::WARNING
            );
        }

        return [
            $request,
            $response,
        ];
    }

    /**
     * Get response body
     *
     * @param ResponseInterface $response
     * @param mixed             $error
     * @return mixed
     * @access private
     * @static
     */
    private static function getResponseBody(
        ResponseInterface $response,
                        & $error = null
    ) {
        $error = false;
        $body  = null;
        try {
            $body = $response->getResponseBody();
        } catch ( RuntimeException $e ) {
            $error = $e;
        } catch ( Exception $e ) {
            $error = $e;
        }
        return $body;
    }

    /**
     * Set response body
     *
     * @param ResponseInterface $response
     * @param mixed             $body
     * @param mixed             $error
     * @return ResponseInterface
     * @access private
     * @static
     */
    private static function setResponseBody(
        ResponseInterface $response,
                          $body,
                        & $error = null
    ) {
        $error = false;
        try {
            $response = $response->withBody( RestServer::getNewStream( $body ))
                                 ->withRawBody( null );
        } catch ( RuntimeException $e ) {
            $error = $e;
        } catch ( Exception $e ) {
            $error = $e;
        }
        return $response;
    }

    /**
     * Set response Content-Length
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface      $response
     * @param bool                   $force
     * @return array [ ServerRequestInterface, ResponseInterface ]
     * @static
     */
    public static function setContentLength(
        ServerRequestInterface $request,
        ResponseInterface      $response,
                               $force = null
    ) {
        if ( (bool) $force ) {
            $response = $response->withHeader(self::CONTENTTYPE, self::$OptionsContentType );
        }
        return [
            $request,
            ( $response->hasHeader( self::CONTENTTYPE ))
            ? $response->withHeader(self::CONTENTLENGTH, $response->getBody()->getSize())
            : $response,
        ];
    }

    /**
     * Filter requested types by accepted types, replace '*' by all
     *
     * Shortcut1: all *+xml content-types are mapped to application/xml Handler
     * Shortcut2: all *+json content-types are mapped to application/json Handler
     *
     * @param string[] $types
     * @return array
     * @access protected
     * @static
     * @todo asterisk mgnt
     */
    protected static function filterAccepted(
        array $types
    ) {
        static $PXML  = '+xml';
        static $TXML  = 'application/xml';
        static $PJSON = '+json';
        static $TJSON = 'application/json';
        foreach ( $types as $sType ) {
            if (( $PXML == \substr( $sType, -4 )) &&
             ! isset( self::$types[$sType] )) { // all xml-types
                self::register(    $sType, self::$types[$TXML] );
                continue;
            }
            if (( $PJSON == \substr( $sType, -5 )) &&
             ! isset( self::$types[$sType] )) { // json-types
                self::register(    $sType, self::$types[$TJSON] );
                continue;
            }
        } // end foreach
        return parent::filterAccepted( $types );
    }
}
