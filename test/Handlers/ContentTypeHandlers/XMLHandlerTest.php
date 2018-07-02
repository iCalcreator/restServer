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
use Kigkonsult\RestServer\Handlers\Exceptions\LIBXMLFatalErrorException;

/**
 * class XMLHandlerTest
 *
 * @author      Kjell-Inge Gustafsson <ical@kigkonsult.se>
 */
class XMLHandlerTest extends TestCase
{
    /**
     * XMLHandlerDataProvider1
     */
    public function XMLHandlerDataProvider1()
    {
        $data   = [];
        $data[] = [ // test data set #1
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

        return $data;
    }

    /**
     * @test
     * @dataProvider XMLHandlerDataProvider1
     */
    public function testXMLHandler1($xml)
    {
        $array = XMLHandler::unserialize( $xml );
        $xml2  = XMLHandler::serialize( $array );
        $this->assertXmlStringEqualsXmlString( $xml, $xml2 );
    }

    /**
     * XMLHandlerDataProvider2
     */
    public function XMLHandlerDataProvider2()
    {
        $data   = [];
        $data[] = [ // An invalid UTF8 sequence
            "\xB1\x31",
        ];

        return $data;
    }

    /**
     * @test
     * @dataProvider XMLHandlerDataProvider2
     * @expectedException Kigkonsult\RestServer\Handlers\Exceptions\LIBXMLFatalErrorException
     */
    public function testXMLHandler2($xml)
    {
        $array = XMLHandler::unserialize( $xml );
        $xml2  = XMLHandler::serialize( $array );
        $this->assertXmlStringEqualsXmlString( $xml, $xml2 );
    }
}
