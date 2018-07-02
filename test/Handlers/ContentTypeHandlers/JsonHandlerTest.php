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

namespace Kigkonsult\RestServer\Handlers\ContentTypeHandlers;

use PHPUnit\Framework\TestCase;
use Kigkonsult\RestServer\Handlers\Exceptions\JsonErrorException;

/**
 * class JsonHandlerTest
 *
 * @author      Kjell-Inge Gustafsson <ical@kigkonsult.se>
 */
class JsonHandlerTest extends TestCase
{
    /**
     * JsonHandlerDataProvider1
     */
    public function JsonHandlerDataProvider1()
    {
        $data   = [];
        $data[] = [
            [ // test data set #1
                [
                    'key1'  => 'value1',
                    'key2' => [
                        'key11'  => 'value11',
                        'key12' => [
                            'key121' => [
                                'key1211' => 'value1211',
                            ],
                        ],
                        'key13' => 'value13',
                    ],
                    'key3' => 'value3',
                ],
            ],
        ];

        return $data;
    }

    /**
     * @test
     * @dataProvider JsonHandlerDataProvider1
     */
    public function testJsonHandler1($data)
    {
        $json  = JsonHandler::serialize( $data );
        $data2 = JsonHandler::unserialize( $json );
        $this->assertEquals( $data, $data2 );
    }

    /**
     * JsonHandlerDataProvider2
     */
    public function JsonHandlerDataProvider2()
    {
        $data   = [];
        $data[] = [
            [ // An invalid UTF8 sequence
                "\xB1\x31",
            ],
        ];

        return $data;
    }

    /**
     * @test
     * @dataProvider JsonHandlerDataProvider2
     * @expectedException Kigkonsult\RestServer\Handlers\Exceptions\JsonErrorException
     */
    public function testJsonHandler2($data)
    {
        $json = JsonHandler::serialize( $data );
//      $this->expectException( JsonErrorException::class );
        $data2 = JsonHandler::unserialize( $json );
        $this->assertEquals( $data, $data2 );
    }
}
