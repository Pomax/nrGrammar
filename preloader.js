/**
 * Preload all the book data in plain text form,
 * and then let the browser perform the rendering.
 */
schedule(function loadData() {

  // menu nonsense
  function setupMenuScrollBehaviour() {

    var prev;
    var n = 0;

    var headings = find("#content h1, #content h2");

    var menu = find("#menu"), sel;
    headings.forEach(function(h) {
      if(h.localName === "h1") {
        sel = create("ul");
        menu.add(sel);
      }
      var a = create("a", { href: "#" + h.id, class: h.localName}, h.textContent);
      var li = create("li");
      sel.add(li.add(a));
    });

    var headerElement = find("header");
    headings = headings.map(function(e) {
      return {
        top: e.getBoundingClientRect().top - headerElement.getBoundingClientRect().top,
        id: e.id
      };
    });

    var closest = function() {
      var h;
      var top = document.documentElement.scrollTop;
      var i = headings.length;
      while (i--) {
        h = headings[i];
        if (top >= h.top - 1) return h;
      }
    };

    document.onscroll = function() {
      var h = closest();
      if (!h) return;

      if (prev) {
        prev.classList.remove('active');
        prev.parentNode.parentNode.classList.remove("active");
      }

      var a = find('nav a[href="#' + h.id + '"]');
      a.classList.add('active');
      a.parentNode.parentNode.classList.add("active");
      prev = a;
    };

    window.scrollBy(0,1);
    window.scrollBy(0,-1);
  }


  var nav = find("nav");
  nav.listen("touchstart", function(evt) { nav.classes().add("active"); });
  find("#content").listen("touchstart", function(evt) { nav.classes().remove("active"); });

  var webworkers = !!window.Worker,

      dir = "./data/pages/",

      pages = [
        "preface/onlinedraft",
        "syntax",
        "verb_grammar",
        "more_verb_grammar",
        "particles",
        "counters",
        "language_patterns"
      ],

      appendices = [
        "conjugation",
        "set_phrases",
        "glossary"
      ],

      section = find("#content"),
      ol_chapters = find("#chapters"),
      ol_appendices = find("#appendices"),
      ol_indexes = find("#indexes"),

      getData = function(dir, filename, callback) {
        return get(dir + filename + ".txt", callback);
      },

      getTitle = function(lines) {
        var title = lines[0].replace(/=/g,'').trim().toLowerCase();
        title = title[0].toUpperCase() + title.substring(1);
        return BookToHTML.convertLine(title);
      },

      buildToC = function(toc, master, buildtoc) {
        // ToC title for this chapter
        var titleEntry = toc.splice(0,1)[0],
            title = titleEntry.title[0].toUpperCase() + titleEntry.title.substring(1).toLowerCase(),
            id = titleEntry.id;
        master.add(create("li", {"class":"chaptertitle"}, "<a href='#" + id + "'>" + title + "</a>"));

        if(!buildtoc) return;

        // chapter ToC
        var depth = 1,
            current = create("ul", {"class": "depth1", id: "toc-" + id}),
            previous,
            stack = [];
        toc.forEach(function(entry) {
          while (entry.depth > depth) {
            depth++;
            stack.push(current);
            current = create("ul", {"class": "depth"+depth});
          }
          while (entry.depth < depth) {
            depth--;
            previous = stack.pop();
            previous.add(current);
            current = previous;
          }
          current.add(create("li", {}, "<a href='#" + entry.id + "'>" + entry.title + "</a>"));
        });

        for(var len=stack.length; len>1; len=stack.length) {
          previous = stack.pop();
          previous.add(current);
          current = previous;
        }

        master.add(current);
      };

  /**
   * Run our data injection
   */
  (function(section, dir, pages, appendices) {
    var data = { keys:[], pages: {}, html: {}},
        destinations = [ol_chapters].repeat(pages.length).concat([ol_appendices].repeat(appendices.length)),
        tocarray = [true].repeat(pages.length).concat([false].repeat(appendices.length)),
        files = pages.concat(appendices),
        markIndicator = 0;

    /**
     * ...
     */
    (function loadFile(files, dir, destinations, fullToC) {
      if(files.length==0) {
        setupMenuScrollBehaviour();
        document.head.add(find("link[href='counters.css']").remove());
        return;
      }

      var filename = files.splice(0,1)[0],
          destination = destinations.splice(0,1)[0],
          buildtoc = fullToC.splice(0,1)[0];

      var lines = getData(dir, filename, function (xhr) {
        var fileData = xhr.responseText.split("\n").slice(4).join("\n");
        data.keys.push(filename);
        data.pages[filename] = fileData;

        // run conversion in a web worker
        if(webworkers) {
          var worker = new Worker('booksyntax.js');
          worker.addEventListener('message', function(evt) {
            data.html[filename] = evt.data.html;
            // prevent page-blocking by not loading in the entire section in a single go!
            (function process(list) {
              if(list.children.length > 0) {
                for(var i=0; i<100 && list.length>0; i++) {
                  section.add(list.get(0));
                }
                return setTimeout(function() { process(list); }, 0);
              }
//              buildToC(evt.data.toc, destination, buildtoc);
              setTimeout(function() { loadFile(files, dir, destinations, fullToC); }, 25);
            }(create("div",data.html[filename])));
          }, false);

          // start web worker in the background
          worker.postMessage({
            useprefix: ([pages[0]].concat(appendices).indexOf(filename) === -1),
            prefix: markIndicator++,
            fileData: fileData
          });
        }

        // legacy fallback...
        else {
          var conversion = BookToHTML.convert(fileData, markIndicator++);
          data.html[filename] = conversion.html;
          section.add(create("div",data.html[filename]));
          buildToC(conversion.toc, destination, buildtoc);
          setTimeout(function() { loadFile(files, dir, destinations, fullToC); }, 25);
        }

      })
    }(files, dir, destinations, tocarray));
  }(section, dir, pages, appendices));
});
