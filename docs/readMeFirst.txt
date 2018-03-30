
restServer, a PSR HTTP Message rest server implementation

This file is a part of restServer.

Copyright 2018 Kjell-Inge Gustafsson, kigkonsult, All rights reserved
Link      http://kigkonsult.se/restServer/index.php
Version   0.8.0
 * Link      http://kigkonsult.se/restServer/index.php
 * Version   0.8.0
License   Subject matter of licence is the software restServer.
          The above copyright, link, package and version notices and
          this licence notice shall be included in all copies or
          substantial portions of the restServer.
          restServer can be used either under the terms of
          a proprietary license, available at <https://kigkonsult.se/>
          or the GNU Affero General Public License, version 3:
          restServer is free software: you can redistribute it and/or
          modify it under the terms of the GNU Affero General Public License
          as published by the Free Software Foundation, either version 3 of
          the License, or (at your option) any later version.
          restServer is distributed in the hope that it will be useful,
          but WITHOUT ANY WARRANTY; without even the implied warranty of
          MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
          GNU Affero General Public License for more details.
          You should have received a copy of the GNU Affero General Public
          License along with this program.
          If not, see <http://www.gnu.org/licenses/>.

Product names mentioned herein are or may be trademarks or registered trademarks
of their respective owners.

-----------------------
rest services made easy
-----------------------

the purpose for restServer is to offer developers, as simple as possible,
rest services for their applications.

"Everything should be made as simple as possible, but not simpler."
[Albert Einstein]

restServer is based on and credits authors of

PSR-7 HTTP message interfaces
 - https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-7-http-message.md
PSR HTTP message Util interfaces
- https://github.com/php-fig/http-message-util
zend-diactoros
 - https://github.com/zendframework/zend-diactoros
FastRoute
 - https://github.com/nikic/FastRoute

restServer requires PHP 5.6 or higher.

OVERVIEW
--------

Here is a brief restServer orientation, more detailed below and in supplements.

Very simple, restServer is wrapper for zend-diactoros Server.
The input message is captured at restServer class instance creation.
The input/output logistics are managed by restServer and its builtin handlers.
There is an interface/API (service definition) for attach of external
application(s) logic.
Output message is returned in restServer method run.

The (external, not included) application(s) must provide rest service
definition(s), each defined by
  request method(s),
  request target (uri),
  application callback.
The service definition(s) are attached to restServer and fired off when an
incoming request match a service definition.

Each rest service definitions callback must be invoked with two arguments,
- (ServerRequestInterface) request
- (ResponseInterface)  response
and return ResponseInterface response.

restServer have builtin handlers managing messages serializing, en-/decoding
and cors (Cross-Origin Resource Sharing). There are also a builtin handler for
(log-)debuging. You can also attach custom handlers, ex. input sanitation.

restServer also provide, optional,
  configuration for fine-tuning service,
  logging
    any log class, (Psr\Log\LoggerInterface and supporting Psr\Log\LogLevel)


INSTALL
-------

To install with composer:

composer require kigkonsult/restserver

OR

Include the (download) RestServer folder to your include-path.
Add

"require_once 'src/autoload.php';"

to your PHP-script.


REST MESSAGES
-------------

Your rest service definition callback(s) as well as custom handler(s) have access
to all methods in the incoming
- Psr\Http\Message\ServerRequestInterface
as well as outgoing
- Psr\Http\Message\ResponseInterface
as described in
https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-7-http-message.md
and defined in
https://github.com/php-fig/http-message

REQUEST

You can access the incoming request message using

  request::getParsedBody()

(opt. decoded and unserialized) for all HTTP methods, GET, POST etc.
Note, for a (non-CORS, Cross-Origin Resource Sharing) request with method
OPTIONS, a builtin response is returned with headers for (attached service
definition) request methods and targets. For CORS pre-flight requests,
response are returned automatically. OPTIONS requests will return a body with
(service definition) http methods and corresponding request targets (uri)
as a json-string.

RESPONSE

For delivering a response message, use

  response::withRawBody( data )

The method is not PSR HTTP standard but included to simplify response
message delivery. Opt. serializing and encoding will be taken care of
automatically, using restServer builtin supported serializers/encoders.


SERVICE DEFINITION
------------------

You must define your rest service definitions as (array)
  method
  uri
  callback

