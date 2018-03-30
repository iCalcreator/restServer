<?php
/**
 * restServer, a PSR HTTP Message rest server implementation
 *
 * This file is a part of restServer.
 *
 * Copyright 2018 Kjell-Inge Gustafsson, kigkonsult, All rights reserved
 * Link      http://kigkonsult.se/restServer/index.php
 * Version   0.8.0
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

namespace Kigkonsult\RestServer;

use PHPUnit\Framework\TestCase;          // PHPUnit > 6.1.0
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

class ServiceClass
{
    /**
     * test value, counter and key
     */
    public $value1 = null;

    public $value2 = null;

    public $counter = 0;

    private static $key = 'key';

    /**
     * @param ServerRequestInterface $request
     * @param ResponseInterface      $response
     * @return array [ ServerRequestInterface, ResponseInterface ]
     */
    public function templateHandler(
        ServerRequestInterface $request,
        ResponseInterface      $response
    ) {
        $this->counter += 1;
        if ( empty( $this->value1 )) {
            $this->value1 = \serialize( $request->getParsedBody());
        }

        $request = $request->withAttribute( self::$key,$this->value1 . $this->counter );

        return [
            $request,
            $response,
        ];
    }

    const METHOD = 'POST';

    const ROUTE = '/';

    const CALLBACK = 'templateService';

    /**
     * Template Route callback
     *
     * @param callable $callback
     */
    public function registerAsRestService(
        $callback
    ) {
        $callback(
            self::METHOD,
            self::ROUTE,
            [
                $this,
                self::CALLBACK
            ]
        );
    }

    /**
     * Template Route callback
     *
     * (opt. derived param(s) from route uri)
     * @param ServerRequestInterface $request      // always last
     * @param ResponseInterface      $response     // always last
     * @return ResponseInterface
     */
    public function templateService(
        ServerRequestInterface $request,
        ResponseInterface      $response
    ) {
        $this->value2 = $request->getAttribute( self::$key );

        return $response->withRawBody( $this->value2 );
    }
}

class RestServerTest extends TestCase
{
    /**
     * testRestServer1a provider
     */
    public function RestServer1aProvider()
    {
        $ServiceClass = new ServiceClass();
        $dataArr      = [];
        $data         = ['param' => 'test'];
        $replyVal     = \serialize( $data );
        $dataArr[]    = [
            $ServiceClass,
            [  // config
                RestServer::DEBUG    => true,
                RestServer::BASEURI  => 'index.php',
                RestServer::HANDLERS => [
                    [  // handler 1
                        $ServiceClass,
                        'templateHandler'
                    ],
                    [  // handler 2
                        $ServiceClass,
                        'templateHandler'
                    ],
                ],
                RestServer::SERVICES => [
                    [  // service definition 1
                        RestServer::METHOD   => 'GET',
                        RestServer::URI      => '/user',
                        RestServer::CALLBACK => [
                            $ServiceClass,
                            'templateService'
                        ],
                    ],
                    [  // service definition 2, detached, no test on this
                        RestServer::METHOD   => 'GET',
                        RestServer::URI      => '/user/{id:\w+}/dummy',
                        RestServer::CALLBACK => [
                            $ServiceClass,
                            'templateService'
                        ],
                    ],
                ],
            ],
            [  // server
                'REQUEST_URI'          => 'http://anyHost.com/index.php/user',
                'SERVER_NAME'          => 'anyHost.com',
                'REQUEST_METHOD'       => 'GET',
                'HTTP_ACCEPT'          => 'text/plain',
                'HTTP_ACCEPT_ENCODING' => 'identity',
            ],
            null,       // query
            $data,      // (array) body
            null,       // cookies
            null,       // files
            $replyVal,
        ];
        $ServiceClass = new ServiceClass();
        $data         = [
            'param' => 'test'
        ];
        $replyVal     = \serialize( $data + [
            'id' => 'testUser'
            ]
        );
        $dataArr[]    = [
            $ServiceClass,
            [  // config
                RestServer::DEBUG    => true,
                RestServer::BASEURI  => 'index.php',
                RestServer::HANDLERS => [
                    [  // handler 1
                        $ServiceClass,
                        'templateHandler'
                    ],
                    [  // handler 2
                        $ServiceClass,
                        'templateHandler'
                    ],
                ],
                RestServer::SERVICES => [
                    [  // service definition 1
                        RestServer::METHOD   => 'GET',
                        RestServer::URI      => '/user[/{id:\w+}]',
                        RestServer::CALLBACK => [
                            $ServiceClass,
                            'templateService'
                        ],
                    ],
                    [  // service definition 2, detached, no test on this
                        RestServer::METHOD   => 'GET',
                        RestServer::URI      => '/user/{id:\w+}/dummy',
                        RestServer::CALLBACK => [$ServiceClass, 'templateService'],
                    ],
                ],
            ],
            [  // server
                'REQUEST_URI'          => 'http://anyHost.com/index.php/user/testUser',
                'SERVER_NAME'          => 'anyHost.com',
                'REQUEST_METHOD'       => 'GET',
                'HTTP_ACCEPT'          => 'text/plain',
                'HTTP_ACCEPT_ENCODING' => 'identity',
            ],
            null,       // query
            $data,      // (array) body
            null,       // cookies
            null,       // files
            \serialize( $data ),
        ];
        $ServiceClass = new ServiceClass();
        $data         = [
            'param' => 'test'
        ];
        $replyVal     = \serialize( $data + [
            'id' => 'testUser'
            ]
        );
        $dataArr[]    = [
            $ServiceClass,
            [  // config
                RestServer::DEBUG    => true,
                RestServer::BASEURI  => 'index.php',
                RestServer::HANDLERS => [
                    [  // handler 1
                        $ServiceClass,
                        'templateHandler'
                    ],
                    [  // handler 2
                        $ServiceClass,
                        'templateHandler'
                    ],
                ],
                RestServer::SERVICES => [
                    [  // service definition 1
                        RestServer::METHOD   => 'GET',
                        RestServer::URI      => '/user[/{id:\w+}]',
                        RestServer::CALLBACK => [
                            $ServiceClass,
                            'templateService'
                        ],
                    ],
                    [  // service definition 2, detached, no test on this
                        RestServer::METHOD   => 'GET',
                        RestServer::URI      => '/user/{id:\w+}/dummy',
                        RestServer::CALLBACK => [
                            $ServiceClass,
                            'templateService'
                        ],
                    ],
                ],
            ],
            [  // server
                'REQUEST_URI'          => 'http://anyHost.com/index.php/user?id=testUser',
                'SERVER_NAME'          => 'anyHost.com',
                'REQUEST_METHOD'       => 'GET',
                'HTTP_ACCEPT'          => 'text/plain',
                'HTTP_ACCEPT_ENCODING' => 'identity',
            ],
            null,       // query
            $data,      // (array) body
            null,       // cookies
            null,       // files
            \serialize( $data ),
        ];

        return $dataArr;
    }

