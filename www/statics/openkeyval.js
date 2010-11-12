/**
 *  JavaScript wrapper for http://openkeyval.org
 *  (C) 2010 by Mark Christian & Brian Klug
 *  https://github.com/shinyplasticbag/openkeyval
 *
 *  Licensed under Creative Commons Attribution-ShareAlike 3.0
 *  http://creativecommons.org/licenses/by-sa/3.0/
 */
var OpenKeyval = {
  api: {
    getItem: function(key, callback) {
      var url = encodeURIComponent(key) + '.jsonp?callback=OpenKeyval.callbacks.' + OpenKeyval.makeCallback(callback, key);
      OpenKeyval.api.makeJSONPRequest(url);
    },

    makeJSONPRequest: function(url) {
      if(url[0] === '/') {
        url = url.substring(1);
      }
      var transport = document.createElement('script');
      transport.src = OpenKeyval.server + url;
      document.body.appendChild(transport);
    },

    setItem: function(key, value, callback) {
      var url = '/store/?' + encodeURIComponent(key) + '=' + encodeURIComponent(value) + '&jsonp_callback=OpenKeyval.callbacks.' + OpenKeyval.makeCallback(callback, key);
      OpenKeyval.api.makeJSONPRequest(url);
    },
  },

  callbacks: {},
  console: {},
  memoizedData: {},
  shouldMemoize: true,
  server: 'http://api.openkeyval.org/',

  setDebugMode: function(value) {
    if(value) {
      OpenKeyval.console = console;
    } else {
      OpenKeyval.console.log = function() {};
    }
  },

  setServer: function(value) {
    if(value === OpenKeyval.server) {
      return;
    }

    if(value.substring(value.length - 1) !== '/') {
      //  We expect the server name to end with a slash
      value += '/';
    }

    OpenKeyval.server = value;

    //  Taint memoized data, if any
    OpenKeyval.memoizedData = {};
  },

  setShouldMemoize: function(value) {
    if(OpenKeyval.shouldMemoize == value) {
      return;
    }
    OpenKeyval.console.log('setShouldMemoize', value);
    OpenKeyval.shouldMemoize = !!value;
    OpenKeyval.memoizedData = {};
  },



  deleteItem: function(key, callback) {
    OpenKeyval.setItem(key, null, callback);
  },

  getItem: function(key, callback) {
    if(OpenKeyval.shouldMemoize && OpenKeyval.memoizedData[key]) {
      OpenKeyval.console.log('Returning memoized value for ', key)
      callback(OpenKeyval.memoizedData[key], key);
      return;
    }

    OpenKeyval.console.log('Starting remote getItem', key);
    OpenKeyval.api.getItem(key, function(value) {
      OpenKeyval.console.log('Received value for', key);
      if(OpenKeyval.shouldMemoize && value) {
        OpenKeyval.console.log('Memoizing', key)
        OpenKeyval.memoizedData[key] = value;
      }
      callback(value, key);
    });
  },

  makeCallback: function(callback, key) {
    var callbackName;
    do {
      callbackName = 'okvcb' + parseInt(Math.random() * 10000000);
    } while(typeof(OpenKeyval.callbacks[callbackName]) !== 'undefined');

    OpenKeyval.callbacks[callbackName] = (function(callbackName, callback, key) {
      return function(value) {
        delete OpenKeyval.callbacks[callbackName];
        callback(value);
      };
    })(callbackName, callback);
    return callbackName;
  },

  setItem: function(key, value, callback) {
    callback = callback || function() { /* no op */ };

    if(OpenKeyval.shouldMemoize) {
      delete OpenKeyval.memoizedData[key];
    }

    var okvCallback = function(response) {
      OpenKeyval.console.log('Received setItem response', key);
      if(response && response.status == 'multiset' && OpenKeyval.shouldMemoize) {
        OpenKeyval.console.log('Memoizing setItem response', key);
        OpenKeyval.memoizedData[key] = value;
      }

      callback(response);
    };

    OpenKeyval.console.log('Starting remote setItem', key);
    OpenKeyval.api.setItem(key, value, okvCallback);
  },
};

OpenKeyval.setDebugMode(false);

//  TODO: add a no-conflict mode like jQuery?
window.remoteStorage = remoteStorage = OpenKeyval;