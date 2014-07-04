/**
 * Preload all the book data in plain text form,
 * and then let the browser perform the rendering.
 */
schedule(function loadData() {

  // menu nonsense
  var headings = [];

  function extendMenu(section) {
    var menu = find("#menu"), sel;
    var headerElement = find("header");
    var curhs = section.find("h1, h2, h3");
    if(curhs instanceof Array) {} else { curhs = [curhs]; }

    var hideh3 = function(sel) {
      if(sel && sel.children.length > 50) {
        sel.classList.add("hideh3");
      }
    }

    curhs.forEach(function(h) {
      if(h.localName === "h1") {
        hideh3(sel);
        sel = create("ul");
        menu.add(sel);
      }
      sel.add(create("li", { class: h.localName}, "<a href='#" + h.id +"'>" + h.textContent + "</a>"));
    });
    hideh3(sel);

    curhs = curhs.map(function(e) {
      return {
        top: e.getBoundingClientRect().top - headerElement.getBoundingClientRect().top,
        id: e.id
      };
    });

    headings = headings.concat(curhs);
  }

  function setupMenuScrollBehaviour() {
    var prev;

    var closest = function() {
      var top = document.body.scrollTop ? document.body.scrollTop : document.documentElement.scrollTop;
      var h, i = headings.length;
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
        prev.parent("ul").classList.remove("active");
      }

      var a = find('nav a[href="#' + h.id + '"]');
      a.classList.add('active');
      a.parent("ul").classList.add("active");
      prev = a;
    };

    var data = find("script[type='text/html']").innerHTML;
    menu.add(data);

    window.scrollBy(0,1);
    window.scrollBy(0,-1);
  }


  var nav = find("nav");
  nav.listen("touchstart", function(evt) { nav.classes().add("active"); });
  find("#content").listen("touchstart", function(evt) { nav.classes().remove("active"); });


  // =============================


  var dir = window.GrammarLoaderConfig ? GrammarLoaderConfig.base : "./data/pages/",

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

      main = find("#content"),
      ol_chapters = find("#chapters"),
      ol_appendices = find("#appendices"),
      ol_indexes = find("#indexes"),

      getData = function(dir, filename, callback) {
        return get(dir + filename + ".txt", callback);
      };

  /**
   * Run our data injection
   */
  (function(main, dir, pages, appendices) {
    var data = { keys:[], pages: {}, html: {}},
        destinations = [ol_chapters].repeat(pages.length).concat([ol_appendices].repeat(appendices.length)),
        tocarray = [true].repeat(pages.length).concat([false].repeat(appendices.length)),
        files = pages.concat(appendices),
        markIndicator = 0;

    /**
     * load each file individually
     */
    (function loadFile(files, dir, destinations, fullToC) {
      if(files.length==0) {
        setupMenuScrollBehaviour();
        document.head.add(find("link[href='counters.css']").remove());
        if (window.GrammarLoaderConfig && GrammarLoaderConfig.onGrammarLoaded) {
          GrammarLoaderConfig.onGrammarLoaded();
        }
        return;
      }


      var filename = files.splice(0,1)[0],
          destination = destinations.splice(0,1)[0],
          buildtoc = fullToC.splice(0,1)[0];


      var loadData = function(filename, data, next) {
        var dataDiv = create("div", data);
        var chapter = create("section", { class: filename });
        main.add(chapter);

        // prevent page-blocking by not loading in the entire section in a single go!
        (function process(list) {
          if(list.children.length > 0) {
            for(var i=0; i<100 && list.length>0; i++) {
              chapter.add(list.get(0));
            }
            return setTimeout(function() { process(list); }, 0);
          }
          setTimeout(function() { next(chapter); }, 0);
        }(dataDiv));
      };


      getData(dir, filename, function (xhr) {
        var useprefix = ([pages[0]].concat(appendices).indexOf(filename) === -1);
        var prefix = markIndicator++;
        var fileData = xhr.responseText.split("\n").slice(4).join("\n");
        var conversion = BookToHTML.convert(fileData, useprefix, prefix, dir);
        loadData(filename, conversion.html, function(chapter) {
          extendMenu(chapter);
          loadFile(files, dir, destinations, fullToC);
        });
      });

    }(files, dir, destinations, tocarray));
  }(main, dir, pages, appendices));
});
