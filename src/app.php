<?php

/* vim: set fdm=marker: */
/* Copyright notice and X11 License {{{
   
   app.php
   A minimal PHP Web framework.
   
   Copyright (C) 2014 Scott Zeid.
   
   Permission is hereby granted, free of charge, to any person obtaining a copy
   of this software and associated documentation files (the "Software"), to deal
   in the Software without restriction, including without limitation the rights
   to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
   copies of the Software, and to permit persons to whom the Software is
   furnished to do so, subject to the following conditions:
   
   The above copyright notice and this permission notice shall be included in
   all copies or substantial portions of the Software.
   
   THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
   IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
   FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
   AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
   LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
   OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
   THE SOFTWARE.
   
   Except as contained in this notice, the name(s) of the above copyright holders
   shall not be used in advertising or otherwise to promote the sale, use or
   other dealings in this Software without prior written authorization.
   
}}}*/

/** Renders output and sends HTTP headers.
 * 
 * Possible options:
 *  - code: the HTTP status code to send (default: 200)
 *  - type: the MIME type to use (default: "text/html; charset=utf-8",
 *    or "application/json" if $output is not a string)
 *  - headers: an associative array of extra HTTP headers to send
 *  - json-options: options to pass to json_encode()
 * 
 * @param $output  The output to send.  Non-strings will be sent as JSON.
 * @param $options An associative array of options described above.
 * @returns Raw output to send to the client.
 */
function result($output, $options = []) {
 $code = (isset($options["code"])) ? $options["code"] : 200;
 $type = (isset($options["type"])) ? $options["type"] : "";
 $headers = (isset($options["headers"])) ? $options["headers"] : [];
 $json_options = (isset($options["json-options"])) ? $options["json-options"] : 0;

 if ($code !== 451)
  http_response_code($code);
 else
  header("HTTP/1.0 451 Unavailable For Legal Reasons");
 
 if (!is_string($output))
  $type = (!empty($type)) ? $type : "application/json";
 else
  $type = (!empty($type)) ? $type : "text/html; charset=utf-8";
 
 header("Content-Type: $type");
 
 foreach ($headers as $k -> $v)
  header("$k: $v");
 
 if (is_string($output))
  return $output;
 else
  return json_encode($output, $json_options);
}


class App {
 /** Makes a new app.
  * 
  * @param $name (optional) The name of the app, sent as the X-Powered-By
  *              header.  If it is the empty string, no X-Powered-By
  *              header will be sent; if null, PHP's default will be sent.
  */
 public function __construct($name = null) {
  $this->name = $name;
  $this->routes = [];
 }
 
 /** Defines a new route.
  * 
  * @param $method The HTTP method with which to associate this route.
  * @param $route_or_routes A string or array of strings defining the URL(s)
  *                         for which this route should answer.
  * @param $callback A callback function to call when this route is requested.
  * 
  * Routes are defined like this:
  * "/something/:param1/:param2/other/:params"
  * Each colon-prefixed parameter will be parsed and put in $params.
  * 
  * Callback arguments (all optional):
  * 1. $params - parameters from the URL itself
  * 2. $_get - GET/query-string parameters
  * 3. $_post - POST parameters
  */
 public function route($method, $route_or_routes, $callback) {
  $routes = (is_array($route_or_routes)) ? $route_or_routes : [$route_or_routes];
  foreach ($routes as $route)
   $this->routes[] = [$method, $route, $callback];
 }
 /** Calls route() with the first argument set to "GET". */
 public function get($route, $callback)  { $this->route("GET",  $route, $callback); }
 /** Calls route() with the first argument set to "POST". */
 public function post($route, $callback) { $this->route("POST", $route, $callback); }
 
 /** Handles a request.
  *
  * Except for $prefix, all arguments will be taken from the server if omitted.
  * $url is just the path component, beginning with a "/".
  * 
  * @returns The HTTP status code for the request.
  */
 public function handle($prefix = "", $url = "", $method = "", $_get = [], $_post = []) {
  $query_string = $_SERVER["QUERY_STRING"];
  $request_method = $_SERVER["REQUEST_METHOD"];
  $default_get = $_GET;
  $default_post = $_POST;
  
  if (empty($url)) {
   $amp = strpos($query_string, "&");
   if ($amp !== false) {
    $url = substr($query_string, 0, $amp);
    $default_get = [];
    parse_str(substr($query_string, $amp + 1), $default_get);
   } else
    $url = $query_string;
  }
  if (empty($method))
   $method = $request_method;
  if (empty($method))
   $method = "GET";
  if (empty($_get))
   $_get = $default_get;
  if (empty($_post))
   $_post = $default_post;
  
  if (strpos($url, "/") !== 0)
   $url = "/" . $url;
  if (!empty($prefix)) {
   if (strpos($url, $prefix) === 0)
    $url = substr($url, strlen($prefix));
   else {
    echo result("<h1>404 Not Found</h1>", ["code" => 404]);
    return;
   }
  }
  
  if ($this->name !== null)
   header("X-Powered-By: {$this->name}");
  else if ($this->name === "")
   header("X-Powered-By:");
  
  foreach ($this->routes as $route) {
   list($route_method, $route_spec, $route_callback) = $route;
   if (strtoupper($method) === strtoupper($route_method) &&
       preg_match(self::route_to_re($route_spec), $url)) {
    $params = self::get_params($url, $route_spec);
    echo $route_callback($params, $_get, $_post);
    return http_response_code();
   }
  }
  echo result("<h1>404 Not Found</h1>", ["code" => 404]);
  return http_response_code();
 }
 
 /** Allows for requests to be simulated on the command line.
  * 
  * To use a custom command line, pass in your own $_argc and $_argv;
  * otherwise, the actual command line will be used.
  * 
  * Command line arguments:
  * 
  * 1.  URL (path component)
  * 2.  Method (default: GET)
  * 3.  Query string parameters (default: none)
  * 4.  POST parameters (default: none)
  * 
  * @returns The HTTP status code for the request.
  */
 public function handle_cli($_argc = null, $_argv = [], $prefix = "") {
  // Allow for requests to be simulated on the command line
  ini_set("error_reporting", E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED);
  ini_set("display_errors", "stderr");
  if ($_argc === null) {
   $_argc = $_SERVER["argc"]; $_argv = $_SERVER["argv"];
  }
  $url = ($_argc > 1) ? $_argv[1] : "/";
  $method = ($_argc > 2) ? $_argv[2] : "GET";
  $get = []; parse_str(($_argc > 3) ? $_argv[3] : "", $get);
  $post = []; parse_str(($_argc > 4) ? $_argv[4] : "", $post);
  return $this->handle($prefix, $url, $method, $get, $post);
 }
 
 static function route_to_re($route) {
  return '#^'.preg_replace('#(/)\\\\:([^/]+)#', '$1([^/]*)', preg_quote($route)).'$#';
 }
 
 static function get_params($url, $route) {
  $keys = []; $values = []; $params = [];
  preg_match_all('#/:([^/]+)#', $route, $keys);
  preg_match_all(self::route_to_re($route), $url, $values);
  for ($i = 0; $i < min(count($keys[1]), max(0, count($values) - 1)); $i++) {
   if (count($values[$i + 1]))
    $params[$keys[1][$i]] = $values[$i + 1][0];
  }
  return $params;
 }
}
