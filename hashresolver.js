/**
 * Hash resolver for when content gets loaded
 * onto a page well after DOMContentLoaded has
 * triggered, and the browser has tried to
 * resolve #fragment ids itself.
 */
(function(w, d) {
  // Try to resolve the hash. Stop
  // trying after thirty seconds.
  var waitInterval = 250;
  var tryJump = function(fragment, waitTime) {
    waitTime = waitTime || 0;
    if(waitTime>30000) return;
    var element = d.getElementById(fragment.substring(1));
    if(!element) {
      setTimeout(function() { tryJump(fragment, waitTime+waitInterval); }, waitInterval);
    } else { w.location = fragment; }
  }

  var loc = window.location.toString();
  var pos = loc.indexOf("#");
  if(pos > -1) {
    var fragment = loc.substring(pos);
    fragment.replace(/\?.*/,'');
    tryJump(fragment);
  }
}(window, document));
