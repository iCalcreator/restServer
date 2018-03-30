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
use RuntimeException;

/**
 * Parent class for contentTypeHandler and encodingHandler
 */
abstract class AbstractCteHandler extends AbstractHandler implements HandlerInterface
{
    /**
     * Class constants, headers, cfg keys etc
     */
    private static $ERRORFMT1 = 'No support for header %s: %s';

    /**
     * Register a new type Handler
     *
     * @param string $type
     * @param string $Handler  ex  [ <full namespace class name>, <method> ]
     * @static
     */
    public static function register(
        $type,
        $handler
    ) {
        $class                               = \get_called_class();
        $class::$types[\strtolower( $type )] = $handler;
    }

    /**
     * Unregister a type Handler
     *
     * @param string $type
     * @return bool
     * @static
     */
    public static function unRegister(
        $type
    ) {
        $class   = \get_called_class();
        $type    = \strtolower( $type );
        $success = false;
        if ( isset( $class::$types[$type] )) {
            $success = true;
            $tmp = [];
            foreach( $class::$types as $aType => $aValue ) {
                if ( $aType != $type ) {
                    $tmp[$aType] = $aValue;
                }
            }
            $class::$types = $tmp;
        }
        return $success;
    }

    /**
     * Return register value or array [key => value]
     *
     * @param string $type
     * @return mixed array on all, string on one, null on not found
     * @static
     */
    public static function getRegister(
        $type = null
    ) {
        $class    = \get_called_class();
        if ( null !== $type ) {
            $type = \strtolower( $type );
            return ( isset( $class::$types[$type] )) ? $class::$types[$type] : null;
        }
        return $class::$types;
    }

    /**
     * Handler callback mgnt header value for request/response
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface      $response
     * @param string                 $headerKey
     * @param string                 $fallback
     * @param int                    $errorCode
     * @return array [ ServerRequestInterface, ResponseInterface ]
     * @access protected
     * @static
     */
    protected static function validateHeader(
        ServerRequestInterface $request,
        ResponseInterface      $response,
                               $headerKey,
                               $fallback,
                               $errorCode
    ) {
        $headerValue = ( $request->hasHeader( $headerKey ))
                       ? $request->getHeader( $headerKey )
                       : null;
        if ( empty( $headerValue )) {
            if ( empty( $fallback )) {
                return [
                    $request,
                    $response,
                ];
            }
            $value = $fallback;
        } // end if
        else {
            $value = self::getAcceptedValue( $headerValue );
        }
        if ( empty( $value )) { // not found
            return self::doLogReturn(
                $request->withAttribute( RestServer::ERROR, true ),
                $response->withStatus( $errorCode ),
                new RuntimeException(
                    \sprintf(
                        self::$ERRORFMT1,
                        $headerKey,
                         \var_export( $headerValue, true )
                    )
                ),
               RestServer::WARNING
            );
        } // end if
        return [
            ( 0 != \strcasecmp( encodingHandler::IDENTITY, $value ))
                ? $request->withAttribute( $headerKey, $value )
                : $request,
            $response,
        ];
    }

    /**
     * return first found accepted type
     *
     * @param array $types
     * @return string
     * @access protected
     */
    protected static function getAcceptedValue(
        array $types
    ) {
        if ( empty( $types )) {
            return null;
        }
        $cTypes = self::typesToArray( $types );
        $found  = self::orderTypesAfterQuality( $cTypes );
        $class  = \get_called_class();
        $found  = $class::filterAccepted( $found );
        return ( empty( $found )) ? null : $found[0];
    }

    /**
     * Marshall types into single item array
     *
     * type value is case insensitive, and whitespace isn't important.
     *
     * @param array $types
     * @return array
     * @access private
     * @static
     */
    final private static function typesToArray(
        array $types
    ) {
        static $SP1 = ' ';
        static $SP2 = '  ';
        static $SC  = ';';
        static $QEQ = 'q=';
        $cTypes     = [];
        foreach ( $types as $cType ) {
            foreach ( \explode( self::COMMA, $cType ) as $theType ) {
                while ( false !== \strpos( $theType, $SP2 )) {
                    $theType = \str_replace( $SP2, $SP1, $theType );
                }
                $theType = \strtolower( \trim( $theType ));
                if ( false !== \strpos( $theType, $SC )) {
                    $tmp = [];
                    foreach ( \explode( $SC, $theType ) as $x => $t ) {
                        $t = \trim( $t );
                        if ( false !== \strpos( $t, $SP1 )) { // skip tail
                            $t = \explode( $SP1, $t, 2 )[0];
                        }
                        if ( empty( $t )) {
                            continue;
                        }
                        if ( empty( $x )) {
                            $tmp[] = $t; // first item
                        } elseif ( $QEQ == \substr( $t, 0, 2 )) {
                            $tmp[] = $t; // skip all but quality
                        }
                    } // end foreach
                    $theType = \implode( $SC, $tmp );
                } // end if
                elseif ( false !== \strpos( $theType, $SP1 )) { // skip tail
                    $theType = \explode( $SP1, $theType, 2 )[0];
                }
                if ( ! isset( $cTypes[$theType] )) {
                    $cTypes[$theType] = $theType;
                }
            } // end foreach
        } // end foreach
        return \array_values( $cTypes );
    }

    /**
     * Order (desc) types (contentType/encoding) after quality
     *
     * @param string[] $types
     * @return array
     * @access private
     * @static
     * Inspired by http://stackoverflow.com/a/1087498/3155344
     */
    final private static function orderTypesAfterQuality(
        array $types
    ) {
        static $SCQEQ  = ';q=';
        static $SORTER = ['self', 'sortfcn'];
        $sorted        = [];
        foreach ( $types as $x => $typeToCheck ) {
            $quality = 1; // the default accept quality (rating).
            // Check if there is a different quality.
            if ( false !== \strpos( $typeToCheck, $SCQEQ )) {
                // Divide "type;q=X" into two parts: "type" and "quality"
                list( $typeToCheck, $quality ) = \explode( $SCQEQ, $typeToCheck, 2 );
            } // end if
            // WARNING: zero quality is means, that type isn't supported! Thus skip them.
            if ( ! empty( $quality )) {
                $sorted[$typeToCheck] = [$quality, $x];
            }
        } // end foreach
        \uasort( $sorted, $SORTER );
        return \array_keys( $sorted );
    }

    /**
     * sort on quality (desc) and order (asc)
     *
     * @param array  $a
     * @param array  $b
     * @return int
     * @access private
     * @static
     */
    final private static function sortfcn(
        array $a,
        array $b
    ) {
        if ( $a[0] != $b[0] ) {
            return ( $a[0] >= $b[0] ) ? -1 : 1;
        }
        return ( $a[1] <= $b[1] ) ? -1 : 1;
    }

    /**
     * Filter requested types by accepted types, replacing '*' by all
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
        $class      = \get_called_class();
        $classTypes = $class::$types;
        $astChars   = $class::$astChar;
        $astFound   = false;
        $found      = [];
        foreach ( $types as $sType ) {
            if ( $astChars == $sType ) {
                $astFound = true;
                continue;
            }
            if ( \array_key_exists( $sType, $classTypes )) {
                $found[$sType] = $sType;
            }
        } // end foreach
        if ( $astFound ) { // append at the end
            foreach ( array_keys( $classTypes ) as $aType ) {
                if ( ! isset( $found[$aType] )) {
                    $found[$aType] = $aType;
                }
            }
        }
        return \array_values( $found );
    }
}
