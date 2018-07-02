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
 * class Ipv6Util
 *
 * @author      Kjell-Inge Gustafsson <ical@kigkonsult.se>
 */
class Ipv6Util implements IpUtilInterface
{
    /**
     * Constants, headers, cfg keys etc
     */
    const A16 = 'A16';

    /**
     * Return true if valid IP v6 format
     *
     * @param string $IPnum
     * @return bool true on success
     * @static
     */
    public static function isValidIP(
        $IPnum
    ) {
        return ( false !== \filter_var(
            $IPnum,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_IPV6
            )
        );
    }

    /**
     * Return true if IP v4 maspped IP v6
     *
     * The IPv4-mapped IPv6 addresses consist of an 80-bit prefix of zeros,
     * the next 16 bits are one, and the remaining,
     * least-significant 32 bits contain the IPv4 address.
     * @param string $IPnum
     * @return bool true on success
     * @static
     */
    public static function isIPv4MappedIPv6(
        $IPnum
    ) {
        static $ALL1IN6GROUPS = '0000:0000:0000:0000:0000:ffff:';
        static $IPV4PREFIX    = '::ffff:';
        if( ! self::isValidIP( $IPnum )) {
            return false;
        }
        if( $ALL1IN6GROUPS == \substr( $IPnum, 0, 35 )) {
            $IPnum = \str_replace( $ALL1IN6GROUPS, $IPV4PREFIX, $IPnum );
        }
        if( $IPV4PREFIX != \substr( $IPnum, 0, 7 )) {
            return false;
        }
        $IPnum   = \str_replace( $IPV4PREFIX, null, $IPnum );
        return IPv4util::isValidIP( $IPnum );
    }

    /**
     * Return (unicast/anycast) IP v6 number routing prefix
     *
     * @param string $IPnum
     * @param int    $cidr
     * @return string
     * @static
     */
    public static function getNetmask(
        $IPnum,
        $cidr
    ) {
        return $IPnum & self::cidr2NetmaskDec( $cidr );
    }

    /**
     * Return (int) IP v6 CIDR block subnet size
     *
     * @param int    $cidr
     * @return int
     * @static
     */
    public static function cidr2NetmaskDec(
        $cidr
    ) {
        return IPutil::cidr2NetmaskDec(
            $cidr,
            128
        );
    }

    /**
     * Return (unicast/anycast) IP v6 number interface identifier (last 64 bits as hex)
     *
     * @param string $IPnum
     * @return string
     * @static
     */
    public static function getInterfaceIdentifier(
        $IPnum
    ) {
        return \implode(
            self::COLON,
            \array_slice(
                \explode(
                    self::COLON,
                    self::expand(
                        $IPnum
                    )
                ),
                4
            )
        );
    }

    /**
     * Return (unicast/anycast) IP v6 number network prefix (first 64 bits as hex)
     *
     * @param string $IPnum
     * @return string
     * @static
     */
    public static function getNetworkPrefix(
        $IPnum
    ) {
        return \implode(
            self::COLON,
            \array_slice(
                \explode(
                    self::COLON,
                    self::expand(
                        $IPnum
                    )
                ),
                0,
                4
            )
        );
    }

    /**
     * Convert IP v6 number to binary string
     *
     * @param string $IPnum
     * @return string (on success, return bool false on IPnum error)
     * @static
     */
    public static function IP2bin(
        $IPnum
    ) {
        return \current(
            \unpack(
                self::A16,
                @\inet_pton(
                    $IPnum
                )
            )
        );
    }

    /**
     * Convert binary string to IP v6 number
     *
     * @param string $IPbin
     * @return mixed (binary) string on success, bool false on IP binary number error
     * @static
     */
    public static function bin2IP(
        $IPbin
    ) {
        return @\inet_ntop(
            \pack(
                self::A16,
                $IPbin
            )
        );
    }

    /**
     * Return expanded condensed full IP v6 number
     *
     * Will also convert a Ipv4_mapped_to_IPv6 to a IP v6 number
     * ex. ::ffff:192.0.2.128 -> ::ffff:c000:280
     * @param string $IPnum
     * @return string
     * @link https://stackoverflow.com/questions/12095835/quick-way-of-expanding-ipv6-addresses-with-php
     * @static
     */
    public static function expand(
        $IPnum
    ) {
        static $Hhex  = 'H*hex';
        static $EXPR1 = '/([A-f0-9]{4})/';
        static $EXPR2 = '$1:';
        static $HEX   = 'hex';
        $hex   = \unpack(
            $Hhex,
            @\inet_pton(
                $IPnum
            )
        );
        $IPnum = \substr(
            \preg_replace(
                $EXPR1,
                $EXPR2,
                $hex[$HEX]
            ),
            0,
            -1
        );
        return $IPnum;
    }

