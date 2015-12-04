freecomment
===========

A minimalist blog comment system with a JavaScript frontend.

Copyright (C) 2014–2015 Scott Zeid.  Released under the X11 License.  
<https://code.s.zeid.me/freecomment>

* * * * *

freecomment is a minimialist blog comment system intended to replace
the likes of Disqus.  There are several reasons to not use proprietary
hosted comment services like Disqus:

* Some of these services have long loading times.
* Privacy concerns:
    * Privacy concerns regarding the comments themselves.
    * These services often require commenters to identify themselves
      with an email address or social networking account.
    * Some of these services are also advertisers or share information
      about comments or commenters with advertisers.
* The possibility that they might go out of business or otherwise stop
  hosting your blog's comments.
* The possibility that they could censor comments or blog authors.
* According to [at least one person][tildehash], Disqus doesn't allow
  users to license their comments under copyleft licenses.  This may
  also be true for other proprietary comment services.
* These services require the use of non-[free software][free-sw] in
  order to use them.
* Some comment services have terms of use that punish or ban users for
  violating unjust laws, even for violations unrelated to the services
  themselves.  Also, a comment service ought to not automatically ban
  users who have broken the law in any way, because these users still
  have the right to have their opinions heard.

[tildehash]: http://tildehash.com/?article=why-im-reinventing-disqus
[free-sw]:   https://gnu.org/philosophy/free-sw.html

freecomment has none of these issues.

Why another comment script?
---------------------------

There are already several [free software][free-sw] blog comment systems,
including:

* [talkatv](https://github.com/talkatv/talkatv) (Python)
* [isso](https://github.com/posativ/isso/) (Python)
* [Juvia](https://github.com/phusion/juvia) (Rails)
* [Debiki](http://www.debiki.com/)
* [e-comments](https://github.com/skx/e-comments/) (plain Ruby)
* [HashOver](http://www.tildehash.com/?article=why-im-reinventing-disqus) (PHP)
* [Echochamber.js](https://github.com/tessalt/echo-chamber-js) (LocalStorage)

Although you may prefer to use one of those systems instead, I find them
to be too complicated (both in frontend and in backend), and most of the
ones I listed require you to run a dedicated server process, which I don't
want to do for something as simple as a blog comment program.  So I made
my own.

Features
--------

* Minimalist CSS, JavaScript, backend code, and URLs
* No mandatory dependencies
* Gravatar support (uses HTTPS)
* No support for authentication (read:  commenters can be anonymous)
* Optional Akismet support (violates your commenters' privacy; see below)
* Flat-file comment storage
* Enabling comments for a post is as simple as making a directory
* Disabling comments for a post is as simple as adding a file called
  "closed" to that directory
* Supports modern browsers and IE8+.  To support IE < 8, provide a
  polyfill for the global `JSON` object (and maybe some other stuff,
  since IE support isn't really at the top of my priority list right
  now).

### Possible future features

* Python implementation using Flask or Bottle
* Database support?

Installation
------------

For the PHP implementation:

1.  Make sure you're running at least PHP 5.4 (I am *not* typing
    `array()` a million times :P).
2.  Drop the `freecomment.php` file somewhere in your document root.
3.  (Optional) Create a `freecomment.conf` file that looks like this:
    (each line is optional)
    
        comments = <directory in which to store comments>
        url_prefix = <prefix to ignore in URLs; useful with rewrite rules>
        akismet = <Akismet API key; omit to disable Akismet>
        blog_url = <root URL for your blog; only needed for Akismet>
        notify_email = <email to send new comments to; omit to disable>
        notify_from = <from line; defaults to "freecomment <user@hostname>">
        notify_subject = <subject line; %s is replaced with the post name>
    
4.  Insert the `freecomment.js` file into your site.
5.  Enable freecomment for your blog posts as described below.
6.  That's it!

Usage
-----

To enable comments for a blog post, add HTML to that post that looks
like this:
    
        <div id="comments"></div>
        <script type="text/javascript">
         freecomment("http://example.com/freecomment.php",
                     "<post identifier>",
                     "<post title>"
                    ).load("comments");
        </script>

Also make sure that `freecomment.js` has been included in the page.

The post identifier can be anything you want, but it must be unique
and have no slashes or illegal filesystem characters.

Optionally, an object containing options may be passed to `freecomment()`
as the fourth argument.  See the "Advanced usage" section below for more
details.

### Markdown

For Markdown support, you can use a third-party Markdown library,
like markdown.js:

1.  Download the latest `markdown-browser-x.y.z.tgz` from
    <https://github.com/evilstreak/markdown-js/releases>.
2.  Extract `markdown.min.js` and put it somewhere in your document
    root.
3.  Include it on the page.
4.  When calling `freecomment()`, use this option:
    
        "formatter": function(s) { return markdown.toHTML(s); }

### Akismet

Enabling Akismet is as simple as setting the `akismet` key in your
`freecomment.conf` file to your Akismet API key.  You will also
need to set `blog_url` to the root URL for your blog.

Do be aware that if you have Akismet enabled, your commenters' IP
addresses, user agent strings, referer headers, and email addresses
will be sent to Automattic, in addition to the actual comments
themselves.  You should disclose this to your readers, and depending
on where you are located, you may be legally required to.

### Advanced usage

In the example above, `load()` takes an element ID.  You can also
pass in an actual DOM element.

The `freecomment()` function takes an optional fourth argument,
which is a set of options.  Available options are:

* `anonymousName`  
  The name to use for anonymous commenters.  The default is "Anonymous".
* `avatarSize`  
  The size in pixels of the Gravatars.  The default is 48 pixels.
* `formatter`  
  A function to use to format the comments.  This should take one
  argument, the raw comment text, and return the formatted text as
  HTML.  The default is to just use the raw comment text mostly
  as-is, but to escape HTML special characters and convert
  paragraphs to `<p>` elements.  **Your formatter should make sure
  that any HTML in the input is escaped or removed entirely.**
* `highlight`  
  One or more Gravatar IDs whose comments should be highlighted.  If
  you only want to highlight one ID's comments, this may be a string
  or an array; otherwise, it must be an array.  The highlighting style
  can be customized via the CSS class `.freecomment-highlight`.
* `html5`  
  If true, HTML 5 semantic elements (e.g. `header` and `article`) will
  be used.  The default is to not use them.
