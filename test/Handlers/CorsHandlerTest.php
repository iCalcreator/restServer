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

namespace Kigkonsult\RestServer\Handlers;

// use PHPUnit_Framework_TestCase as TestCase; // PHPUnit < 6.1.0
use PHPUnit\Framework\TestCase;          // PHPUnit > 6.1.0
use Zend\Diactoros\ServerRequest;
use Kigkonsult\RestServer\Response;
use Kigkonsult\RestServer\RestServer;
use Kigkonsult\RestServer\StreamFactory;

class CorsHandlerTest extends TestCase
{
    /**
     * testvalidateCors1 provider
     */
    public function validateCors1Provider()
    {
        $dataArr   = [];
        $dataArr[] = [
            [],
            [],
        ];
        $dataArr[] = [
            [CorsHandler::ORIGIN => 'test.com'],
            [],
        ];
        $dataArr[] = [
            [CorsHandler::ORIGIN => 'test.com'],
            [CorsHandler::CORS => [RestServer::IGNORE => true]],
        ];

        return $dataArr;
    }

    /**
     * test validateCors, no cors mgnt  (not exists and found+ignore)
     *
     * @test
     * @dataProvider validateCors1Provider
     */
    public function testvalidateCors1(
        array $headers,
        array $config
    ) {
        $request = new ServerRequest(
            [],                         // serverParams
            [],                         // $uploadedFiles
           null,                    // uri
           null,                 // method
            StreamFactory::createStream(), // body
            $headers                    // headers
                                    );
        $request                    = $request->withAttribute( RestServer::CONFIG, $config );
        list( $request, $response ) = CorsHandler::validateCors(
            $request,
            new Response()
        );
        $this->assertFalse( $response->hasHeader( CorsHandler::ACCESSCONTROLALLOWORIGIN ));
    }

    /**
     * testvalidateCors2 provider
     */
    public function validateCors2Provider()
    {
        $dataArr   = [];
        $dataArr[] = [
            [],
            [
                CorsHandler::CORS => [
                    RestServer::ALLOW => [
                        'test.com'
                    ]
                ]
            ],
        ];
        $dataArr[] = [
            [],
            [
                CorsHandler::CORS => [
                    RestServer::ALLOW       => ['test.com'],
                    CorsHandler::ERRORCODE1 => 418,
                ],
            ],
        ];

        return $dataArr;
    }

    /**
     * test validateCors, require origin header but not found, ERRORCODE1
     *
     * @test
     * @dataProvider validateCors2Provider
     */
    public function testvalidateCors2(
        array $headers,
        array $config
    ) {
        $request = new ServerRequest(
            [],                          // serverParams
            [],                          // $uploadedFiles
           null,                     // uri
           null,                  // method
            StreamFactory::createStream(),  // body
            $headers                     // headers
        );
        $request                    = $request->withAttribute( RestServer::CONFIG, $config );
        list( $request, $response ) = CorsHandler::validateCors(
            $request,
            new Response()
        );
        $this->assertTrue( $request->getAttribute( RestServer::ERROR ));
        $statusCode = ( isset( $config[CorsHandler::CORS][CorsHandler::ERRORCODE1] ))
                             ? $config[CorsHandler::CORS][CorsHandler::ERRORCODE1]
                             : 400;
        $this->assertEquals( $statusCode, $response->getStatusCode());
    }

    /**
     * testvalidateCors3a provider
     */
    public function validateCors3aProvider()
    {
        $dataArr[] = [
            [
                CorsHandler::ORIGIN => 'wrong.com'
            ],
            [
                CorsHandler::CORS => [
                    RestServer::IGNORE      => false,
                    CorsHandler::ERRORCODE2 => 418,
                ],
            ],
        ];

        return $dataArr;
    }

