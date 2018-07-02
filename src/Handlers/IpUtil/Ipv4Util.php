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

namespace Kigkonsult\RestServer\Handlers\IpUtil;

/**
 * class Ipv4Util
 *
 * @author      Kjell-Inge Gustafsson <ical@kigkonsult.se>
 */
class Ipv4Util implements IpUtilInterface
{
    /**
     * Constants, headers, cfg keys etc
     */

    /**
     * Check IP v4 format
     *
     * Return  bool true if valid format of (full) IP v4 number
     * @param string $IPnum
     * @return bool true on success
     * @static
     */
    public static function isValidIP(
        $IPnum
    ) {
        if( false !== \strpos( $IPnum , self::COLON )) {
            return false;
        }
        if( 3 != \substr_count( $IPnum , self::DOT )) {
            return false;
        }
        return ( $IPnum == ( \long2ip( \ip2long( $IPnum ))));
    }

    /**
     * Return (dec int) IP v4 CIDR block subnet size
     *
     * @param int    $cidr
     * @return int
     * @static
     */
    public static function cidr2NetmaskDec(
        $cidr
    ) {
        return IPutil::cidr2NetmaskDec( $cidr, 32 );
    }
    /**
     * Return expanded IP v4 number to 4 octets
     *
     * @param string $IPnum
     * @return string (bool false on error)
     * @static
     */
    public static function expand(
        $IPnum
    ) {
        static $FMTIPno      = '%u.%u.%u.%u';
        if( false !== \strpos( $IPnum , self::COLON )) {
            return false;
        }
        $IParr = \explode( self::DOT, $IPnum );
        for( $x=0; $x<4; ++$x ) {
            if( ! isset( $IParr[$x] )) {
                $IParr[$x] = self::ZERO;
            }
            else {
                $IParr[$x] = \ltrim( $IParr[$x], self::ZERO );
                if( empty( $IParr[$x] )) {
                    $IParr[$x] = self::ZERO;
                }
            }
        } // end for
        return \sprintf(
            $FMTIPno,
            $IParr[0],
            $IParr[1],
            $IParr[2],
            $IParr[3]
        );
    }

    /**
     * Return true if hostName exists for a valid IP v4 number
     *
     * Return bool true if valid format of IP v4 number
     *                     and IP number resolves to a hostName
     *                     and hostName != IP number
     *                     and the hostName lookup resolves in IP number(s)
     *                     and the hostName IP number(s) includes the IP number
     * @param string $IPnum
     * @return bool true on success
     * @static
     */
    public static function hasIPValidHost(
        $IPnum
    ) {
        $hostName = \gethostbyaddr( $IPnum );
        if(( false === $hostName ) || // malformed input
           ( $IPnum == $hostName )) { // on failure
            return false;
        }
        $extIPs   = self::getIPnumsFromHostname( $hostName );
        return \in_array( $IPnum, $extIPs );
    }

    /**
     * Return array IpNums for resolved hostName
     *
     * @param string $hostName
     * @return array
     * @static
     */
    public static function getIPnumsFromHostname(
        $hostName
    ) {
        $extIPs   = \gethostbynamel( $hostName );
        return ( false === $extIPs ) ? [] : $extIPs;
    }

