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
use Kigkonsult\RestServer\ResponseInterface;
use Kigkonsult\RestServer\RestServer;
use Kigkonsult\RestServer\Handlers\IpUtil\IpUtil;
use RuntimeException;

/**
 * class IpHandler
 *
 * @author      Kjell-Inge Gustafsson <ical@kigkonsult.se>
 */
class IpHandler extends AbstractCAHandler implements HandlerInterface
{
    /**
     * Class constants, headers, cfg keys etc
     */
    const IPHEADER      = 'ipHeader';
    const CLIENT_IP     = 'CLIENT-IP';
    const FORWARDED     = 'FORWARDED';
    const FORWARDED_FOR = 'FORWARDED-FOR';
    const REFERER       = 'REFERER';
    const REMOTE_ADDR   = 'REMOTE-ADDR';

    const EXAMINE       = 'examine';
    const RANGE         = 'range';
    const FIRST         = 'first';
    const LAST          = 'last';
    const ALL           = 'all';

    public static $ipHeaders = [
        self::REMOTE_ADDR,
        self::FORWARDED,
        self::FORWARDED_FOR,
        self::CLIENT_IP,
        self::REFERER
    ];

    /**
     * Default error codes
     */
    private static $DEFAULTS = [
        self::ERRORCODE1 => 403, // incorrect Origin, 403 - Forbidden
    ];

    /**
     * Return error status codes to check changed log prio
     */
    private static $STATUSCODESWITHALTLOGPRIO = [
        self::ERRORCODE1
    ];

    /**
     * Internal...
     */
    private static $COMMA   = ',';

    /**
     * Return bool true if header is a IP header
     *
     * @param string $header
     * @return bool
     * @static
     */
    public static function isIpHeader(
        $header
    ) {
        $header = \strtr( $header, self::$US, self::$D );
        foreach( self::$ipHeaders as $ipHeader ) {
            if( 0 == strcasecmp ( $ipHeader, $header )) {
                return true;
                break;
            }
        } // end foreach
        return false;
    }

    /**
     * Handler validating headers with IPnumbers
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface      $response
     * @return array [ ServerRequestInterface, ResponseInterface ]
     */
    public static function validateIP(
        ServerRequestInterface $request,
        ResponseInterface      $response
    ) {
        static $ERRORFMT1 = 'Can\'t find any valid IPnum in %s';
        if( parent::earlierErrorExists( $request )) {
            return [
                $request,
                $response,
            ];
        }
        $ipCfg  = parent::getConfig(
            $request,
            self::IPHEADER,
            self::$DEFAULTS,
            self::$STATUSCODESWITHALTLOGPRIO
        );

        if((   isset( $ipCfg[RestServer::IGNORE] ) &&
           ( true === $ipCfg[RestServer::IGNORE] )) ||
             ! isset( $ipCfg[self::EXAMINE] ) ||
               empty( $ipCfg[self::EXAMINE] )) {
            return [
                $request,
                $response,
            ];
        }

        $requiredCnt = 0;
        foreach( $ipCfg[self::EXAMINE] as $ipHeader => $directives ) {
            if( ! is_array( $directives )) {
                $ipCfg[self::EXAMINE][$ipHeader] = $directives = [];
            }
            if( ! isset( $directives[self::REQUIRED] )) {
                $ipCfg[self::EXAMINE][$ipHeader][self::REQUIRED] = false;
            }
            elseif( false !== $directives[self::REQUIRED] ) {
                $requiredCnt += 1;
            }
        } // end foreach
        $success          = false;
        $inValidIpNumbers = [];
        foreach( $ipCfg[self::EXAMINE] as $ipHeader => $directives ) {
            if( ! $request->hasHeader( $ipHeader )) {
                continue;
            }
            if( ! isset( $directives[self::RANGE] )) {
                continue;
            }
            $headerValue  = $request->getHeader( $ipHeader );
            switch( true ) {
                case ( self::CLIENT_IP   == $ipHeader ) :
                    // no break
                case ( self::REFERER == $ipHeader ) :
                // no break
                case ( self::REMOTE_ADDR == $ipHeader ) :
                    if( $directives[self::REQUIRED] ) {
                        $requiredCnt -= 1;
                    }
                    $ipNumbers = self::parseIpHeaderValue( $headerValue[0] );
                    if( false !== self::examineIpNumbers(
                        $ipNumbers,
                        $directives[self::RANGE],
                        $ipHeader,
                        $inValidIpNumbers
                    )) {
                        $success = true;
                        break;
                    }
                    $inValidIpNumbers[$ipHeader] = $ipNumbers;
                    $success = false;
                    break;
                case ( self::FORWARDED_FOR == $ipHeader ) :
                    if( $directives[self::REQUIRED] ) {
                        $requiredCnt -= 1;
                    }
                    $ipNumbers = self::parseIpHeaderValue( $headerValue[0] );
                    if( false !== self::examineIpNumbers2(
                        $ipNumbers,
                        $directives[self::RANGE],
                        $ipHeader,
                        $inValidIpNumbers
                    )) {
                        $success = true;
                        break;
                    }
                    $inValidIpNumbers[$ipHeader] = $ipNumbers;
                    $success = false;
                    break;
                case ( self::FORWARDED == $ipHeader ) :
                    if( $directives[self::REQUIRED] ) {
                        $requiredCnt -= 1;
                    }
                    $ipNumbers = self::parseForvardedHeader( $headerValue );
                    if( false !== self::examineIpNumbers2(
                        $ipNumbers,
                        $directives[self::RANGE],
                        $ipHeader,
                        $inValidIpNumbers
                    )) {
                        $success = true;
                        break;
                    }
                    $inValidIpNumbers[$ipHeader] = $ipNumbers;
                    $success = false;
                    break;
                default :
                    break;
            } // end switch
            if( $success && empty( $requiredCnt )) {
                break;
            }
        } // end foreach

        if( $success ) {
            return [
                $request,
                $response,
            ];
        }
        return self::doLogReturn(
            $request->withAttribute( RestServer::ERROR, true ),
            $response->withStatus( $ipCfg[self::ERRORCODE1][0] ),
            new RuntimeException(
                \sprintf(
                    $ERRORFMT1,
                    var_export(
                        $inValidIpNumbers,
                        true
                    )
                )
            ),
            $ipCfg[self::ERRORCODE1][1]
        );
    }