    /**
     * test RestServer1a - method && route found
     * same test as RestServer1b but attach services using config
     * NO detachRestService() test here,
     *
     * @test
     * @dataProvider RestServer1aProvider
     */
    public function testRestServer1a(
        ServiceClass $ServiceClass,
               array $config,
               array $server = null,
               array $query = null,
                     $body = null,
               array $cookies = null,
               array $files = null,
                     $expected = null
    ) {
        $cntHandlers      = \count( $config[RestServer::HANDLERS] );
        $RestServer = new RestServer( $config, $server, $query, $body, $cookies, $files );
        if( LOG )
            $RestServer->setLogger( new RestServerLogger());

        list( $method, $uri, $callback ) = \array_values( $config[RestServer::SERVICES][1] );
        $this->assertTrue( $RestServer->detachRestService( $method, $uri ));

        $response = $RestServer->serverCallback(
            $RestServer->server->request,
            $RestServer->server->response
        );
        $this->assertEquals( 200, $response->getStatusCode());
        $body2 = $response->getBody();
        $body2->rewind();
        $data2 = $body2->getContents();

        $this->assertEquals( $cntHandlers, $ServiceClass->counter );
        $this->assertEquals( $ServiceClass->value1 . $ServiceClass->counter, $ServiceClass->value2 );
        $this->assertEquals( $expected . $ServiceClass->counter, $data2 );
        $RestServer->__destruct();
    }

