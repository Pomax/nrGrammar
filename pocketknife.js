/**

  This is a tiny "I don't need the full jQuery API"
  library. It has a small API, and acts more as a
  JS API enrichment than a "library". You don't call
  functions on $ or Pocketknife or something, you just
  call functions in global scope or on HTML elements
  and arrays. If that seems bad, don't use this.

**/
(function(_w, _d) {
  "use strict";

  /**
   * Give things a foreach.
   */
  NodeList.prototype.forEach =
  HTMLCollection.prototype.forEach =
  Array.prototype.forEach;

  /**
   * Pocketknife object, for accessing the update() function
   */
  var Pocketknife = {
    version: "2013.05.19"
  };

  /**
   * bind Pocketknife object
   */
  _w.Pocketknife = Pocketknife;

  /**
   * Also set up a "does thing exist?" evaluation function
   */
  _w.exists = (function(undef) {
    return function(thing) {
      return (thing !== undef) && (thing !== null);
    };
  }());

  /**
   * And a simplified AJAX API. If called with a callback,
   * it's async. If not, it's synchronouse, returning the
   * data obtained through the request. BASED ON XHR2
   */
  (function setupXHR(){
    var doXHR = function(method,url,data,callback) {
      var xhr = new XMLHttpRequest(),
          async = exists(callback);
      xhr.open(method, url, async);
      if(async) {
        xhr.withCredentials = true;
        xhr.onreadystatechange = function() {
          if(xhr.readyState===4 && (xhr.status===200||xhr.status===0)) {
            callback(xhr);
          }
        };
      }
      if(data) {
        var fd = new FormData(), name;
        for(name in Object.keys(data)) {
          fd.append(name,data[name]); }
        data = fd; }
      xhr.send(data);
      if(!async) { return xhr.responseText; }
    };
    _w.get = function(url, callback) { return doXHR("GET", url, null, callback); };
    _w.post = function(url, data, callback) { return doXHR("POST", url, data, callback); };
  }(_w));

  /**
   * Extend window so that there is a "create" function that we
   * can use instead of the limited document.createElement().
   */
  _w.create = function(tagname, attributes, content) {
    var element = _d.createElement(tagname),
        property;
    // element attributes
    if(typeof attributes == "object") {
      for(property in attributes) {
        if(attributes.hasOwnProperty(property)) {
          element.setAttribute(property, attributes[property]);
        }
      }
    }
    if (typeof attributes === "string") { content = attributes; }
    if(content) { element.innerHTML = content; }
    return element;
  };

  /**
   * First off, we replace querySelector/querySelectorAll with "find".
   * In part to homogenise the API, in part because NodeList is an
   * utterly useless thing to work with, compared to arrays.
   */
  var find = function(context, selector) {
    var nodelist = context.querySelectorAll(selector),
        elements = [];
    if (nodelist.length === 0) {
      return [];
    }
    if (nodelist.length === 1) {
      return nodelist[0];
    }
    for(var i = 0, last = nodelist.length; i < last; i++) {
      elements[i] = nodelist[i];
    }
    return elements;
  };

  /**
   * The global implementation of "find" uses the current document.
   */
  _w.find = function(selector, _) {
    var context = _d;
    if (_) {
      context = create("section",selector);
      selector = _;
    }
    return find(context, selector);
  };


/*************************************************************************

  The API, callable on both HTML elements and arrays:

      find, html, position,
      css, show, toggle,
      classes().{add, remove, contains},
      parent, add, replace, remove, clear,
      get, set,
      listen, listenOnce,
      forEach

*************************************************************************/


  var hiderule = "data-pocketknife-hidden";
  var classesName = "内組";


/*************************************************************************/

  /**
   * No browsers offers a simple way to find out which functions will
   * fire on an element, and for which event. Let's change that.
   */
  var EventListeners = function(owner) {
    this.owner = owner;
    this.events = [];
    this.listeners = {};
  };

  EventListeners.prototype = {
    record: function(evt, fn) {
      this.events.pushUnique(evt);
      if (!exists(this.listeners[evt])) {
        this.listeners[evt] = [];
      }
      this.listeners[evt].push(fn);
    },
    ignore: function(evt, fn) {
      var pos = this.listeners[evt].indexOf(fn);
      this.listeners[evt].splice(pos, 1);
    }
  };

  /**
   * Not all browsers support .classList, and even those that do
   * don't let us decorate them to make them chaining functions,
   * so: too bad, so sad, and we implement our own class list.
   */
  var ClassList = function(owner) {
    this.owner = owner;
    var classAttr = owner.getAttribute("class");
    this.classes = (!classAttr ? [] : classAttr.split(/\s+/));
    this.length = 0;
  };

  ClassList.prototype = {
    classes: [],
    __update: function() {
      if(this.classes.length === 0) { this.owner.removeAttribute("class"); }
      else { this.owner.setAttribute("class", this.classes.join(" ")); }
      this.length = this.classes.length;
    },
    add: function(clstring) {
      if(this.classes.indexOf(clstring)===-1) {
        this.classes.push(clstring);
      }
      this.__update();
      return this.owner;
    },
    remove: function(clstring) {
      var pos = this.classes.indexOf(clstring);
      if(pos>-1) {
        this.classes.splice(pos, 1);
        this.__update();
      }
      return this.owner;
    },
    contains: function(clstring) {
      return (this.classes.indexOf(clstring) !== -1);
    },
    item: function(idx) { return this.classes[idx]; }
  };

  /**
   * We need to make sure that from a user perspective,
   * the difference between "array" and "single element"
   * is irrelevant. This means homogenizing the Array
   * and HTMLElement prototypes. Yes, prototype pollution,
   * because we want to install our library, not "use" it.
   */
  (function($){
    // public helper for "add only if not already added"
    $.pushUnique = function(e) { if(this.indexOf(e) === -1) { this.push(e); }};
    // public helper for "do any of the elements in this array pass this test"
    $.test = function(f, strict) {
      if (strict !== true) strict = false;
      var i, len=this.length, t;
      for(i=0; i<len; i++) {
        t = f(this[i]);
        if(strict && !t) return false;
        if(t && !strict) return true;
      }
      return false;
    };
    // helper function for containment
    $.contains = function(e) {
      return this.indexOf(e) > -1;
    };
    // make forEach() a chaining function
    $.forEach = function(forEach) {
      return function(fn) {
        forEach.call(this,fn);
        return this;
      };
    }($.forEach);
    // API implementation
    $.classes = function() {
      if(!this[classesName]) {
        this[classesName] = {};
        var arr = this;
        ["add","remove"].forEach(function(fn) {
          arr[classesName][fn] = function() {
            var input = arguments, classes;
            arr.forEach(function(e) {
              classes = e.classes();
              classes[fn].apply(classes,input);
            });
            return arr;
          };
        });
        this[classesName].contains = function() {
          var input = arguments, classes;
          return arr.test(function(e) {
            classes = e.classes();
            return classes.contains.apply(classes,input);
          });
        };
      }
      return this[classesName];
    };
    // functions that will end up applying only to the first element:
    ["add", "replace"].forEach(function(fn){
      $[fn] = function() {
        var e = this[0];
        if (e)
          e[fn].apply(e, arguments);
        return this;
      };
    });
    // functions that get applied to all elements, returning the array:
    ["show", "toggle", "set", "remove", "clear", "listen", "ignore", "listenOnce"].forEach(function(fn){
      $[fn] = function() {
        var input = arguments;
        this.map(function(e) {
          e[fn].apply(e, input);
        });
        return this;
      };
    });
    // aggregating functions with the same aggregation shape:
    ["find", "parent", "query"].forEach(function(fn) {
      $[fn] = function() {
        var results = [];
        this.forEach(function(e) {
          e[fn].apply(e,arguments).forEach(function(r) {
            results.pushUnique(r);
          });
        });
        return results;
      };
    });
    // functions that get applied to all elements, returning the array-of-results:
    ["position", "html", "css", "get"].forEach(function(fn) {
      $[fn] = function() {
        var result = [],
            input = arguments,
            forEachFn = (function(result, input) {
              return function(e) {
                result.push(e[fn].apply(e, input));
              };
            }(result, input));
        this.forEach(forEachFn);
        return result;
      };
    });
  }(Array.prototype));


  /**
   * Extend the HTMLElement prototype.
   */
  (function($, find){
    // Array homogenization
    $.length = 1;
    // This lets us call forEach irrespective of whether we're
    // dealing with an HTML element or an array of HTML elements:
    $.forEach = function(fn) {
      fn(this);
      return this;
    };
    $.css = function(prop, val) {
      if(typeof val === "string") {
        this.style[prop] = val;
        if (this.get("style") === "") {
          this.set("style", "");
        }
        return this;
      }
      if(!val && typeof prop === "object") {
        for(var p in prop) {
          if(Object.hasOwnProperty(prop,p)) continue;
          this.css(p,prop[p]); }
        return this;
      }
      return getComputedStyle(this).getPropertyValue(prop) || this.style[prop];
    };
    $.qs = function(full) {
      var n = this,
          c = n.get("class"),
          qs = (n.id ? n.localName + '#' + n.id : n.localName) + (c ? '.' + c.replace(/ /g,'.') : '');
      if(!full) {
        return qs;
      }
      var parent = this.parentNode;
      while(parent.localName !== "html") {
        qs = parent.qs() + " > " + qs;
        parent = parent.parentNode;
      }
      return qs;
    };
    $.position = function() {
      return this.getBoundingClientRect();
    };
    $.classes = function() {
      if(!this[classesName]) {
        this[classesName] = new ClassList(this);
      }
      return this[classesName];
    };
    $.show = function(yes) {
      if(yes) { this.set(hiderule,false); }
      else { this.set(hiderule,"true"); }
      return this;
    };
    $.toggle = function() {
      this.show(_w.exists(this.get(hiderule)));
      return this;
    };
    $.html = function(html) {
      if(_w.exists(html)) {
        this.innerHTML = html;
        return this;
      }
      return this.innerHTML;
    };
    $.parent = function(newParent) {
      if(typeof newParent === "string") {
        var all = find(_d, newParent), i;
        if(all.length === 1) {
          if (all.contains(this)) return all;
          return false;
        }
        var ancestor = false;
        for(i = all.length-1; i>0; i--) {
          if(all[i].contains(this)) {
            ancestor = all[i];
            break;
          }
        }
        return ancestor;
      }
      if(newParent) {
        newParent.add(this);
        return this;
      }
      return this.parentNode;
    };
    $.add = function(arg) {
      if(typeof arg === "string") {
        this.innerHTML += arg;
      }
      else {
        var e, fn = function(a) { e.add(a); };
        for(var i=0, last=arguments.length; i<last; i++) {
          if(_w.exists(arguments[i])) {
            if(arguments[i] instanceof Array) {
              e = this;
              arguments[i].forEach(fn);
            } else { this.appendChild(arguments[i]); }
          }
        }
      }
      return this;
    };
    $.replace = function(o,n) {
      if(typeof o === "string") {
        var re = new RegExp(o,'g');
        this.innerHTML = this.innerHTML.replace(re,n);
        return this;
      }
      else if(_w.exists(o.parentNode) && _w.exists(n)) {
        o.parentNode.replaceChild(n,o);
        return n;
      }
      this.parentNode.replaceChild(o,this);
      return o;
    };
    $.remove = function(c) {
      if(typeof arg === "string") {
        return this.replace(c,"");
      }
      // remove self
      else if(!_w.exists(c)) { this.parentNode.removeChild(this); }
      // remove child by number
      else if(parseInt(c,10)==c) { this.removeChild(this.children[c]); }
      // remove child by reference
      else if(c.parentNode && c.parentNode === this) { this.removeChild(c); }
      return this;
    };
    $.clear = function() {
      this.innerHTML = "";
      return this;
    };
    $.get = function(a) {
      if(a == parseInt(a,10)) {
        return this.children[a];
      }
      return this.getAttribute(a);
    };
    $.set = function(a,b) {
      if(!_w.exists(b)) {
        for(var prop in a) {
          if(!Object.hasOwnProperty(a, prop)) {
            this.setAttribute(prop, a[prop]);
          }
        }
      }
      else if (b === false) { this.removeAttribute(a); }
      else { this.setAttribute(a, b); }
      return this;
    };
  }(HTMLElement.prototype, find));

  // IE has no HTMLDocument, so we have to use Document, instead.
  var docPrototype = (_w.HTMLDocument? HTMLDocument.prototype : Document.prototype);

  /**
   * Extend the HTMLElement and HTMLDocument prototypes.
   */
  [docPrototype, HTMLElement.prototype].forEach(function($) {
    $.find = function(selector) {
      return find(this, selector);
    };
    $.eventListeners = false;
    $.__addAnEventListener = function(s,f,b) {
      this.addEventListener(s,f,b);
      if(!this.eventListeners) {
        this.eventListeners = new EventListeners(this);
      }
      this.eventListeners.record(s,f);
    };
    $.__removeAnEventListener = function(s,f,b) {
      this.removeEventListener(s,f,b);
      this.eventListeners.ignore(s,f);
    };
    // better functions
    $.listen = function(s, f) {
      this.__addAnEventListener(s, f, false);
      return this;
    };
    $.ignore = function(s, f) {
      if (exists(f)) {
        this.__removeAnEventListener(s, f, false);
      }
      else {
        var entity = this;
        var functions = this.eventListeners.listeners[s], i;
        if (exists(functions)) {
          for (i = functions.length - 1; i >= 0; i--) {
            entity.ignore(s, functions[i]);
          }
        }
      }
      return this;
    };
    $.listenOnce = function(s, f) {
      var e = this, _ = function() {
        e.__removeAnEventListener(s, _, false);
        f.call();
      };
      this.__addAnEventListener(s, _, false);
      return this;
    };
  });

  /**
   * In order for show() to be reliable, we don't want to intercept style.display.
   * Instead, we use a special data attribute that regulates visibility. Handy!
   */
  (function(dataAttr){
    var rules = ["display:none!important", "visibility:hidden!important","opacity:0!important"],
        rule  = "*["+dataAttr+"]{" + rules.join(";") + "}",
        sheet = _w.create("style", {type: "text/css"}, rule);
    _d.head.add(sheet);
  }(hiderule));

  /**
   * Final steps
   */
  (function(){
    /**
     * top-level handling function for functions that are normally
     * added using document.addEventListener("DOMContentLoaded"...)
     * or jQuery's $.ready(...)
     */
    _w.schedule = function(fn) {
      if (_w.ready) { return fn(); }
      _d.listenOnce("DOMContentLoaded",fn);
    };

    // relied on by the above function
    var rd = function() { _w.ready = true; };
    if (["complete","loaded","interactive"].indexOf(_d.readyState) !== -1) { rd(); }
    else { _d.listenOnce("DOMContentLoaded", rd); }

    /**
     * For DOM manipulation, we really want 'head' and 'body' to just
     * be global variables.
     */
    _w.schedule(function() { _w.head = document.head; });
    _w.schedule(function() { _w.body = document.body; });

    // This is the worst thing: an IE hack. For some reason,
    // IE's "p" has a .clear property, for no good reason.
    delete HTMLParagraphElement.prototype.clear;
  }());

}(window, document));