    /**
     * test validateCors, not require origin header and found and not ignore, ERRORCODE2
     *
     * @test
     * @dataProvider validateCors3aProvider
     */
    public function testvalidateCors3a(
        array $headers,
        array $config
    ) {
        $request = new ServerRequest(
            [],                         // serverParams
            [],                         // $uploadedFiles
            null,                    // uri
            null,                 // method
            StreamFactory::createStream(), // body
            $headers                    // headers
        );
        $request                    = $request->withAttribute( RestServer::CONFIG, $config );
        $request                    = $request->withAttribute( RestServer::REQUESTMETHODURI, [RequestMethodHandler::METHOD_GET => ['/']]);
        $request                    = $request->withMethod( RequestMethodHandler::METHOD_GET );
        list( $request, $response ) = CorsHandler::validateCors(
            $request,
            new Response()
        );
        $this->assertTrue( $request->getAttribute( RestServer::ERROR ));
        $statusCode = ( isset( $config[CorsHandler::CORS][CorsHandler::ERRORCODE2] ))
            ? $config[CorsHandler::CORS][CorsHandler::ERRORCODE2]
            : 433;
        $this->assertEquals( $statusCode, $response->getStatusCode());
    }

    /**
     * testvalidateCors3b provider
     */
    public function validateCors3bProvider()
    {
        $dataArr   = [];
        $dataArr[] = [
            [CorsHandler::ORIGIN => 'wrong.com'],
            [CorsHandler::CORS => [RestServer::ALLOW => ['test.com']]],
        ];
        $dataArr[] = [
            [CorsHandler::ORIGIN => 'wrong.com'],
            [
                CorsHandler::CORS => [
                    RestServer::ALLOW       => ['test.com'],
                    CorsHandler::ERRORCODE2 => 418,
                ],
            ],
        ];

        return $dataArr;
    }

    /**
     * test validateCors, require origin header and no match, ERRORCODE2
     *
     * @test
     * @dataProvider validateCors3bProvider
     */
    public function testvalidateCors3b(
        array $headers,
        array $config
    ) {
        $request = new ServerRequest(
            [],                         // serverParams
            [],                         // $uploadedFiles
            null,                    // uri
            null,                 // method
            StreamFactory::createStream(), // body
            $headers                    // headers
        );
        $request                    = $request->withAttribute( RestServer::CONFIG, $config );
        $request                    = $request->withAttribute( RestServer::REQUESTMETHODURI, [RequestMethodHandler::METHOD_GET => ['/']]);
        $request                    = $request->withMethod( RequestMethodHandler::METHOD_GET );
        list( $request, $response ) = CorsHandler::validateCors(
            $request,
            new Response()
        );
        $this->assertTrue( $request->getAttribute( RestServer::ERROR ));
        $statusCode = ( isset( $config[CorsHandler::CORS][CorsHandler::ERRORCODE2] ))
            ? $config[CorsHandler::CORS][CorsHandler::ERRORCODE2]
            : 403;
        $this->assertEquals( $statusCode, $response->getStatusCode());
    }

    /**
     * testvalidateCors4 provider
     */
    public function validateCors4Provider()
    {
        $dataArr   = [];
        $dataArr[] = [
            [CorsHandler::ORIGIN => 'test.com'],
            [CorsHandler::CORS => [
                    RestServer::ALLOW => ['test.com'],
                ],
            ],
        ];
        $dataArr[] = [
            [CorsHandler::ORIGIN => 'test.com'],
            [
                CorsHandler::CORS => [
                    RestServer::ALLOW                          => ['test.com'],
                    CorsHandler::ACCESSCONTROLALLOWCREDENTIALS => false,
                ],
            ],
        ];
        $dataArr[] = [
            [CorsHandler::ORIGIN => 'test.com'],
            [
                CorsHandler::CORS => [
                    RestServer::ALLOW                          => ['test.com'],
                    CorsHandler::ACCESSCONTROLALLOWCREDENTIALS => true,
                ],
            ],
        ];
        $dataArr[] = [
            [CorsHandler::ORIGIN => 'test.com'],
            [
                CorsHandler::CORS => [
                    RestServer::ALLOW                          => ['test.com'],
                    CorsHandler::ACCESSCONTROLALLOWCREDENTIALS => true,
                    CorsHandler::ACCESSCONTROLEXPOSEHEADERS    => [],
                ],
            ],
        ];
        $dataArr[] = [
            [CorsHandler::ORIGIN => 'test.com'],
            [
                CorsHandler::CORS => [
                    RestServer::ALLOW                          => ['test.com'],
                    CorsHandler::ACCESSCONTROLALLOWCREDENTIALS => true,
                    CorsHandler::ACCESSCONTROLEXPOSEHEADERS    => ['X-header-to-expose-1', 'X-header-to-expose-2'],
                ],
            ],
        ];

        return $dataArr;
    }

