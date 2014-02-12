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
 var css = "";
 css += ".freecomment-list     { margin: 0; padding: 0; }\n";
 css += ".freecomment-entry    { list-style-type: none; margin-bottom: 2em; }\n";
 css += ".freecomment-avatar   { vertical-align: middle; margin-right: 1em; }\n";
 css += ".freecomment-info     { vertical-align: middle; display: inline-block; }\n";
 css += ".freecomment-author   { font-weight: bold; }\n";
 css += ".freecomment-time     { font-size: smaller; }\n";
 css += ".freecomment-time > a { color: inherit; text-decoration: none; }\n";
 css += ".freecomment-time > a:hover { text-decoration: underline; }\n";
 css += ".freecomment-body     { margin-left: __avatarSize__px; padding-left: 1em; }\n";
 css += "\n";
 css += ".freecomment-form                 { display: table; }\n";
 css += ".freecomment-form label           { display: table-row; }\n";
 css += ".freecomment-form label > *       { display: table-cell; margin-bottom: 1em; }\n";
 css += ".freecomment-form label > span    { padding-right: .5em; text-align: right; }\n";
 css += ".freecomment-form-body > textarea { width: 32em; height: 16em; }\n";
 
 function freecomment(endpoint, post, options) {
  if (!(this instanceof arguments.callee))
   return new arguments.callee(endpoint, post, options);
  
  if (typeof(options) !== "object") options = {};
  
  this.css = css;
  
  this.endpoint = endpoint;
  this.post = post;
  this.open = null;
  this.comments = null;
  this.anonymousName = (options.anonymousName!=null) ? options.anonymousName : "Anonymous";
  this.avatarSize = (options.avatarSize != null) ? options.avatarSize : 48;
  this.formatter = options.formatter || this.defaultFormatter;
  this.html5 = (options.html5 != null) ? options.html5 : false;
  
  return this;
 };
 
 freecomment.prototype.stripHTML = function(html) {
  return html.replace(/<\/?[a-z][a-z0-9-]*\b[^>]*>?/gi, "");
 }
 
 freecomment.prototype.dateToLocalISOString = (function() {
  function pad(number) {
   var r = String(number);
   if (r.length === 1) {
    r = '0' + r;
   }
   return r;
  }
  return function(date, friendly) {
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
 }());
 
 freecomment.prototype.defaultFormatter = function(s) {
  var el = document.createElement("div");
  var ps = s.replace("\r\n", "\n").replace("\r", "\n").split("\n\n");
  for (var i = 0; i < ps.length; i++) {
   var p = document.createElement("p");
   p.innerHTML = this.stripHTML(ps[i]);
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
 
 freecomment.prototype.gravatarCSS = function(gravatar, size) {
  var url = this.gravatarURL(gravatar, size);
  url = url.replace("\\", "\\\\").replace("'", "\\'");
  return "url('" + url + "')";
 };
 
 freecomment.prototype.gravatarURL = function(gravatar, size) {
  if (typeof(size) === "undefined") size = this.avatarSize;
  var url = "https://secure.gravatar.com/avatar/" + this.stripHTML(gravatar);
  if (Number(size) == Number(size)) url += "?s=" + Number(size);
  return url;
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
    element.appendChild(_this.renderWidget(postData));
    _this.scrollToHash();
   },
   function(error) {
    _this.postData = error;
    _this.open = false;
    _this.comments = false;
    element.appendChild(_this.renderWidget(error));
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
 
 freecomment.prototype.renderError = function(json, defaultText) {
  if (typeof(defaultText) === "undefined") defaultText = "An unknown error occurred.";
  var errorStr = defaultText;
  if (typeof(json) === "object" && json["error"])
   errorStr = this.stripHTML(json["error"]);
  var errorDiv = document.createElement("div");
  errorDiv.setAttribute("class", "freecomment-error");
  errorDiv.innerHTML = this.stripHTML(errorStr);
  return errorDiv;
 }
 
 freecomment.prototype.renderWidget = function(postData) {
  var root = document.createElement("div");
  root.setAttribute("class", "freecomment-root");
  
  if (postData["comments"]) {
   var ul = document.createElement("ul");
   ul.setAttribute("class", "freecomment-list");
   root.appendChild(ul);
   
   for (var i = 0; i < postData["comments"].length; i++)
    ul.appendChild(this.renderComment(postData["comments"][i]));
   
   root.appendChild(this.renderForm(postData["open"], ul));
  } else {
   var defaultErrorText = "There was an error loading the comments for this post.";
   root.appendChild(this.renderError(postData, defaultErrorText));
  }
  
  return root;
 }
 
 freecomment.prototype.renderComment = function(comment) {
  var li = document.createElement("li");
  li.setAttribute("class", "freecomment-entry");
  li.setAttribute("id", this.stripHTML("freecomment-" + comment["id"]));
  
  var header = document.createElement(this.html5 ? "header" : "div");
  header.setAttribute("class", "freecomment-header");
  li.appendChild(header);
  
  var img = document.createElement("img");
  img.setAttribute("class", "freecomment-avatar");
  img.setAttribute("alt", "");
  img.setAttribute("src", this.gravatarURL(this.stripHTML(comment["gravatar"])));
  header.appendChild(img);
  
  var info = document.createElement("div");
  info.setAttribute("class", "freecomment-info");
  header.appendChild(info);
  
  var author = document.createElement("div");
  author.setAttribute("class", "freecomment-author");
  info.appendChild(author);
  
  var authorName = this.stripHTML(comment["author"]).trim();
  if (!authorName)
   authorName = this.stripHTML(this.anonymousName);
  
  if (comment["website"]) {
   var authorA = document.createElement("a");
   authorA.setAttribute("href", this.stripHTML(comment["website"]));
   authorA.setAttribute("target", "_blank");
   authorA.innerHTML = this.stripHTML(authorName);
   author.appendChild(authorA);
  } else
   author.innerHTML = this.stripHTML(authorName);
  
  var time = document.createElement(this.html5 ? "time" : "div");
  time.setAttribute("class", "freecomment-time");
  time.style.display = "block";
  time.style.fontSize = "smaller";
  info.appendChild(time);
  if (this.html5)
   time.setAttribute("datetime",
                     this.dateToLocalISOString(this.stripHTML(comment["time"])));
  
  var timeA = document.createElement("a");
  timeA.setAttribute("href", "#" + li.getAttribute("id"));
  timeA.innerHTML = this.dateToLocalISOString(this.stripHTML(comment["time"]), " ");
  time.appendChild(timeA);
  
  var body = document.createElement(this.html5 ? "article" : "div");
  body.setAttribute("class", "freecomment-body");
  body.innerHTML = this.formatter(this.stripHTML(comment["body"]));
  li.appendChild(body);
  
  return li;
 };
 
 freecomment.prototype.renderForm = function(open, ul) {
  var _this = this;
  
  var form = document.createElement(open ? "form" : "div");
  form.setAttribute("class", "freecomment-form");
  
  if (open) {
   form.setAttribute("method", "post");
   form.setAttribute("action", "javascript:;");
   
   form.appendChild(this.renderFormField("author", "Name"));
   form.appendChild(this.renderFormField("email", "Email"));
   form.appendChild(this.renderFormField("website", "Website"));
   form.appendChild(this.renderFormField("body", "", "textarea"));
   
   var submit = document.createElement("input");
   submit.setAttribute("class", "freecomment-form-submit");
   submit.setAttribute("type", "submit");
   submit.setAttribute("name", "submit");
   submit.setAttribute("value", "Submit");
   
   var submitDiv = document.createElement("div");
   submitDiv.appendChild(submit);
   
   form.appendChild(this.renderFormField("submit", "", submitDiv));
   
   var errorEl = document.createElement("p");
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
       "author": form.author.value,
       "email": form.email.value,
       "website": form.website.value,
       "body": form.body.value,
     },
     function(comment) {
      form.reset();
      if (ul && ul.appendChild) {
       var li = _this.renderComment(comment);
       ul.appendChild(li);
       li.scrollIntoView();
      } else {
       errorEl.appendChild(_this.renderError(
        {"error": "Your comment has been saved.  Reload the page to see it."}
       ));
      }
     },
     function(error) {
      errorEl.appendChild(_this.renderError(error));
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
 }
 
 freecomment.prototype.renderFormField = function(field, label, element) {
  if (typeof(element) === "undefined") element = "input";
  
  var labelEl = document.createElement("label");
  labelEl.setAttribute("class", "freecomment-form-" + field);
  
  var span = document.createElement("span");
  span.innerHTML = this.stripHTML(label);
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
 }
 
 exports.freecomment = freecomment;
})((typeof(top) == "object") ? window : ((typeof(exports) == "object") ? exports : {}));
