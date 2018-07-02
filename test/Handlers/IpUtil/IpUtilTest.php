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

use PHPUnit\Framework\TestCase;

    /**
     * class IpUtilTest
     *
     * @author      Kjell-Inge Gustafsson <ical@kigkonsult.se>
     */
class IpUtilTest extends TestCase
{
        /**
         * CIDR IP v6 block chart
         *
         * IPv6 uses 128 binary digits for each IP address
         *  The first 48 bits are for Internet routing (3 * 16)
         *  The 16 bits from the 49th to the 64th are for defining subnets.
         *  The last 64 bits are for device (interface) ID's (4 * 16)
         * @link https://en.wikipedia.org/wiki/Classless_Inter-Domain_Routing#IPv6_CIDR_blocks
         * @access private
         * @static
         */
    private static $CIDRIPv6Block = [
        '128', // 80  Single end-points and loopback
        '127', // 7f  Point-to-point links (inter-router)
        '124', // 7c
        '120', // 78
        '116', // 74
        '112', // 70
        '108', // 6c
        '104', // 68
        '100', // 64
         '96', // 60
         '92', // 5c
         '88', // 58
         '84', // 54
         '80', // 50
         '76', // 4c
         '72', // 48
         '68', // 44
         '64', // 40   Single LAN (default prefix size for SLAAC)
         '60', // 3c   Some (very limited) 6rd deployments (/60 = 16 /64)
         '56', // 38   Minimal end sites assignment[12] (e.g. Home network) (/56 = 256 /64)
         '52', // 34   (/52 = 4096 /64)
         '48', // 30   Typical assignment for larger sites (/48 = 65536 /64) - Many ISP also do for residential
         '44', // 2c
         '40', // 28
         '36', // 24    possible future Local Internet registry extra-small allocations
         '32', // 20    Local Internet registry minimum allocations
         '28', // 1c    Local Internet registry medium allocations
         '24', // 18    Local Internet registry large allocations
         '20', // 14    Local Internet registry extra large allocations
         '16', // 10
         '12', //  c    Regional Internet Registry allocations from IANA[15]
          '8', //  8
          '4', //  4
    ];

    /**
     * CIDR IP v4 block netmask chart
     *
     * @link https://en.wikipedia.org/wiki/Classless_Inter-Domain_Routing#IPv6_CIDR_blocks
     * @access private
     * @static
     */
    private static $CIDRIPv4Block2Netmask = [
        32 => '255.255.255.255',
        31 => '255.255.255.254',
        30 => '255.255.255.252',
        29 => '255.255.255.248',
        28 => '255.255.255.240',
        27 => '255.255.255.224',
        26 => '255.255.255.192',
        25 => '255.255.255.128',
        24 => '255.255.255.0',
        23 => '255.255.254.0',
        22 => '255.255.252.0',
        21 => '255.255.248.0',
        20 => '255.255.240.0',
        19 => '255.255.224.0',
        18 => '255.255.192.0',
        17 => '255.255.128.0',
        16 => '255.255.0.0',
        15 => '255.254.0.0',
        14 => '255.252.0.0',
        13 => '255.248.0.0',
        12 => '255.240.0.0',
        11 => '255.224.0.0',
        10 => '255.192.0.0',
         9 => '255.128.0.0',
         8 => '255.0.0.0',
         7 => '254.0.0.0',
         6 => '252.0.0.0',
         5 => '248.0.0.0',
         4 => '240.0.0.0',
         3 => '224.0.0.0',
         2 => '192.0.0.0',
         1 => '128.0.0.0',
    ];

    /* **************************************************************************
       IP v4 tests
       ************************************************************************** */
    /**
     * @test
     *
     * Test IP number format
     */
    public function testisValidIPv4num() {
        $res = IpUtil::isValidIPv4( '192.168.0.1' );
        $this->assertTrue(  $res );

        $res = IpUtil::isValidIP( '192.168.0.1' );
        $this->assertTrue(  $res );

        $res = IpUtil::isValidIPv4( '192.168.0.256' );
        $this->assertFalse( $res);
    }

    public function hasIPv4ValidHostProvider() {
        return [
            [
                'google.com'
            ],
            [
                gethostname()
            ]
        ];
    }
    /**
     * @test
     * @dataProvider hasIPv4ValidHostProvider
     *
     * Test if IP number has a valid host
     * (i.e. get the host for an IP number and the host has the same IP number)
     */
    public function testhasIPv4ValidHost(
        $externalHostName
    ) {
        $ipNums  = IpUtil::getIPnumsFromHostname( $externalHostName );
        if( ! empty( $ipNums )) {
            $res = IpUtil::hasIPValidHost( $ipNums[0] );
            $this->assertTrue( $res );
        }
    }

    /**
     * @test
     *
     * Test expand of IP v4 number
     */
    public function testIPv4expand() {
        $expandArr = [
            '1.2.3' => '1.2.3.0',
            '1.2'   => '1.2.0.0',
            '1'     => '1.0.0.0',
        ];
        foreach( $expandArr as $Ip2Expand => $expected ) {
            $res = IpUtil::expand( $Ip2Expand );
            $this->assertEquals( $expected, $res );
        }
    }

