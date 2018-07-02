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

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Kigkonsult\RestServer\ResponseInterface;
use Zend\Diactoros\ServerRequest;
use Kigkonsult\RestServer\Response;
use Kigkonsult\RestServer\RestServer;
use Kigkonsult\RestServer\StreamFactory;

/**
 * class IpHandlerTest
 *
 * @author      Kjell-Inge Gustafsson <ical@kigkonsult.se>
 */
class IpHandlerTest extends TestCase
{
    /**
     * testisIpHeader provider
     */
    public function isIpHeaderProvider()
    {
        $dataArr   = [];
        $dataArr[] = [
            IpHandler::CLIENT_IP,
            true
        ];
        $dataArr[] = [
            IpHandler::FORWARDED,
            true
        ];
        $dataArr[] = [
            IpHandler::FORWARDED_FOR,
            true
        ];
        $dataArr[] = [
            IpHandler::REFERER,
            true
        ];
        $dataArr[] = [
            IpHandler::REMOTE_ADDR,
            true
        ];
        $dataArr[] = [
            'falseHeader',
            false
        ];

        return $dataArr;
    }

    /**
     * test isIpHeader, exists and not
     *
     * @test
     * @dataProvider isIpHeaderProvider
     */
    public function testisIpHeader(
        $header,
        $expected
    ) {
        $this->assertEquals(
            $expected,
            IpHandler::isIpHeader( $header )
        );
    }

    /**
     * test validateIP, detect previous error
     *
     * @test
     */
    public function testvalidateIP1(
    ) {
        $request    = new ServerRequest();
        $request    = $request->withAttribute( RestServer::ERROR, true );
        $response   = new Response();
        $statusCode = $response->getStatusCode();

        $result     = IpHandler::validateIP(
            $request,
            $response
        );
        $this->assertTrue(
            $result[0] instanceof ServerRequestInterface
        );
        $this->assertTrue(
            $result[1] instanceof ResponseInterface
        );
        $this->assertEquals(
            $statusCode,
            $result[1]->getStatusCode()
        );
    }

    /**
     * testvalidateIP2 provider
     */
    public function validateIP2Provider() {
        $dataArr   = [];
        $dataArr[] = [ // set #1
            [ // config
                RestServer::CORRELATIONID => RestServer::getGuid(),
                IpHandler::IPHEADER => [
                    RestServer::IGNORE => true
                ]
            ],
        ];
        $dataArr[] = [ // set #2
            [ // config
                RestServer::CORRELATIONID => RestServer::getGuid(),
                IpHandler::IPHEADER => [
                    IpHandler::EXAMINE => []
                ]
            ],
        ];

        return $dataArr;
    }

    /**
     * test validateIP, ignore and empty examine
     *
     * @test
     * @dataProvider validateIP2Provider
     */
    public function testvalidateIP2(
        array $config
    ) {
        $request    = new ServerRequest();
        $request    = $request->withAttribute( RestServer::CONFIG, $config );
        $response   = new Response();
        $statusCode = $response->getStatusCode();

        $result  = IpHandler::validateIP(
            $request,
            $response
        );
        $this->assertTrue(
            $result[0] instanceof ServerRequestInterface
        );
        $this->assertTrue(
            $result[1] instanceof ResponseInterface
        );
        $this->assertEquals(
            $statusCode,
            $result[1]->getStatusCode()
        );
    }

    /**
     * testvalidateIP3 provider
     */
    public function validateIP3Provider() {
        $dataArr   = [];
        $dataArr[] = [ // set #1
            [ // config
                RestServer::CORRELATIONID => RestServer::getGuid(),
                IpHandler::IPHEADER => [
                    IpHandler::EXAMINE => [
                        'falseHeader',
                    ]
                ]
            ],
        ];

        return $dataArr;
    }

    /**
     * test validateIP, not-empty examine but invalid
     *
     * @test
     * @dataProvider validateIP3Provider
     */
    public function testvalidateIP3(
        array $config
    ) {
        $request    = new ServerRequest();
        $request    = $request->withAttribute( RestServer::CONFIG, $config );
        $response   = new Response();

        $result  = IpHandler::validateIP(
            $request,
            $response
        );
        $this->assertTrue(
            $result[0] instanceof ServerRequestInterface
        );
        $this->assertTrue(
            $result[1] instanceof ResponseInterface
        );
        $this->assertEquals(
            403,
            $result[1]->getStatusCode()
        );
    }