    /**
     * Return array of parsed IPheader (number) header values
     *
     * CLIENT-IP, REMOTE_ADDR, REFERER
     * single IP value
     * FORWARDED_FOR
     * is a de facto standard for identifying the originating IP address
     * of a client connecting to a web server through an HTTP proxy or load balancer.
     * Superseded by Forwarded header.
     * A comma+space separated list of IP addresses,
     * the left-most being the original client, and each successive proxy but the most last one
     * Ex. : X-Forwarded-For: client1, proxy1, proxy2
     * Ex. : X-Forwarded-For: 129.78.138.66, 129.78.64.103
     * @param string $ipHeaderValue
     * @return array
     * @access private
     * @static
     */
    private static function parseIpHeaderValue(
        $ipHeaderValue
    ) {
        $ipNumbers = [];
        foreach( \explode( self::$COMMA, $ipHeaderValue ) as $ipNumber ) {
            $ipNumber = IpUtil::trimIpNum( $ipNumber );
            if( IpUtil::isValidIP( $ipNumber )) {
                $ipNumbers[] = $ipNumber;
            }
        } // end foreach
       return $ipNumbers;
    }

    /**
     * Return parsed IPheader FORWARDED content(s) (by/for) as array with trimmed elements (IPs) without port number
     *
     * @param array $ipHeaderValues
     * @return array
     * @access private
     * @static
     * @see https://tools.ietf.org/html/rfc7239
     * @since     2017-11-20
     */
    private static function parseForvardedHeader(
        array $ipHeaderValues
    ) {
        static $SQ      = ';';
        static $BYEQ    = 'by=';
        static $EQ      = '=';
        static $FOREQ   = 'for=';
        $ipNumbers      = [];
        foreach( $ipHeaderValues as $ipHeaderValue ) {
            foreach( \explode( self::$COMMA, $ipHeaderValue ) as $ipValue ) {
                $ipValue    = trim( $ipValue );
                foreach( explode( $SQ, $ipValue ) as $ipElement ) {
                    switch( true ) {
                        case ( 0 != \strcasecmp( $BYEQ,  \substr( $ipElement, 0, 3 ))) :
                            $ipNumber = IpUtil::trimIpNum( \explode( $EQ, $ipElement, 2 )[1] );
                            if( self::isUnknownOrObfport( $ipNumber )) {
                                break;
                            }
                            if( IpUtil::isValidIP( $ipNumber )) {
                                $ipNumbers[] = $ipNumber;
                            }
                            break;
                        case ( 0 != strcasecmp( $FOREQ, \substr( $ipElement, 0, 4 ))) :
                            $ipNumber = IpUtil::trimIpNum( \explode( $EQ, $ipElement, 2 )[1] );
                            if( self::isUnknownOrObfport( $ipNumber )) {
                                break;
                            }
                            if( IpUtil::isValidIP( $ipNumber )) {
                                $ipNumbers[] = $ipNumber;
                            }
                            break;
                        default :
                            break;
                    } // end switch
                } // end foreach
            } // end foreach
        } // end foreach
        return $ipNumbers;
    }

