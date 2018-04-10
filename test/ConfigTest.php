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
     * ConfigTest.php
     * @since     2018-02-09
     */

namespace Kigkonsult\RestServer;

// use PHPUnit_Framework_TestCase as TestCase; // PHPUnit < 6.1.0
use PHPUnit\Framework\TestCase;          // PHPUnit > 6.1.0
use Kigkonsult\RestServer\Handlers\RequestMethodHandler;
use Kigkonsult\RestServer\Handlers\CorsHandler;
use Kigkonsult\RestServer\Handlers\ContentTypeHandler;
use Kigkonsult\RestServer\Handlers\EncodingHandler;
use Zend\Diactoros\ServerRequest;

class ConfigTest extends TestCase
{
    /**
     * testing setting up RestServer config
     *
     * @test
     */
    public function testConfig()
    {
        $config1 = [];

        /**
         * include RestServer config
         */
        $config1[RestServer::INIT]          = \microtime( true );
        $config1[RestServer::CORRELATIONID] = strtoupper( bin2hex( openssl_random_pseudo_bytes( 16) ));
        $config1[RestServer::BASEURI]       = 'index.php';
        $config1[RestServer::DISALLOW]      = [
            RequestMethodHandler::METHOD_OPTIONS,
            RequestMethodHandler::METHOD_HEAD,
        ];
        /**
         * include CorsHandler config
         */
        $config1[CorsHandler::CORS] = [
            RestServer::IGNORE      => true,
            CorsHandler::ERRORCODE1 => 400,
            CorsHandler::ERRORCODE2 => 403,
            CorsHandler::ERRORCODE3 => 406,
            CorsHandler::ERRORCODE4 => 406,
            RestServer::ALLOW       => ['*'],
            CorsHandler::ACCESSCONTROLALLOWHEADERS     => ['x-header'],
            CorsHandler::ACCESSCONTROLMAXAGE           => 200,
            CorsHandler::ACCESSCONTROLEXPOSEHEADERS    => ['x-header'],
            CorsHandler::ACCESSCONTROLALLOWCREDENTIALS => true,
        ];

        /**
         * include ContentTypeHandler/EncodingHandler config
         */
        $config1 += include './cfg/cfg.56.cte.php';

        /**
         * set up
         */
        $request = new ServerRequest();
        $request = $request->withAttribute(RestServer::CONFIG, $config1 );
        $config2 = $request->getAttribute( RestServer::CONFIG );

        /**
         * test RequestMethodHandler config
         */
        /*
                $this->assertTrue( isset( $config2[RequestMethodHandler::REQUESTMETHOD] ));
                $this->assertTrue( isset( $config2[RequestMethodHandler::REQUESTMETHOD][RestServer::ALLOW] ));
                $this->assertEquals(      $config2[RequestMethodHandler::REQUESTMETHOD][RestServer::ALLOW], [ RequestMethodHandler::AST ] );
         */
        /**
         * test RestServer config
         */
        $this->assertTrue( isset( $config2[RestServer::INIT] ));
        $this->assertEquals( 'double', gettype( $config2[RestServer::INIT] ));
        $this->assertTrue( isset( $config2[RestServer::CORRELATIONID] ));
        $this->assertEquals( 'string', gettype( $config2[RestServer::CORRELATIONID] ));
        $this->assertTrue( isset( $config2[RestServer::BASEURI] ));
        $this->assertEquals( 'string', gettype( $config2[RestServer::BASEURI] ));
        $this->assertTrue( isset( $config2[RestServer::DISALLOW] ));
        $this->assertEquals( 'array', gettype( $config2[RestServer::DISALLOW] ));

        /**
         * test CorsHandler config
         */
        $this->assertTrue( isset( $config2[CorsHandler::CORS] ));
        $this->assertTrue( isset( $config2[CorsHandler::CORS][RestServer::ALLOW] ));
//      $this->assertEquals(      $config2[CorsHandler::CORS][RestServer::ALLOW], [ 'Kigkonsult.se' ] );
        $this->assertEquals(      $config2[CorsHandler::CORS][RestServer::ALLOW], ['*'] );

        $this->assertTrue( isset( $config2[CorsHandler::CORS][RestServer::ALLOW] ));
//      $this->assertEquals(      $config2[CorsHandler::CORS][RestServer::ALLOW], [ 'Kigkonsult.se' ] );
        $this->assertEquals(      $config2[CorsHandler::CORS][RestServer::ALLOW], ['*'] );

        $this->assertTrue( isset( $config2[CorsHandler::CORS][CorsHandler::ERRORCODE1] ));
        $this->assertEquals(      $config2[CorsHandler::CORS][CorsHandler::ERRORCODE1], 400 );

        $this->assertTrue( isset( $config2[CorsHandler::CORS][CorsHandler::ERRORCODE2] ));
        $this->assertEquals(      $config2[CorsHandler::CORS][CorsHandler::ERRORCODE2], 403 );

        $this->assertTrue( isset( $config2[CorsHandler::CORS][CorsHandler::ERRORCODE3] ));
        $this->assertEquals(      $config2[CorsHandler::CORS][CorsHandler::ERRORCODE3], 406 );

        $this->assertTrue( isset( $config2[CorsHandler::CORS][CorsHandler::ERRORCODE4] ));
        $this->assertEquals(      $config2[CorsHandler::CORS][CorsHandler::ERRORCODE4], 406 );

        $this->assertTrue( isset( $config2[CorsHandler::CORS][CorsHandler::ACCESSCONTROLALLOWHEADERS] ));
        $this->assertEquals(      $config2[CorsHandler::CORS][CorsHandler::ACCESSCONTROLALLOWHEADERS], ['x-header'] );

        $this->assertTrue( isset( $config2[CorsHandler::CORS][CorsHandler::ACCESSCONTROLMAXAGE] ));
        $this->assertEquals(      $config2[CorsHandler::CORS][CorsHandler::ACCESSCONTROLMAXAGE], 200 );

        $this->assertTrue( isset( $config2[CorsHandler::CORS][CorsHandler::ACCESSCONTROLEXPOSEHEADERS] ));
        $this->assertEquals(      $config2[CorsHandler::CORS][CorsHandler::ACCESSCONTROLEXPOSEHEADERS], ['x-header'] );

        $this->assertTrue( isset( $config2[CorsHandler::CORS][CorsHandler::ACCESSCONTROLALLOWCREDENTIALS] ));
        $this->assertEquals(      $config2[CorsHandler::CORS][CorsHandler::ACCESSCONTROLALLOWCREDENTIALS], true );
        /**
         * test ContentTypeHandler config
         */
        $this->assertTrue( isset( $config2[ContentTypeHandler::ACCEPT][ContentTypeHandler::FALLBACK] ));
        $this->assertEquals(      $config2[ContentTypeHandler::ACCEPT][ContentTypeHandler::FALLBACK], 'application/json' );

        $this->assertTrue( isset( $config2['application/json'][ContentTypeHandler::UNSERIALIZEOPTIONS] ));
        $this->assertEquals(      $config2['application/json'][ContentTypeHandler::UNSERIALIZEOPTIONS], JSON_OBJECT_AS_ARRAY );
        /**
         * test EncodingHandler config
         */
        $this->assertTrue( isset( $config2[EncodingHandler::ACCEPTENCODING][EncodingHandler::FALLBACK] ));
        $this->assertEquals(      $config2[EncodingHandler::ACCEPTENCODING][EncodingHandler::FALLBACK], 'gzip' );

        $this->assertTrue( isset( $config2['gzip'][EncodingHandler::ENCODELEVEL] ));
        $this->assertEquals(      $config2['gzip'][EncodingHandler::ENCODELEVEL], -1 );
        $this->assertTrue( isset( $config2['gzip'][EncodingHandler::ENCODEOPTIONS] ));
        $this->assertEquals(      $config2['gzip'][EncodingHandler::ENCODEOPTIONS], FORCE_GZIP );
    }
}
