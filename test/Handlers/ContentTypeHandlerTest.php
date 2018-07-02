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
use Kigkonsult\RestServer\StreamFactory;
use Kigkonsult\RestServer\Handlers\ContentTypeHandlers\XMLHandler;

/**
 * class ContentTypeHandlerTest
 *
 * @author      Kjell-Inge Gustafsson <ical@kigkonsult.se>
 */
class ContentTypeHandlerTest extends TestCase
{
    /**
     * testregister provider
     */
    public function registerProvider()
    {
        $dataArr   = [];
        $dataArr[] = [
            'key',
            'value'
        ];
        return $dataArr;
    }
    /**
     * test register mgnt
     *
     * @test
     * @dataProvider registerProvider
     */
    public function testregister(
        $key,
        $value
    ) {
        $allTypes = ContentTypeHandler::getRegister();
        ContentTypeHandler::register( $key, $value );
        $this->assertEquals( $value, ContentTypeHandler::getRegister( $key ));
        ContentTypeHandler::unRegister( $key );
        $this->assertNull( ContentTypeHandler::getRegister( $key ));
        $this->assertEquals( $allTypes, ContentTypeHandler::getRegister());
    }

    /**
     * testhasFormHeader provider
     */
    public function hasFormHeaderProvider()
    {
        $dataArr   = [];
        $dataArr[] = [
            [ContentTypeHandler::CONTENTTYPE => 'application/x-www-form-urlencoded'],
            true,
        ];
        $dataArr[] = [
            [ContentTypeHandler::CONTENTTYPE => 'application/json'],
            false,
        ];
        $dataArr[] = [
            [ContentTypeHandler::ACCEPT => 'application/json'],
            false,
        ];

        return $dataArr;
    }

    /**
     * test hasFormHeader
     * @dataProvider hasFormHeaderProvider
     *
     * @test
     */
    public function testhasFormHeader(
        $headers,
        $excepted
    ) {
        $this->assertEquals( $excepted, ContentTypeHandler::hasFormHeader( $headers ));
        $this->assertEquals( $excepted, ContentTypeHandler::hasFormHeader( $headers ));
        $this->assertEquals( $excepted, ContentTypeHandler::hasFormHeader( $headers ));
    }

    /**
     * testvalidateRequestHeader provider
     */
    public function validateRequestHeaderProvider()
    {
        $dataArr   = [];
        $dataArr[] = [
            ContentTypeHandler::CONTENTTYPE,
            'application/json',
            '; charset=utf-8',
            true,
        ];
        $dataArr[] = [
            ContentTypeHandler::CONTENTTYPE,
            'application/json',
            '  otherdata',
            true,
        ];
        $dataArr[] = [
            ContentTypeHandler::CONTENTTYPE,
            'application/json',
            '; charset=utf-8  otherdata',
            true,
        ];
        $dataArr[] = [
            'OTHER_TYPE',
            'application/json',
            '',
            false,
        ];

        return $dataArr;
    }

    /**
     * test validateRequestHeader
     * @dataProvider validateRequestHeaderProvider
     *
     * @test
     */
    public function testvalidateRequestHeader(
        $headerKey,
        $headerValue,
        $hVext,
        $expected
    ) {
        $headers = [$headerKey => $headerValue . $hVext];
        $request = new ServerRequest(
            [],           // serverParams
            [],           // $uploadedFiles
           null,         // uri
           null,         // method
           'php://input', // body
            $headers      // headers
        );
        list( $request, $response ) = ContentTypeHandler::validateRequestHeader(
            $request,
            new Response()
        );
        $attr = $request->getAttribute( ContentTypeHandler::CONTENTTYPE, false );
        if ( $expected ) {
            $this->assertEquals( $attr, $headerValue );
        } else {
            $this->assertFalse( $attr );
        }
    }

