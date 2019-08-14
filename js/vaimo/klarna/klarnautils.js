/** Helping functions for replacing jQuery functions with default js * */
(function(funcName, baseObj) {

    funcName = funcName || "docReady";
    baseObj = baseObj || window;
    var readyList = [];
    var readyFired = false;
    var readyEventHandlersInstalled = false;

    function ready() {
        if (!readyFired) {
            readyFired = true;
            for (var i = 0; i < readyList.length; i++) {
                readyList[i].fn.call(window, readyList[i].ctx);
            }
            readyList = [];
        }
    }

    function readyStateChange() {
        if ( document.readyState === "complete" ) {
            ready();
        }
    }

    baseObj[funcName] = function(callback, context) {
        if (readyFired) {
            setTimeout(function() {callback(context);}, 1);
            return;
        } else {
            readyList.push({fn: callback, ctx: context});
        }
        if (document.readyState === "complete") {
            setTimeout(ready, 1);
        } else if (!readyEventHandlersInstalled) {
            if (document.addEventListener) {
                document.addEventListener("DOMContentLoaded", ready, false);
                window.addEventListener("load", ready, false);
            } else {
                document.attachEvent("onreadystatechange", readyStateChange);
                window.attachEvent("onload", ready);
            }
            readyEventHandlersInstalled = true;
        }
    }

})("docReady", window);

// Abstract(s) for Klarna: Suspend and resume
function _klarnaCheckoutWrapper(callback) {
    if (typeof _klarnaCheckout != 'undefined') {
        _klarnaCheckout(function(api) {
            api.suspend();
            typeof callback === 'function' && callback(api);
        });
    }
};

// Helpers for Klarna: Suspend and resume
function klarnaCheckoutSuspend() {
    _klarnaCheckoutWrapper();
};

function klarnaCheckoutResume() {
    _klarnaCheckoutWrapper(function(api) {
        api.resume();
    });
};

function vanillaAjax(url, dataString, callbackOnSuccess, callbackOnError, callbackOnOther) {
    var xmlhttp;

    if (window.XMLHttpRequest) {
        // code for IE7+, Firefox, Chrome, Opera, Safari
        xmlhttp = new XMLHttpRequest();
    } else {
        // code for IE6, IE5
        xmlhttp = new ActiveXObject("Microsoft.XMLHTTP");
    }

    xmlhttp.onreadystatechange = function() {
        if (xmlhttp.readyState == XMLHttpRequest.DONE ) {
            var response = xmlhttp.responseText;
            if(xmlhttp.status == 200){
                callbackOnSuccess(response);
            } else if(xmlhttp.status == 400) {
                callbackOnError(response);
            } else {
                callbackOnOther(response);
            }
        }
    }

    xmlhttp.open("POST", url, true);

    // Send the proper header information along with the request
    xmlhttp.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
    /*xmlhttp.setRequestHeader("Content-length", dataString.length);
    xmlhttp.setRequestHeader("Connection", "close");*/
    xmlhttp.setRequestHeader("X_REQUESTED_WITH", "XMLHttpRequest");

    xmlhttp.send(dataString);
};

// fade out
function fadeOut(el){
    el.style.opacity = 1;

    (function fade() {
        if ((el.style.opacity -= .1) < 0) {
            el.style.display = "none";
        } else {
            requestAnimationFrame(fade);
        }
    })();
};

// fade in
function fadeIn(el, display){
    if (el) {
        el.style.opacity = 0;
        el.style.display = display || "block";

        (function fade() {
            var val = parseFloat(el.style.opacity);
            if (!((val += .1) > 1)) {
                el.style.opacity = val;
                requestAnimationFrame(fade);
            }
        })();
    }
};

// Closest
function closest() {
    var parents = [];
    var tmpList = document.getElementsByClassName('world');
    for (var i = 0; i < tmpList.length; i++) {
        parents.push(tmpList[i].parentNode);
    }

    var list = [];
    for (var i = 0; i < parents.lenght; i++) {
        if ((parents[i].hasAttribute('data-prefix')) && (parents[i].attributes.getNamedItem('data-prefix').textContent == 'hello')) {
            list.push(tmpList[i]);
        }
    }
    return list;
};

// IE check
function isIECheck() {
    var ua = window.navigator.userAgent;
    var msie = ua.indexOf("MSIE ");

    if (msie > 0 || !!navigator.userAgent.match(/Trident.*rv\:11\./)) {
        return true;
    } else {
        return false;
    }
};

// Serialize
/* Add the forEach method to Array elements if absent */
if (!Array.prototype.forEach) {
    Array.prototype.forEach = function(fn, scope) {
        'use strict';

        var i, len;

        for (i = 0, len = this.length; i < len; ++i) {
            if (i in this) {
                fn.call(scope, this[i], i, this);
            }
        }
    };
}

/* Extrapolate the Array forEach method to NodeList elements if absent */
if (!NodeList.prototype.forEach) {
    NodeList.prototype.forEach = Array.prototype.forEach;
}

/*
 * Extrapolate the Array forEach method to HTMLFormControlsCollection elements
 * if absent
 */
if (!isIECheck) {
    if (!HTMLFormControlsCollection.prototype.forEach) {
        HTMLFormControlsCollection.prototype.forEach = Array.prototype.forEach;
    }
} else {
    if (!HTMLCollection.prototype.forEach) {
        HTMLCollection.prototype.forEach = Array.prototype.forEach;
    }
}

/**
 * Convert form elements to query string or JavaScript object.
 *
 * @param asObject
 *            If the serialization should be returned as an object.
 */
HTMLFormElement.prototype.serialize = function(asObject) {
    'use strict';
    var form = this;
    var elements;
    var add = function(name, value) {
        value = encodeURIComponent(value);

        if (asObject) {
            elements[name] = value;
        } else {
            elements.push(name + '=' + value);
        }
    };

    if (asObject) {
        elements = {};
    } else {
        elements = [];
    }

    form.elements.forEach(function(element) {
        switch (element.nodeName) {
            case 'BUTTON':
                /* Omit this elements */
                break;

            default:
                switch (element.type) {
                    case 'submit':
                    case 'button':
                        /* Omit this types */
                        break;
                    case 'radio':
                        if (element.checked) {
                            add(element.name, element.value);
                        }
                        break;
                    default:
                        add(element.name, element.value);
                        break;
                }
                break;
        }
    });

    if (asObject) {
        return elements;
    }

    return elements.join('&');
};