    public function UnvalidIPv4RangeProvider() {
        return [
            [
                [ '$' ],
                '192.168.2.1'
            ],
        ];
    }
    /**
     * @test
     * @dataProvider UnvalidIPv4RangeProvider
     *
     * Test unvalid range
     */
    public function testUnvalidIPv4Range(
        $rangeArray,
        $ipNum
    ) {
        $res = IpUtil::isIPnumInRange( $ipNum, $rangeArray, $matchIx );
        $this->assertFalse( $res );
        $this->assertNull( $matchIx );
    }

    public function check_IPv4_allProvider() {
        return [
            [
                [ '*' ],
                '192.168.3.1'
            ],
        ];
    }
    /**
     * @test
     * @dataProvider check_IPv4_allProvider
     *
     * Test accept all IPs
     */
    public function testcheck_IPv4_all(
        $rangeArray,
        $ipNum
    ) {
        $res = IpUtil::isIPnumInRange( $ipNum, $rangeArray, $matchIx );
        $this->assertTrue( $res );
        $this->assertTrue( 0 == $matchIx );
    }

    /**
     * @test
     *
     * Test unvalid range
     */
    public function testcheckUnvalidIPv4Range() {
        $rangeArray = [
            '192,168,4,1',
        ];
        $res = IpUtil::isIPnumInRange( '192.168.4.1', $rangeArray, $matchIx );
        $this->assertFalse( $res );
        $this->assertNull( $matchIx );
    }

    /**
     * @test
     *
     * Test unvalid range 2
     */
    public function testcheckUnvalidIPv4Range2() {
        $range = '192,168,31,2';
        $res   = IpUtil::isIPnumInRange( '192.168.31.2', $range, $matchIx );
        $this->assertFalse( $res );
        $this->assertTrue( 0 == $matchIx );
    }

    /**
     * @test
     *
     * Test unvalid range
     */

    public function testcheckUnvalidIPv4Range3() {
        $rangeArray = [
            'no Match here',
        ];
        $res = IpUtil::isIPnumInRange( '192.168.0.1', $rangeArray, $matchIx );
        $this->assertFalse( $res );
        $this->assertNull( $matchIx );
    }

    /**
     * @test
     *
     * Test Wildcard format: 1.2.3.*
     */
    public function testisIPv4numInRange_wildcard() {
        $rangeArray = [
            '192.168.0.1',
            '192.168.31.*',
            '192.168.32.*',
        ];
        $res = IpUtil::isIPnumInRange( '192.168.32.2', $rangeArray, $matchIx );
        $this->assertTrue( $res );
        $this->assertEquals( $matchIx, 2 );

        $res = IpUtil::isIPnumInRange( '192.168.33.2', $rangeArray, $matchIx );
        $this->assertFalse( $res );
        $this->assertNull( $matchIx );
    }

    /**
     * @test
     *
     * Test unvalid CIDR format: 1.2.3.4/C (unvalid)
     */
    public function testcheck_IPv4_CIDR_unvalid() {
        $rangeArray = [
            '192.168.0.1',
            '192.168.40.40/C',
        ];
        $res = IpUtil::isIPnumInRange( '192.168.40.40', $rangeArray, $matchIx );
        $this->assertFalse( $res );
        $this->assertNull( $matchIx );
    }

    /**
     * @test
     *
     * Test all CIDR netmask formats
     */
    public function testcheck_IPv4_CIDR_Netmask() {
        $FMTIP   = '192.168.%d.1';
        $FMTcidr = '%s/%s';
        $range   = [];
        foreach( self::$CIDRIPv4Block2Netmask as $x => $netmask ) {
            $testIP  = \sprintf( $FMTIP, $x );
            $range[] = \sprintf( $FMTcidr, $testIP, $netmask );
            $res     = IpUtil::isIPnumInRange( $testIP, $range, $matchIx );
            $this->assertTrue( $res );
            $this->assertNotNull( $matchIx );
        }
    }

    /**
     * @test
     *
     * Test all CIDR block formats
     */
    public function testcheck_IPv4_CIDRblock() {
        $FMTIP   = '192.168.%d.1';
        $FMTcidr = '%s/%d';
        $range   = [];
        foreach( array_keys( self::$CIDRIPv4Block2Netmask ) as $block ) {
            $testIP  = \sprintf( $FMTIP, $block );
            $range[] = \sprintf( $FMTcidr, $testIP, $block );
            $res     = IpUtil::isIPnumInRange( $testIP, $range, $matchIx );
            $this->assertTrue( $res );
            $this->assertNotNull( $matchIx );
        }
    }

    /**
     * @test
     *
     * Test CIDR format: 1.2.3/22
     */
    public function testcheck_IPv4_CIDR_block22() {
        $rangeArray = [
            '192.168.0.1',
            '192.168.41/22',
            '192.168.51/22',
        ];
        $res = IpUtil::isIPnumInRange( '192.168.39.255', $rangeArray, $matchIx );
        $this->assertFalse( $res );
        $this->assertNull( $matchIx );

        $res = IpUtil::isIPnumInRange( '192.168.40.0',   $rangeArray, $matchIx );
        $this->assertTrue( $res );
        $this->assertEquals( $matchIx, 1 );

        $res = IpUtil::isIPnumInRange( '192.168.43.255', $rangeArray, $matchIx );
        $this->assertTrue( $res );
        $this->assertEquals( $matchIx, 1 );

        $res = IpUtil::isIPnumInRange( '192.168.44.0',   $rangeArray, $matchIx );
        $this->assertFalse( $res );
        $this->assertNull( $matchIx );
    }

