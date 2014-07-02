schedule(function setupMenuScrollBehaviour() {

  var prev;
  var n = 0;

  var headings = find("#content h1, #content h2").map(function(e) {
    return {
      top: e.offsetY,
      id: e.id
    };
  });

  var menu = find("#menu");
  headings.forEach(function(h) {
    menu.add(create("a", { href: "#" + h.id}, h.id));
  });

  var closest = function() {
    var h;
    var top = window.scrollTop();
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
      prev.removeClass('active');
    }

    var a = find('a[href="#' + h.id + '"]');
    a.addClass('active');
    prev = a;
  };

});
