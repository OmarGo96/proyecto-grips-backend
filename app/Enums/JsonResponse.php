<?php
namespace App\Enums;

/**
 * Clase para manejar los tipos de respuestas http, tomado de: https://restfulapi.net/http-status-codes/
*/
class JsonResponse
{
    const OK = 200; // It indicates that the REST API successfully carried
    const CREATED = 201; // A REST API responds with the 201 status code whenever a resource is created inside a collection.
    const ACCEPTED = 202; // A 202 response is typically used for actions that take a long while to process. It indicates that the request has been accepted for processing, but the processing has not been completed.
    const NO_CONTENT = 204; //The 204 status code is usually sent out in response to a PUT, POST, or DELETE request when the REST API declines to send back any status message or representation in the response message’s body.
    const MOVED_PERMANENTLY = 301; //The 301 status code indicates that the REST API’s resource model has been significantly redesigned, and a new permanent URI has been assigned to the client’s requested resource.
    const FOUND = 302; //The HTTP response status code 302 Found is a common way of performing URL redirection. An HTTP response with this status code will additionally provide a URL in the Location header field.
    const SEE_OTHER = 303; //
    const NOT_MODIFIED = 304;
    const TEMPORARY_REDIRECT = 307;
    const BAD_REQUEST = 400;
    const UNAUTHORIZED = 401;
    const FORBIDDEN = 403;
    const NOT_FOUND = 404;
    const METHOD_NOT_ALLOWED = 405;
    const NOT_ACCEPTABLE = 406;
    const INTERNAL_SERVER_ERROR = 500;
    const NOT_IMPLEMENTED = 501;

}