    /**
     * @test
     *
     * Test CIDR format: 1.2.3.4/255.255.252.0
     */
    public function testcheck_IPv4_CIDR_netmask22() {
        $rangeArray = [
            '192.168.0.1',
            '192.168.41.0/255.255.252.0',
        ];
        $res = IpUtil::isIPnumInRange( '192.168.39.255', $rangeArray, $matchIx );
        $this->assertFalse( $res );
        $this->assertNull( $matchIx );

        $res = IpUtil::isIPnumInRange( '192.168.40.0',   $rangeArray, $matchIx );
        $this->assertTrue( $res );
        $this->assertEquals( $matchIx, 1 );

        $res = IpUtil::isIPnumInRange( '192.168.43.255', $rangeArray, $matchIx );
        $this->assertTrue( $res );
        $this->assertEquals( $matchIx, 1 );

        $res = IpUtil::isIPnumInRange( '192.168.44.0',   $rangeArray, $matchIx );
        $this->assertFalse( $res );
        $this->assertNull( $matchIx );
    }

    /**
     * @test
     *
     * Test CIDR format: 1.2.3/23, boundary test
     */
    public function testcheck_IPv4_CIDR_block23() {
        $rangeArray = [
            '192.168.10.1',
            '192.168.12.1',
            '192.168.22.1',
            '192.168.32.1',
            '192.168.42/23',
            '192.168.52.1',
            '192.168.53.1',
            '192.168.62.1',
            '192.168.82.1',
            '192.168.92.1',
        ];
        $res = IpUtil::isIPnumInRange( '192.168.41.255', $rangeArray, $matchIx );
        $this->assertFalse( $res );
        $this->assertNull( $matchIx );

        $res = IpUtil::isIPnumInRange( '192.168.42.0',   $rangeArray, $matchIx );
        $this->assertTrue( $res );
        $this->assertEquals( $matchIx, 4 );

        $res = IpUtil::isIPnumInRange( '192.168.43.255', $rangeArray, $matchIx );
        $this->assertTrue( $res );
        $this->assertEquals( $matchIx, 4 );

        $res = IpUtil::isIPnumInRange( '192.168.44.0',   $rangeArray, $matchIx );
        $this->assertFalse( $res );
        $this->assertNull( $matchIx );
    }

    /**
     * @test
     *
     * Test CIDR format: 1.2.3.4/255.255.254.0, boundary test
     */
    public function testcheck_IPv4_CIDR_netmask23() {
        $rangeArray = [
            '192.168.0.1',
            '192.168.42.0/255.255.254.0',
        ];
        $res = IpUtil::isIPnumInRange( '192.168.31.255', $rangeArray, $matchIx );
        $this->assertFalse( $res );
        $this->assertNull( $matchIx );

        $res = IpUtil::isIPnumInRange( '192.168.42.0',   $rangeArray, $matchIx );
        $this->assertTrue( $res );
        $this->assertEquals( $matchIx, 1 );

        $res = IpUtil::isIPnumInRange( '192.168.43.255', $rangeArray, $matchIx );
        $this->assertTrue( $res );
        $this->assertEquals( $matchIx, 1 );

        $res = IpUtil::isIPnumInRange( '192.168.44.0',   $rangeArray, $matchIx );
        $this->assertFalse( $res );
        $this->assertNull( $matchIx );
    }

    /**
     * @test
     *
     * Accept CIDR format: 1.2.3/24, boundary test
     */
    public function testcheck_IPv4_CIDR_block24() {
        $rangeArray = [
            '192.168.0.1',
            '192.168.43/24',
            '192.168.44.2',
        ];
        $res = IpUtil::isIPnumInRange( '192.168.42.255', $rangeArray, $matchIx );
        $this->assertFalse( $res );
        $this->assertNull( $matchIx );

        $res = IpUtil::isIPnumInRange( '192.168.43.0',   $rangeArray, $matchIx );
        $this->assertTrue( $res );
        $this->assertEquals( $matchIx, 1 );

        $res = IpUtil::isIPnumInRange( '192.168.43.255', $rangeArray, $matchIx );
        $this->assertTrue( $res );
        $this->assertEquals( $matchIx, 1 );

        $res = IpUtil::isIPnumInRange( '192.168.44.0',   $rangeArray, $matchIx );
        $this->assertFalse( $res );
        $this->assertNull( $matchIx );
    }

    /**
     * @test
     *
     * Test CIDR format: 1.2.3.4/255.255.255.0, boundary test
     */
    public function testcheck_IPv4_CIDR_netmask24() {
        $rangeArray = [
            '192.168.0.1',
            '192.168.43.0/255.255.255.0',
            '192.168.44.2',
        ];
        $res = IpUtil::isIPnumInRange( '192.168.42.255', $rangeArray, $matchIx );
        $this->assertFalse( $res );
        $this->assertNull( $matchIx );

        $res = IpUtil::isIPnumInRange( '192.168.43.0',   $rangeArray, $matchIx );
        $this->assertTrue( $res );
        $this->assertEquals( $matchIx, 1 );

        $res = IpUtil::isIPnumInRange( '192.168.43.255', $rangeArray, $matchIx );
        $this->assertTrue( $res );
        $this->assertEquals( $matchIx, 1 );

        $res = IpUtil::isIPnumInRange( '192.168.44.0',   $rangeArray, $matchIx );
        $this->assertFalse( $res );
        $this->assertNull( $matchIx );
    }

