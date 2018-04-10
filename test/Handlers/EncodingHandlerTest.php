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
use Kigkonsult\RestServer\Handlers\ContentTypeHandlers\JsonHandler;
use Kigkonsult\RestServer\Handlers\ContentTypeHandlers\XMLHandler;
use Kigkonsult\RestServer\Handlers\EncodingHandlers\DeflateHandler;
use Kigkonsult\RestServer\Handlers\EncodingHandlers\GzipHandler;

class EncodingHandlerTest extends TestCase
{
    /**
     * testvalidateRequestHeader provider
     */
    public function validateRequestHeaderProvider()
    {
        $dataArr   = [];
        $dataArr[] = [
            EncodingHandler::CONTENTENCODING,
            'gzip',
            true,
        ];
        $dataArr[] = [
            'OTHER_TYPE',
            'compress',
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
        $expected
    ) {
        $headers = [$headerKey => $headerValue];
        $request = new ServerRequest(
            [],                // serverParams
            [],                // $uploadedFiles
           null,           // uri
           null,        // method
           'php://input', // body
            $headers           // headers
                                    );
        list( $request, $response ) = EncodingHandler::validateRequestHeader(
            $request,
            new Response()
        );
        $attr = $request->getAttribute( EncodingHandler::CONTENTENCODING, false );
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
            EncodingHandler::ACCEPTENCODING,
            'deflate, gzip; q=0.5',
            'deflate',
        ];
        $dataArr[] = [
            'OTHER_TYPE',
            'compress',
            'gzip',
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
        list( $request, $response ) = EncodingHandler::validateResponseHeader(
            $request,
            new Response()
        );
        $attr = $request->getAttribute( EncodingHandler::ACCEPTENCODING, false );
        $this->assertEquals( $attr, $expected );
    }

    /**
     * testConfig provider
     */
    public function ConfigProvider()
    {
        $dataArr   = [];
        $dataArr[] = [
            'gzip',
            EncodingHandler::ENCODELEVEL,
            -1,
            EncodingHandler::ENCODEOPTIONS,
            FORCE_GZIP,
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
        $cfgKey,
        $cfgKey1,
        $cfgValue1,
        $cfgKey2,
        $cfgValue2
    ) {
        $config                    = [];
        $config[$cfgKey][$cfgKey1] = $cfgValue1;
        $config[$cfgKey][$cfgKey2] = $cfgValue2;
        $request                   = new ServerRequest();
        $request                   = $request->withAttribute( RestServer::CONFIG, $config );

        $attrCfG = $request->getAttribute( RestServer::CONFIG, [] );

        $this->assertEquals( $cfgValue1, $attrCfG[$cfgKey][$cfgKey1] );
        $this->assertEquals( $cfgValue2, $attrCfG[$cfgKey][$cfgKey2] );
    }

    /**
     * testCompressing1 provider
     */
    public function compressingProvider1()
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
            [
                1 => 19, 'test' => 'json',
            ],
        ];

        return $dataArr;
    }

