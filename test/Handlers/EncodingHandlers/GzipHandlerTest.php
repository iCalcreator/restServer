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

namespace Kigkonsult\RestServer\Handlers\EncodingHandlers;

// use PHPUnit_Framework_TestCase as TestCase; // PHPUnit < 6.1.0
use PHPUnit\Framework\TestCase;          // PHPUnit > 6.1.0
use Kigkonsult\RestServer\Handlers\Exceptions\ZlibErrorException;

class GzipHandlerTest extends TestCase
{
    /**
     * gzipHandlerDataProvider1
     */
    public function gzipHandlerDataProvider1()
    {
        $data   = [];
        $data[] = [// test data set #1
            'this is uncompressed data',
        ];

        return $data;
    }

    /**
     * @test
     * @dataProvider gzipHandlerDataProvider1
     */
    public function testgzipHandler1($data)
    {
        $compressed = GzipHandler::enCode( $data );
        $data2      = GzipHandler::deCode( $compressed );
        $this->assertEquals( $data, $data2 );
    }

    /**
     * gzipHandlerDataProvider2
     */
    public function gzipHandlerDataProvider2()
    {
        $data   = [];
        $data[] = [ // An invalid UTF8 sequence
            "\xB1\x31",
        ];

        return $data;
    }

    /**
     * @test
     * @dataProvider gzipHandlerDataProvider2
     * @expectedException Kigkonsult\RestServer\Handlers\Exceptions\ZlibErrorException
     */
    public function testgzipHandler2($data)
    {
        $data2 = GzipHandler::deCode( $data );
        $this->assertEquals( $data, $data2 );
    }
}
