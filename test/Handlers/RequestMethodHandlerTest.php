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
use Zend\Diactoros\ServerRequest;
use Kigkonsult\RestServer\Response;
use Kigkonsult\RestServer\RestServer;

/**
 * class RequestMethodHandlerTest
 *
 * @author      Kjell-Inge Gustafsson <ical@kigkonsult.se>
 */
class RequestMethodHandlerTest extends TestCase
{
    /**
     * testgetRequestMethod1 provider
     */
    public function getRequestMethod1Provider()
    {
        $dataArr   = [];
        $dataArr[] = [
            [
                RequestMethodHandler::REQUESTMETHOD => RequestMethodHandler::METHOD_GET,
            ],
            RequestMethodHandler::METHOD_GET,
        ];
        $dataArr[] = [
            [
                RequestMethodHandler::REQUESTMETHOD => RequestMethodHandler::METHOD_POST,
            ],
            RequestMethodHandler::METHOD_POST,
        ];
        $dataArr[] = [
            [
                RequestMethodHandler::REQUESTMETHOD => RequestMethodHandler::METHOD_POST,
                'X-HTTP-METHOD-OVERRIDE'            => RequestMethodHandler::METHOD_PUT,
            ],
            RequestMethodHandler::METHOD_PUT,
        ];
        $dataArr[] = [
            [
                RequestMethodHandler::REQUESTMETHOD => RequestMethodHandler::METHOD_GET,
                'X-HTTP-METHOD-OVERRIDE'            => RequestMethodHandler::METHOD_PUT,
            ],
            RequestMethodHandler::METHOD_GET,
        ];

        return $dataArr;
    }

    /**
     * test getRequestMethod
     *
     * @test
     * @dataProvider getRequestMethod1Provider
     */
    public function testgetRequestMethod1(
        array $server,
              $expected
    ) {
        $this->assertEquals(
            $expected,
            RequestMethodHandler::getRequestMethod( $server )
        );
    }

    /**
     * testvalidateRequestMethod1 provider
     */
    public function validateRequestMethod1Provider()
    {
        $dataArr   = [];
        $dataArr[] = [    // set #1
            [  // $config
                'test set'           => ' number 1',
                RestServer::DISALLOW => [],
            ],
               // request method
            RequestMethodHandler::METHOD_GET,
            [  // $methodUri
                RequestMethodHandler::METHOD_GET => ['/'],
            ],
               // $expected, no error
            false,
        ];
        $dataArr[] = [    // set #2, allow HEAD if GET exists
            [  // $config
                'test set'           => ' number 2',
                RestServer::DISALLOW => [],
            ],
               // request method
            RequestMethodHandler::METHOD_HEAD,
            [  // $methodUri
                RequestMethodHandler::METHOD_GET => ['/'],
            ],
               // $expected, no error
            false,
        ];
        $dataArr[] = [    // set #3
            [  // $config
                'test set'           => ' number 3',
                RestServer::DISALLOW => [RequestMethodHandler::METHOD_HEAD],
            ],
               // request method
            RequestMethodHandler::METHOD_HEAD,
            [  // $methodUri
                RequestMethodHandler::METHOD_GET => ['/'],
            ],
               // $expected, error
            true,
        ];
        $dataArr[] = [    // set #4
            [  // $config
                'test set' => ' number 4',
            ],
               // request method
            'WRONG',
            [  // $methodUri
                RequestMethodHandler::METHOD_GET => ['/'],
            ],
               // $expected, error
            true,
        ];

        return $dataArr;
    }

    /**
     * test validateRequestMethod
     *
     * @test
     * @dataProvider validateRequestMethod1Provider
     */
    public function testvalidateRequestMethod1(
        array $config,
              $method,
        array $methodUri,
              $expected
    ) {
        $request = new ServerRequest(
            [],           // serverParams
            [],           // $uploadedFiles
           null,      // uri
            $method
        );
        $request = $request->withAttribute( RestServer::CONFIG, $config )
                           ->withAttribute( RestServer::REQUESTMETHODURI, $methodUri );
        $response = new Response();

        list( $request, $response ) = RequestMethodHandler::validateRequestMethod( $request, $response );

        $this->assertEquals( $expected, $request->getAttribute( RestServer::ERROR, false ));
    }

    /**
     * test setStatusMethodNotAllowed & setResponseHeaderAllow
     *
     * @test
     */
    public function testsetStatusMethodNotAllowed1()
    {
        $allowedMethods = [RequestMethodHandler::METHOD_GET, RequestMethodHandler::METHOD_POST];
        $response       = RequestMethodHandler::setStatusMethodNotAllowed( new Response(), $allowedMethods );

        $this->assertEquals( 405, $response->getStatusCode());

        $this->assertTrue( $response->hasHeader( RestServer::ALLOW ));
        $headersValue = $response->getHeader( RestServer::ALLOW )[0];
        $this->assertEquals( \implode( ', ', $allowedMethods ), $headersValue );
    }
}