    /**
     * @test
     *
     * Accept CIDR format: 1.2.3/25, boundary test
     */
    public function testcheck_IPv4_CIDR_block25() {
        $rangeArray = [
            '192.168.0.1',
            '192.168.0.2',
            '192.168.0.1',
            '192.168.44/25',
            '192.168.45.*',
        ];
        $res = IpUtil::isIPnumInRange( '192.168.43.255', $rangeArray, $matchIx );
        $this->assertFalse( $res );
        $this->assertNull( $matchIx );

        $res = IpUtil::isIPnumInRange( '192.168.44.0',   $rangeArray, $matchIx );
        $this->assertTrue( $res );
        $this->assertEquals( $matchIx, 3 );

        $res = IpUtil::isIPnumInRange( '192.168.44.127', $rangeArray, $matchIx );
        $this->assertTrue( $res );
        $this->assertEquals( $matchIx, 3 );

        $res = IpUtil::isIPnumInRange( '192.168.44.128', $rangeArray, $matchIx );
        $this->assertFalse( $res );
        $this->assertNull( $matchIx );
    }

    /**
     * @test
     *
     * Test CIDR format: 1.2.3.4/255.255.255.128, boundary test
     */
    public function testcheck_IPv4_CIDR_netmask25() {
        $rangeArray = [
            '192.168.0.1',
            '192.168.0.2',
            '192.168.0.3',
            '192.168.44.0/255.255.255.128',
        ];
        $res = IpUtil::isIPnumInRange( '192.168.43.255', $rangeArray, $matchIx );
        $this->assertFalse( $res );
        $this->assertNull( $matchIx );

        $res = IpUtil::isIPnumInRange( '192.168.44.0',   $rangeArray, $matchIx );
        $this->assertTrue( $res );
        $this->assertEquals( $matchIx, 3 );

        $res = IpUtil::isIPnumInRange( '192.168.44.127', $rangeArray, $matchIx );
        $this->assertTrue( $res );
        $this->assertEquals( $matchIx, 3 );

        $res = IpUtil::isIPnumInRange( '192.168.44.128', $rangeArray, $matchIx );
        $this->assertFalse( $res );
        $this->assertNull( $matchIx );
    }

    /**
     * @test
     *
     * Test start-end IP format: 1.2.3.0-1.2.3.255, boundary test
     */
    public function testcheck_IPv4_Start_End_IP_format() {
        $rangeArray = [
            '192.168.0.1',
            '192.168.0.2',
            '192.168.53.10-192.168.53.15',
        ];
        $res = IpUtil::isIPnumInRange( '192.168.53.9',  $rangeArray, $matchIx );
        $this->assertFalse( $res );
        $this->assertNull( $matchIx );

        $res = IpUtil::isIPnumInRange( '192.168.53.10', $rangeArray, $matchIx );
        $this->assertTrue( $res );
        $this->assertEquals( $matchIx, 2 );

        $res = IpUtil::isIPnumInRange( '192.168.53.15', $rangeArray, $matchIx );
        $this->assertTrue( $res );
        $this->assertEquals( $matchIx, 2 );

        $res = IpUtil::isIPnumInRange( '192.168.53.16', $rangeArray, $matchIx );
        $this->assertFalse( $res );
        $this->assertNull( $matchIx );
    }

    /**
     * @test
     *
     * Test specific IP: 1.2.3.4
     */
    public function testcheck_Specific_IPv4() {
        $rangeArray = [
            '192.168.0.1',
            '192.168.62.2',
            '192.168.62.4',
        ];
        $res = IpUtil::isIPnumInRange( '192.168.62.1', $rangeArray, $matchIx );
        $this->assertFalse( $res );
        $this->assertNull( $matchIx );

        $res = IpUtil::isIPnumInRange( '192.168.62.2', $rangeArray, $matchIx );
        $this->assertTrue( $res );
        $this->assertEquals( $matchIx, 1 );

        $res = IpUtil::isIPnumInRange( '192.168.62.3', $rangeArray, $matchIx );
        $this->assertFalse( $res );
        $this->assertNull( $matchIx );

        $res = IpUtil::isIPnumInRange( '192.168.62.4', $rangeArray, $matchIx );
        $this->assertTrue( $res );
        $this->assertEquals( $matchIx, 2 );

        $res = IpUtil::isIPnumInRange( '192.168.62.5', $rangeArray, $matchIx );
        $this->assertFalse( $res );
        $this->assertNull( $matchIx );
    }

    /**
     * @test
     *
     * Test decbin32
     */
    public function testdecbin32() {
        foreach( [\gethostbyname( \gethostname()), '1.1.1.1'] as $IPnum ) {
            $IPlong = \ip2long( $IPnum );
            $IPbin  = \decbin( $IPlong );
            $res    = IpUtil::decbin32( $IPlong );
            $this->assertTrue(( 32 == \strlen( $res )));
            $cmpBool = ( 32 == \strlen( $IPbin ));
            $this->assertTrue( $cmpBool == ( $IPbin == $res ));
        }
    }

