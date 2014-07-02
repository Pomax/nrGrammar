(function(){

  /**
   * String repeat
   */
  if(!String.prototype.repeat) {
    String.prototype.repeat = function(c) {
      return (new Array(c+1)).join(this);
    };
  }

  /**
   * Array repeat
   */
  if(!Array.prototype.repeat) {
    Object.defineProperty(Array.prototype, "repeat", {
      value: function(v) {
        var len = this.length,
            tlen = v * len,
            arr = [].concat(this);
        while(arr.length<tlen) {
          arr = arr.concat(this);
        }
        return arr;
      }
    });
  }

}());