    /**
     * testvalidateResponseHeader provider
     */
    public function validateResponseHeaderProvider()
    {
        $dataArr   = [];
        $dataArr[] = [
            ContentTypeHandler::ACCEPT,
            'application/xml, application/xhtml+xml, text/xml;  q=0.5, application/json; q=0.5, */*; q=0.1',
            'application/xml',
        ];
        $dataArr[] = [
            ContentTypeHandler::ACCEPT,
            'application/xml, */*; q=0',
            'application/xml',
        ];
        $dataArr[] = [
            'OTHER_TYPE',
            'text/plain',
            'application/json',
        ];
        $dataArr[] = [
            ContentTypeHandler::ACCEPT,
            'application/xhtml+xml, text/xml;  q=0.5, application/json; q=0.5, */*; q=0.1',
            'application/xhtml+xml',
        ];
       $dataArr[] = [
           ContentTypeHandler::ACCEPT,
           'application/json-patch+json, application/json; q=0.5, */*; q=0.1',
           'application/json-patch+json',
       ];

        return $dataArr;
    }

    /**
     * test validateRequestHeader
     * @dataProvider validateResponseHeaderProvider
     *
     * @test
     */
    public function testvalidateResponseHeader(
        $headerKey,
        $headerValue,
        $expected
    ) {
        $request                    = new ServerRequest();
        $request                    = $request->withHeader( $headerKey, $headerValue );
        list( $request, $response ) = ContentTypeHandler::validateResponseHeader(
            $request,
            new Response()
        );
        $attr = $request->getAttribute( ContentTypeHandler::ACCEPT, false );
        $this->assertEquals( $attr, $expected );
    }

    /**
     * testConfig provider
     */
    public function ConfigProvider()
    {
        $dataArr   = [];
        $dataArr[] = [
            'application/json',
            ContentTypeHandler::UNSERIALIZEOPTIONS,
            JSON_OBJECT_AS_ARRAY,
        ];

        return $dataArr;
    }

    /**
     * test Config
     *
     * @test
     * @dataProvider ConfigProvider
     */
    public function testConfig(
        $cfgKey1,
        $cfgKey2,
        $cfgValue
    ) {
        $config  = [$cfgKey1 => [$cfgKey2 => $cfgValue]];
        $request = new ServerRequest();
        $request = $request->withAttribute( RestServer::CONFIG, $config );

        $attrCfG = $request->getAttribute( RestServer::CONFIG, [] );

        $this->assertEquals( $cfgValue, $attrCfG[$cfgKey1][$cfgKey2] );
    }

    /**
     * testSerializing1 provider
     */
    public function serializingProvider1()
    {
        $dataArr   = [];
        $dataArr[] = [
//          '1!2"3#4¤5%6&7/8(9)0=+?*äÄöÖ.:,;',
            '1!2"3#4¤5%6&7/8(9)0=+?*.:,;',
        ];
        $dataArr[] = [
            '<-- data -->',
        ];
        $dataArr[] = [
            [1 => 19, 'test' => 'json'],
        ];

        return $dataArr;
    }

    /**
     * testing unserialize/serialize json
     *
     * @test
     * @dataProvider serializingProvider1
     */
    public function testSerializing1(
        $data
    ) {
        $contentType = 'application/json';
        $json        = \json_encode( $data );
        $request     = new ServerRequest();
        $request     = $request->withMethod( 'POST' )
                               ->withAttribute( ContentTypeHandler::CONTENTTYPE, $contentType )
                               ->withAttribute( ContentTypeHandler::ACCEPT, $contentType )
                               ->withBody( StreamFactory::createStream( $json ));
        $this->assertEquals( $contentType, $request->getAttribute( ContentTypeHandler::CONTENTTYPE, false ));
        $this->assertEquals( $contentType, $request->getAttribute( ContentTypeHandler::ACCEPT, false ));
        $response                   = new Response();
        list( $request, $response ) = ContentTypeHandler::unserializeRequest( $request, $response );
        $data2                      = $request->getParsedBody();
        $this->assertEquals( $data, $data2 );
        $response                   = $response->withRawBody( $data2 );
        list( $request, $response ) = ContentTypeHandler::serializeResponse( $request, $response );
        list( $request, $response ) = ContentTypeHandler::setContentLength( $request, $response );
        $stream                     = $response->getBody();
        $stream->rewind();
        $data3 = $stream->getContents();
        $this->assertEquals( $json, $data3 );
        $this->assertEquals( $contentType, $response->getHeader( ContentTypeHandler::CONTENTTYPE )[0] );
        $this->assertTrue( $response->hasHeader( ContentTypeHandler::CONTENTLENGTH ));
    }