    /**
     * testRestServer1ba provider
     */
    public function RestServer1bProvider()
    {
        $ServiceClass = new ServiceClass();
        $dataArr      = [];
        $data         = ['param' => 'test'];
        $replyVal     = \serialize( $data );
        $dataArr[]    = [
            $ServiceClass,
            [  // config
                RestServer::DEBUG    => true,
                RestServer::BASEURI  => 'index.php',
                RestServer::HANDLERS => [
                    [  // handler 1
                        $ServiceClass,
                        'templateHandler'
                    ],
                    [  // handler 2
                        $ServiceClass,
                        'templateHandler'
                    ],
                ],
            ],
            [  // server
                'REQUEST_URI'          => 'http://anyHost.com/index.php/user',
                'SERVER_NAME'          => 'anyHost.com',
                'REQUEST_METHOD'       => 'GET',
                'HTTP_ACCEPT'          => 'text/plain',
                'HTTP_ACCEPT_ENCODING' => 'identity',
            ],
            null,       // query
            $data,      // (array) body
            null,       // cookies
            null,       // files
            $replyVal,
            [
                [  // service definition 1
                    RestServer::METHOD   => 'GET',
                    RestServer::URI      => '/user',
                    RestServer::CALLBACK => [
                        $ServiceClass,
                        'templateService'
                    ],
                ],
                [  // service definition 2, detached, no test on this
                    RestServer::METHOD   => 'GET',
                    RestServer::URI      => '/user/{id:\w+}/dummy',
                    RestServer::CALLBACK => [$ServiceClass, 'templateService'],
                ],
            ],
        ];
        $ServiceClass = new ServiceClass();
        $data         = [
            'param' => 'test'
        ];
        $replyVal     = \serialize( $data + [
            'id' => 'testUser'
            ]
        );
        $dataArr[]    = [
            $ServiceClass,
            [  // config
                RestServer::DEBUG    => true,
                RestServer::BASEURI  => 'index.php',
                RestServer::HANDLERS => [
                    [  // handler 1
                        $ServiceClass,
                        'templateHandler'
                    ],
                    [  // handler 2
                        $ServiceClass,
                        'templateHandler'
                    ],
                ],
            ],
            [  // server
                'REQUEST_URI'          => 'http://anyHost.com/index.php/user/testUser',
                'SERVER_NAME'          => 'anyHost.com',
                'REQUEST_METHOD'       => 'GET',
                'HTTP_ACCEPT'          => 'text/plain',
                'HTTP_ACCEPT_ENCODING' => 'identity',
            ],
            null,       // query
            $data,      // (array) body
            null,       // cookies
            null,       // files
            \serialize( $data ),
            [
                [  // service definition 1
                    RestServer::METHOD   => 'GET',
                    RestServer::URI      => '/user[/{id:\w+}]',
                    RestServer::CALLBACK => [
                        $ServiceClass, 'templateService'
                    ],
                ],
                [  // service definition 2, detached, no test on this
                    RestServer::METHOD   => 'GET',
                    RestServer::URI      => '/user/{id:\w+}/dummy',
                    RestServer::CALLBACK => [
                        $ServiceClass,
                        'templateService'
                    ],
                ],
            ],
        ];
        $ServiceClass = new ServiceClass();
        $data         = [
            'param' => 'test'
        ];
        $replyVal     = \serialize( $data + [
            'id' => 'testUser'
            ]
        );
        $dataArr[]    = [
            $ServiceClass,
            [  // config
                RestServer::DEBUG    => true,
                RestServer::BASEURI  => 'index.php',
                RestServer::HANDLERS => [
                    [
                        $ServiceClass,
                        'templateHandler'
                    ], // handler 1
                    [
                        $ServiceClass,
                        'templateHandler'
                    ],  // handler 2
                ],
            ],
            [  // server
                'REQUEST_URI'          => 'http://anyHost.com/index.php/user?id=testUser',
                'SERVER_NAME'          => 'anyHost.com',
                'REQUEST_METHOD'       => 'GET',
                'HTTP_ACCEPT'          => 'text/plain',
                'HTTP_ACCEPT_ENCODING' => 'identity',
            ],
            null,       // query
            $data,      // (array) body
            null,       // cookies
            null,       // files
            \serialize( $data ),
            [
                [  // service definition 1
                    RestServer::METHOD   => 'GET',
                    RestServer::URI      => '/user[/{id:\w+}]',
                    RestServer::CALLBACK => [
                        $ServiceClass,
                        'templateService'
                    ],
                ],
                [  // service definition 2, detached, no test on this
                    RestServer::METHOD   => 'GET',
                    RestServer::URI      => '/user/{id:\w+}/dummy',
                    RestServer::CALLBACK => [
                        $ServiceClass,
                        'templateService'
                    ],
                ],
            ],
        ];

        return $dataArr;
    }

