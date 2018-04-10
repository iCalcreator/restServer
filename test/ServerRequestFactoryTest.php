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

    /**
     *
     * @since     2018-02-09
     */

namespace Kigkonsult\RestServer;

// use PHPUnit_Framework_TestCase as TestCase; // PHPUnit < 6.1.0
use PHPUnit\Framework\TestCase;          // PHPUnit > 6.1.0

class ServerRequestFactoryTest extends TestCase
{
    /**
     * testHeader provider
     */
    public function HeaderProvider()
    {
        $dataArr   = [];
        $dataArr[] = [
                       [
                         'REQUEST_METHOD'                 => 'POST',
                         'X_HTTP_METHOD_OVERRIDE'         => 'PUT',
                         'HTTP_X_HTTP_METHOD_OVERRIDE'    => 'PUT',
                         'HTTP_ORIGIN'                    => 'test.com',
                         'CONTENT_ENCODING'               => 'gzip',
                         'CONTENT_TYPE'                   => 'text/plain',
                         'HTTP_ACCEPT'                    => 'application/xml',
                         'HTTP_ACCEPT_ENCODING'           => 'deflate',
                         'Access_Control_Request_Method'  => 'GET',
                         'Access_Control_Request_Headers' => 'X-HEADER-1',
                         'X_HEADER_2'                     => 'hejsan',
                         'REMOTE_ADDR'                    => '192.168.0.1',
                         'FORWARDED'                      => '192.168.0.1',
                         'X_FORWARDED_BY'                 => '192.168.0.1',
                         'HTTP_X_FORWARDED_BY'            => '192.168.0.1',
                         'X_FORWARDED_FOR'                => '192.168.0.1',
                         'HTTP_X_FORWARDED_FOR'           => '192.168.0.1',
                         'CLIENT_IP'                      => '192.168.0.1',
                         'HTTP_CLIENT_IP'                 => '192.168.0.1',
                         'X-CLIENT_IP'                    => '192.168.0.1',
                         'HTTP_X_CLIENT_IP'               => '192.168.0.1',
                       ],
                     ];

        return $dataArr;
    }

    /**
     * test header mgnt
     *
     * @test
     * @dataProvider HeaderProvider
     */
    public function testHeader($headers)
    {
        $request = ServerRequestFactory::fromGlobals( $headers );
        foreach ( $headers as $hKey => $hValue ) {
            if ( 0 === \strpos( $hKey, 'HTTP_' )) {
                $hKey = \substr( $hKey, 5 );
            }
            $hKey = \str_replace( '_', '-', $hKey );
            if ( $request->hasHeader( $hKey )) {
                $this->assertEquals( $hValue, $request->getHeader( $hKey )[0] );
            }
        }
    }
}
