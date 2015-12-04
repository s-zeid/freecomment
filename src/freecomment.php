<?php

/* vim: set fdm=marker: */
/* Copyright notice and X11 License {{{
   
   freecomment.php
   A minimalist blog comment system with a JavaScript frontend.
   
   Copyright (C) 2014-2015 Scott Zeid.
   https://code.s.zeid.me/freecomment
   
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

require("app.php");
require("super-mailer-bros.php");

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
   return super_mailer_bros($from_name, $from_email, $from_email, $config["notify_email"],
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
