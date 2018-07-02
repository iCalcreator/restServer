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

/**
 * Class AsIsHandler manages nothing...
 *
 * @author      Kjell-Inge Gustafsson <ical@kigkonsult.se>
 */
class AsIsHandler implements ContentTypeInterface
{
    /**
     * class constants & statics
     * produces RFC4627-compliant JSON, capable of embedding into HTML.
     */

    /**
     * Return data 'as is'
     *
     * @param mixed $data
     * @param int   $decodeOptions
     * @return mixed
     * @static
     */
    public static function unserialize(
        $data,
        $decodeOptions = null
    ) {
        return $data;
    }

    /**
     * Return data 'As Is'
     *
     * @param mixed $data
     * @param int   $encodeOptions
     * @return mixed
     * @static
     */
    public static function serialize(
        $data,
        $encodeOptions = null
    ) {
        return $data;
    }
}