    /* **************************************************************************
       IP v6 tests
       ************************************************************************** */
    /**
     * @test
     *
     * Test IP number format
     */
    public function testisValidIPv6num() {
        $res = IpUtil::isValidIP( '3ffe:f200:0234:ab00:0123:4567:8901:abcd' );
        $this->assertTrue(  $res );

        $res = IpUtil::isValidIP( '3ffe:f200:0234:ab00:0123:4567:8901:abcd' );
        $this->assertTrue(  $res );

        $res = IpUtil::isValidIP( '3ffe:f200:0234:ab00:0123:4567:8901.abcd' );      // dot
        $this->assertFalse( $res);

        $res = IpUtil::isValidIP( ':3ffe:f200:0234:ab00:0123:4567:8901' );          // lead. :
        $this->assertFalse( $res);

        $res = IpUtil::isValidIP( '3ffe:f200:0234:ab00:0123:4567:8901:' );          // trail. :
        $this->assertFalse( $res);

        $res = IpUtil::isValidIP( '0001:0002:0003:0004:0005:0006:0007' );           // 7 segments
        $this->assertFalse( $res);

        $res = IpUtil::isValidIP( '0001:0002:0003:0004:0005:0006:0007:0008:0009' ); // 9 segments
        $this->assertFalse( $res);
    }

    /**
     * @test
     *
     * Test IP number to binary and reverse
     */
    public function testIPnum2bin2IPnum() {
        $testIp1 = '3ffe:f200:0234:ab00:0123:4567:8901:abcd';
        $testIp2 = IpUtil::expand( IpUtil::bin2IP( IpUtil::IP2bin( $testIp1 )));
        $res     = ( $testIp1 == $testIp2 );
        $this->assertTrue(  $res );

        $testIp3 = '3ffe::abcd';
        $testIp4 = IpUtil::expand( IpUtil::bin2IP( IpUtil::IP2bin( $testIp3 )));
        $res     = ( IpUtil::expand(  $testIp3 ) == $testIp4 );
        $this->assertTrue(  $res );
    }

    public function check_IPv6_expandProvider() {
        return [
            [ '1:2:3:4:5:6::8' ],
            [ '1:2:3:4:5::8' ],
            [ '1:2:3:4::8' ],
            [ '1:2:3::8' ],
            [ '1:2::8' ],
            [ '1::8' ],
            [ '1::2:3:4:5:6:7' ],
            [ '1::2:3:4:5:6' ],
            [ '1::2:3:4:5' ],
            [ '1::2:3:4' ],
            [ '1::2:3' ],
            [ '1::8' ],
            [ '::2:3:4:5:6:7:8' ],
            [ '::2:3:4:5:6:7' ],
            [ '::2:3:4:5:6' ],
            [ '::2:3:4:5' ],
            [ '::2:3:4' ],
            [ '::2:3' ],
            [ '::8' ],
            [ '1:2:3:4:5:6::' ],
            [ '1:2:3:4:5::' ],
            [ '1:2:3:4::' ],
            [ '1:2:3::' ],
            [ '1:2::' ],
            [ '1::' ],
            [ '1:2:3:4:5::7:8' ],
        ];
    }

    /**
     * @test
     * @dataProvider check_IPv6_expandProvider
     *
     * Test expanding condensed IP v6 num
     *
     * Test data from
     * @link https://static.helpsystems.com/intermapper/third-party/test-ipv6-regex.pl?_ga=2.99805854.1049820461.1509723703-1673652796.1509723703
     * IPv6 regular expression courtesy of Dartware, LLC (http://intermapper.com)
     * For full details see http://intermapper.com/ipv6regex
     * will expand  1:2:3:4:5:6::8  to  0001:0002:0003:0004:0005:0006:0000:0008
     */
    public function testcheck_IPv6_expand( $condensedIP ) {
        $exandedIp = IpUtil::expand( $condensedIP );
        $this->assertTrue( IpUtil::isValidIPv6( $exandedIp ));
        $this->assertEquals( IpUtil::IP2bin( $condensedIP ), IpUtil::IP2bin( $exandedIp ));
    }

    public function check_IPv6_compress1Provider() {
        return [
            [
                '1:2::5:6:7:8'
            ]
        ];
    }
    /**
     * @test
     * @dataProvider check_IPv6_compress1Provider
     *
     * Test NO compress IP
     */
    public function testcheck_IPv6_compress1(
        $IPtoCompress
    ) {
        $this->assertEquals( IpUtil::compress( $IPtoCompress ), $IPtoCompress );
    }