    /**
     * testSerializing2 provider
     */
    public function serializingProvider2()
    {
        $dataArr   = [];
        $dataArr[] = [
"<?xml version='1.0'?>
<movies>
 <movie>
  <title>PHP: Behind the Parser</title>
  <characters>
   <character>
    <name>Ms. Coder</name>
    <actor>Onlivia Actora</actor>
   </character>
   <character>
    <name>Mr. Coder</name>
    <actor>El Act&#211;r</actor>
   </character>
  </characters>
  <plot>
   So, this language. It's like, a programming language. Or is it a
   scripting language? All is revealed in this thrilling horror spoof
   of a documentary.
  </plot>
  <great-lines>
   <line>PHP solves all my web problems</line>
  </great-lines>
  <rating>5</rating>
 </movie>
</movies>",
                        ];
        $dataArr[] = [
'<?xml version="1.0"?>
<test>
  <key1>data&amp;1</key1>
  <key2>
    <key21>data21</key21>
    <key22>data&amp;22</key22>
  </key2>
</test>
',
                    ];

        return $dataArr;
    }

    /**
     * testing unserialize/serialize xml
     *
     * @test
     * @dataProvider serializingProvider2
     */
    public function testSerializing2(
        $xml
    ) {
        $contentType = 'application/xml';
        $arrayData   = XMLHandler::unSerialize( $xml );
        $request     = new ServerRequest();
        $request     = $request->withMethod( 'POST' )
                               ->withAttribute( ContentTypeHandler::CONTENTTYPE, $contentType )
                               ->withAttribute( ContentTypeHandler::ACCEPT, $contentType )
                               ->withBody( StreamFactory::createStream( $xml ));
        $this->assertEquals( $contentType, $request->getAttribute( ContentTypeHandler::CONTENTTYPE, false ));
        $this->assertEquals( $contentType, $request->getAttribute( ContentTypeHandler::ACCEPT, false ));
        $response                   = new Response();
        list( $request, $response ) = ContentTypeHandler::unserializeRequest( $request, $response );
        $data2                      = $request->getParsedBody();
        $this->assertEquals( $arrayData, $data2 );
        $response                   = $response->withRawBody( $data2 );
        list( $request, $response ) = ContentTypeHandler::serializeResponse( $request, $response );
        $stream                     = $response->getBody();
        $stream->rewind();
        $data3                      = $stream->getContents();
        list( $request, $response ) = ContentTypeHandler::setContentLength( $request, $response );
        $stream                     = $response->getBody();
        $stream->rewind();
        $data3 = $stream->getContents();
//      $this->assertEquals( $xml, $data3 );
        $this->assertXmlStringEqualsXmlString( $xml, $data3 );
        $this->assertEquals( $contentType, $response->getHeader( ContentTypeHandler::CONTENTTYPE )[0] );
        $this->assertTrue( $response->hasHeader( ContentTypeHandler::CONTENTLENGTH ));
    }

    /**
     * testing serialize xml (xml-string=no-serialize)
     *
     * @test
     * @dataProvider serializingProvider2
     */
    public function testSerializing3(
        $xml
    ) {
        $contentType = 'application/xml';
        $request     = new ServerRequest();
        $request     = $request->withMethod( 'POST' )
                               ->withAttribute( ContentTypeHandler::CONTENTTYPE, $contentType )
                               ->withAttribute( ContentTypeHandler::ACCEPT, $contentType )
                               ->withBody( StreamFactory::createStream( $xml ));
        $this->assertEquals( $contentType, $request->getAttribute( ContentTypeHandler::CONTENTTYPE, false ));
        $this->assertEquals( $contentType, $request->getAttribute( ContentTypeHandler::ACCEPT, false ));
        $response = new Response();

        $response                   = $response->withRawBody( $xml );
        list( $request, $response ) = ContentTypeHandler::serializeResponse( $request, $response );

        $stream = $response->getBody();
        $stream->rewind();
        $data3 = $stream->getContents();
        $this->assertXmlStringEqualsXmlString( $xml, $data3 );
    }
}
