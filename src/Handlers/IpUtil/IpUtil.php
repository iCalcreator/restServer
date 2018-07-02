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
 * class IpUtil
 *
 * @author      Kjell-Inge Gustafsson <ical@kigkonsult.se>
 */
class IpUtil
{
    /**
     * Invoke Ipv4Util::isValidIP or Ipv6Util::isValidIP
     *
     * @param string $IPnum
     * @return mixed
     * @static
     */
    public static function isValidIP( $IPnum ) {
        return ( Ipv4Util::isValidIP( $IPnum ) ||
                 Ipv6Util::isValidIP( $IPnum ));
    }

    /**
     * Invoke Ipv4Util::isValidIP
     *
     * @param string $IPnum
     * @return mixed
     * @static
     */
    public static function isValidIPv4( $IPnum ) {
        return Ipv4Util::isValidIP( $IPnum );
    }

    /**
     * Invoke Ipv6Util::isValidIP
     *
     * @param string $IPnum
     * @return mixed
     * @static
     */
    public static function isValidIPv6( $IPnum ) {
        return Ipv6Util::isValidIP( $IPnum );
    }

    /**
     * Invoke Ipv6Util::isIPv4MappedIPv6
     *
     * @param string $IPnum
     * @return bool true on success
     * @static
     */
    public static function isIPv4MappedIPv6(
        $IPnum
    ) {
        return Ipv6Util::isIPv4MappedIPv6( $IPnum );
    }

    /**
     * Invoke Ipv6Util::expand or Ipv4Util::expand
     *
     * @param string $IPnum
     * @return string|bool
     * @static
     */
    public static function expand(
        $IPnum
    ) {
        if( Ipv6Util::isValidIP( $IPnum ) ) {
            return Ipv6Util::expand( $IPnum );
        }
        $IPnum = Ipv4Util::expand( $IPnum );
        if( Ipv4Util::isValidIP( $IPnum ) ) {
            return $IPnum;
        }
        return false;
    }

    /**
     * Invoke Ipv6Util::compress
     *
     * @param string $IPnum
     * @param bool   $is8BitBlocks  default true (null/true if full IP v6)
     * @return string
     * @static
     */
    public static function compress(
        $IPnum,
        $is8BitBlocks=true
    ) {
        return Ipv6Util::compress( $IPnum, $is8BitBlocks );
    }

    /**
     * Invoke Ipv4Util::hasIPValidHost
     *
     * @param string $IPnum
     * @return string|bool
     * @static
     */
    public static function hasIPValidHost(
        $IPnum
    ) {
        if( Ipv4Util::isValidIP( $IPnum ) ) {
            return Ipv4Util::hasIPValidHost( $IPnum );
        }
        return false;
    }

    /**
     * Invoke Ipv4Util::getIPnumFromUrl
     *
     * @param string $hostName
     * @return array
     * @static
     */
    public static function getIPnumsFromHostname(
        $hostName
    ) {
        return Ipv4Util::getIPnumsFromHostname( $hostName );
    }

    /**
     * Invoke Ipv6Util::getInterfaceIdentifier
     *
     * @param string $IPnum
     * @return string
     * @static
     */
    public static function getInterfaceIdentifier(
        $IPnum
    ) {
        return Ipv6Util::getInterfaceIdentifier( $IPnum );
    }

    /**
     * Invoke Ipv6Util::getNetworkPrefix
     *
     * @param string $IPnum
     * @return string
     * @static
     */
    public static function getNetworkPrefix(
        $IPnum
    ) {
        return Ipv6Util::getNetworkPrefix( $IPnum );
    }

    /**
     * Invoke Ipv6Util::IP2bin
     *
     * @param string $IPnum
     * @return string (on success, return bool false on IPnum error)
     * @static
     */
    public static function IP2bin(
        $IPnum
    ) {
        return Ipv6Util::IP2bin( $IPnum );
    }

