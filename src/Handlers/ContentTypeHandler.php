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

use Kigkonsult\RestServer\RestServer;
use Kigkonsult\RestServer\StreamFactory;
use Psr\Http\Message\ServerRequestInterface;
use Kigkonsult\RestServer\ResponseInterface;
use Kigkonsult\RestServer\Handlers\Exceptions\simplexmlLoadErrorException;
use Kigkonsult\RestServer\Handlers\Exceptions\LIBXMLFatalErrorException;
use Kigkonsult\RestServer\Handlers\Exceptions\jsonErrorException;
use RuntimeException;
use Exception;

/**
 * ContentTypeHandler class
 *
 * @author      Kjell-Inge Gustafsson <ical@kigkonsult.se>
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
     * Class constants, headers
     */
    const ACCEPT             = 'Accept';
    const CONTENTTYPE        = 'Content-Type';
    const CONTENTLENGTH      = 'Content-Length';
    const APPLICATIONJSON    = 'application/json';

    /**
     * Class constants, cfg keys
     */
    const UNSERIALIZEOPTIONS = 'unSerializeOptions';
    const SERIALIZEOPTIONS   = 'serializeOptions';

    /**
     * @var string[] $types  array *(type => Handler)
     * @access protected
     * @static
     */
    protected static $types = [
        self::APPLICATIONJSON               => __NAMESPACE__ . '\\ContentTypeHandlers\\JsonHandler',
        'application/xml'                   => __NAMESPACE__ . '\\ContentTypeHandlers\\XMLHandler',
        'text/xml'                          => __NAMESPACE__ . '\\ContentTypeHandlers\\XMLHandler',
        'application/x-www-form-urlencoded' => null,
        'multipart/form-data'               => null,
        'text/plain'                        => __NAMESPACE__ . '\\ContentTypeHandlers\\AsIsHandler',
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
     * Return contenttyp headers content
     *
     * @param array $headers
     * @return string
     * @access private
     * @static
     */
    private static function getContentTypeHeader(
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
            $content = \explode( $SC, $content, 2 )[0];
        }
        return $content;
    }

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
        $content = self::getContentTypeHeader( $headers );
        if ( false === $content ) {
            return false;
        }
        $accepted = \array_slice( \array_keys( self::$types ), 3, 2 );
        if ( \in_array( $content, $accepted )) {
            return true;
        }
        return false;
    }

    /**
     * Return bool true if content-type reveals urlencoding
     *
     * @param array $headers
     * @return bool
     * @static
     */
    public static function hasUrlEncodedBody(
        array $headers
    ) {
        $content = self::getContentTypeHeader( $headers );
        return ( false === $content )
            ? false
            : ( 0 == \strcasecmp( $content, \array_keys( self::$types )[3] ));
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
        if( parent::earlierErrorExists( $request )) {
            return [
                $request,
                $response,
            ];
        }
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
        } // end if

        $config  = parent::getConfig(
            $request,
            self::ACCEPT,
            [self::FALLBACK => self::APPLICATIONJSON]
        );

        return self::validateHeader(
            $request,
            $response,
            self::ACCEPT,
            $config[self::ACCEPT][self::FALLBACK],
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
    public static function unSerializeRequest(
        ServerRequestInterface $request,
        ResponseInterface      $response
    ) {
        if( parent::earlierErrorExists( $request )) {
            return [
                $request,
                $response,
            ];
        }
        $contentType = $request->getAttribute( ContentTypeHandler::CONTENTTYPE, false );
        if ( false === $contentType ) {
            return [
                $request,
                $response,
            ];
        } // end if

        $body = $request->getParsedBody();
        if ( ! empty( $body ) && ( \is_array( $body ) || \is_object( $body ))) {
            return [
                $request,
                $response,
            ];
        } // end if

        $stream = $request->getBody();
        if ( empty( $stream->getSize())) {
            return [
                $request,
                $response,
            ];
        } // end if

        $config  = parent::getConfig(
            $request,
            $contentType,
            [self::UNSERIALIZEOPTIONS => null]
        );

        $error = false;
        try {
            $stream->rewind();
            $body     = $stream->getContents();
            $handler  = self::getHandlerFor( $contentType );
            if ( ! empty( $handler )) {
                $body = $handler::unSerialize(
                    $body,
                    $config[$contentType][self::UNSERIALIZEOPTIONS]
                );
            }
            $request  = $request->withParsedBody( $body )
                                ->withBody( StreamFactory::createStream());
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
                RestServer::ERROR
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
    public static function serializeResponse(
        ServerRequestInterface $request,
        ResponseInterface      $response
    ) {
        $contentType = $request->getAttribute( ContentTypeHandler::ACCEPT, false );

        $body = self::getResponseBody( $response, $error );
        if ( $error instanceof Exception ) { // read body error
            return self::doLogReturn(
                $request->withAttribute( RestServer::ERROR, true ),
                $response->withStatus( 500 )->withRawBody( null ),
                $error,
                RestServer::WARNING
            );
        } // end if

        if ( false === $contentType ) { // no request content-type
            $response2 = self::setResponseBody( $response, $body, $error );
            if ( $error instanceof Exception ) {
                return self::doLogReturn(
                    $request->withAttribute( RestServer::ERROR, true ),
                    $response->withStatus( 500 ),
                    $error,
                    RestServer::ERROR
                );
            }
            return [
                $request,
                $response2,
            ];
        } // end if

        $response = $response->withHeader( self::CONTENTTYPE, $contentType );
        $handler  = self::getHandlerFor( $contentType );
        if ( empty( $handler )) { // no serializing for Content-Type
            $response2 = self::setResponseBody( $response, $body, $error );
            if ( $error instanceof Exception ) {
                return self::doLogReturn(
                    $request->withAttribute( RestServer::ERROR, true ),
                    $response->withStatus( 500 ),
                    $error,
                    RestServer::ERROR
                );
            }
            return [
                $request,
                $response2,
            ];
        } // end if

        $config  = parent::getConfig(
            $request,
            $contentType,
            [self::SERIALIZEOPTIONS => null]
        );

        $error = false;
        try {
            if ( empty( $body )) {
                $body = null;
            } elseif ( 0 !== $body ) {
                $body = $handler::serialize(
                    $body,
                    $config[$contentType][self::SERIALIZEOPTIONS]
                );
            }
            $response = $response->withRawBody( null )
                                 ->withBody( StreamFactory::createStream( $body ));
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
                RestServer::ERROR
            );
        } // end if
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
            $response = $response->withBody( StreamFactory::createStream( $body ))
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
        if ((bool) $force ) {
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
