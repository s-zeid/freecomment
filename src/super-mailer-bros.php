<?php

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