    /**
     * testing compressing json
     *
     * @test
     * @dataProvider compressingProvider1
     */
    public function testCompressing1(
        $data
    ) {
        $contentType = 'application/json';
        $encoding    = 'gzip';
        $json        = JsonHandler::serialize( $data );
        $compressed  = GzipHandler::enCode( $json );
        $request     = new ServerRequest();
        $request     = $request->withMethod( 'POST' )
                               ->withAttribute( EncodingHandler::CONTENTENCODING, $encoding )
                               ->withAttribute( ContentTypeHandler::CONTENTTYPE, $contentType )
                               ->withAttribute( ContentTypeHandler::ACCEPT, $contentType )
                               ->withAttribute( EncodingHandler::ACCEPTENCODING, $encoding )
                               ->withBody( StreamFactory::createStream( $compressed ));
        $this->assertEquals( $encoding, $request->getAttribute( EncodingHandler::CONTENTENCODING, false ));
        $this->assertEquals( $contentType, $request->getAttribute( ContentTypeHandler::CONTENTTYPE, false ));
        $this->assertEquals( $contentType, $request->getAttribute( ContentTypeHandler::ACCEPT, false ));
        $this->assertEquals( $encoding, $request->getAttribute( EncodingHandler::ACCEPTENCODING, false ));

        $response                   = new Response();
        list( $request, $response ) = EncodingHandler::deCode( $request, $response );
        list( $request, $response ) = ContentTypeHandler::unserialize( $request, $response );
        $data2                      = $request->getParsedBody();
        $this->assertEquals( $data, $data2 );

        $response                   = $response->withRawBody( $data2 );
        list( $request, $response ) = ContentTypeHandler::serialize( $request, $response );
        list( $request, $response ) = EncodingHandler::enCode( $request, $response );
        list( $request, $response ) = ContentTypeHandler::setContentLength( $request, $response );
        $stream                     = $response->getBody();
        $stream->rewind();
        $data3 = $stream->getContents();

        $this->assertEquals( $compressed, $data3 );
        $this->assertEquals( @\gzdecode( $compressed ), @\gzdecode( $data3 ));
        $this->assertEquals( $contentType, $response->getHeader( ContentTypeHandler::CONTENTTYPE )[0] );
        $this->assertEquals( $encoding, $response->getHeader( EncodingHandler::CONTENTENCODING )[0] );
        $this->assertTrue( $response->hasHeader( ContentTypeHandler::CONTENTLENGTH ));
    }

    /**
     * testCompressing2 provider
     */
    public function compressingProvider2()
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
  <key3>
    <key4>data41</key4>
    <key4>data42</key4>
  </key3>
</test>
',
                    ];

        return $dataArr;
    }

    /**
     * testing unserialize/serialize xml
     *
     * @test
     * @dataProvider compressingProvider2
     */
    public function testCompressing2(
        $xml
    ) {
        $contentType = 'application/xml';
        $encoding    = 'deflate';
        $compressed  = DeflateHandler::enCode( $xml );
        $arrayData   = XMLHandler::unSerialize( $xml );
        $request     = new ServerRequest();
        $request     = $request->withMethod( 'POST' )
                               ->withAttribute( EncodingHandler::CONTENTENCODING, $encoding )
                               ->withAttribute( ContentTypeHandler::CONTENTTYPE, $contentType )
                               ->withAttribute( ContentTypeHandler::ACCEPT, $contentType )
                               ->withAttribute( EncodingHandler::ACCEPTENCODING, $encoding )
                               ->withBody( StreamFactory::createStream( $compressed ));
        $this->assertEquals( $encoding, $request->getAttribute( EncodingHandler::CONTENTENCODING, false ));
        $this->assertEquals( $contentType, $request->getAttribute( ContentTypeHandler::CONTENTTYPE, false ));
        $this->assertEquals( $contentType, $request->getAttribute( ContentTypeHandler::ACCEPT, false ));
        $this->assertEquals( $encoding, $request->getAttribute( EncodingHandler::ACCEPTENCODING, false ));
        $response = new Response();

        list( $request, $response ) = EncodingHandler::deCode( $request, $response );
        list( $request, $response ) = ContentTypeHandler::unserialize( $request, $response );
        $data2                      = $request->getParsedBody();
        $this->assertEquals( $arrayData, $data2 );

        $response                   = $response->withRawBody( $data2 );
        list( $request, $response ) = ContentTypeHandler::serialize( $request, $response );
        list( $request, $response ) = EncodingHandler::enCode( $request, $response );
        list( $request, $response ) = ContentTypeHandler::setContentLength( $request, $response );

        $stream = $response->getBody();
        $stream->rewind();
        $data3 = $stream->getContents();
        $this->assertXmlStringEqualsXmlString( @\gzuncompress( $compressed ), @\gzuncompress( $data3 ));
        $this->assertEquals( $contentType, $response->getHeader( ContentTypeHandler::CONTENTTYPE )[0] );
        $this->assertEquals( $encoding, $response->getHeader( EncodingHandler::CONTENTENCODING )[0] );
        $this->assertTrue( $response->hasHeader( ContentTypeHandler::CONTENTLENGTH ));
    }
}
