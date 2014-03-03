/* vim: set fdm=marker: */
/* Copyright notice and X11 License {{{
   
   freecomment.js
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

;(function(exports) {
 // CSS {{{1
 var css = "";
 css += ".freecomment-list      { margin: 0; padding: 0; }\n";
 css += ".freecomment-comment   { list-style-type: none; margin-bottom: 1.5em;\n";
 css += "                         padding: .25em; }\n";
 css += ".freecomment-highlight { background-color: #ffffcc; }\n";
 css += ".freecomment-avatar    { vertical-align: middle; margin-right: 1em; }\n";
 css += ".freecomment-info      { vertical-align: middle; display: inline-block; }\n";
 css += ".freecomment-author    { font-weight: bold; }\n";
 css += ".freecomment-time      { display: block; font-size: smaller; }\n";
 css += ".freecomment-time > a  { color: inherit; text-decoration: none; }\n";
 css += ".freecomment-time > a:hover { text-decoration: underline; }\n";
 css += ".freecomment-body      { margin-left: __avatarSize__px; padding-left: 1em; }\n";
 css += ".freecomment-body p:last-child { margin-bottom: 0; }\n";
 css += "\n";
 css += ".freecomment-form                 { display: table; }\n";
 css += ".freecomment-form label           { display: table-row; }\n";
 css += ".freecomment-form label > *       { display: table-cell; margin-bottom: 1em; }\n";
 css += ".freecomment-form label > span    { padding-right: .5em; text-align: right; }\n";
 css += ".freecomment-form-body > textarea { width: 32em; height: 16em; }\n";
 css += ".freecomment-form-submit .freecomment-error      { display: inline; }\n";
 css += ".freecomment-form-submit .freecomment-form-error { display: inline; }\n";
 css += ".freecomment-form-submit .freecomment-form-error { margin-left: .5em; }\n";
 
 // Public API {{{1
 
 function freecomment(endpoint, post, postTitle, options) {
  if (!(this instanceof arguments.callee))
   return new arguments.callee(endpoint, post, postTitle, options);
  
  if (typeof(options) !== "object") options = {};
  
  this.css = css;
  this.components = components(this);
  
  this.endpoint = endpoint;
  this.post = post;
  this.postTitle = postTitle;
  this.open = null;
  this.comments = null;
  
  this.anonymousName = (options.anonymousName!=null) ? options.anonymousName : "Anonymous";
  this.avatarSize = (options.avatarSize != null) ? options.avatarSize : 48;
  this.formatter = options.formatter || this.defaultFormatter;
  this.highlight = toArray(options.highlight || [], function(s) {return s.toLowerCase();});
  this.html5 = (options.html5 != null) ? options.html5 : false;
  
  return this;
 };
 
 freecomment.prototype.defaultFormatter = function(s) {
  var el = document.createElement("div");
  var ps = s.replace("\r\n", "\n").replace("\r", "\n").split("\n\n");
  for (var i = 0; i < ps.length; i++) {
   var p = document.createElement("p");
   p.innerHTML = stripHTML(ps[i]);
   el.appendChild(p);
  }
  return el.innerHTML;
 };
 
 freecomment.prototype.endpointURL = function(path) {
  if (!path.startsWith("/"))
   path = "/" + path;
  
  if (this.endpoint.endsWith("/"))
   return this.endpoint.substr(0, this.endpoint.length - 1) + path
  else if (this.endpoint.endsWith("?"))
   return this.endpoint + path;
  else
   return this.endpoint + "?" + path;
 };
 
 freecomment.prototype.request = function(method, url, params, success, error) {
  var request = new XMLHttpRequest();
  request.open(method.toUpperCase(), this.endpointURL(url), true);
  request.onreadystatechange = function() {
   if (this.readyState === 4) {
    var response = this.responseText;
    try { response = JSON.parse(response) } catch (e) {}
    if (this.status >= 200 && this.status < 400)
     success(response);
    else
     error(response);
   }
  }
  var data = null;
  if (typeof(params) === "object") {
   request.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
   var data = "";
   for (var k in params) {
    if (params.hasOwnProperty(k))
     data += "&" + encodeURIComponent(k) + "=" + encodeURIComponent(params[k]);
   }
   data = data.substr(1);
  }
  request.send(data);
  request = null;
 };
 
 freecomment.prototype.load = function(element) {
  if (typeof(element) === "string")
   element = document.getElementById(element);
  
  element.freecomment = this;
  
  if (!document.getElementById("freecomment-css")) {
   var css = this.css;
   if (Number(this.avatarSize) == Number(this.avatarSize))
    css = css.replace("__avatarSize__", String(Number(this.avatarSize)));
   else
    css = css.replace("__avatarSize__", "0");
   var style = document.createElement("style");
   style.setAttribute("id", "freecomment-css");
   style.setAttribute("type", "text/css");
   style.innerHTML = css;
   if (document.head.hasChildNodes())
    document.head.insertBefore(style, document.head.firstChild);
   else
    document.head.appendChild(style);
  }
  
  var _this = this;
  this.request("GET", "comments/" + this.post, null,
   function(postData) {
    _this.postData = postData;
    _this.open = postData["open"];
    _this.comments = postData["comments"];
    element.appendChild(_this.components.main(postData));
    _this.scrollToHash();
   },
   function(error) {
    _this.postData = error;
    _this.open = false;
    _this.comments = false;
    element.appendChild(_this.components.main(error));
    _this.scrollToHash();
   }
  );
 };
 
 freecomment.prototype.scrollToHash = function() {
  if (document.location.hash && document.location.hash.startsWith("#freecomment-")) {
   var el = document.getElementById(document.location.hash.substr(1));
   if (el && el.scrollIntoView)
    el.scrollIntoView(true);
  }
 }
 
 // Utility functions {{{1
 
 function dateToLocalISOString(date, friendly) {
  function pad(number) {
   var r = String(number);
   if (r.length === 1)
    r = '0' + r;
   return r;
  }
  
  if (typeof(friendly) === "undefined") friendly = false;
  if (!date)
   date = new Date();
  if (typeof(date) === "string") {
   if (Number(date) == Number(date))
    date = Number(date);
   else
    date = new Date(date);
  }
  if (typeof(date) === "number")
   date = new Date(date * 1000);
  var tzo = date.getTimezoneOffset();
  return date.getFullYear()
          + '-' + pad(date.getMonth() + 1)
          + '-' + pad(date.getDate())
          + (friendly ? " " : "T") + pad(date.getHours())
          + ':' + pad(date.getMinutes())
          + ':' + pad(date.getSeconds())
          + (friendly?"":'.'+String((date.getMilliseconds()/1000).toFixed(3)).slice(2,5))
          + (friendly ? "" : ((tzo === 0) ? "Z" : (
           ((tzo > 0) ? "-" : "+")
           + pad(tzo / 60) + ":" + pad(tzo % 60)
          )))
 };
 
 function gravatarURL(gravatar, size) {
  if (typeof(size) === "undefined") size = this.avatarSize;
  var url = "https://secure.gravatar.com/avatar/" + stripHTML(gravatar);
  if (Number(size) == Number(size)) url += "?s=" + Number(size);
  return url;
 };
 
 function stripHTML(html) {
  return html.replace("&", "&amp;").replace("<", "&lt;").replace(">", "&gt;");
 }
 
 function toArray(v, filter) {
  if (typeOf(filter) === "undefined") filter = null;
  if (typeOf(v) !== "Array")
   v = [v];
  if (filter != null) {
   var vOld = v;
   v = [];
   for (var i = 0; i < vOld.length; i++)
    v[i] = filter(vOld[i], i);
  }
  return v;
 }
 
 function typeOf(o) {
  if (typeof(o) == "undefined")
   return "undefined";
  if (o === null)
   return "null";
  return Object.prototype.toString.apply(o)
          .replace(/^\[object /i, "")
          .replace(/\]$/, "")
 }
 
 // DOM generators {{{1
 
 var _components = {
  "error": function(json, defaultText) {
   if (typeof(defaultText) === "undefined") defaultText = "An unknown error occurred.";
   var errorStr = defaultText;
   if (typeof(json) === "object" && json["error"])
    errorStr = stripHTML(json["error"]);
   var errorDiv = document.createElement("div");
   errorDiv.setAttribute("class", "freecomment-error");
   errorDiv.innerHTML = stripHTML(errorStr);
   return errorDiv;
  },
  
  "main": function(postData) {
   var root = document.createElement("div");
   root.setAttribute("class", "freecomment-root");
   
   if (postData["comments"]) {
    var ul = document.createElement("ul");
    ul.setAttribute("class", "freecomment-list");
    root.appendChild(ul);
    
    for (var i = 0; i < postData["comments"].length; i++)
     ul.appendChild(this.components.comment(postData["comments"][i]));
    
    root.appendChild(this.components.form(postData["open"], ul));
   } else {
    var defaultErrorText = "There was an error loading the comments for this post.";
    root.appendChild(this.components.error(postData, defaultErrorText));
   }
   
   return root;
  },
  
  "comment": function(comment) {
   var li = document.createElement("li");
   li.setAttribute("class", "freecomment-comment");
   li.setAttribute("id", stripHTML("freecomment-" + comment["id"]));
   li.setAttribute("data-gravatar", stripHTML(comment["gravatar"].toLowerCase()));
   
   if (this.highlight.indexOf(stripHTML(comment["gravatar"].toLowerCase())) > -1)
    li.setAttribute("class", li.getAttribute("class") + " freecomment-highlight");
   
   var header = document.createElement(this.html5 ? "header" : "div");
   header.setAttribute("class", "freecomment-header");
   li.appendChild(header);
   
   var img = document.createElement("img");
   img.setAttribute("class", "freecomment-avatar");
   img.setAttribute("alt", "");
   img.setAttribute("src", gravatarURL(stripHTML(comment["gravatar"]), this.avatarSize));
   header.appendChild(img);
   
   var info = document.createElement("div");
   info.setAttribute("class", "freecomment-info");
   header.appendChild(info);
   
   var author = document.createElement("div");
   author.setAttribute("class", "freecomment-author");
   info.appendChild(author);
   
   var authorName = stripHTML(comment["author"]).trim();
   if (!authorName)
    authorName = stripHTML(this.anonymousName);
   
   if (comment["website"]) {
    var authorA = document.createElement("a");
    authorA.setAttribute("href", stripHTML(comment["website"]));
    authorA.setAttribute("target", "_blank");
    authorA.innerHTML = stripHTML(authorName);
    author.appendChild(authorA);
   } else
    author.innerHTML = stripHTML(authorName);
   
   var time = document.createElement(this.html5 ? "time" : "div");
   time.setAttribute("class", "freecomment-time");
   info.appendChild(time);
   if (this.html5)
    time.setAttribute("datetime", dateToLocalISOString(stripHTML(comment["time"])));
   
   var timeA = document.createElement("a");
   timeA.setAttribute("href", "#" + li.getAttribute("id"));
   timeA.innerHTML = dateToLocalISOString(stripHTML(comment["time"]), " ");
   time.appendChild(timeA);
   
   var body = document.createElement(this.html5 ? "article" : "div");
   body.setAttribute("class", "freecomment-body");
   body.innerHTML = this.formatter(comment["body"]);  // the formatter strips HTML
   li.appendChild(body);
   
   return li;
  },
  
  "form": function(open, ul) {
   var _this = this;
   
   var form = document.createElement(open ? "form" : "div");
   form.setAttribute("class", "freecomment-form");
   
   if (open) {
    form.setAttribute("method", "post");
    form.setAttribute("action", "javascript:;");
    
    form.appendChild(this.components.formField("author", "Name"));
    form.appendChild(this.components.formField("email", "Email"));
    form.appendChild(this.components.formField("website", "Website"));
    form.appendChild(this.components.formField("body", "", "textarea"));
    
    var submit = document.createElement("input");
    submit.setAttribute("class", "freecomment-form-submit");
    submit.setAttribute("type", "submit");
    submit.setAttribute("name", "submit");
    submit.setAttribute("value", "Submit");
    
    var submitDiv = document.createElement("div");
    submitDiv.appendChild(submit);
    
    form.appendChild(this.components.formField("submit", "", submitDiv));
    
    var errorEl = document.createElement("div");
    errorEl.setAttribute("class", "freecomment-form-error");
    submitDiv.appendChild(errorEl);
    
    submit.onclick = function() {
     if (errorEl.hasChildNodes) {
      for (var i = 0; i < errorEl.childNodes.length; i++)
       errorEl.removeChild(errorEl.childNodes[i]);
     }
     _this.request("POST", "comments/" + _this.post + "/new",
      {
        "post_url": document.location.href,
        "post_title": _this.postTitle,
        "author": form.author.value,
        "email": form.email.value,
        "website": form.website.value,
        "body": form.body.value,
      },
      function(comment) {
       form.reset();
       if (ul && ul.appendChild) {
        var li = _this.components.comment(comment);
        ul.appendChild(li);
        li.scrollIntoView();
       } else {
        errorEl.appendChild(_this.components.error(
         {"error": "Your comment has been saved.  Reload the page to see it."}
        ));
       }
      },
      function(error) {
       errorEl.appendChild(_this.components.error(error));
      }
     );
    }
   } else {
    var errorEl = document.createElement("p");
    errorEl.setAttribute("class", "freecomment-form-error");
    form.appendChild(errorEl);
    
    errorEl.innerHTML = "Comments for this post are closed.";
   }
   
   return form;
  },
  
  "formField": function(field, label, element) {
   if (typeof(element) === "undefined") element = "input";
   
   var labelEl = document.createElement("label");
   labelEl.setAttribute("class", "freecomment-form-" + field);
   
   var span = document.createElement("span");
   span.innerHTML = stripHTML(label);
   labelEl.appendChild(span);
   
   var fieldEl;
   if (typeof(element) === "string") {
    fieldEl = document.createElement(element);
    fieldEl.setAttribute("name", field);
    if (element == "input")
     fieldEl.setAttribute("type", "text");
   } else
    fieldEl = element;
   labelEl.appendChild(fieldEl);
   
   return labelEl;
  },
 };
 
 function components(_this) {
  var ret = {};
  for (var i in _components) {
   if (_components.hasOwnProperty(i))
    ret[i] = (function(x) {
     return function() { return _components[x].apply(_this, arguments); }
    })(i);
  }
  return ret;
 }
 
 //}}}
 exports.freecomment = freecomment;
})((typeof(top) == "object") ? window : ((typeof(exports) == "object") ? exports : {}));