    /**
     * test validateCors, require origin header and it is found!
     *                    NO preflight request
     *                    With and without 'Access-Control-Allow-Credentials'
     *                    i.e. error
     *
     * @test
     * @dataProvider validateCors4Provider
     */
    public function testvalidateCors4(
        array $headers,
        array $config
    ) {
        $request = new ServerRequest(
            [],                         // serverParams
            [],                         // $uploadedFiles
           null,                    // uri
           null,                 // method
            StreamFactory::createStream(), // body
            $headers                    // headers
                                    );
        $request                    = $request->withAttribute( RestServer::CONFIG, $config );
        $request                    = $request->withAttribute( RestServer::REQUESTMETHODURI, [RequestMethodHandler::METHOD_GET => ['/']]);
        $request                    = $request->withMethod( RequestMethodHandler::METHOD_GET );
        list( $request, $response ) = CorsHandler::validateCors(
            $request,
            new Response()
        );

        $this->assertTrue( $response->hasHeader( CorsHandler::ACCESSCONTROLALLOWORIGIN ));
        $this->assertEquals(
            $headers[CorsHandler::ORIGIN],
            $response->getHeader( CorsHandler::ACCESSCONTROLALLOWORIGIN )[0]
        );
        if ( isset( $config[CorsHandler::CORS][CorsHandler::ACCESSCONTROLALLOWCREDENTIALS] ) &&
           true == $config[CorsHandler::CORS][CorsHandler::ACCESSCONTROLALLOWCREDENTIALS] ) {
            $this->assertTrue( $response->hasHeader( CorsHandler::ACCESSCONTROLALLOWCREDENTIALS ));
            $this->assertEquals(
                'true',
                $response->getHeader( CorsHandler::ACCESSCONTROLALLOWCREDENTIALS )[0]
            );
        }
        if ( isset( $config[CorsHandler::CORS][CorsHandler::ACCESSCONTROLEXPOSEHEADERS] ) &&
          ! empty( $config[CorsHandler::CORS][CorsHandler::ACCESSCONTROLEXPOSEHEADERS] )) {
            $this->assertTrue( $response->hasHeader( CorsHandler::ACCESSCONTROLEXPOSEHEADERS ));
            $this->assertEquals(
                $config[CorsHandler::CORS][CorsHandler::ACCESSCONTROLEXPOSEHEADERS],
                \explode( ', ', $response->getHeader( CorsHandler::ACCESSCONTROLEXPOSEHEADERS )[0] )
            );
        }
    }

    /**
     * testvalidateCors5 provider
     */
    public function validateCors5Provider()
    {
        $dataArr   = [];
        $dataArr[] = [
            [
                CorsHandler::ORIGIN                     => 'test.com',
                CorsHandler::ACCESSCONTROLREQUESTMETHOD => RequestMethodHandler::METHOD_POST,
            ],
            [
                CorsHandler::CORS => [
                    RestServer::ALLOW => ['test.com'],
                ],
            ],
        ];
        $dataArr[] = [
            [
                CorsHandler::ORIGIN                     => 'test.com',
                CorsHandler::ACCESSCONTROLREQUESTMETHOD => RequestMethodHandler::METHOD_POST,
            ],
            [
                CorsHandler::CORS => [
                    RestServer::ALLOW       => ['test.com'],
                    CorsHandler::ERRORCODE3 => 418,
                ],
            ],
        ];

        return $dataArr;
    }

