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


/* Super Mailer Bros. 3
 * Copyright (c) 2012 Scott Zeid.  Released under the X11 License.
 * 
 * This isn't my best code, but here it is anyway.  It uses
 * imap_mail_compose() to generate the MIME code for the attachments
 * and the main message separately, and then it pastes the two together,
 * as imap_mail_compose() doesn't let you put a multipart/alternative
 * part inside a multipart/mixed, which is necessary to combine HTML
 * and plain text versions of the message and attachments and have it
 * display properly in all MUAs.  It's hackish, but it works.
 * 
 * I know I could have used PHPMailer or something, but I wanted to do
 * it myself, and besides, this comes out to be about 3.5 KB excluding
 * comments (~5 KB with comments), and it also has a funny name.
 * 
 * Requires a `file` command with the `--brief` and `--mime-type` (not
 * just `-i`) options, and PHP >= 5.1 with the IMAP extension
 * (`sudo apt-get install php5-imap`).
 * 
 */

$SMB_TYPES = array(
 "text"        => TYPETEXT,
 "multipart"   => TYPEMULTIPART,
 "message"     => TYPEMESSAGE,
 "application" => TYPEAPPLICATION,
 "audio"       => TYPEAUDIO,
 "image"       => TYPEIMAGE,
 "video"       => TYPEVIDEO,
 "other"       => TYPEOTHER
);

/* Most arguments are self-explanatory.  $uploads is an associative array
 * of attachments in the same format as $_FILES.  $max_body_size refers to
 * the total size of the MIME body after processing, right before mail()
 * is called.  $to should be a plain e-mail address.
 * 
 * The message will have the user's IP address prepended to the beginning,
 * and the HTML version will have a link to a WHOIS lookup of the IP
 * address (currently using bgp.he.net).  The text "(Sent by Super Mailer
 * Bros. 3)" will be appended to the end of the message.
 * 
 * Returns true on success or false if mail() fails.  If one or more
 * arguments failed to validate, it returns an indexed array containing
 * the name(s) of the argument(s).  If an individual file exceeds
 * $max_file_size, the array will also contain "file_size", and if the
 * size of the MIME body exceeds $max_body_size, then the array will
 * contain *only* "body_size".
 * 
 */