    public function check_IPv6_compressProvider2() {
        return [
            [ '0001:0002:0003:0004:0005:0006:0007:0008', '1:2:3:4:5:6:7:8'  ],
            [ '0001:0002:0003:0004:0005:0006:0007:0000', '1:2:3:4:5:6:7::'  ],
            [ '0001:0002:0003:0004:0005:0006:0000:0008', '1:2:3:4:5:6::8'   ],
            [ '0001:0002:0003:0004:0005:0000:0007:0008', '1:2:3:4:5::7:8'   ],
            [ '0001:0002:0003:0004:0000:0006:0007:0008', '1:2:3:4::6:7:8'   ],
            [ '0001:0002:0003:0000:0005:0006:0007:0008', '1:2:3::5:6:7:8'   ],
            [ '0001:0002:0000:0004:0005:0006:0007:0008', '1:2::4:5:6:7:8'   ],
            [ '0001:0000:0003:0004:0005:0006:0007:0008', '1::3:4:5:6:7:8'   ],
            [ '0000:0002:0003:0004:0005:0006:0007:0008',  '::2:3:4:5:6:7:8' ], // #9

            [ '0001:0002:0003:0004:0005:0006:0000:0000', '1:2:3:4:5:6::' ],
            [ '0001:0002:0003:0004:0005:0000:0000:0008', '1:2:3:4:5::8'  ],
            [ '0001:0002:0003:0004:0000:0000:0007:0008', '1:2:3:4::7:8'  ],
            [ '0001:0002:0003:0000:0000:0006:0007:0008', '1:2:3::6:7:8'  ],
            [ '0001:0002:0000:0000:0005:0006:0007:0008', '1:2::5:6:7:8'  ],
            [ '0001:0000:0000:0004:0005:0006:0007:0008', '1::4:5:6:7:8'  ],
            [ '0000:0000:0003:0004:0005:0006:0007:0008', '::3:4:5:6:7:8' ],    // #16

            [ '0001:0002:0003:0004:0005:0000:0000:0000', '1:2:3:4:5::' ],
            [ '0001:0002:0003:0004:0000:0000:0000:0008', '1:2:3:4::8'  ],
            [ '0001:0002:0003:0000:0000:0000:0007:0008', '1:2:3::7:8'  ],
            [ '0001:0002:0000:0000:0000:0006:0007:0008', '1:2::6:7:8'  ],
            [ '0001:0000:0000:0000:0005:0006:0007:0008', '1::5:6:7:8'  ],
            [ '0000:0000:0000:0004:0005:0006:0007:0008', '::4:5:6:7:8' ],      // #22

            [ '0001:0002:0003:0004:0000:0000:0000:0000', '1:2:3:4::'  ],
            [ '0001:0002:0003:0000:0000:0000:0000:0008', '1:2:3::8'   ],
            [ '0001:0002:0000:0000:0000:0000:0007:0008', '1:2::7:8'   ],
            [ '0001:0000:0000:0000:0000:0006:0007:0008', '1::6:7:8'   ],
            [ '0000:0000:0000:0000:0005:0006:0007:0008',  '::5:6:7:8' ],       // #27

            [ '0001:0002:0000:0000:0005:0006:0000:0000', '1:2::5:6:0:0'   ],
            [ '0001:0000:0000:0004:0005:0000:0000:0008', '1::4:5:0:0:8'   ],
            [ '0000:0000:0003:0004:0000:0000:0007:0008',  '::3:4:0:0:7:8' ],   // #30

            [ '0001:0000:0000:0000:0000:0000:0000:0008', '1::8'     ],
            [ '0000:0002:0000:0000:0000:0000:0007:0000', '0:2::7:0' ],
            [ '0000:0000:0000:0004:0005:0000:0000:0000', '0:0:0:4:5:0:0:0' ],  // #33

            [ '0000:0000:0003:0004:0005:0006',           '::3:4:5:6' ],
            [ '0001:0000:0000:0004:0000:0006',           '1::4:0:6'  ],
            [ '0001:0000:0003:0000:0000:0006',           '1:0:3::6'  ],
            [ '0001:0002:0003:0004:0000:0000',           '1:2:3:4::' ],        // #37
        ];
    }

    /**
     * @test
     * @dataProvider check_IPv6_compressProvider2
     *
     * Test compress IPs
     */
    public function testcheck_IPv6_compress2(
        $IPtoCompress,
        $compareIP
    ) {
        $isFull      = ( 7 == \substr_count( $IPtoCompress, ':' ));
        $arg2        = ( $isFull ) ? null : false;
        $condensedIP = IpUtil::compress( $IPtoCompress, $arg2 );
        if( ! $isFull )
            $this->assertEquals( $compareIP, $condensedIP );
        else {
            $this->assertTrue(  IpUtil::isValidIPv6( $condensedIP ));
            $this->assertEquals( IpUtil::IP2bin( $IPtoCompress ), IpUtil::IP2bin( $condensedIP ));
            $this->assertEquals( IpUtil::IP2bin( $compareIP ),    IpUtil::IP2bin( $condensedIP ));
        }
    }

    public function check_IPv6_getInterfaceIdentifierProvider() {
        return [
            [
                '3ffe:f200:0234:ab00:0123:4567:8901:1234',
                '0123:4567:8901:1234'
            ]
        ];
    }
    /**
     * @test
     * @dataProvider check_IPv6_getInterfaceIdentifierProvider
     *
     * Test getInterfaceIdentifier
     */
    public function testcheck_IPv6_getInterfaceIdentifier(
        $testIP,
        $interfc
    ) {
        $res = IpUtil::getInterfaceIdentifier( $testIP );
        $this->assertEquals( $res, $interfc );
    }