    /**
     * test validateCors, OPTIONS preflight request but wrong Access-Control-Request-Method
     *                            i.e. ERRORCODE3
     *
     * @test
     * @dataProvider validateCors5Provider
     */
    public function testvalidateCors5(
        array $headers,
        array $config
    ) {
        $request = new ServerRequest(
            [],                                          // serverParams
            [],                                          // $uploadedFiles
           null,                                      // uri
           RequestMethodHandler::METHOD_OPTIONS,  // method
            StreamFactory::createStream(),                  // body
            $headers                                     // headers
        );
        $request                    = $request->withAttribute( RestServer::CONFIG, $config );
        $request                    = $request->withAttribute( RestServer::REQUESTMETHODURI, [RequestMethodHandler::METHOD_GET => ['/']]);
        $request                    = $request->withMethod( RequestMethodHandler::METHOD_OPTIONS );
        list( $request, $response ) = CorsHandler::validateCors(
            $request,
            new Response()
        );

        $this->assertTrue( $request->getAttribute( RestServer::ERROR ));
        $statusCode = ( isset( $config[CorsHandler::CORS][CorsHandler::ERRORCODE3] ))
                             ? $config[CorsHandler::CORS][CorsHandler::ERRORCODE3]
                             : 406;
        $this->assertEquals( $statusCode, $response->getStatusCode());
    }

    /**
     * testvalidateCors6 provider
     */
    public function validateCors6Provider()
    {
        $dataArr   = [];
        $dataArr[] = [
            [
                CorsHandler::ORIGIN                      => 'test.com',
                CorsHandler::ACCESSCONTROLREQUESTMETHOD  => RequestMethodHandler::METHOD_POST,
                CorsHandler::ACCESSCONTROLREQUESTHEADERS => 'X-Control-Header-1err',
            ],
            [
                CorsHandler::CORS => [
                    RestServer::ALLOW => ['test.com'],
                ],
            ],
        ];
        $dataArr[] = [
            [
                CorsHandler::ORIGIN                      => 'test.com',
                CorsHandler::ACCESSCONTROLREQUESTMETHOD  => RequestMethodHandler::METHOD_POST,
                CorsHandler::ACCESSCONTROLREQUESTHEADERS => 'X-Control-Header-2err',
            ],
            [
                CorsHandler::CORS => [
                    RestServer::ALLOW                      => ['test.com'],
                    CorsHandler::ACCESSCONTROLALLOWHEADERS => ['X-Control-Header-2'],
                    CorsHandler::ERRORCODE4                => 418,
                ],
            ],
        ];

        return $dataArr;
    }

    /**
     * test validateCors, OPTIONS preflight request and
     *                            right Access-Control-Request-Method
     *                            missing/unvalid Access-Control-Request-Headers
     *                            i.e. ERRORCODE4
     *
     * @test
     * @dataProvider validateCors6Provider
     */
    public function testvalidateCors6(
        array $headers,
        array $config
    ) {
        $request = new ServerRequest(
            [],                                         // serverParams
            [],                                         // $uploadedFiles
           null,                                     // uri
           RequestMethodHandler::METHOD_OPTIONS, // method
            StreamFactory::createStream(),                 // body
            $headers                                    // headers
        );
        $request                    = $request->withAttribute( RestServer::CONFIG, $config );
        $request                    = $request->withAttribute( RestServer::REQUESTMETHODURI, [RequestMethodHandler::METHOD_POST => ['/']]);
        $request                    = $request->withMethod( RequestMethodHandler::METHOD_OPTIONS );
        list( $request, $response ) = CorsHandler::validateCors(
            $request,
            new Response()
        );

        $this->assertTrue( $request->getAttribute( RestServer::ERROR, false ));
        $statusCode = ( isset( $config[CorsHandler::CORS][CorsHandler::ERRORCODE4] ))
                             ? $config[CorsHandler::CORS][CorsHandler::ERRORCODE4]
                             : 406;
        $this->assertEquals( $statusCode, $response->getStatusCode());
    }

    /**
     * testvalidateCors7 provider
     */
    public function validateCors7Provider()
    {
        $dataArr   = [];
        $dataArr[] = [
            [
                CorsHandler::ORIGIN                      => 'test.com',
                CorsHandler::ACCESSCONTROLREQUESTMETHOD  => RequestMethodHandler::METHOD_POST,
                CorsHandler::ACCESSCONTROLREQUESTHEADERS => 'X-Control-Header-1',
            ],
            [
                CorsHandler::CORS => [
                    RestServer::ALLOW                      => ['test.com'],
                    CorsHandler::ACCESSCONTROLALLOWHEADERS => ['X-Control-Header-1', 'X-Control-Header-2'],
                ],
            ],
        ];
        $dataArr[] = [
            [
                CorsHandler::ORIGIN                      => 'test2.com',
                CorsHandler::ACCESSCONTROLREQUESTMETHOD  => RequestMethodHandler::METHOD_POST,
                CorsHandler::ACCESSCONTROLREQUESTHEADERS => 'X-Control-Header-1',
            ],
            [
                CorsHandler::CORS => [
                    RestServer::ALLOW                          => ['test1.com', 'test2.com'],
                    CorsHandler::ACCESSCONTROLALLOWHEADERS     => ['X-Control-Header-1', 'X-Control-Header-2'],
                    CorsHandler::ACCESSCONTROLMAXAGE           => 1234,
                    CorsHandler::ACCESSCONTROLALLOWCREDENTIALS => true,
                ],
            ],
        ];

        return $dataArr;
    }