function super_mailer_bros($from_name, $from_email, $to, $subject,
                           $message, $uploads=array(), $max_file_size=0,
                           $max_body_size=0) {
 global $SMB_TYPES;
 $file_sizes_ok = true;
 $attachments = array();
 foreach ($uploads as $field => $file) {
  if ($file["tmp_name"]) {
   if ($max_file_size)
    $file_sizes_ok = $file_sizes_ok && ($file["size"] <= $max_file_size);
   $filename = basename($file["name"]);
   $mime = explode("/", smb_mime($file["tmp_name"]));
   $attachments[] = array(
    "type" => $SMB_TYPES[$mime[0]], "subtype" => $mime[1],
    "encoding" => ENCBINARY, "description" => $filename,
    "disposition.type" => "attachment",
    "disposition" => array("filename" => $filename),
    "type.parameters" => array("name" => $filename),
    "contents.data" => file_get_contents($file["tmp_name"])
   );
  }
 }
 
 if ($from_name && strpos($from_email, "@") !== false &&
     $subject && $message && $file_sizes_ok) {
  $headers = array(
   "from" => "$from_name <$from_email>",
   "custom_headers" => array(
    "X-Mailer: Super Mailer Bros./3.0-bnay-6"
   )
  );
  $content_array = array(
   array( "type" => TYPEMULTIPART, "subtype" => "alternative" ),
   array(
    "type" => "text", "subtype" => "plain", charset => "utf8",
    "contents.data" =>
     "IP Address:  {$_SERVER["REMOTE_ADDR"]}\r\n\r\n{$message}\r\n\r\n"
     ."(Sent by Super Mailer Bros. 3)\r\n"
   ),
   array(
    "type" => "text", "subtype" => "html", charset => "utf8",
    "contents.data" =>
     "<p><strong>IP Address:</strong>&nbsp; "
     ."<a href=\"http://bgp.he.net/ip/{$_SERVER["REMOTE_ADDR"]}#_whois\">"
     ."{$_SERVER["REMOTE_ADDR"]}</a>"
     ."</p>\r\n\r\n"
     ."<pre style='font-size: medium;'>".smb_esc($message)."</pre>\r\n\r\n"
     ."<p style='font-size: smaller;'>\r\n"
     ." (Sent by Super Mailer Bros. 3)\r\n"
     ."</p>\r\n"
   )
  );
  if ($attachments) {
   $content = explode("\r\n", imap_mail_compose(array(), $content_array), 2);
   $content = $content[1];
   $parts = explode("\r\n\r\n", imap_mail_compose($headers, array_merge(array(
    array( "type" => TYPEMULTIPART, "subtype" => "mixed" ),
   ), $attachments)), 2);
   $headers    = $parts[0];
   $body_parts = explode("\r\n", $parts[1], 2);
   // $body_parts[0] = MIME boundary; [1] = attachments
   $body      = $body_parts[0]."\r\n".$content."\r\n";
   $body     .= $body_parts[0]."\r\n".$body_parts[1];
  } else { // no attachments
   $parts     = explode("\r\n\r\n", imap_mail_compose($headers, $content_array), 2);
   $headers   = $parts[0];
   $body      = $parts[1];
  }
  if ($max_body_size && strlen($body) > $max_body_size)
   return array("body_size");
  return mail($to, $subject, $body, $headers);
 } else {
  $failed = array();
  $vars = explode(",","from_name,from_email,to,subject,message");
  foreach($vars as $var) {
   if (!$$var) $failed[] = $var;
  }
  if (!$file_sizes_ok)
   $failed[] = "file_size";
  return $failed;
 }
}

function smb_esc($s) {
 return htmlentities($s, ENT_QUOTES, "UTF-8");
}