    /**
     * test RestServer1b - method && route found
     * same test as RestServer1a but attach services using attachRestService
     * Includes detachRestService() test here
     *
     * @test
     * @dataProvider RestServer1bProvider
     */
    public function testRestServer1b(
        ServiceClass $ServiceClass,
               array $config,
               array $server = null,
               array $query = null,
                     $body = null,
               array $cookies = null,
               array $files = null,
                     $expected = null,
               array $services
    ) {
        $cntHandlers      = \count( $config[RestServer::HANDLERS] );

        $RestServer = new RestServer( null, $server, $query, $body, $cookies, $files );
        $RestServer->setConfig( $config );
        if( LOG )
            $RestServer->setLogger( new RestServerLogger());

        foreach ( $services as $x => $service ) {
            if ( empty( $x )) {
                list( $method, $uri, $callback ) = \array_values( $service );
                $RestServer->attachRestService( $method, $uri, $callback );
            } else {
                $RestServer->attachRestService( $service );
            }
        }

        list( $method, $uri, $callback ) = \array_values( $services[1] );
        $RestServer->detachRestService( $method, $uri );

        $response = $RestServer->serverCallback(
            $RestServer->server->request,
            $RestServer->server->response
        );
        $this->assertEquals( 200, $response->getStatusCode());
        $body2 = $response->getBody();
        $body2->rewind();
        $data2 = $body2->getContents();

        $this->assertEquals( $cntHandlers, $ServiceClass->counter );
        $this->assertEquals( $ServiceClass->value1 . $ServiceClass->counter, $ServiceClass->value2 );
        $this->assertEquals( $expected . $ServiceClass->counter, $data2 );
        $RestServer->__destruct();
    }

    /**
     * testRestServer2 provider
     */
    public function RestServer2Provider()
    {
        $ServiceClass = new ServiceClass();
        $dataArr      = [];
        $data         = ['test'];
        $dataArr[]    = [
            $ServiceClass,
            [  // config
                RestServer::DEBUG    => true,
                RestServer::HANDLERS => [
                    [  // handler 1
                        $ServiceClass,
                        'templateHandler'
                    ],
                    [  // handler 2
                        $ServiceClass,
                        'templateHandler'
                    ],
                ],
                RestServer::SERVICES => [
                    [  // service definition 1
                        RestServer::METHOD   => 'GET',
                        RestServer::URI      => '/user',
                        RestServer::CALLBACK => [
                            $ServiceClass,
                            'templateService'
                        ],
                    ],
                ],
            ],
            [  // server
               'REQUEST_URI'          => 'http://anyHost.com/address', // no callback here
               'SERVER_NAME'          => 'anyHost.com',
               'REQUEST_METHOD'       => 'GET',
               'HTTP_ACCEPT'          => 'text/plain',
               'HTTP_ACCEPT_ENCODING' => 'identity',
            ],
            null,       // query
            $data,      // (array) body
            null,       // cookies
            null,       // files
        ];

        return $dataArr;
    }

    /**
     * test RestServer2 - method found && route NOT found (i.e. FastRoute\BadRouteException)
     *
     * @test
     * @dataProvider RestServer2Provider
     */
    public function testRestServer2(
        ServiceClass $ServiceClass,
               array $config,
               array $server = null,
               array $query = null,
                     $body = null,
               array $cookies = null,
               array $files = null
    ) {
        $cntHandlers      = \count( $config[RestServer::HANDLERS] );

        $RestServer = new RestServer( null, $server, $query, $body, $cookies, $files );
        $RestServer->setConfig( $config );
        if( LOG )
            $RestServer->setLogger( new RestServerLogger());

        $response = $RestServer->serverCallback(
            $RestServer->server->request,
            $RestServer->server->response
        );

        $this->assertEquals( 404, $response->getStatusCode());
        $RestServer->__destruct();
    }

    /**
     * testRestServer3 provider
     */
    public function RestServer3Provider()
    {
        $ServiceClass = new ServiceClass();
        $dataArr      = [];
        $data         = ['test'];
        $dataArr[]    = [
            $ServiceClass,
            [  // handler 1
               $ServiceClass,
               'templateHandler'
            ],
            [  // config
                RestServer::DEBUG    => true,
                RestServer::HANDLERS => [
                    [ // handler 2
                        $ServiceClass,
                        'templateHandler'
                    ],
                    [  // handler 3
                        $ServiceClass,
                        'templateHandler'
                    ],
                ],
                RestServer::SERVICES => [
                    [  // service definition 1
                        RestServer::METHOD   => 'GET',
                        RestServer::URI      => '/user',
                        RestServer::CALLBACK => [
                            $ServiceClass,
                            'templateService'
                        ],
                    ],
                ],
            ],
            [  // server
                'REQUEST_URI'          => 'http://anyHost.com/user',
                'SERVER_NAME'          => 'anyHost.com',
                'REQUEST_METHOD'       => 'POST',    // no callback here
                'HTTP_ACCEPT'          => 'text/plain',
                'HTTP_ACCEPT_ENCODING' => 'identity',
            ],
            null,       // query
            $data,      // (array) body
            null,       // cookies
            null,       // files
        ];

        return $dataArr;
    }

