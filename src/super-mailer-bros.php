<?php

/* Super Mailer Bros. 3
 * Copyright (c) 2012-2015 Scott Zeid.  Released under the X11 License.
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
 * it myself, and besides, this comes out to be about 4 KB excluding
 * comments (~7 KB with comments), and it also has a funny name.
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

/* Most arguments are self-explanatory.
 * 
 * $send_from is the address to use in the From header.  If $from_email
 * is user-provided, then $send_from should be an address on a domain you
 * control in order to prevent messages from being eaten by hungry spam
 * filters, although you can set it to null to make it use the $from_email
 * address instead.  If $send_from contains a plus sign right before the at
 * sign, then a random hexadecimal number will be inserted between the plus
 * and at signs.  This prevents similar messages from different senders from
 * being grouped together in various threaded email clients.  $from_email,
 * $send_from, and $to should be plain email addresses
 * 
 * $uploads is an associative array of attachments in the same format as
 * $_FILES.  $max_body_size refers to the total size of the MIME body after
 * processing, right before mail() is called.
 * 
 * The message will include the user's IP address and email address at the
 * end, and the HTML version will have a link to a WHOIS lookup of the IP
 * address (currently using bgp.he.net).  The text "Sent by Super Mailer
 * Bros. 3" will also appear at the end of the message.
 * 
 * Returns true on success or false if mail() fails.  If one or more
 * arguments failed to validate, it returns an indexed array containing
 * the name(s) of the argument(s).  If an individual file exceeds
 * $max_file_size, the array will also contain "file_size", and if the
 * size of the MIME body exceeds $max_body_size, then the array will
 * contain *only* "body_size".
 * 
 */
function super_mailer_bros($from_name, $from_email, $send_from, $to,
                           $subject, $message, $uploads=array(),
                           $max_file_size=0, $max_body_size=0) {
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
 
 if (empty($send_from)) {
  $send_from = $from_email;
 } else if (strpos($send_from, "+@") !== false) {
  $random_part = dechex(rand(0x10000000, 0xffffffff));
  $send_from = str_replace("+@", "+$random_part@", $send_from);
 }
 
 if ($from_name && strpos($from_email, "@") !== false &&
     $subject && $message && $file_sizes_ok) {
  $headers = array(
   "from" => "$from_name <$send_from>",
   "reply_to" => $from_email,
   "custom_headers" => array(
    "X-Mailer: Super Mailer Bros./3.0-bnay-6"
   )
  );
  $content_array = array(
   array( "type" => TYPEMULTIPART, "subtype" => "alternative" ),
   array(
    "type" => "text", "subtype" => "plain", "charset" => "utf8",
    "contents.data" => ""
     ."$message\r\n\r\n"
     ."\r\n"
     ."--\r\n"
     ."Sent by Super Mailer Bros. 3\r\n"
     ."\r\n"
     ."Sender's email address:  $from_email\r\n"
     ."Sender's IP address:  {$_SERVER["REMOTE_ADDR"]}\r\n"
   ),
   array(
    "type" => "text", "subtype" => "html", "charset" => "utf8",
    "contents.data" => ""
     ."<pre style='white-space: pre-wrap; font-family: inherit;'>"
     .  smb_esc($message)
     ."</pre>\r\n\r\n"
     ."\r\n<br />\r\n"
     ."<div style='font-size: smaller;'>\r\n"
     ." <p>\r\n"
     ."  --<br />\r\n"
     ."  Sent by Super Mailer Bros. 3\r\n"
     ." </p>\r\n"
     ." <p>\r\n"
     ."  <strong>Sender's email address:</strong>&nbsp; \r\n"
     ."  <a href=\"mailto:".smb_esc($from_email)."\">\r\n"
     ."   ".smb_esc($from_email)."\r\n"
     ."  </a>\r\n"
     ."  <br />\r\n"
     ."  <strong>Sender's IP address:</strong>&nbsp; \r\n"
     ."  <a href=\"http://bgp.he.net/ip/{$_SERVER["REMOTE_ADDR"]}#_whois\">\r\n"
     ."   {$_SERVER["REMOTE_ADDR"]}\r\n"
     ."  </a>\r\n"
     ." </p>\r\n"
     ."</div>\r\n"
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
  // when mail() uses sendmail (i.e. if we're not on Windoze) to send messages,
  // native line endings need to be used
  if (strtolower(substr(php_uname("s"), 0, 3)) !== "win")
   $body = str_replace("\r\n", PHP_EOL, $body);
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

?>