function smb_mime($f) {
 return trim(exec("file --brief --mime-type ".escapeshellarg($f)));
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




$config = [
 "comments" => "comments",
 "url_prefix" => "",
 "akismet" => "",
 "blog_url" => "",
 "notify_email" => "",
 "notify_from" => "freecomment <".$_SERVER["USER"]."@".gethostname().">",
 "notify_subject" => "New comment on \"%s\"",
];

if (is_file("freecomment.conf"))
 $config = array_merge($config, parse_ini_file("freecomment.conf"));

// HTTP routes ///////////////////////////////////////////////////////////

$app = new App("freecomment.php");

$app->get(["/comments/:post", "/comments/:post/"], function($params) {
 global $config;
 $post = new Post(sanitize($params["post"]));
 $comments = $post->comments();
 
 if (!$post->is_enabled() ||$comments === null)
  return error(404, "Comments are disabled for this post.");
 else
  $comments = array_map(function($c) { return $c->data(); }, $comments);
 
 $r = ["post" => $post->name, "open" => $post->is_open(), "comments" => $comments];
 return result($r, ["json-options" => JSON_PRETTY_PRINT]);
});

$app->get("/comments/:post/:comment", function($params) {
 global $config;
 $post = new Post(sanitize($params["post"]));
 
 if (!$post->is_enabled())
  return error(404, "Comments are disabled for this post.");
 
 $comment = $post->comment(sanitize($params["comment"]));
 if ($comment->exists()) {
  if (is_array($comment->data()))
   return result($comment->data(), ["json-options" => JSON_PRETTY_PRINT]);
  else {
   $error = $comment->error;
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

$app->post("/comments/:post/new", function($params, $_get, $_post) {
 global $config;
 $post = new Post(sanitize($params["post"]));
 if (!$post->is_enabled())
  return error(404, "Comments are disabled for this post.");
 if (!$post->is_open())
  return error(403, "Comments are closed for this post.");
 if (!trim($_post["body"]))
  return error(400, "The comment may not be empty.");
 
 $email = (isset($_post["email"])) ? $_post["email"] : "";
 $post_url = (isset($_post["post_url"])) ? $_post["post_url"] : "";
 $post_title = (isset($_post["post_title"])) ? $_post["post_title"] : "";
 
 $comment = $post->comment(null, [
  "id" => null,
  "hash" => null,
  "time" => "".time(),
  "post" => null,
  "author" => (isset($_post["author"])) ? $_post["author"] : "",
  "gravatar" => md5(strtolower(trim($email))),
  "website" => (isset($_post["website"])) ? $_post["website"] : "",
  "body" => $_post["body"]
 ]);
 
 if (!$comment->akismet_check($email, $post_url))
  return error(500, "There was a problem saving your comment.");
 
 if (!$comment->save())
  return error(500, "There was a problem saving your comment.");
 
 $comment->notify($post_url, $post_title);
 
 return result($comment->data(), ["json-options" => JSON_FORCE_OBJECT|JSON_PRETTY_PRINT]);
});

// Data types ////////////////////////////////////////////////////////////

class Post {
 public function __construct($name) {
  $this->name = sanitize($name);
 }
 public function comment($id, $data = null) {
  return new Comment($this, sanitize($id), $data);
 }
 public function dir() {
  global $config;
  $dir = "{$config["comments"]}/{$this->name}";
  if (is_dir($dir))
   return $dir;
  return null;
 }
 public function comments() {
  if (is_dir($this->dir())) {
   $comments = [];
   $post_files = scandir($this->dir());
   sort($post_files, SORT_NATURAL);
   foreach ($post_files as $comment_file) {
    if ($comment_file !== "." && $comment_file !== ".." &&
        strpos($comment_file, ".") !== 0) {
     $comment = $this->comment($comment_file);
     if (!$comment->error)
      $comments[] = $comment;
    }
   }
   return $comments;
  }
  return null;
 }
 public function greatest_comment_id() {
  $comment_files = scandir($this->dir());
  sort($comment_files, SORT_NATURAL);  // natsort() keeps the key numbers
  for ($i = count($comment_files) - 1; $i >= 0; $i--) {
   $match = [];
   if (preg_match("/^([0-9]+)/", $comment_files[$i], $match))
    return (int) $match[0];
  }
  return 0;
 }
 public function is_enabled() {
  return is_dir($this->dir());
 }
 public function is_open() {
  return $this->is_enabled() && !file_exists((new Comment($this, "closed"))->file());
 }
}

define("FREECOMMENT_DEFAULT_HASH_ALGORITHM", "sha1");

class Comment {
 public function __construct($post, $id, $data = null) {
  $this->post = $post;
  $this->id = sanitize($id);
  $this->__data = $data;
  $this->error = null;
  if ($data === null && file_exists($this->file())) {
   $this->__data = json_decode(file_get_contents($this->file()), true);
   $this->data();  // sanitize data
  }
  if (!is_array($this->__data)) {
   $this->error = $this->__data;
   $this->__data = null;
  }
 }
 public function data($key = null, $value = null) {
  if (!is_array($this->__data))
   return null;
  $this->__data["id"] = sanitize($this->id);
  if ($this->post !== null)
   $this->__data["post"] = sanitize($this->post->name);
  if ($key === null)
   return $this->__data;
  else if (func_num_args() < 2)
   return $this->__data[$key];
  else
   $this->__data[$key] = $value;
 }
 public function dir() {
  if ($this->post !== null)
   return $this->post->dir();
 }
 public function exists() {
  return file_exists($this->file());
 }
 public function file() {
  $post_dir = $this->dir();
  if ($post_dir === null || $this->id === null)
   return null;
  return "$post_dir/{$this->id}";
 }
 public function hash($algorithm = FREECOMMENT_DEFAULT_HASH_ALGORITHM) {
  $keys = array_keys($this->data());
  $values = [];
  sort($keys);
  foreach ($keys as $k) {
   if ($k !== "id" && $k !== "hash")
    $values[] = $this->data($k);
  }
  $this->data("hash", $algorithm.":".hash($algorithm, implode("\0", $values)));
  return $this->data("hash");
 }
 public function notify($post_url = null, $post_title = null) {
  global $config;
  if (!empty($config["notify_email"])) {
   $from_name = "freecomment";
   $from_email = $_SERVER["USER"]."@".gethostname();
   $matches = [];
   preg_match('/^([^<]+)?<?([^>]+)>?$/', $config["notify_from"], $matches);
   if (count($matches) >= 3) {
    $matches[1] = trim($matches[1]);
    $from_name = (!empty($matches[1])) ? $matches[1] : $from_name;
    $from_email = (!empty($matches[2])) ? $matches[2] : $from_email;
   }
   $post_title = (!empty($post_title)) ? $post_title : $this->data("post");
   $post_url = (!empty($post_url)) ? preg_replace('/#.*$/', "", $post_url) : "";
   $comment_url = (!empty($post_url)) ? $post_url."#freecomment-".$this->data("id") : "";
   $comment_author = $this->data("author");
   $comment_website = $this->data("website");
   $comment_body = $this->data("body");
   return super_mailer_bros($from_name, $from_email, $config["notify_email"],
                            str_replace("%s", $post_title, $config["notify_subject"]),
                            "A new comment has been made on \"{$post_title}\""
                            .(!empty($comment_url)?" (<$comment_url>)":"")
                            .":\n\n"
                            .(!empty($comment_author)?"Name: {$comment_author}\n":"")
                            .(!empty($comment_website)?"Website: {$comment_website}\n":"")
                            ."\n{$comment_body}");
  }
  return true;
 }
 public function save() {
  if (is_array($this->data())) {
   $this->hash();
   
   if ($this->post === null)
    return false;
   
   if ($this->data("id") == null) {
    // Make a new comment with an auto-incremented ID
    $old_id = $this->data("id");
    $this->id = $this->__data["id"] = $this->post->greatest_comment_id() + 1;
    
    while (true) {
     // Try to open a new file for saving the comment.
     // If the file already exists, increment the comment ID and try
     // again until a new file is created.
     // 
     // This method should not be subject to race conditions as
     // we use the "x" mode for fopen(), so checking the file's
     // existence and creating a new one if it doesn't should
     // be pretty close to atomic.
     $comment_file = $this->file();
     $fd = fopen($comment_file, "x");
     if ($fd === false) {
      if (file_exists($comment_file))
       $this->id = $this->__data["id"] += 1;
      else {
       $this->id = $this->__data["id"] = $old_id;
       return false;
      }
     } else break;
    }
    $this->id = $this->data("id");
   } else
    $fd = fopen($this->file(), "w");
   
   if ($fd === false)
    return false;
   
   if (fwrite($fd, json_encode($this->data(), JSON_PRETTY_PRINT)) === false)
    return false;
  
   fclose($fd);
   return true;
  }
  return false;
 }
 public function akismet_check($email, $post_url) {
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
   "comment_author" => $this->data("author"),
   "comment_author_email" => $email,
   "comment_author_url" => $this->data("website"),
   "comment_content" => $this->data("body"),
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
}

// Utility functions /////////////////////////////////////////////////////

function error($code = 404, $message = "") {
 return result(["error" => $message, "code" => $code], ["code" => $code]);
}

function sanitize($path) {
 $path = basename(str_replace("\\", "/", $path));
 if ($path === "." || $path === "..")
  return "";
 return $path;
}

// Entry point ///////////////////////////////////////////////////////////

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
   echo (new Comment(null, null, json_decode(file_get_contents($_argv[2]), true)))->hash();
  } else $c = 404;
 } else if ($cmd === "request") {
  $c = $app->handle_cli($_argc - 1, array_slice($_argv, 1), $config["prefix"]);
 }
 echo "\n";
 if ($c >= 400)
  exit(1);
 exit(0);
}

?>