    /**
     * Return bool true if ipNum is unknown or obfnode
     *
     * @param string $ipNumber
     * @return bool
     * @access private
     * @static
     */
    private static function isUnknownOrObfport(
        $ipNumber
    ) {
        static $UNKNOWN = 'unknown';
        static $US      = '_';
        if( $UNKNOWN == $ipNumber ) {
            return true;
        }
        if( $US == \substr( $ipNumber, 0, 1 )) {
            return true;
        }
        return false;
    }

    /**
     * Return result of examined IpNumbers
     *
     * @param array  $ipNumbers
     * @param array  $range
     * @param string $ipHeader
     * @param array  $inValidIpNumbers
     * @return bool
     * @access private
     * @static
     */
    private static function examineIpNumbers(
        array   $ipNumbers,
        array   $range,
                $ipHeader,
        array & $inValidIpNumbers
    ) {
        if( empty( $ipNumbers )) {
            return false;
        }
        if( false !== self::examineIpNumbersForFirstMatchInRange(
                $ipNumbers,
                $range
        )) {
            return  true;
        }
        $inValidIpNumbers[$ipHeader] = $ipNumbers;
        return false;
    }

    /**
     * Return result of examined (limited set of) IpNumbers
     *
     * @param array  $ipNumbers
     * @param array  $range
     * @param string $ipHeader
     * @param array  $inValidIpNumbers
     * @return bool
     * @access private
     * @static
     */
    private static function examineIpNumbers2(
        array   $ipNumbers,
        array   $range,
                $ipHeader,
        array & $inValidIpNumbers
    ) {
        if( empty( $ipNumbers )) {
            return false;
        }
        if( false !== self::examineIpNumbersForFirstMatchInRange2(
                $ipNumbers,
                $range
        )) {
            return  true;
        }
        $inValidIpNumbers[$ipHeader] = $ipNumbers;
        return false;
    }

    /**
     * Return result of searching ipNumber for a match in range
     *
     * @param array $ipNumbers
     * @param array $range
     * @return bool
     * @access private
     * @static
     */
    private static function examineIpNumbersForFirstMatchInRange(
        array $ipNumbers,
        array $range
    ) {
        foreach( $ipNumbers as $ipNumber ) {
            if( false !== IpUtil::isIPnumInRange(
                $ipNumber,
                $range,
                $matchIx
            )) {
               return true;
               break;
            }
        } // end foreach
        return false;
    }

    /**
     * Return result of searching (limited set of) ipNumbers for a match in range
     *
     * @param array $ipNumbers
     * @param array $range
     * @return bool
     * @access private
     * @static
     */
    private static function examineIpNumbersForFirstMatchInRange2(
        array $ipNumbers,
        array $range
    ) {
        if( empty( $ipNumbers )) {
            return false;
        }
        switch( true ) {
            case ( isset( $range[self::FIRST] )) :
                $ipNumberToTest = \array_slice( $ipNumbers, 0, 1 );
                $currentRange   = $range[self::FIRST];
                break;
            case ( isset( $range[self::LAST] )) :
                $ipNumberToTest = \array_slice( $ipNumbers, -1 );
                $currentRange   = $range[self::LAST];
                break;
            case ( isset( $range[self::ALL] )) :
                $ipNumberToTest = $ipNumbers;
                $currentRange   = $range[self::ALL];
                break;
            default :
                return false;
                break;
        } // end switch
        return self::examineIpNumbersForFirstMatchInRange(
            $ipNumberToTest,
            $currentRange
        );
    }
}
