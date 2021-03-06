
restServer, a PSR HTTP Message rest server implementation

This file is a part of restServer.

Copyright 2018 Kjell-Inge Gustafsson, kigkonsult, All rights reserved
Link      https://kigkonsult.se/restServer/index.php
Version   0.9.123
License   Subject matter of license is the software restServer.
          The above copyright, link, package and version notices and
          this license notice shall be included in all copies or
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

Product names mentioned herein or elsewhere are or may be trademarks
or registered trademarks of their respective owners.

-----------------------
rest services made easy
-----------------------

the purpose for restServer is to offer developers, as simple as possible,
rest service and API for their applications.

"Everything should be made as simple as possible, but not simpler."
[Albert Einstein]

restServer is based on and credits authors of
PSR-7 HTTP message interfaces
 - https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-7-http-message.md
PSR-7 HTTP message Util interfaces
- https://github.com/php-fig/http-message-util
zend-diactoros
 - https://github.com/zendframework/zend-diactoros
FastRoute
 - https://github.com/nikic/FastRoute
restServer requires PHP 5.6 or higher.


OVERVIEW
--------

Here is a brief restServer orientation, more detailed below and in supplements.

The input message is captured at restServer class instance creation.

The input/output logistics are managed by restServer and its builtin handlers.
There is an interface/API (service definition) for attach of (external)
.

The restServer method run executes application(s) logic and end off returning
response message.

The (external, not included) application(s) must provide rest service
definition(s), each defined by
  request method(s),
  request target (uri),
  application callback.

The service definition(s) are attached to restServer and the callback is fired
off when an incoming request match method and uri in a service definition,

Each rest service definitions callback must be invoked with two arguments,
- ServerRequestInterface request
- ResponseInterface response
and return ResponseInterface response.

restServer have builtin handlers managing
- opt. request IPnumber(s) validation
- opt. cors (Cross-Origin Resource Sharing)
- opt. request user authentication
- messages serializing, en-/decoding

There are also a builtin handler for (log-)debugging. You can attach custom
handlers, ex. for input sanitation, as well as a final handler.
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
  Psr\Http\Message\ServerRequestInterface
as well as outgoing
  Psr\Http\Message\ResponseInterface
as described in
  https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-7-http-message.md
and defined in
  https://github.com/php-fig/http-message

REQUEST

You can access the (decoded and unserialized) incoming request message using
  request::getParsedBody()
for all HTTP methods, GET, POST etc.

Requests ipnumbers may be checked against accept range(s).

CORS pre-flight requests response are returned automatically (using the builtin
CorsHandler).

OPTIONS requests will return a body with (service definition) http methods and
corresponding request targets (uri) as a json-string.

HEAD requests (default allowed if GET service definition(s) exists) exec a
matching GET service definition callback and return response with empty body.

RESPONSE
For delivering a response message, use
  response::withRawBody( data )
The method is not PSR HTTP standard but included to simplify response message
delivery. Later (opt.) serializing and encoding (with headers) are taken care
of automatically, using restServer builtin supported serializing/encoding
handlers.


SERVICE DEFINITION
------------------

The rest service definition(s) are the API for you application.

You must define your rest service definition(s) as (array)
  method,
    a http method (GET, POST etc) (or array of methods),
  uri,
    how to apply uri are described in https://github.com/nikic/FastRoute,
  callback
    callable :
      simple function
        functionName
      anonymous function (closure)
        variableValue...
      instantiated object+method,
        (array): objectInstance, method name
      class name and static method,
        (array): classNameWithNamespace, method name (factory method?)
      instantiated object, class has an (magic) __call method
        (array): objectInstance, method name
      class name, class has an (magic) __callStatic method
        (array): classNameWithNamespace, method name
      instantiated object, class has an (magic) __invoke method
        objectInstance

For examples, review 'test/RestServerTest.php' (method RestServer0Provider).

The service definition callback MUST be invoked with two arguments,
   ServerRequestInterface request,
   ResponseInterface response.

The callback MUST return a ResponseInterface response.

Service definition can be attached using
  RestServer::attachRestService(),
  using callback from RestServer:getAttachRestServiceCallback(),
  config,
all described below.

CALLBACK EXAMPLES

Review TemplateService.php for more information about services and callbacks,
examples of demo rest service classes and config and explicit attach.

The callback will have access to all methods in the incoming
  Psr\Http\Message\ServerRequestInterface
as well as outgoing
  Psr\Http\Message\ResponseInterface.
For return a response message, use
  response::withRawBody( data )

There is a simple builtin '/ping' service, ex. used when checking service is
alive, attached using the builtin method RestServer::getPingServiceDef().
The included 'pingIndex.php' is a usage example.


RESTSERVER HANDLERS
-------------------

The (optional) IpHandler will validate request IpNumber(s) against accepting
definition (ranges). Review cfg/cfg.1.ip.php for details.

For the (optional) CORS (Cross-Origin Resource Sharing) builtin handler, review
config (below) and cfg/cfg.2.cors.php for details.