    public function check_IPv6_getNetworkPrefixProvider() {
        return [
            [
                '3ffe:f200:0234:ab00:0123:4567:8901:1234',
                '3ffe:f200:0234:ab00'
            ]
        ];
    }
    /**
     * @test
     * @dataProvider check_IPv6_getNetworkPrefixProvider
     *
     * Test getNetworkPrefix
     */
    public function testcheck_IPv6_getNetworkPrefix(
        $testIP,
        $interfc
    ) {
        $res = IpUtil::getNetworkPrefix( $testIP );
        $this->assertEquals( $res, $interfc );
    }

    public function check_IPv6_allProvider() {
        return [
            [
                [ '*' ],
                '3ffe:f200:0234:ab00:0123:4567:8901:1234',
            ]
        ];
    }
    /**
     * @test
     * @dataProvider check_IPv6_allProvider
     *
     * Test accept all IPs
     */
    public function testcheck_IPv6_all(
        $rangeArray,
        $testIP
    ) {
        $res        = IpUtil::isIPnumInRange( $testIP, $rangeArray, $matchIx );
        $this->assertTrue( $res );
        $this->assertTrue( 0 == $matchIx );
    }

    public function checkUnvalidIPv6RangeProvider() {
        return [
            [
                'no Match here',
                '3ffe:f200:0234:ab00:0123:4567:1:20'
            ]
        ];
    }
    /**
     * @test
     * @dataProvider checkUnvalidIPv6RangeProvider
     *
     * Test unvalid range
     */
    public function testcheckUnvalidIPv6Range(
        $rangeArray,
        $testIP
    ) {
        $res = IpUtil::isIPnumInRange( $testIP, $rangeArray, $matchIx );
        $this->assertFalse( $res );
        $this->assertNull( $matchIx );
    }

    public function checkUnvalidCIDRProvider() {
        return [
            [
                '3ffe:f200:0234:ab00:0123:4567:1:20/210',
                '3ffe:f200:0234:ab00:0123:4567:1:20'
            ],
            [
                '3ffe:f200:0234:ab00:0123:4567:1.20/64',
                '3ffe:f200:0234:ab00:0123:4567:1:20'
            ]
        ];
    }
    /**
     * @test
     * @dataProvider checkUnvalidCIDRProvider
     *
     * Test unvalid range
     */
    public function testcheckUnvalidCIDR(
        $rangeArray,
        $testIP
    ) {
        $res = IpUtil::isIPnumInRange( $testIP, $rangeArray, $matchIx );
        $this->assertFalse( $res );
        $this->assertNull( $matchIx );

    }

    /**
     * @test
     *
     * Test all CIDR block formats
     */
    public function testcheck_IP_CIDRblock() {
        $FMTcidr   = '%s/%d';
        $FMTIP     = '3ffe:f200:0234:ab00:0123:4567:8901:%04d';
        $rangeBase = 'fe80:1:2:3:a:bad:1dea:%1$04d/%1$d';
        foreach( self::$CIDRIPv6Block as $x => $block ) {
            $testIP  = \sprintf( $FMTIP, $block );
            $range   = [
                \sprintf( $rangeBase, $block ),
                \sprintf( $FMTcidr, $testIP, $block )
            ];
            $res     = IpUtil::isIPnumInRange( $testIP, $range, $matchIx );
            $this->assertTrue( $res );
            $this->assertNotNull( $matchIx );
        }

        $rangeArray = [
            '3ffe:f200:0234::/64',
        ];
        $res = IpUtil::isIPnumInRange( '3ffe:f200:0234:ab00:0123:4567:8901:20', $rangeArray, $matchIx );
        $this->assertTrue( $res );
        $this->assertEquals( $matchIx, 0 );
    }

    public function check_IPv6_CIDR_noMatchProvider() {
        return [
            [
                'fe80:1:2:3:a:bad:1dea::10',
                [ '3ffe:f200:0234:ab00:0123:4567:8901:1/64' ],
            ]
        ];
    }
    /**
     * @test
     * @dataProvider check_IPv6_CIDR_noMatchProvider
     *
     * Test no match in CIDR block
     */
    public function testcheck_IPv6_CIDR_noMatch(
        $testIP,
        $rangeArray
    ) {
        $res = IpUtil::isIPnumInRange( $testIP,  $rangeArray, $matchIx );
        $this->assertFalse( $res );
        $this->assertNull( $matchIx );
    }