    /**
     * Return true if (valid) IP number match any element in array of IP/network ranges
     *
     * Network ranges can be specified as:
     * 0. Accept all IPs:      *           // warning, use it on your own risk, accepts all
     * 1. Wildcard format:     1.2.3.*
     * 2. CIDR format:         1.2.3/24  OR  1.2.3.4/255.255.255.0
     * 3. Start-End IP format: 1.2.3.0-1.2.3.255
     * 4. Specific IP:         1.2.3.4
     * IPnum try to find a match in array order
     * @param string $IPnum
     * @param array  $acceptRanges (string[])
     * @param int    $matchIx
     * @return bool  true on success, $matchIx hold match range array index
     * @static
     */
    public static function isIPnumInRange(
              $IPnum,
        array $acceptRanges,
            & $matchIx=null
    ) {
        foreach( $acceptRanges as $matchIx => $acceptRange ) {
            switch( true ) {
                case ( self::AST == $acceptRange ) :
                    return true;
                case ( false !== ( \strpos( $acceptRange, self::AST ))) :
                    // fall through
                case ( false !== ( \strpos( $acceptRange, self::DASH ))) :
                    // fall through
                case ( false !== ( \strpos( $acceptRange, self::SLASH ))) :
                    if( false !== self::ip_in_range( $IPnum, $acceptRange )) {
                        return true;
                    }
                    break;
                case ( self::isValidIP( $acceptRange )) :
                    if( \ip2long( $acceptRange ) == \ip2long( $IPnum )) {
                        return true;
                    }
                    break;
                default :
                    break;
            } // end switch
        } // end foreach
        $matchIx = null;
        return false;
    }

    /**
     * Return true if IPnum is in a IP/NETMASK format range i.e. separated by slash
     *
     * @param  string $IPnum
     * @param  string $range
     * @return bool  true on success, ip in range
     * @access private
     * @static
     */
    private static function rangeIsIpOrNETMASK(
        $IPnum,
        $range
    ) {
        list( $range, $netmask ) = \explode( self::SLASH, $range, 2 );
        $range = self::expand( $range );
        if( false === $range ) {
            return false;
        }
        if( false !== \strpos( $netmask, self::DOT )) {
            // netmask is a 255.255.0.0 format
            return self::netmaskIsIPFormat( $IPnum, $range, $netmask );
        }
        if( ctype_digit( $netmask ) && ( 0 < $netmask ) && (33 > $netmask )) {
            // netmask is a CIDR size block
            return self::netmaskIsCIDRsizeBlock( $IPnum, $range, $netmask );
        }
        return false;
    }

    /**
     * Return true if IPnum is in a IP/NETMASK format range
     * and netmask is a 255.255.0.0 format (opt trail *)
     *
     * @param  string $IPnum
     * @param  string $range
     * @param  string $netmask
     * @return bool  true on success, ip in range
     * @access private
     * @static
     */
    private static function netmaskIsIPFormat(
        $IPnum,
        $range,
        $netmask
    ) {
        if( false !== \strpos( $netmask, self::AST )) {
            $netmask = \str_replace( self::AST, self::ZERO, $netmask );
        }
        $netmask_dec = \ip2long( $netmask );
        return (( \ip2long( $IPnum ) & $netmask_dec ) == ( \ip2long( $range ) & $netmask_dec ));
    }

    /**
     * Return true if IP v4 num is in a IP/NETMASK format range
     * and netmask is a CIDR size block
     *
     * @param  string $IPnum
     * @param  string $range
     * @param  string $netmask
     * @return bool  true on success, ip in range
     * @access private
     * @static
     */
    private static function netmaskIsCIDRsizeBlock(
        $IPnum,
        $range,
        $netmask
    ) {
        $netmask_dec = self::cidr2NetmaskDec( $netmask );
        return (( \ip2long( $IPnum ) & $netmask_dec ) == ( \ip2long( $range ) & $netmask_dec ));
    }

    /**
     * Convert range a.b.*.* format to A-B format
     *         replacing * by 0   for A
     *     and replacing * by 255 for B
     *
     * @param  string $range
     * @return string
     * @access private
     * @static
     */
    private static function convertToLowerUpperFmt(
        $range
    ) {
        static $STR255        = '255';
        static $FMTLowerUpper = '%s%s%s';
        $lower = \str_replace(
            self::AST,
            self::ZERO,
            $range
        );
        $upper = \str_replace(
            self::AST,
            $STR255,
            $range
        );
        return \sprintf(
            $FMTLowerUpper,
            $lower,
            self::DASH,
            $upper
        );
    }

