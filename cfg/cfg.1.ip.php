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

use Kigkonsult\RestServer\Handlers\IpHandler;

    /**
     * Configuration for the builtin IpHandler
     * The handler is optional.
     *
     * @author      Kjell-Inge Gustafsson <ical@kigkonsult.se>
     *
     * NOTE, do NOT rely on IP numbers!
     * BUT if you are using a proxy or are inside an internal network...
     *
     * include example
     * $config[IpHandler::IPHEADER] = include 'cfg/cfg.1.ip.php';
     */
$ipCfg = [];

    /**
     * Ignore all IP header(s)
     *
     * value type : bool
     * default false (or not set)
     */
$ipCfg[RestServer::IGNORE] = true;

    /**
     * Default for (opt) logging
     *   response status 4xx results in logging with prio warning
     *   response status 500 results in logging with prio error
     */

    /**
     * StatusCode for response if expected IPnum not found,
     *
     * value type : int|array
     * default 403, 'Forbidden', set only here if other !!
     * Due to security, you can alter logging prio to error
     * using value type array : [ 403, RestServer::ERROR ]
     */
$ipCfg[IpHandler::ERRORCODE1] = 403;

    /**
     * Network ranges to find a match for Ipnumber
     *
     * IPv4 network ranges can be specified as:
     * 0. Accept all IPs:      *           // warning, use it on your own risk, accepts all
     * 1. Wildcard format:     1.2.3.*
     * 2. CIDR format:         1.2.3/24  OR  1.2.3.4/255.255.255.0
     * 3. Start-End IP format: 1.2.3.0-1.2.3.255
     * 4. Specific IP:         1.2.3.4
     *
     * IPv6 network ranges can be specified as:
     * 0. Accept all IPs:      *           // warning, use it on your own risk, accepts all
     * 2. CIDR format:         fe80:1:2:3:a:bad:1dea:dad/82
     * 3. Start-End IP format: 3ffe:f200:0234:ab00:0123:4567:1:20-3ffe:f200:0234:ab00:0123:4567:1:30
     * 4. Specific IP:         fe80:1:2:3:a:bad:1dea:dad
     *
     * You can combine one or more ranges, also mix IPv4 and IPv6 ranges.
     * Every header value can have a individual range array
     *
     * value type : array
     */
$range = [
    '1.2.3.4/255.255.255.0',
    'fe80:1:2:3:a:bad:1dea:dad/82',
];

    /**
     * Header value(s) to examine (in order) and corr. range
     * No or empty array will give NO examination, compare IGNORE above.
     *
     * SubKey REQUIRED
     *   true  : a match for the header value is mandatory
     *   false : (or no key) one match in any header values is mandatory
     *
     * In case of more than one IPnum in header FORWARDED or FORWARDED_FOR
     * Examination depth : ALL   - all IPnums in header
     *                     FIRST - first only (originating client)
     *                     LAST  - the most recent proxy
     */
$ipCfg[IpHandler::EXAMINE]   = [];

    /**
     * FORWARDED header value to examine and range
     * Both sub-fields 'for' and opt. 'by' are examinated (in order)
     *
     * value type : array
     */
$ipCfg[IpHandler::EXAMINE][IpHandler::FORWARDED] = [
    IpHandler::REQUIRED => false,
    IpHandler::RANGE => [
        IpHandler::FIRST => $range,
        IpHandler::LAST  => $range,
    ]
];

    /**
     * FORWARDED_FOR header value to examine and find in range
     * The first being the original client, and each successive proxy BUT the last
     *
     * value type : array
     */
$ipCfg[IpHandler::EXAMINE][IpHandler::FORWARDED_FOR] = [
    IpHandler::REQUIRED => false,
    IpHandler::RANGE => [
        IpHandler::FIRST => $range,
        IpHandler::LAST  => $range,
    ]
];

    /**
     * CLIENT_IP header value to examine and find in range
     *
     * value type : array
     */
$ipCfg[IpHandler::EXAMINE][IpHandler::CLIENT_IP] = [
    IpHandler::REQUIRED => false,
    IpHandler::RANGE    => $range,
];

    /**
     * REMOTE_ADDR header value to examine and find in range
     *
     * value type : array
     */
$ipCfg[IpHandler::EXAMINE][IpHandler::REMOTE_ADDR] = [
    IpHandler::REQUIRED => false,
    IpHandler::RANGE    => $range,
];

    /**
     * REFERER header value to examine and find in range
     *
     * value type : array
     */
$ipCfg[IpHandler::EXAMINE][IpHandler::REFERER] = [
    IpHandler::REQUIRED => false,
    IpHandler::RANGE    => $range,
];

    /** ************************************************************************
     * Add to main config
     * <code>
     * $config[IpHandler::IPHEADER] = include 'cfg/cfg.1.ip.php';
     * </code>
     */
return $ipCfg;