restServer (optional) authentication work in three modes:
- Basic, static usernames and passwords
- Basic, username and password are checked using callback
- Digest, using one callback for checking and another for 401 response values
Review config (below) and cfg/cfg.3.auth.php for details.

restServer builtin unserializing and decoding and opt. custom handlers are
  invoked before exec of matching rest service definition callback,
opt. serializing and encoding builtin handlers are invoked after exec of
  matching rest service definition callback,
the finalHandler (if set),
  last, after return response is fired off, use ex. for tidy up, database close,
  statistic metrics of performance footprint etc.

Builtin serializing handlers manages
  application/json
  application/xml
  text/xml
  text/plain
  application/x-www-form-urlencoded  -- incoming POST only
  multipart/form-data                -- incoming POST only

For any serializing (header) Content-Type/Accept with
  trailing '+json' uses application/json handler,
  trailing '+xml' uses application/xml handler.

Missing, empty or '*/*' Accept header will use application/json as default
(you can alter it in config).

Builtin de-/encoding handlers manages
  gzip,
  deflate,
  identity ('as is').

Missing, empty or '*' Accept-Encoding header will use gzip as default
(you can alter it in config).

CUSTOM HANDLERS

A custom handler MUST be invoked with two arguments,
   ServerRequestInterface request,
   ResponseInterface response.
and will have access to all request/response methods.

All handlers (but final handler) MUST return (array)
   ServerRequestInterface request,
   ResponseInterface response.

Review TemplateHandler.php for how to implement custom handlers.


CONFIGURATION
-------------

Here are a short summary of configuration options:

- correlation-id
  unique (session uuid) id (will otherwise be set automatically)

- baseUri
  part of request target (uri) to eliminate to match service uri

- disallow
  list of non-accepted request methods

- debug
  using builtin log debug or not
  (requires a logger)

- IPnumber validation
  config for the the builtin IpHandler
  review cfg/cfg.1.ip.php for details

- cors
  config for the the builtin CorsHandler (Cross-Origin Resource Sharing)
  review cfg/cfg.2.cors.php for details

- authentication
  config for the the builtin AuthenticationHandler
  review cfg/cfg.3.auth.php for details

- serializing and en/decoding handlers adaption (opt)
  review cfg/cfg.56.cte.php

- services
  attaching rest service definition(s)
  see TemplateService.php

- handlers
  attaching custom handler(s)
  see TemplateHandler.php

- final handler
  attaching a final handler, exec after return response is fired off.
  Any return value is ignored.
  see TemplateHandler.php

Review cfg/cfg.RestServer.php for general restServer configuration.
A strong recommendation is to place config outside webserver document root.
Use all RestServer configs as templates and concatenate into your own one.


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
 RestServer::WARNING (default response status 4xx, config as ERROR)
   logging rejected requests
 RestServer::INFO
 RestServer::DEBUG

See config (above) for invoke of debugging.

You can set the logger using static method:
RestServer::setLogger( $logger );

A handler or service callback can invoke the logger
$prio    = RestServer::ERROR;
$message = 'hello world';
$logger  = RestServer::getLogger();
if ( ! empty( $logger ) && method_exists( $logger, $prio )) {
    $logger->{$prio}( $message );
}


RESTSERVER METHODS
------------------

RestServer::factory()
RestServer::factory( config )
  return new RestServer object instance
  static
  config : array
  see cfg/cfg.RestServer.php

RestServer::__construct()
RestServer::__construct( config )
  RestServer constructor
  config : array
  see cfg/cfg.RestServer.php

RestServer::setConfig( config )
  config : array
  see cfg/cfg.RestServer.php
  throws InvalidArgumentException
    (on errors in handlers or services set up)

RestServer::addHandler( handler )
  handler : callable|array, one handler or (array) handlers
            custom handlers are described in TemplateHandler.php
  throws InvalidArgumentException (on errors in handlers)

RestServer::addFinalHandler( handler )
  handler : callable, one handler
            custom handlers are described in TemplateHandler.php
  throws InvalidArgumentException (on errors in handler)

RestServer::attachRestService( serviceDef );
RestServer::attachRestService( method, uri, callback );
  serviceDef : rest service definition as described in TemplateService.php
               array, [ method, uri, callback ]
  method     : string|array, request method(s)
  uri        : string, rest service uri
  callback   : callable, rest service endpoint; class method/function
  throws InvalidArgumentException (on invalid arguments)

RestServer::detachRestService( $method, $uri );
  method : string|array request method(s)
  uri    : string, rest service uri
  return bool true on success

RestServer::getAttachRestServiceCallback()
  return method attachRestService as callback
  throws InvalidArgumentException (on invalid arguments)
  see TemplateService.php, last example

RestServer::getLogger()
  return logger object instance
  static
  return opt. preset logger or null

RestServer::setLogger( logger )
  set logger object instance
  static
  logger : object, logger object instance
           (Psr\Log\LoggerInterface and supporting Psr\Log\LogLevel)