    /**
     * Return true if IP v4 num is in a A-B format range
     *
     * @param  string $IPnum
     * @param  string $range
     * @return bool  true on success, ip in range
     * @access private
     * @static
     */
    private static function rangeIsLowerUpperFmt(
        $IPnum,
        $range
    ) {
        static $FMTINTunsign   = '%u';
        list( $lower, $upper ) = \explode( self::DASH, $range, 2 );
        $lower_dec = (float) \sprintf( $FMTINTunsign, ip2long( $lower ));
        $upper_dec = (float) \sprintf( $FMTINTunsign, ip2long( $upper ));
        $IPnum_dec = (float) \sprintf( $FMTINTunsign, ip2long( $IPnum ));
        return (( $IPnum_dec >= $lower_dec ) && ( $IPnum_dec <= $upper_dec ));
    }
    /**
     * Copyright 2008: Paul Gregg <pgregg@pgregg.com>
     */
    /**
     * @link https://web.archive.org/web/20090503013408/http://pgregg.com/projects/php/ip_in_range/ip_in_range.phps
     * @link http://pgregg.com
     * @link https://en.wikipedia.org/wiki/Classless_Inter-Domain_Routing
     * Method refactoring and code style update Kjell-Inge Gustafsson, kigkonsult
     */
    /*
     * ip_in_range.php - Function to determine if an IP is located in a
     *                   specific range as specified via several alternative
     *                   formats.
     * Network ranges can be specified as:
     * 1. Wildcard format:     1.2.3.*
     * 2. CIDR format:         1.2.3/24  OR  1.2.3.4/255.255.255.0
     * 3. Start-End IP format: 1.2.3.0-1.2.3.255
     *
     * Return value BOOLEAN : ip_in_range($ip, $range);
     *
     * Copyright 2008: Paul Gregg <pgregg@pgregg.com>
     * 10 January 2008
     * Version: 1.2
     *
     * Source website: http://www.pgregg.com/projects/php/ip_in_range/
     * Version 1.2
     *
     * This software is Donationware - if you feel you have benefited from
     * the use of this tool then please consider a donation. The value of
     * which is entirely left up to your discretion.
     * http://www.pgregg.com/donate/
     *
     * Please do not remove this header, or source attibution from this file.
     */
    /**
     * Return binary string padded to 32 bit numbers
     *
     * In order to simplify working with IP addresses (in binary) and their
     * netmasks, it is easier to ensure that the binary strings are padded
     * with zeros out to 32 characters - IP addresses are 32 bit numbers
     * @param string $dec
     * @return string
     * @static
     */
    public static function decbin32(
        $dec
    ) {
        return \str_pad(
            decbin( $dec ),
            32,
            self::ZERO,
            STR_PAD_LEFT
        );
    }

    /**
     * Return bool true if IP is in range
     *
     * This function takes 2 arguments, an IP address and a "range" in several
     * different formats.
     * Network ranges can be specified as:
     * 1. Wildcard format:     1.2.3.*
     * 2. CIDR format:         1.2.3/24  OR  1.2.3.4/255.255.255.0
     * 3. Start-End IP format: 1.2.3.0-1.2.3.255
     * The function will return true if the supplied IP is within the range.
     * Note little validation is done on the range inputs - it expects you to
     * use one of the above 3 formats.
     * @param string $IPnum
     * @param string $range
     * @return bool
     * @static
     */
    public static function ip_in_range(
        $IPnum,
        $range
    ) {
           // $range is in IP/NETMASK format
        if( false !== \strpos( $range, self::SLASH )) {
            return self::rangeIsIpOrNETMASK( $IPnum, $range );
        }
           // range might be 255.255.*.* or 1.2.3.0-1.2.3.255
        if( false !== \strpos( $range, self::AST )) {
            $range = self::convertToLowerUpperFmt( $range );
        }
           // range is in 1.2.3.0-1.2.3.255 format
        if( false !== \strpos( $range, self::DASH )) {
            return self::rangeIsLowerUpperFmt( $IPnum, $range );
        }
        return false;
    }
}
