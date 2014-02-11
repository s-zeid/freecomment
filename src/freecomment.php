<?php /**/

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

require("app.php");

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
 
 $comment["hash"] = hash_comment($comment);
 $comment["id"] = $comment["time"]."-".$comment["hash"];
 $comment["hash"] = hash_comment().":".$comment["hash"];
 $comment_file = comment_file($post, $comment["id"]);
 
 $comment_json = json_encode($comment, JSON_FORCE_OBJECT|JSON_PRETTY_PRINT);
 
 $success = file_put_contents($comment_file, $comment_json) !== false;
 if (!$success)
  return error(500, "There was a problem saving your comment.");
 
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