    /**
     * test RestServer3 - method NOT found && route NOT found (i.e. FastRouteException)
     *
     * @test
     * @dataProvider RestServer3Provider
     */
    public function testRestServer3(
        ServiceClass $ServiceClass,
               array $handler,
               array $config,
               array $server = null,
               array $query = null,
                     $body = null,
               array $cookies = null,
               array $files = null
    ) {
        $cntHandlers      = \count( $config[RestServer::HANDLERS] );

        $RestServer = new RestServer( null, $server, $query, $body, $cookies, $files );
        $RestServer->setConfig( $config );
        if( LOG )
            $RestServer->setLogger( new RestServerLogger());
        $RestServer->addHandler( $handler );

        $response = $RestServer->serverCallback(
            $RestServer->server->request,
            $RestServer->server->response
        );

        $this->assertEquals( 405, $response->getStatusCode());
        $RestServer->__destruct();
    }

    /**
     * testRestServer4 provider
     */
    public function RestServer4Provider()
    {
        $ServiceClass = new ServiceClass();
        $dataArr      = [];
        $data         = [
            'param' => 'test'
        ];
        $dataArr[]    = [
            $ServiceClass,
            [  // config
                RestServer::DEBUG    => true,
                RestServer::BASEURI  => 'index.php',
                RestServer::HANDLERS => [
                    [ // handler 1
                        $ServiceClass,
                        'templateHandler'
                    ],
                    [ // handler 2
                        $ServiceClass,
                        'templateHandler'
                    ],
                ],
                RestServer::SERVICES => [
                    [  // service definition 1
                        RestServer::METHOD   => 'GET',
                        RestServer::URI      => '/user',
                        RestServer::CALLBACK => [
                            $ServiceClass,
                            'templateService'
                        ],
                    ],
                ],
            ],
            [  // server
                'REQUEST_URI'          => 'http://anyHost.com/index.php/user',
                'SERVER_NAME'          => 'anyHost.com',
                'REQUEST_METHOD'       => 'OPTIONS',
                'HTTP_ACCEPT'          => 'text/plain',
                'HTTP_ACCEPT_ENCODING' => 'identity',
            ],
            null,       // query
            $data,      // (array) body
            null,       // cookies
            null,       // files
            [           // expected
               'GET',
               'HEAD',
               'OPTIONS',
            ],
        ];

        return $dataArr;
    }

    /**
     * test RestServer4 - OPTIONS (no preflight), check response body
     * Could (or should) be placed in RequestMethodHandlerTest...
     * Preflight tests placed in CorsHandlerTest
     *
     * @test
     * @dataProvider RestServer4Provider
     */
    public function testRestServer4(
        ServiceClass $ServiceClass,
               array $config,
               array $server = null,
               array $query = null,
                     $body = null,
               array $cookies = null,
               array $files = null,
                     $expected = null
    ) {
        $cntHandlers      = \count( $config[RestServer::HANDLERS] );

        $RestServer = new RestServer( null, $server, $query, $body, $cookies, $files );
        $RestServer->setConfig( $config );
        if( LOG )
            $RestServer->setLogger( new RestServerLogger());

        $ServiceClass->registerAsRestService( $RestServer->getAttachRestServiceCallback());
        $this->assertTrue( $RestServer->detachRestService( ServiceClass::METHOD, ServiceClass::ROUTE ));

        $response = $RestServer->serverCallback(
            $RestServer->server->request,
            $RestServer->server->response
        );

        $this->assertEquals( 200, $response->getStatusCode());

        $headerMethods = \explode( ',', $response->getHeader( 'Allow' )[0] );
        foreach ( $headerMethods as &$method ) {
            $method  = \trim( $method );
        }
        foreach ( $headerMethods as $method ) {
            $this->assertTrue( \in_array( $method, $expected ));
        }

        $body1       = $response->getBody();
        $body1->rewind();
        $body2       = $body1->getContents();
        $body3       = (array) @\json_decode( $body2, true );
        foreach ( \array_keys( $body3 ) as $method ) {
            $this->assertTrue( \in_array( $method, $expected ));
        }
        $this->assertSame( $body3['GET'], $body3['HEAD'] );
        $RestServer->__destruct();
    }
}