    /**
     * Invoke Ipv6Util::bin2IP
     *
     * @param string $IPbin
     * @return mixed
     * @static
     */
    public static function bin2IP(
        $IPbin
    ) {
        return Ipv6Util::bin2IP( $IPbin );
    }

    /**
     * Invoke Ipv4Util::decbin32
     *
     * @param string $dec
     * @return mixed
     * @static
     */
    public static function decbin32(
        $dec
    ) {
        return Ipv4Util::decbin32( $dec );
    }

    /**
     * Return bool true if (valid) IP v4/v6 number match (any element in array of) IP/network range(s)
     *
     * IPv4 network ranges can be specified as:
     * 0. Accept all IPs:      *           // warning, use it on your own risk, accepts all
     * 1. Wildcard format:     1.2.3.*
     * 2. CIDR format:         1.2.3/24  OR  1.2.3.4/255.255.255.0
     * 3. Start-End IP format: 1.2.3.0-1.2.3.255
     * 4. Specific IP:         1.2.3.4
     * IPv6 network ranges can be specified as:
     * 0. Accept all IPs:      *           // warning, use it on your own risk, accepts all
     * 1. CIDR format:         3ffe:f200:0234::/64
     * 2. Start-End IP format: 1:2:3:4::-1:2:3:5::
     * 3. Specific IP:         3ffe:f200:0234:ab00:0123:4567:1:10
     * IPnum try to find a match in array order
     * @param string $IPnum
     * @param mixed  $acceptRanges
     * @param int    $matchIx
     * @return bool  true on success, $matchIx hold match range array index
     * @static
     */
    public static function isIPnumInRange(
        $IPnum,
        $acceptRanges,
      & $matchIx=null
    ) {
        if( ! \is_array( $acceptRanges ) ) {
            $acceptRanges = [ $acceptRanges ];
        }
        if( Ipv4Util::isValidIP( $IPnum )) {
            return Ipv4Util::isIPnumInRange(
                $IPnum,
                $acceptRanges,
                $matchIx
            );
        }
        if( Ipv6Util::isValidIP( $IPnum )) {
            return Ipv6Util::isIPnumInRange(
                $IPnum,
                $acceptRanges,
                $matchIx
            );
        }
        $matchIx = null;
        return false;
    }

    /**
     * Return (int) IP v4/v6 CIDR block subnet size
     *
     * @param int    $cidr
     * @param int    $bitNum (32/128)
     * @return int
     * @static
     */
    public static function cidr2NetmaskDec(
        $cidr,
        $bitNum
    ) {
        static $EMPTY = '';
        static $ONE   = '1';
        static $ZERO  = '0';
        return bindec(
            \str_pad( $EMPTY, $cidr, $ONE ) .
            \str_pad(
                $EMPTY,
                ( $bitNum - $cidr ),
                $ZERO
            )
        );
    }

    /**
     * Return trimmed IPnum without portNum
     *
     * @param string $IPnum
     * @return string
     * @static
     */
    public static function trimIpNum(
        $IPnum
    ) {
        static $DOT   = '.';
        static $COLON = ':';
        static $DQ    = '"';
        static $SQ1   = '[';
        static $SQC   = ']:';
        static $SQ2   = ']';
        $IPnum = trim( $IPnum );
        // skip IPv4 port number
        if(( 3 == \substr_count( $IPnum, $DOT )) &&
           ( 1 == \substr_count( $IPnum, $COLON ))) {
            return \trim( \explode( $COLON, $IPnum, 2 )[0] );
        }
        $IPnum = \trim( $IPnum, $DQ );
        if( $SQ1 != \substr( $IPnum, 0, 1 )) {
            return $IPnum;
        }
        // skip IPv6 port number, ex [2001:db8:cafe::17]:47011
        $IPnum = \substr( $IPnum, 1 );
        if( 1 == \substr_count( $IPnum, $SQC )) {
            $IPnum = \trim( \explode( $SQC, $IPnum, 2 )[0] );
        }
        return \trim( $IPnum, $SQ2 );
    }
}