    /**
     * Return condensed IP v6 number or Ip v6 bitBlock group
     * If compressed, the IP num is returned
     * Trim leading zero in (non-empty) hexadecimal fields (one left if all trimmed)
     * Compress (first) consecutive hexadecimal fields of zeros using Double colon
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
        static $COLON2 = '::';
        if( 0 < \substr_count( $IPnum, $COLON2 )) {
            return $IPnum;
        }
        $cntBitblocks  = (( null == $is8BitBlocks ) || ( true === $is8BitBlocks ))
                       ? 8
                       : ( \substr_count( $IPnum, self::COLON ) + 1 );
        $IParr         = [];
        $emptyArr      = [];
        $emptyIx       = 0;
        $found         = false;
        foreach( \explode( self::COLON, $IPnum, $cntBitblocks ) as $x => $bitBlock ) {
            $bitBlock  = \ltrim( $bitBlock, self::ZERO );
            if( empty( $bitBlock )) {
                $IParr[]   = self::ZERO;
                if( ! isset( $emptyArr[$emptyIx] )) {
                    $emptyArr[$emptyIx] = [];
                }
                $emptyArr[$emptyIx][$x] = $x;
                $found     = true;
            }
            else {
                $IParr[]   = $bitBlock;
                $emptyIx  += 1;
            }
        } // end foreach..
        if( ! $found ) {// no empty bitBlocks
            return \implode( self::COLON, $IParr );
        }
        $longest       = 0;
        $longIx        = null;
        foreach( $emptyArr as $emptyIx => $empty ) {
            $cnt         = \count( $empty );
            if( $longest < $cnt ) { // first found has precedence
                $longest = $cnt;
                $longIx  = $emptyIx;
            }
        }
        $first         = \reset( $emptyArr[$longIx] );
        $end           = \end(   $emptyArr[$longIx] );
        if( 1 > $first ) {
            return $COLON2 .
                \implode(
                    self::COLON,
                    \array_slice(
                        $IParr, (
                            $end + 1
                        )
                    )
                );
        }
        if( 6 < $first ) {
            return \implode(
                self::COLON,
                \array_slice(
                    $IParr,
                    0,
                    7
                )
            ) . $COLON2;
        }
        $leadStr = ( 1 > $first )
                 ? null
                 : \implode(
                     self::COLON,
                     \array_slice(
                         $IParr,
                         0,
                         $first
                     )
                   );
        return $leadStr .
               $COLON2 .
               \implode(
                   self::COLON,
                   \array_slice(
                       $IParr, (
                           $end + 1
                       )
                   )
               );
    }

    /**
     * Return bool true if (valid) IP number match any element in array of IP/network ranges
     *
     * Network ranges can be specified as:
     * 0. Accept all IPs:      *           // warning, use it on your own risk, accepts all
     * 2. CIDR format:         fe80:1:2:3:a:bad:1dea:dad/82
     * 3. Start-End IP format: 3ffe:f200:0234:ab00:0123:4567:1:20-3ffe:f200:0234:ab00:0123:4567:1:30
     * 4. Specific IP:         fe80:1:2:3:a:bad:1dea:dad
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
                    break;
                case ( false !== ( \strpos( $acceptRange, self::DASH ))) :
                    if( false !== self::rangeIsIpToIp( $IPnum, $acceptRange )) {
                        return true;
                    }
                    break;
                case ( false !== \strpos( $acceptRange, self::SLASH )) :
                    if( false !== self::rangeIsIpAndNetmask( $IPnum, $acceptRange )) {
                        return true;
                    }
                    break;
                case ( self::isValidIP( $acceptRange )) :
                    if( false !== self::rangeIsIp( $IPnum, $acceptRange )) {
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
     * Check if range is an IP-IP range, return true if IP matches range
     *
     * @param  string $IPnum
     * @param  string $range
     * @return bool  true on success, ip in range
     * @access private
     * @static
     */
    private static function rangeIsIpToIp(
        $IPnum,
        $range
    ) {
        list( $IPlow, $IPhigh ) = \explode( self::DASH, $range, 2 );
        if( ! self::isValidIP( $IPlow )) {
            return false;
        }
        if( ! self::isValidIP( $IPhigh )) {
            return false;
        }
        $IPnumBin = self::IP2bin( $IPnum );
        if( $IPnumBin < self::IP2bin( $IPlow )) {
            return false;
        }
        if( $IPnumBin > self::IP2bin( $IPhigh )) {
            return false;
        }
        return true;
    }

    /**
     * Check if range is an IP/CIDR block, delegates IPnum-in-range evaluation
     *
     * @param  string $IPnum
     * @param  string $range
     * @return bool  true on success, ip in range
     * @access private
     * @static
     */
    private static function rangeIsIpAndNetmask(
        $IPnum,
        $range
    ) {
        list( $range, $netmask ) = \explode( self::SLASH, $range, 2 );
        if( ! self::isValidIP( $range )) {
            return false;
        }
        // netmask is a (IPv6) CIDR block
        if( \ctype_digit( $netmask ) && ( 0 < $netmask ) && ( 129 > $netmask )) {
            return self::netmaskIsCIDRsizeBlock( $IPnum, $range, $netmask );
        }
        return false;
    }

    /**
     * Return true if IP number matches an IP/CIDR block
     *
     * With IPv6 you have a "prefix length" which you can interpret as the number of 1 bits in an equivalent netmask.
     * Taking the concept of "prefix length" you no longer have to have "netmask rules",
     * although there pretty much is only one:
     * the netmask should consist of only left aligned contiguous 1 bits.
     * @param  string $IPnum
     * @param  string $range
     * @param  string $cidr
     * @return bool  true on success, ip in range
     * @access private
     * @static
     */
    private static function netmaskIsCIDRsizeBlock(
        $IPnum,
        $range,
        $cidr
    ) {
        return ( self::getNetmask( $IPnum, $cidr ) == self::getNetmask( $range, $cidr ));
    }

    /**
     * Check if range is an IPnum, return true if IP matches range
     *
     * @param  string $IPnum
     * @param  string $range
     * @return bool  true on success, ip same as range
     * @access private
     * @static
     */
    private static function rangeIsIp(
        $IPnum,
        $range
    ) {
        return ( self::IP2bin( $IPnum ) === self::IP2bin( $range ));
    }
}
