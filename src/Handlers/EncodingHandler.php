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

use Kigkonsult\RestServer\RestServer;
use Kigkonsult\RestServer\StreamFactory;
use Psr\Http\Message\ServerRequestInterface;
use Kigkonsult\RestServer\ResponseInterface;
use Kigkonsult\RestServer\Handlers\Exceptions\zlibErrorException;
use RuntimeException;
use InvalidArgumentException;
use Exception;

/**
 * EncodingHandler class
 *
 * Managing request headers Content-Encoding and Accept-Encoding
 * and manage message encode/decode
 *
 * If header 'Accept-Encoding' is missing in request headers,
 * the config setting
 * config[Accept-Encoding][default] = <Accept-Encoding>
 * is set (make sure there is a supported Handler for the default),
 * otherwise 'gzip' is set
 * ex
 * <code>
 * $config[EncodingHandler::ACCEPTENCODING][EncodingHandler::FALLBACK] = <default>;
 * </code>
 *
 * You can add an encoding Handler
 * ex
 * <code>
 * Kigkonsult\RestServer\Handler\EncodingHandler::register( 'gzip', 'kigkonsult\\RestServer\\Handlers\\EncodingHandlers\\GzipHandler' );
 * </code>
 * The new Handler class MUST implement Kigkonsult\RestServer\Handlers\EncodingHandlers\EncodingInterface
 *
 * You can alter or set deCode/enCode level/options in config
 * ex (for gzip)
 * <code>
 * $config[EncodingHandler::GZIP][EncodingHandler::ENCODELEVEL]   = -1;
 * $config[EncodingHandler::GZIP][EncodingHandler::ENCODEOPTIONS] = FORCE_GZIP;
 * </code>
 *
 * @link https://en.wikipedia.org/wiki/HTTP_compression
 */
class EncodingHandler extends AbstractCteHandler
{
    /**
     * Class constants, headers
     */
    const CONTENTENCODING = 'Content-Encoding';
    const ACCEPTENCODING  = 'Accept-Encoding';

    /**
     * Class constants, header value
     */
    const IDENTITY        = 'identity';

    /**
     * Class constants, decode parameter config keys
     */
    const DECODELEVEL     = 'deCodeLevel';
    const DECODEOPTIONS   = 'deCodeOptions';

    /**
     * Class constants, encode parameter config keys
     */
    const ENCODELEVEL     = 'enCodeLevel';
    const ENCODEOPTIONS   = 'enCodeOptions';

    /**
     * @var string[] $types  array of encoding type => Handler
     * @access protected
     * @static
     */
    protected static $types = [
        'gzip'         => 'Kigkonsult\\RestServer\\Handlers\\EncodingHandlers\\GzipHandler',
        'deflate'      => 'Kigkonsult\\RestServer\\Handlers\\EncodingHandlers\\DeflateHandler',
        self::IDENTITY => 'Kigkonsult\\RestServer\\Handlers\\EncodingHandlers\\IdentityHandler',
    ];

    /**
     * @var string $astChar "i'll accept whatever you have..."
     * @access protected
     * @static
     */
    protected static $astChar = '*';

    /**
     * Handler callback mgnt decoding header value for request
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface      $response
     * @return array
     * @static
     */
    public static function validateRequestHeader(
        ServerRequestInterface $request,
        ResponseInterface      $response
    ) {
        return self::validateHeader(
            $request,
            $response,
            self::CONTENTENCODING,
            null,
            415     // Unsupported Media Type
        );
    }

    /**
     * Handler callback mgnt coding header value for response
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
        $config = $request->getAttribute( RestServer::CONFIG, [] );

        $default = ( isset( $config[self::ACCEPTENCODING][self::FALLBACK] ))
                          ? $config[self::ACCEPTENCODING][self::FALLBACK]
                          : \array_keys( self::$types)[0];

        return self::validateHeader(
            $request,
            $response,
            self::ACCEPTENCODING,
            $default,
            406     // not acceptable
        );
    }

    /**
     * Handler callback deCoding request body
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface      $response
     * @return array [ ServerRequestInterface, ResponseInterface ]
     * @static
     */
    public static function deCode(
        ServerRequestInterface $request,
        ResponseInterface      $response
    ) {
        $encoding = $request->getAttribute( self::CONTENTENCODING, false );
        if ( false === $encoding ) {
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
        $level   = ( isset( $config[$encoding][self::DECODELEVEL] ))
                          ? $config[$encoding][self::DECODELEVEL]
                          : null;
        $options = ( isset( $config[$encoding][self::DECODEOPTIONS] ))
                          ? $config[$encoding][self::DECODEOPTIONS]
                          : null;
        $error   = false;
        try {
            $stream->rewind();
            $body  = $stream->getContents();
            if ( ! empty( self::$types[$encoding] )) {
                $body = self::$types[$encoding]::deCode( $body, $level, $options );
            }
            if ( \is_array( $body ) || \is_object( $body )) {
                $request = $request->withParsedBody( $body )
                                   ->withBody( StreamFactory::createStream());
            } else {
                $request = $request->withBody( StreamFactory::createStream( $body ));
            }
        } catch ( zlibErrorException $e ) {
            $error = $e;
        } catch ( RuntimeException $e ) {
            $error = $e;
        } catch ( InvalidArgumentException $e ) {
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
        } // end if
        return [
            $request,
            $response,
        ];
    }

    /**
     * Handler callback enCoding response body
     *
     * Will also (opt) set response encoding header
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface      $response
     * @return array [ ServerRequestInterface, ResponseInterface ]
     * @static
     */
    public static function enCode(
        ServerRequestInterface $request,
        ResponseInterface      $response
    ) {
        $encoding = $request->getAttribute( self::ACCEPTENCODING, false );
        if ( false === $encoding ) { // no content encoding
            return [
                $request,
                $response,
            ];
        }

        $response = $response->withHeader( self::CONTENTENCODING, $encoding );
        if ( empty( self::$types[$encoding] )) { // no encoding required
            return [
                $request,
                $response,
            ];
        }

        $config  = $request->getAttribute( RestServer::CONFIG, [] );
        $level   = ( isset( $config[$encoding][self::ENCODELEVEL] ))
                          ? $config[$encoding][self::ENCODELEVEL]
                          : null;
        $options = ( isset( $config[$encoding][self::ENCODEOPTIONS] ))
                          ? $config[$encoding][self::ENCODEOPTIONS]
                          : null;
        $error   = false;
        try {
            $body     = $response->getResponseBody();
            if ( ! empty( self::$types[$encoding] )) {
                $body = self::$types[$encoding]::enCode( $body, $level, $options );
            }
            $response = $response->withRawBody( null )
                                 ->withBody( StreamFactory::createStream( $body ));
        } catch ( RuntimeException $e ) {
            $error = $e;
        } catch ( zlibErrorException $e ) {
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
        } // end if
        return [
            $request,
            $response,
        ];
    }
}
