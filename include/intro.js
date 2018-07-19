/*jslint white: true, browser: true*/
/*global window,$,jQuery,jPlayerPlaylist*/

var basic = {};
var	g = {};

(function() {
  "use strict";

  jQuery.extend({
    postJSON: function (url, data, callback) {
      return jQuery.post(url, data, callback, "json");
    }
  });

  $(function() {
    basic.startup = function() {
      $.getScript("include/load.php?nocache=" + Date.now());
    };

    basic.startup();
  });
}());
