/**
 * Relaxed Dokuwiki to HTML converter
 */
(function() {

  /**
   * extract a term from an index/glossary entity
   */
  var extractTerm = function(data) {
    data = data.replace(/@.*/,'');
    if(data.indexOf("!")!==-1) {
      return data.split("!")[1];
    }
    return data;
  };

  /**
   * replace the LaTeX line break hints with HTML line break hints.
   */
  var replaceLinebreak = function(line) {
    return line.replace(/\\linebreak\[(\d)\]/g, function(_, count) {
      // \linebreak[0] is a hint hint, [1]..[4] is basically a command
      return "<" + (count == "0" ? 'w' : '') + "br>";
    });
  }

  /**
   * remove left-over LaTeX code.
   */
  var removeLaTeX = function(line) {
    var removed = line.replace(/\\\w+\{\w*\}/g,'');
    if(removed.length != line.length) {
      if(window.console && console.log) {
        console.log("removed unknown LaTeX code from line [" + line + "]");
      }
    }
    return removed;
  }

  /**
   * Replace image links
   */
  var replaceImages = function(line) {
    return line.replace(/\{\{([^|]+)\|([^\}]*)\}\}/, function(_, image, description) {
      var url = "./data/media" + image.replace(/\:/,'/');
      return "<figure><img src='" + url + "'><figcaption>"+description+"</figcaption></figure>";
    });
  };

  /**
   *
   */
  var replaceIndexTerm = function(line) {
    return line.replace(/\{idx\:([^\:]+)\:([^\}]+)\}/g, function(_, language, termdata) {
      var term =  extractTerm(termdata);
      return "<span class='"+language+" indexterm'>" + term + "</span>";
    });
  };

  /**
   *
   */
  var replaceGlossTerm = function(line) {
    return line.replace(/\{gls\:([^\:]+)\:([^\}]+)\}/g, function(_, glossaryName, termdata){
      return "<span class='glossterm'>" + extractTerm(termdata) + "</span>";
    });
  };

  /**
   *
   */
  var replaceEmphasis = function(line) {
    return line.replace(/\/\/([^\/]+)\/\//g, "<em>$1</em>");
  };

  /**
   *
   */
  var formHeaderId = function(header) {
    header.trim();
    var text = performLineReplacements(header);
    text = text.replace(/<[^>]+>/g, '');
    return text.trim().replace(/ /g,'_');
  };

  // some trickery
  var section_tracking = [false, 1];

  /**
   *
   */
  var replaceHeading = function(line, toc, useprefix, prefix) {
    return line.replace(/(=+)\s?([^=]*)\s*=*/, function(_, spacing, htext) {
      htext = htext.trim().replace(/"/g,'');
      var depth = 7 - spacing.length,
          id = formHeaderId(htext);
      if(depth === 1) {
        section_tracking[1] = prefix;
      } else {
        if(section_tracking[depth]) {
          section_tracking[depth]++;
        } else {
          section_tracking[depth] = 1;
        }
      }
      section_tracking = section_tracking.slice(0,depth+1);
      var sequence = section_tracking.slice(1).join('-');
      var pid = "section-" + sequence + "-" + id;
      var before = "";

      if(useprefix) {
        var delimiter = " — ";
        if (depth === 1) { before = "Chapter " + sequence + delimiter; }
        else if (depth === 2) { before = "Section " + sequence + delimiter; }
        else { before = "§" + sequence + delimiter; }
      }

      var html = "<h" + depth + " id='" + pid + "' data-before='" + before + "'><a href='#" + pid + "'>" + htext + "</a></h" + depth + ">";
      toc.push({
        depth: depth,
        id: pid,
        title: id.replace(/_/g,' ')
      });
      return html;
    });
  };

  var kanji = "[\\u4E00-\\u9FFF\\u3005\\u30F6]+";
  var kana = "[\\u3040-\\u30FF]*";
  var furi = "(" + kanji + ")\\((" + kana + ")\\)";

  /**
   *
   */
  var replaceFurigana = function(line) {
    var search = new RegExp(furi, "g");
    var replace = "<ruby><rb>$1</rb><rt>$2</rt></ruby>";
    return line.replace(search, replace);
  };

  /**
   * FIXME: nested listings
   */
  var processList = function(lines, pos, mark, tag) {
    var inlist = true;
    var re = new RegExp("^(  )*"+mark+" ");
    var depth = 0;
    for(inlist=true; inlist==true && pos<lines.length; pos++) {
      var line = lines[pos];

      if(line.trim()==="" || !line.trim().substring(0,1) === mark) {
        inlist = false;
        continue;
      }

      var curDepth = line.match(/^(  )+/)[0].length;
      if(depth !== curDepth) {
        if(curDepth > depth) {
          lines[pos-1] += "\n<" + tag + ">\n";
        } else {
          lines[pos-1] += "\n</" + tag + ">\n";
        }
        depth = curDepth;
      }

      lines[pos] = "<li>" + performLineReplacements(line.trim().substring(1)) + "</li>";
    }
    pos--;
    lines[pos] += "\n</" + tag + ">\n";
    return pos;
  };

  /**
   *
   */
  var processUnorderedList = function(lines, pos) {
    return processList(lines, pos, "\\*", "ul");
  };

  /**
   *
   */
  var processOrderedList = function(lines, pos) {
    return processList(lines, pos, "-", "ol");
  };

  /**
   *
   */
  var processTable = function(lines, pos) {
    var intable = true;
    var header = false;
    var crosstable = false;
    var rows = [], columns = [];
    var colcount = 0;

    lines[pos-1] += "\n<table>\n";

    // table with header?
    if(lines[pos+1].trim() === "" && lines[pos+2].indexOf("\t")!==-1) {
      header = performLineReplacements(lines[pos]).split("\t");
      colcount = header.length;
      lines[pos] = "<tr><th" + (header[0].trim()==="" ?  " class='noline'": '') + ">" + header.join("</th><th>") + "</th></tr>\n";
      crosstable = (header[0].trim()==="");
      pos +=2;
    }

    var dataPos = pos;

    // rest of table (or headerless)
    for(intable=true; intable==true && pos<lines.length; pos++) {
      var line = lines[pos];
      if(line.trim()==="") {
        pos--;
        intable = false;
        continue;
      }
      if(line.indexOf("\t")===-1) {
        pos--;
        intable = false;
        continue;
      }
      line = performLineReplacements(line);
      columns = line.split("\t");
      if(columns.length>colcount) { colcount = columns.length; }
      rows.push(columns);
    }

    lines[dataPos+rows.length] += "\n</table>\n";

    for(var i=0, last=rows.length; i<last; i++) {
      var row = "<tr><td" + (crosstable ? " class='label'" : '') + ">" + rows[i].join("</td><td>");
      row += "</td><td>".repeat(colcount - rows[i].length);
      row += "</td></tr>";
      lines[dataPos+i] = row;
    }

    return pos;
  };

  /**
   *
   */
  var processExampleSet = function(lines, pos) {
    var re = new RegExp("^(  )+(.*)$");
    var inexamples = true;
    var line, lang;
    var japanese = new RegExp("[\u4E00-\u9FFF\u3000-\u303F\u30F6\u3040-\u30FF]");
    lines[pos-1] += "\n<div class='example'>\n";
    for(inexamples=true; inexamples==true && pos<lines.length; pos++) {
      line = lines[pos];
      if(line.match(re)) {
        lines[pos] = line.replace(re, function(_, cg1, cg2) {
          lang = (cg2.match(japanese) ? "japanese" : "english");
          return "<div class='"+lang+"'>" + performLineReplacements(cg2) + "</div>";
        });
      } else {
        pos--;
        inexamples=false;
      }
    }
    lines[pos] += "\n</div>\n";
    return pos;
  }

  /**
   *
   */
  var processVertical = function(lines, pos) {
    var invertical = true;
    var line = lines[pos];
    line = line.replace("<begin vertical ",'').replace(">",'');
    description = line.replace(/^.*\{([^\}]*)\}\{(\d+)\}\{([^\}]*)\}$/, function(_, font, size, description) {
      return description;
    });
    line = line.replace(/^(.*)\{([^\}]*)\}\{(\d+)\}\{([^\}]*)\}$/, function(_, content) {
      return content;
    });
    lines[pos] = performLineReplacements(line) + "\n<div class='vertical'>";
    pos++;
    for(invertical=true; invertical==true && pos<lines.length; pos++) {
      line = lines[pos].replace("\\\\",''.trim());
      if(line.trim()==="<end vertical>") {
        pos--;
        invertical=false;
        continue;
      }
      lines[pos] = "<div class='vline'>" + performLineReplacements(line) + "</div>";
    }
    lines[pos] = "</div>";
    return pos;
  }

  /**
   * Deal with in-line replacements.
   */
  var performLineReplacements = function(line, toc, useprefix, prefix) {
    // headings
    if(line.substring(0,1)==="=") {
      line = replaceHeading(line, toc, useprefix, prefix);
    }

    // emphasis
    if(line.indexOf("//")!==-1) {
      line = replaceEmphasis(line);
    }

    // idx syntax
    if (line.indexOf("{idx")!==-1) {
      line = replaceIndexTerm(line);
    }

    // glossary syntax
    if (line.indexOf("{gls")!==-1) {
      line = replaceGlossTerm(line);
    }

    // image links

    if (line.indexOf("{{")!==-1) {
      line = replaceImages(line);
    }

    // furi(gana) notation to <ruby> markup
    if (true) {
      line = replaceFurigana(line);
      line = removeLaTeX(line);
      line = replaceLinebreak(line);
    }

    return line;
  }

  /**
   * Convert a body of text in relaxed DokuWiki form
   * to HTML
   */
  window.BookToHTML = {
    convert: function(text, useprefix, prefix) {
      var lines = text.replace(/\r\n?/g,"\n").split("\n"),
          len = lines.length,
          l, line, toc = [];

      for(l=0; l<len; l++) {
        line = lines[l];

        // table processing
        if (line.indexOf("\t")!==-1) {
          l = processTable(lines, l);
        }

        // unordered list processing
        else if (line.match(/^(  )*- /)) {
          l = processUnorderedList(lines, l);
        }

        // ordered list processing
        else if (line.match(/^(  )*\* /)) {
          l = processOrderedList(lines, l);
        }

        // example set
        else if (line.substring(0,2) === "  ") {
          l = processExampleSet(lines, l);
        }

        // vertical typesetting
        else if (line.indexOf("<begin vertical")!==-1) {
          l = processVertical(lines, l);
        }

        else { lines[l] = "<p>" + performLineReplacements(line, toc, useprefix, prefix) + "</p>"; }
      }
      return {
        html: lines.join("\n"),
        toc: toc
      };
    }
  };
}());
