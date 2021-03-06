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

namespace Kigkonsult\RestServer;

use PHPUnit\Framework\TestCase;

/**
 * class ResponseTest
 *
 * @author      Kjell-Inge Gustafsson <ical@kigkonsult.se>
 */
class ResponseTest extends TestCase
{
    /**
     * test rawBody methods
     *
     * @test
     */
    public function testrawBody()
    {
        $data     = '1!2"3#4¤5%6&7/8(9)0=+?*.:,;';
        $response = new Response();
        $response = $response->withRawBody( $data );
        $this->assertEquals( $data, $response->getRawBody());
        $this->assertEquals( $data, $response->getResponseBody());

        $response = $response->withRawBody( null );
        $response = $response->withBody( StreamFactory::createStream( $data ));
        $this->assertEquals( $data, $response->getResponseBody());

        $data     = null;
        $response = $response->withRawBody( null );
        $response = $response->withBody( StreamFactory::createStream( $data ));
        $this->assertEquals( $data, $response->getResponseBody());
    }
}