    /**
     * testvalidateIP_singelValues provider
     */
    public function validateIP_singelValues_Provider() {
        $dataArr   = [];
        $ipEnd     = 0;
        foreach( [
                     IpHandler::CLIENT_IP,
                     IpHandler::REMOTE_ADDR,
                     IpHandler::REFERER
                 ] as $ipHeader ) {
            $dataArr[] = [ // set #1
                [ // config
                    RestServer::CORRELATIONID => RestServer::getGuid(),
                    IpHandler::IPHEADER => [
                        IpHandler::EXAMINE => [
                            $ipHeader => [
                                IpHandler::RANGE => [
                                    '*'
                                ],
                            ],
                        ],
                    ],
                ],
                [ // server
                    $ipHeader => '1.2.3.' . ++$ipEnd,
                ],
                200 // expected statusCode
            ];
            $dataArr[] = [ // set #2
                [ // config
                    RestServer::CORRELATIONID => RestServer::getGuid(),
                    IpHandler::IPHEADER => [
                        IpHandler::EXAMINE => [
                            $ipHeader => [
                                IpHandler::RANGE => [
                                    '1.3.4.*'
                                ],
                            ],
                        ],
                    ],
                ],
                [ // server
                    $ipHeader => '1.2.3.' . ++$ipEnd,
                ],
                403 // expected statusCode
            ];
            $dataArr[] = [ // set #3
                [ // config
                    RestServer::CORRELATIONID => RestServer::getGuid(),
                    IpHandler::IPHEADER => [
                        IpHandler::EXAMINE => [
                            $ipHeader => [
                                IpHandler::REQUIRED => true,
                                IpHandler::RANGE => [
                                    '1.2.3.*'
                                ],
                            ],
                            IpHandler::FORWARDED_FOR => [
                                IpHandler::REQUIRED => true,
                                IpHandler::RANGE => [
                                    IpHandler::FIRST => [
                                        '1.2.3.5'
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                [ // server
                    $ipHeader                => '1.2.3.' . ++$ipEnd,
                    IpHandler::FORWARDED_FOR => '1.2.3.5',
                ],
                200 // expected statusCode
            ];
            $dataArr[] = [ // set #4
                [ // config
                    RestServer::CORRELATIONID => RestServer::getGuid(),
                    IpHandler::IPHEADER => [
                        IpHandler::EXAMINE => [
                            $ipHeader => [
                                IpHandler::REQUIRED => false,
                                IpHandler::RANGE => [
                                    '1.2.3.*'
                                ],
                            ],
                            IpHandler::FORWARDED_FOR => [
                                IpHandler::REQUIRED => true,
                                IpHandler::RANGE => [
                                    IpHandler::FIRST => [
                                        '1.2.3.4'
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                [ // server
                    $ipHeader                => '1.2.3.' . ++$ipEnd,
                    IpHandler::FORWARDED_FOR => '1.2.3.5',
                ],
                403 // expected statusCode
            ];
            $dataArr[] = [ // set #5
                [ // config
                    RestServer::CORRELATIONID => RestServer::getGuid(),
                    IpHandler::IPHEADER => [
                        IpHandler::EXAMINE => [
                            $ipHeader => [
                                IpHandler::REQUIRED => true,
                                IpHandler::RANGE => [
                                    '1.2.3.*'
                                ],
                            ],
                            IpHandler::FORWARDED_FOR => [
                                IpHandler::REQUIRED => false,
                                IpHandler::RANGE => [
                                    IpHandler::FIRST => [
                                        '1.2.3.4'
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                [ // server
                    $ipHeader                => '1.2.3.' . ++$ipEnd,
                    IpHandler::FORWARDED_FOR => '1.2.3.5',
                ],
                200 // expected statusCode
            ];
        } // end foreach

        return $dataArr;
    }

    /**
     * test validateIP, CLIENT-IP, REMOTE_ADDR, REFERER
     *
     * @test
     * @dataProvider validateIP_singelValues_Provider
     */
    public function testvalidateIP_singelValues(
        array $config,
        array $headers,
              $statusCode
    ) {
        $request = new ServerRequest(
            [],                                          // serverParams
            [],                                          // $uploadedFiles
            null,                                     // uri
            'GET',                                // method
            StreamFactory::createStream(),               // body
            $headers                                     // headers
        );
        $request    = $request->withAttribute( RestServer::CONFIG, $config );
        $response   = new Response();

        $result     = IpHandler::validateIP(
            $request,
            $response
        );
        $this->assertEquals(
            $statusCode,
            $result[1]->getStatusCode()
        );
    }

    /**
     * validateIP_Forvarded_For_Provider
     */
    public function validateIP_Forvarded_For_Provider() {
        $dataArr   = [];
        $ipEnd     = 0;
        $dataArr[] = [ // set #1, ok 200
            [ // config
                RestServer::CORRELATIONID => RestServer::getGuid(),
                IpHandler::IPHEADER => [
                    IpHandler::EXAMINE => [
                        IpHandler::FORWARDED_FOR => [
                            IpHandler::RANGE => [
                                IpHandler::FIRST => [
                                    '*'
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [ // server
                IpHandler::REMOTE_ADDR   => '1.2.3.' . ++$ipEnd,
                IpHandler::FORWARDED_FOR => '1.2.3.4',
            ],
            200 // expected statusCode
        ];
        $dataArr[] = [ // set #2, ok 200
            [ // config
                RestServer::CORRELATIONID => RestServer::getGuid(),
                IpHandler::IPHEADER => [
                    IpHandler::EXAMINE => [
                        IpHandler::FORWARDED_FOR => [
                            IpHandler::RANGE => [
                                IpHandler::FIRST => [
                                    '*'
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [ // server
                IpHandler::REMOTE_ADDR   => '1.2.3.' . ++$ipEnd,
                IpHandler::FORWARDED_FOR => '1.2.3.4, 1.2.3.10'
            ],
            200 // expected statusCode
        ];
        $dataArr[] = [ // set #3, nok 403
            [ // config
                RestServer::CORRELATIONID => RestServer::getGuid(),
                IpHandler::IPHEADER => [
                    IpHandler::EXAMINE => [
                        IpHandler::FORWARDED_FOR => [
                            IpHandler::RANGE => [
                                IpHandler::FIRST => [
                                    '1.2.3.4'
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [ // server
                IpHandler::REMOTE_ADDR   => '1.2.3.' . ++$ipEnd,
                IpHandler::FORWARDED_FOR => '1.2.3.10, 1.2.3.4'
            ],
            403 // expected statusCode
        ];
        $dataArr[] = [ // set #4, ok 200
            [ // config
                RestServer::CORRELATIONID => RestServer::getGuid(),
                IpHandler::IPHEADER => [
                    IpHandler::EXAMINE => [
                        IpHandler::FORWARDED_FOR => [
                            IpHandler::RANGE => [
                                IpHandler::LAST => [
                                '1.2.3.10'
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [ // server
                IpHandler::REMOTE_ADDR   => '1.2.3.' . ++$ipEnd,
                IpHandler::FORWARDED_FOR => '1.2.3.4, 1.2.3.10'
            ],
            200 // expected statusCode
        ];
        $dataArr[] = [ // set #5, nok 403
            [ // config
                RestServer::CORRELATIONID => RestServer::getGuid(),
                IpHandler::IPHEADER => [
                    IpHandler::EXAMINE => [
                        IpHandler::FORWARDED_FOR => [
                            IpHandler::RANGE => [
                                IpHandler::LAST => [
                                    '1.2.3.10'
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [ // server
                IpHandler::REMOTE_ADDR   => '1.2.3.' . ++$ipEnd,
                IpHandler::FORWARDED_FOR => '1.2.3.10, 1.2.3.4'
            ],
            403 // expected statusCode
        ];
        $dataArr[] = [ // set #5, ok 200
            [ // config
                RestServer::CORRELATIONID => RestServer::getGuid(),
                IpHandler::IPHEADER => [
                    IpHandler::EXAMINE => [
                        IpHandler::FORWARDED_FOR => [
                            IpHandler::RANGE => [
                                IpHandler::ALL => [
                                    '1.2.3.3'
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [ // server
                IpHandler::REMOTE_ADDR   => '1.2.3.' . ++$ipEnd,
                IpHandler::FORWARDED_FOR => '1.2.3.2, 1.2.3.3, 1.2.3.4'
            ],
            200 // expected statusCode
        ];
        $dataArr[] = [ // set #6, ok 403
            [ // config
                RestServer::CORRELATIONID => RestServer::getGuid(),
                IpHandler::IPHEADER => [
                    IpHandler::EXAMINE => [
                        IpHandler::FORWARDED_FOR => [
                            IpHandler::RANGE => [
                                IpHandler::ALL => [
                                    '1.2.3.5'
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [ // server
                IpHandler::REMOTE_ADDR   => '1.2.3.' . ++$ipEnd,
                IpHandler::FORWARDED_FOR => '1.2.3.2, 1.2.3.3, 1.2.3.4'
            ],
            403 // expected statusCode
        ];

        $dataArr[] = [ // set #7, nok 403
            [ // config
                RestServer::CORRELATIONID => RestServer::getGuid(),
                IpHandler::IPHEADER => [
                    IpHandler::EXAMINE => [
                        IpHandler::REMOTE_ADDR   => [
                            IpHandler::REQUIRED => false,
                            IpHandler::RANGE => [
                                '1.2.3.*'
                            ],
                        ],
                        IpHandler::FORWARDED_FOR => [
                            IpHandler::REQUIRED => true,
                            IpHandler::RANGE => [
                                IpHandler::ALL => [
                                    '1.2.3.10'
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [ // server
                IpHandler::REMOTE_ADDR   => '1.2.3.' . ++$ipEnd,
                IpHandler::FORWARDED_FOR => '1.2.3.2, 1.2.3.3, 1.2.3.4'
            ],
            403 // expected statusCode
        ];
        $dataArr[] = [ // set #8, ok 200
            [ // config
                RestServer::CORRELATIONID => RestServer::getGuid(),
                IpHandler::IPHEADER => [
                    IpHandler::EXAMINE => [
                        IpHandler::REMOTE_ADDR   => [
                            IpHandler::REQUIRED => true,
                            IpHandler::RANGE => [
                                '1.2.3.*'
                            ],
                        ],
                        IpHandler::FORWARDED_FOR => [
                            IpHandler::REQUIRED => false,
                            IpHandler::RANGE => [
                                IpHandler::ALL => [
                                    '1.2.3.10'
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [ // server
                IpHandler::REMOTE_ADDR   => '1.2.3.' . ++$ipEnd,
                IpHandler::FORWARDED_FOR => '1.2.3.2, 1.2.3.3, 1.2.3.4'
            ],
            200 // expected statusCode
        ];
        $dataArr[] = [ // set #9, ok 200
            [ // config
                RestServer::CORRELATIONID => RestServer::getGuid(),
                IpHandler::IPHEADER => [
                    IpHandler::EXAMINE => [
                        IpHandler::REMOTE_ADDR   => [
                            IpHandler::REQUIRED => true,
                            IpHandler::RANGE => [
                                '1.2.3.*'
                            ],
                        ],
                        IpHandler::FORWARDED_FOR => [
                            IpHandler::REQUIRED => true,
                            IpHandler::RANGE => [
                                IpHandler::LAST => [
                                    '1.2.3.4'
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [ // server
                IpHandler::REMOTE_ADDR   => '1.2.3.' . ++$ipEnd,
                IpHandler::FORWARDED_FOR => '1.2.3.2, 1.2.3.3, 1.2.3.4'
            ],
            200 // expected statusCode
        ];
        return $dataArr;
    }

    /**
     * test validateIP, Forvarded_For
     *
     * @test
     * @dataProvider validateIP_Forvarded_For_Provider
     */
    public function testvalidateIP_Forvarded_For(
        array $config,
        array $headers,
        $statusCode
    ) {
        $request = new ServerRequest(
            [],                                          // serverParams
            [],                                          // $uploadedFiles
            null,                                     // uri
            'GET',                                // method
            StreamFactory::createStream(),               // body
            $headers                                     // headers
        );
        $request    = $request->withAttribute( RestServer::CONFIG, $config );
        $response   = new Response();

        $result     = IpHandler::validateIP(
            $request,
            $response
        );
        $this->assertEquals(
            $statusCode,
            $result[1]->getStatusCode()
        );
    }

    /**
     * validateIP_Forvarded_Provider
     */
    public function validateIP_Forvarded_Provider() {
        $dataArr   = [];
        $ipEnd     = 0;
        $range     = '';
        $range    .= 'For="_gazonk"';
        $range    .= ',For="[2001:db8:cafe::17]:4711"';
        $range    .= ', for=192.0.2.60;proto=http;by=203.0.113.43';
        $range    .= ',for=192.0.2.43, for=198.51.100.17';
        $dataArr[] = [ // set #1, ok 200
            [ // config
                RestServer::CORRELATIONID => RestServer::getGuid(),
                IpHandler::IPHEADER => [
                    IpHandler::EXAMINE => [
                        IpHandler::FORWARDED => [
                            IpHandler::RANGE => [
                                IpHandler::FIRST => [
                                    '*'
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [ // server
                IpHandler::REMOTE_ADDR   => '1.2.3.' . ++$ipEnd,
                IpHandler::FORWARDED => $range,
            ],
            200 // expected statusCode
        ];
        $dataArr[] = [ // set #2, ok 200
            [ // config
                RestServer::CORRELATIONID => RestServer::getGuid(),
                IpHandler::IPHEADER => [
                    IpHandler::EXAMINE => [
                        IpHandler::FORWARDED => [
                            IpHandler::RANGE => [
                                IpHandler::FIRST => [
                                    '2001:db8:cafe::17'
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [ // server
                IpHandler::REMOTE_ADDR   => '1.2.3.' . ++$ipEnd,
                IpHandler::FORWARDED => $range,
            ],
            200 // expected statusCode
        ];
        $dataArr[] = [ // set #3, nok 403
            [ // config
                RestServer::CORRELATIONID => RestServer::getGuid(),
                IpHandler::IPHEADER => [
                    IpHandler::EXAMINE => [
                        IpHandler::FORWARDED => [
                            IpHandler::RANGE => [
                                IpHandler::FIRST => [
                                    '192.0.2.60'
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [ // server
                IpHandler::REMOTE_ADDR   => '1.2.3.' . ++$ipEnd,
                IpHandler::FORWARDED_FOR => $range,
            ],
            403 // expected statusCode
        ];
        $dataArr[] = [ // set #4, ok 200
            [ // config
                RestServer::CORRELATIONID => RestServer::getGuid(),
                IpHandler::IPHEADER => [
                    IpHandler::EXAMINE => [
                        IpHandler::FORWARDED => [
                            IpHandler::RANGE => [
                                IpHandler::LAST => [
                                    '198.51.100.17'
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [ // server
                IpHandler::REMOTE_ADDR   => '1.2.3.' . ++$ipEnd,
                IpHandler::FORWARDED => $range,
            ],
            200 // expected statusCode
        ];
        $dataArr[] = [ // set #5, nok 403
            [ // config
                RestServer::CORRELATIONID => RestServer::getGuid(),
                IpHandler::IPHEADER => [
                    IpHandler::EXAMINE => [
                        IpHandler::FORWARDED => [
                            IpHandler::RANGE => [
                                IpHandler::LAST => [
                                '192.0.2.43'
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [ // server
                IpHandler::REMOTE_ADDR   => '1.2.3.' . ++$ipEnd,
                IpHandler::FORWARDED => $range,
            ],
            403 // expected statusCode
        ];
        $dataArr[] = [ // set #5, ok 200
            [ // config
                RestServer::CORRELATIONID => RestServer::getGuid(),
                IpHandler::IPHEADER => [
                    IpHandler::EXAMINE => [
                        IpHandler::FORWARDED => [
                            IpHandler::RANGE => [
                                IpHandler::ALL => [
                                    '203.0.113.43'
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [ // server
                IpHandler::REMOTE_ADDR   => '1.2.3.' . ++$ipEnd,
                IpHandler::FORWARDED => $range,
            ],
            200 // expected statusCode
        ];
        $dataArr[] = [ // set #6, nok 403
            [ // config
                RestServer::CORRELATIONID => RestServer::getGuid(),
                IpHandler::IPHEADER => [
                    IpHandler::EXAMINE => [
                        IpHandler::FORWARDED => [
                            IpHandler::RANGE => [
                                IpHandler::ALL => [
                                '1.2.3.10'
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [ // server
                IpHandler::REMOTE_ADDR   => '1.2.3.' . ++$ipEnd,
                IpHandler::FORWARDED => $range,
            ],
            403 // expected statusCode
        ];
        return $dataArr;
    }

    /**
     * test validateIP, Forvarded
     *
     * @test
     * @dataProvider validateIP_Forvarded_Provider
     */
    public function testvalidateIP_Forvarded(
        array $config,
        array $headers,
        $statusCode
    ) {
        $request = new ServerRequest(
            [],                                          // serverParams
            [],                                          // $uploadedFiles
            null,                                     // uri
            'GET',                                // method
            StreamFactory::createStream(),               // body
            $headers                                     // headers
        );
        $request    = $request->withAttribute( RestServer::CONFIG, $config );
        $response   = new Response();

        $result     = IpHandler::validateIP(
            $request,
            $response
        );
        $this->assertEquals(
            $statusCode,
            $result[1]->getStatusCode()
        );
    }

}
