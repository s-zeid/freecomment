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
   if ($amp !== FALSE) {
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


/* vim: set fdm=marker: */
/* Copyright notice and X11 License {{{
   
   freecomment.php
   A minimalist blog comment system with a JavaScript frontend.
   
   Copyright (C) 2014 Scott Zeid.
   http://code.s.zeid.me/freecomment
   
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

chdir(dirname($_SERVER["SCRIPT_NAME"]));



$config = [
 "comments" => "comments",
 "url_prefix" => "",
 "akismet" => "",
 "blog_url" => "",
];

if (is_file("freecomment.conf"))
 $config = array_merge($config, parse_ini_file("freecomment.conf"));

function sanitize($path) {
 $path = basename($path);
 if ($path === "." || $path === "..")
  return "";
 return $path;
}

function post_dir($post) {
 global $config;
 $post = sanitize($post);
 $post_dir = "{$config["comments"]}/$post";
 if (is_dir($post_dir))
  return $post_dir;
 return null;
}

function comment_file($post, $comment) {
 $post_dir = post_dir(sanitize($post));
 return "$post_dir/".sanitize($comment);
}

function comments_open($post) {
 return !file_exists(comment_file(sanitize($post), "closed"));
}

function hash_comment($comment = null, $algorithm = "sha1") {
 if ($comment === null)
  return $algorithm;
 
 $keys = array_keys($comment);
 $values = [];
 sort($keys);
 foreach ($keys as $k) {
  if ($k !== "id" && $k !== "hash")
   $values[] = $comment[$k];
 }
 return hash($algorithm, implode("\0", $values));
}

function greatest_comment_id($post) {
 $post = sanitize($post);
 $comment_files = scandir(post_dir($post));
 natsort($comment_files);
 for ($i = count($comment_files) - 1; $i >= 0; $i--) {
  $match = [];
  if (preg_match("/^([0-9]+)/", $comment_files[$i], $match) !== false)
   return (int) $match[0];
 }
 return 0;
}

function error($code = 404, $message = "") {
 return result(["error" => $message, "code" => $code], ["code" => $code]);
}

function akismet_check($comment, $email, $post_url) {
 global $config;
 if (!$config["akismet"] || !$config["blog_url"])
  return true;
 
 if (!empty($_SERVER["HTTP_X_FORWARDED_FOR"]))
  $ip = $_SERVER["HTTP_X_FORWARDED_FOR"];
 else
  $ip = $_SERVER["REMOTE_ADDR"];
 if (empty($ip))
  $ip = "127.0.0.1";
 $fields = [
  "blog" => $config["blog_url"],
  "user_ip" => $ip,
  "user_agent" => $_SERVER["HTTP_USER_AGENT"],
  "referrer" => $_SERVER["HTTP_REFERER"],
  "permalink" => $post_url,
  "comment_type" => "comment",
  "comment_author" => $comment["author"],
  "comment_author_email" => $email,
  "comment_author_url" => $comment["website"],
  "comment_content" => $comment["body"],
  "blog_charset" => "UTF-8"
 ];
 if ($config["language"])
  $fields["blog_lang"] = $config["language"];
 $opts = ["method" => "POST", "user_agent" => "freecomment.php",
          "header" => "Content-Type: application/x-www-form-urlencoded",
          "content" => http_build_query($fields)];
 $ctx = stream_context_create(["http" => $opts]);
 $url = "http://{$config["akismet"]}.rest.akismet.com/1.1/comment-check";
 $stream = fopen($url, 'r', false, $ctx);
 $content = stream_get_contents($stream);
 if (trim($content) == "true")
  return false;
 return true;
}

$app = new App("freecomment.php");

$app->get(["/comments/:post", "/get/:post", "/list/:post"], function($params) {
 global $config;
 $comments = [];
 $post = sanitize($params["post"]);
 
 $post_dir = "{$config["comments"]}/$post";
 if (is_dir($post_dir)) {
  $post_files = scandir($post_dir);
  natsort($post_files);
  foreach ($post_files as $comment_file) {
   if ($comment_file !== "." && $comment_file !== ".." &&
       strpos($comment_file, ".") !== 0) {
    $comment = json_decode(file_get_contents("$post_dir/$comment_file"), true);
    if (is_array($comment))
     $comments[] = $comment;
   }
  }
 } else
  return error(404, "Comments are disabled for this post.");
 
 $r = ["post" => $post, "open" => comments_open($post), "comments" => $comments];
 return result($r, ["json-options" => JSON_PRETTY_PRINT]);
});

$app->get(["/comments/:post/:comment", "/get/:post/:comment"], function($params) {
 global $config;
 $post = sanitize($params["post"]);
 $comment = sanitize($params["comment"]);
 
 if (!post_dir($post))
  return error(404, "Comments are disabled for this post.");
 
 $comment_file = comment_file($post, $comment);
 if (is_file($comment_file)) {
  $comment = file_get_contents($comment_file);
  if (is_array(json_decode($comment, true)))
   return result($comment, ["type" => "application/json"]);
  else {
   $error = $comment;
   $http = explode("\n", str_replace("\r", "\n", str_replace("\r\n", "\n", $error)), 1);
   if (preg_match('/^([0-9]{3}) /', $http[0]))
    $code = (int) substr($http[0], 0, 3);
   else
    $code = 410;
   if (!$error)
    $error = "This comment has been removed.";
   return error($code, $error);
  }
 } else
  return error(404, "This comment does not exist.");
});

$app->post(["/comments/:post/new", "/add/:post"], function($params, $_get, $_post) {
 global $config;
 $post = sanitize($params["post"]);
 if (!post_dir($post))
  return error(404, "Comments are disabled for this post.");
 if (!comments_open($post))
  return error(403, "Comments are closed for this post.");
 if (!trim($_post["body"]))
  return error(400, "The comment may not be empty.");
 
 $email = (isset($_post["email"])) ? $_post["email"] : "";
 $post_url = (isset($_post["post_url"])) ? $_post["post_url"] : "";
 
 $comment = [
  "id" => null,
  "hash" => null,
  "time" => "".time(),
  "post" => $post,
  "author" => (isset($_post["author"])) ? $_post["author"] : "",
  "gravatar" => md5(strtolower(trim($email))),
  "website" => (isset($_post["website"])) ? $_post["website"] : "",
  "body" => $_post["body"]
 ];
 
 if (!akismet_check($comment, $email, $post_url))
  return error(500, "There was a problem saving your comment.");
 
 $comment["hash"] = hash_comment().":".hash_comment($comment);
 
 $comment["id"] = greatest_comment_id($post) + 1;
 
 while (true) {
  // Try to open a new file for saving the comment.
  // If the file already exists, increment the comment ID and try
  // again until a new file is created.
  // 
  // This method should not be subject to race conditions as
  // we use the "x" mode for fopen(), so checking the file's
  // existence and creating a new one if it doesn't should
  // be pretty close to atomic.
  $comment_file = comment_file($post, $comment["id"]);
  $fd = fopen($comment_file, "x");
  if ($fd === false) {
   if (file_exists($comment_file))
    $comment["id"] += 1;
   else
    return error(500, "There was a problem saving your comment.");
  } else break;
 }
 
 $comment_json = json_encode($comment, JSON_FORCE_OBJECT|JSON_PRETTY_PRINT);
 
 $success = fwrite($fd, $comment_json) !== false;
 if (!$success)
  return error(500, "There was a problem saving your comment.");
 
 fclose($fd);
 
 return result($comment_json, ["type" => "application/json"]);
});

if (PHP_SAPI !== "cli")
 $app->handle($config["url_prefix"]);
else {
 ini_set("error_reporting", E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED);
 ini_set("display_errors", "stderr");
 $_argc = $_SERVER["argc"]; $_argv = $_SERVER["argv"];
 
 if ($_argc < 3) {
  fwrite(STDERR, "Usage: {$_argv[0]} hash <comment>\n");
  fwrite(STDERR, "       {$_argv[0]} request <url> [method=GET] [query-string]");
  fwrite(STDERR, " [post-params]\n");
  exit(2);
 }
 $cmd = strtolower(trim($_argv[1]));
 $c = 200;
 if ($cmd === "hash") {
  if (is_file($_argv[2])) {
   $c = 200;
   echo hash_comment(json_decode(file_get_contents($_argv[2]), true));
  } else $c = 404;
 } else if ($cmd === "request") {
  $c = $app->handle_cli($_argc - 1, array_slice($_argv, 1), $config["prefix"]);
 }
 echo "\n";
 if ($c < 400)
  exit(1);
 exit(0);
}

?>
