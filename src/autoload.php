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
    /**
     * restServer autoloader
     */
\spl_autoload_register(
    function($class) {
        static $PREFIX = 'Kigkonsult\\RestServer\\';
        static $BS     = '\\';
        static $FMT    = '%1$s%2$ssrc%2%s%3$s.php';
        if ( 0 != \strncmp( $PREFIX, $class, 22 )) {
            return;
        }
        $class = \substr( $class, 22 );
        if ( false !== \strpos( $class, $BS )) {
            $class = \str_replace( $BS, DIRECTORY_SEPARATOR, $class );
        }
        $file = sprintf( $FMT, __DIR__, DIRECTORY_SEPARATOR, $class );
        if ( \file_exists( $file )) {
            include $file;
        }
    }
);