    /**
     * test validateCors, OPTIONS preflight request and
     *                            right Access-Control-Request-Method
     *                            right Access-Control-Request-Headers
     *
     * @test
     * @dataProvider validateCors7Provider
     */
    public function testvalidateCors7(
        array $headers,
        array $config
    ) {
        $request = new ServerRequest(
            [],                                          // serverParams
            [],                                          // $uploadedFiles
           null,                                     // uri
           RequestMethodHandler::METHOD_OPTIONS,  // method
            StreamFactory::createStream(),                  // body
            $headers                                     // headers
                                    );
        $request = $request->withAttribute( RestServer::CONFIG, $config );
        $request = $request->withAttribute(
            RestServer::REQUESTMETHODURI,
            [
                RequestMethodHandler::METHOD_GET  => ['/'],
                RequestMethodHandler::METHOD_POST => ['/'],
            ]
        );
        $request                    = $request->withMethod( RequestMethodHandler::METHOD_OPTIONS );
        list( $request, $response ) = CorsHandler::validateCors(
            $request,
            new Response()
        );

        $this->assertTrue( $response->hasHeader( CorsHandler::ACCESSCONTROLALLOWMETHODS ));
        $cmpMthds = [RequestMethodHandler::METHOD_GET,
                      RequestMethodHandler::METHOD_POST,
                      RequestMethodHandler::METHOD_HEAD,
                      RequestMethodHandler::METHOD_OPTIONS,
                    ];
        $this->assertEquals(
            $cmpMthds,
            \explode( ', ', $response->getHeader( CorsHandler::ACCESSCONTROLALLOWMETHODS )[0] )
        );

        $this->assertTrue( $response->hasHeader( CorsHandler::ACCESSCONTROLALLOWHEADERS ));
        $this->assertEquals(
            $config[CorsHandler::CORS][CorsHandler::ACCESSCONTROLALLOWHEADERS],
            \explode( ', ', $response->getHeader( CorsHandler::ACCESSCONTROLALLOWHEADERS )[0] )
        );

        if ( isset( $config[CorsHandler::CORS][CorsHandler::ACCESSCONTROLMAXAGE] ) &&
           true == $config[CorsHandler::CORS][CorsHandler::ACCESSCONTROLMAXAGE] ) {
            $this->assertTrue( $response->hasHeader( CorsHandler::ACCESSCONTROLMAXAGE ));
            $this->assertEquals(
                $config[CorsHandler::CORS][CorsHandler::ACCESSCONTROLMAXAGE],
                $response->getHeader( CorsHandler::ACCESSCONTROLMAXAGE )[0]
            );
        }

        $this->assertTrue( $response->hasHeader( CorsHandler::ACCESSCONTROLALLOWORIGIN ));
        $this->assertEquals(
            $headers[CorsHandler::ORIGIN],
            $response->getHeader( CorsHandler::ACCESSCONTROLALLOWORIGIN )[0]
        );

        if ( isset( $config[CorsHandler::CORS][CorsHandler::ACCESSCONTROLALLOWCREDENTIALS] ) &&
           true == $config[CorsHandler::CORS][CorsHandler::ACCESSCONTROLALLOWCREDENTIALS] ) {
            $this->assertTrue( $response->hasHeader( CorsHandler::ACCESSCONTROLALLOWCREDENTIALS ));
            $this->assertEquals(
                'true',
                $response->getHeader( CorsHandler::ACCESSCONTROLALLOWCREDENTIALS )[0]
            );
        }
    }
}