    /**
     * @test
     *
     * Test IP num in range ex. 1:2:3:4::-1:2:3:5::, boundary test
     */
    public function testcheck_IPv6_Start_End_IP_format() {
        $rangeArray = [
            '3ffe:f200:0234:ab00:0123:4567:1:2',
            '3ffe:f200:0234:ab00:0123:4567:1:10-3ffe:f200:0234:ab00:0123:4567:1:19',
            '1:2:3:4::-1:2:3:5::',
        ];
        $res = IpUtil::isIPnumInRange( '3ffe:f200:0234:ab00:0123:4567:1:09', $rangeArray, $matchIx );
        $this->assertFalse( $res );
        $this->assertNull( $matchIx );

        $res = IpUtil::isIPnumInRange( '3ffe:f200:0234:ab00:0123:4567:1:10', $rangeArray, $matchIx );
        $this->assertTrue( $res );
        $this->assertEquals( $matchIx, 1 );

        $res = IpUtil::isIPnumInRange( '3ffe:f200:0234:ab00:0123:4567:1:19', $rangeArray, $matchIx );
        $this->assertTrue( $res );
        $this->assertEquals( $matchIx, 1 );

        $res = IpUtil::isIPnumInRange( '3ffe:f200:0234:ab00:0123:4567:1:1a', $rangeArray, $matchIx );
        $this->assertFalse( $res );
        $this->assertNull( $matchIx );

        $res = IpUtil::isIPnumInRange( '1:2:3:3::ffff', $rangeArray, $matchIx );
        $this->assertFalse( $res );
        $this->assertNull( $matchIx );

        $res = IpUtil::isIPnumInRange( '1:2:3:4::16',   $rangeArray, $matchIx );
        $this->assertTrue( $res );
        $this->assertEquals( $matchIx, 2 );

        $res = IpUtil::isIPnumInRange( '1:2:3:6::0',    $rangeArray, $matchIx );
        $this->assertFalse( $res );
        $this->assertNull( $matchIx );
    }

    /**
     * @test
     *
     * Test specific IPv6
     */
    public function testcheck_Specific_IPv6() {
        $rangeArray = [
            '1:2:3:80::1:0',
            '1:2:3:80::3:0',
            '1:2:3:80::5:0',
        ];
        $res = IpUtil::isIPnumInRange( '1:2:3:80::2:0', $rangeArray, $matchIx );
        $this->assertFalse( $res );
        $this->assertNull( $matchIx );

        $res = IpUtil::isIPnumInRange( '1:2:3:80::3:0', $rangeArray, $matchIx );
        $this->assertTrue( $res );
        $this->assertEquals( $matchIx, 1 );

        $res = IpUtil::isIPnumInRange( '1:2:3:80::4:0', $rangeArray, $matchIx );
        $this->assertFalse( $res );
        $this->assertNull( $matchIx );

        $res = IpUtil::isIPnumInRange( '1:2:3:80::5:0', $rangeArray, $matchIx );
        $this->assertTrue( $res );
        $this->assertEquals( $matchIx, 2 );

        $res = IpUtil::isIPnumInRange( '1:2:3:80::6:0', $rangeArray, $matchIx );
        $this->assertFalse( $res );
        $this->assertNull( $matchIx );
    }

    /* **************************************************************************
       IP v4/v6 mixed tests
       ************************************************************************** */
    /**
     * @test
     *
     * Test mixed IPv4 / IPv6
     */
    public function testcheck_mixed() {
        $rangeArray = [
            '3ffe:f200:0234:ab00:0123:4567:8901:1/64',
            '192.168.42.0/255.255.254.0',
        ];
        $testIPv6_1 = 'fe80:1:2:3:a:bad:1dea:10/20';
        $testIPv6_2 = '3ffe:f200:0234:ab00:0123:4567:8901:1';
        $testIPv4_1 = '192.168.55.55';
        $testIPv4_2 = '192.168.42.44';

        $res = IpUtil::isIPnumInRange( $testIPv6_1, $rangeArray, $matchIx );
        $this->assertFalse( $res );
        $this->assertNull( $matchIx );

        $res = IpUtil::isIPnumInRange( $testIPv4_1, $rangeArray, $matchIx );
        $this->assertFalse( $res );
        $this->assertNull( $matchIx );

        $res = IpUtil::isIPnumInRange( $testIPv6_2, $rangeArray, $matchIx );
        $this->assertTrue( $res );
        $this->assertEquals( $matchIx, 0 );

        $res = IpUtil::isIPnumInRange( $testIPv4_2, $rangeArray, $matchIx );
        $this->assertTrue( $res );
        $this->assertEquals( $matchIx, 1 );
    }

    /**
     * @test
     *
     * Test IPv4 mapped to IPv6
     */
    public function testcheck_IPv4MappedV6() {
        $testIP1 = '::ffff:192.0.2.128';
        $testIP2 = '::1234:192.0.2.128';
        $res = IpUtil::isValidIP( $testIP1 );
        $this->assertTrue(  $res );

        $res = IpUtil::isValidIPv4( $testIP1 );
        $this->assertFalse( $res );

        $res = IpUtil::isValidIPv6( $testIP1 );
        $this->assertTrue(  $res );

        $res = IpUtil::isIPv4MappedIPv6( $testIP1 );
        $this->assertTrue(  $res );

        $res = IpUtil::isIPv4MappedIPv6( $testIP2 );
        $this->assertFALSE( $res );
    }

    public function check_IPv4MappedV6_expandProvider() {
        return [
            [
                '::ffff:192.0.2.128',
            ]
        ];
    }
    /**
     * @test
     * @dataProvider check_IPv4MappedV6_expandProvider
     *
     * Test IPv4 mapped to IPv6 and expanded
     */
    public function testcheck_IPv4MappedV6_expand(
        $testIP
    ) {
        $exandedIp = IpUtil::expand( $testIP );
        $this->assertTrue( IpUtil::isValidIPv6( $exandedIp ));
        $this->assertEquals( IpUtil::IP2bin( $testIP ), IpUtil::IP2bin( $exandedIp ));
    }
}