Method is a http method (GET, POST etc) (or array of methods).
How to use and apply uri are described at https://github.com/nikic/FastRoute.
A callback (callable) can be
  simple function
  anonymous function
  instantiated object+method, passed as an array: object, method name
  class name and static method, passed as an array: class, method name (factory method?)
  class instance, class has an (magic) __call method
  class name, class has an (magic) __callStatic method

  The callback must be invoked with two arguments,
   (ServerRequestInterface) request,
   (ResponseInterface)  response.
  The callback must return a ResponseInterface response.

Service definition can be attached using
  restServer::attachRestService()
    below
  using callback from RestServer:getAttachRestServiceCallback()
    below
  config
    below

CALLBACK EXAMPLES

Review TemplateService.php for more information about services and callbacks.
There are examples of demo rest service classes and config and explicit attach.

There is a simple builtin 'ping' service, ex. used when checking service is
alive, attached using the builtin method restServer::getPingServiceDef().
The included 'pingIndex.php' is a usage example.


RESTSERVER HANDLERS
-------------------

For CORS (Cross-Origin Resource Sharing) builtin handler, review config (below)
and cfg/cfg.2.cors.php for details.

restServer unserializing and decoding builtin and custom handlers are invoked
before any callback exec, opt. serializing and encoding builtin handlers after.

Builtin serializing handlers manages
  application/json
  application/xml
  text/xml
  text/plain
  application/x-www-form-urlencoded (*
  multipart/form-data (*
(* incomming POST only

For Content-Type/Accept with
  trailing '+json' uses application/json handler,
  trailing '+xml' uses application/xml handler.

Empty or '*/*' Accept header will use application/json as default (configurable).

Builtin de/encoding handlers manages
  gzip,
  deflate,
  identity ('as is').

Empty or '*' Accept-Encoding header will use gzip as default (configurable).

Review TemplateHandler.php for how to implement custom handlers.


CONFIGURATION
-------------

Here are a short summary of configuration options:

- baseUri
  part of request target (uri) to eliminate to match service uri

- cors
  config for the the builtin CorsHandler (Cross-Origin Resource Sharing)
  review cfg/cfg.2.cors.php for details

- serializing and en/decoding handlers adaption (opt)
  review cfg/cfg.56.cte.php

- disallow
  initial reject of non-accepted request methods

- debug
  using builtin log debug or not
  (requires logging is set)

- services
  attaching rest service definition(s)
  see TemplateService.php

- handlers
  attaching custom handler(s)
  see TemplateHandler.php

Review cfg/cfg.RestServer.php for general restServer configuration.


LOGGING
-------

You can invoke a logger, any logger class with support for
methods error, warning, info and debug
(Psr\Log\LogLevel and Psr\Log\LoggerInterface)
Examine
RestServerLogger.php  (logger class wrapper for PHP error_log, for testing)
for more info.

RestServer uses prios:

 RestServer::ERROR   (response status 500)
   internal server error like serializing/encoding errors etc
 RestServer::WARNING (response status 4xx)
   logging unaccepted requests
 RestServer::INFO
 RestServer::DEBUG

See config (above) for invoke of debugging.

You can set the logger using static method:

restServer::setLogger( $logger );

A handler or service callback can invoke the logger

$prio    = RestServer::ERROR;
$message = 'hello world';
$logger  = RestServer::getLogger();
if ( ! empty( $logger ) && method_exists( $logger, $prio )) {
    $logger->{$prio}( $message );
}


RESTSERVER METHODS
------------------

restServer constructor
restServer::__construct()
restServer::__construct( config )

  config : array
  see cfg/cfg.RestServer.php


restServer::setConfig( config )

  config : array
  throws InvalidArgumentException
    (if handlers or services is attached from config)
  see cfg/cfg.RestServer.php

restServer::addHandler( handler )

  handler : custom handler as described in TemplateHandler.php
  throws InvalidArgumentException on error


restServer::attachRestService( serviceDef );
restServer::attachRestService( method, uri, callback );

  serviceDef : rest service definition as described in TemplateService.php
               [ method, uri, callback ]
  method     : request method(s)
  uri        : rest service uri
  callback   : rest service endpoint; class method/function
  throws InvalidArgumentException on unvalid arguments.


restServer::detachRestService( $method, $uri );

  method     : request method(s)
  uri        : rest service uri
  return bool true on success


restServer::getAttachRestServiceCallback()

  return method attachRestService as callback
  see TemplateService.php, last example


restServer::getLogger()

  static
  return opt. preset logger or null


restServer::setLogger( logger )

  logger     : logger class instance
  static
  set logger class instance


NEXT TO COME
---------------

In the plans are handlers;
  AuthenticationHandler (basic),
  extended CorsHandler,
  IPnumHandler.
