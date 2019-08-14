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
    xmlhttp.setRequestHeader("Content-length", dataString.length);
    xmlhttp.setRequestHeader("Connection", "close");
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

/** Actual Klarna Checkout functions * */
/** ================================ * */

function getCookie(name) {
	var re = new RegExp(name + "=([^;]+)");
	var value = re.exec(document.cookie);
	return (value != null) ? unescape(value[1]) : null;
};

function updateCartKlarna(type, input, quantity) {
	var klarnaCart     = document.getElementById("klarna_wrapper");
	var klarnaLoader   = document.getElementById("klarna_loader");
	var klarnaMsg	   = document.getElementById("klarna_msg");
	var klarnaCartHtml = document.getElementById("klarna_cart_reload");
	var klarnaHtml     = document.getElementById("klarna_checkout_reload");
	var klarnaCheckout = document.getElementById("klarna_checkout");
	var klarnaTotals   = document.getElementById("klarna_totals");
	var klarnaCheckoutContainer = document.getElementById('klarna-checkout-container');
	var klarnaQtyInput = typeof input != 'undefined'    ? input : null;
	var klarnaQty      = typeof quantity != 'undefined' ? quantity : null;

	klarnaMsg.style.display = 'none';
	klarnaMsg.className = klarnaMsg.className.replace( /(?:^|\s)error(?!\S)/g , '' )
	fadeIn(klarnaLoader);

	// Checks what part that triggered the updateCartKlarna()
    var formID = null;
    switch (type) {
        case 'cart':
            formID = document.getElementById('klarna_cart');
            break;
        case 'shipping':
            formID = document.getElementById('klarna_shipping');
            break;
        case 'coupon':
            formID = document.getElementById('klarna_coupon');
            break;
        case 'giftcard':
        	formID = document.getElementById('giftcard-form');
        	break;
        case 'giftcard-remove':
        	formID = document.getElementById('giftcard-form');
        	ajaxUrl = input;
        	break;
        case 'reward':
            formID = document.getElementById('klarna-checkout-reward');
            break;
        case 'customer_balance':
            formID = document.getElementById('klarna-checkout-customer-balance')
    }
    
    if (formID === null) { return; }

    var dataString = formID.serialize(false);
    if (typeof ajaxUrl === "undefined") {
    	var ajaxUrl = formID.getAttribute("action");
    }

    _klarnaCheckoutWrapper(function(api) {
        vanillaAjax(ajaxUrl, dataString,
            function (data) {
                var obj = JSON.parse(data);
                fadeOut(klarnaLoader);
                if (obj.redirect_url) {
                    window.location.href = obj.redirect_url;
                } else if (obj.success) {
                    if (getCookie("klarnaAddShipping") != 1) {
                        klarnaMsg.innerHTML = obj.success;
                        fadeIn(klarnaMsg);
                    } else {
                        document.cookie = 'klarnaAddShipping=0; expires=-1;';
                    }
                    
                    var klarnaCartValue = ''
                    if (klarnaCartHtml) {
                        klarnaCartValue = klarnaCartHtml.value;
                    }

                    if (klarnaCartValue) {
                    	// Reload the Klarna iFrame.
	                    vanillaAjax(
	                        klarnaCartValue,
	                        '',
	                        function (results) {
	                            var objHtml = JSON.parse(results);
	                            klarnaCart.innerHTML = objHtml.update_sections.html;
	
	                            if (getCookie("klarnaDiscountShipping") == 1) {
	                                document.cookie = 'klarnaDiscountShipping=0; expires=0;';
	                                if (document.getElementById('s_method_matrixrate_matrixrate_free').innerHTML.length > 0 && document.getElementById('s_method_matrixrate_matrixrate_free').checked){
	                                    document.getElementById('s_method_matrixrate_matrixrate_free').checked = true;
	                                }
	                                updateCartKlarna("shipping");
	                            }
	
	                            //for (i=0;i<3;i++) { // "Highlight" the totals table
	                            //	fadeOut(document.getElementById("klarna_totals"));
	                                fadeIn(document.getElementById("klarna_totals"));
	                            //}
	
	                            vanillaAjax( // Refresh the Klarna iFrame
	                                klarnaHtml.value,
	                                '',
	                                function(results) {
	                                    var objKlarnaHtml = JSON.parse(results);
	                                    var evaluatedObjKlarnaHtml = objKlarnaHtml.update_sections.html;
	                                    var textNode = document.createTextNode(objKlarnaHtml.update_sections.html);
	                                    var scriptToEval = textNode.substringData(evaluatedObjKlarnaHtml.search('<script')+31, evaluatedObjKlarnaHtml.search('</script')-evaluatedObjKlarnaHtml.search('<script')-31);
	                                    var js = document.createElement('script');
	                                    js.async = true;
	                                    js.innerHTML = scriptToEval;
	
	                                    klarnaCheckoutContainer.innerHTML = '';//eval(scriptToEval);
	                                    klarnaCheckoutContainer.appendChild(js);
	                                    bindCheckoutControls();
	
	                                    //klarnaCheckout.innerHTML = objKlarnaHtml.update_sections.html;//eval(scriptToEval);
	
	                                    /*
	                                    var scripts = document.getElementsByTagName('script');
	                                    for (i=0; i<scripts.length;i++) {
	                                        scriptNode = scripts[i];
	                                        if (scriptNode.parentNode.id=='klarna-checkout-container') {
	                                            //eval(scriptNode.innerHTML);
	                                            console.log(scriptNode.innerHTML);
	                                        }
	                                    }*/
	
	                                    api.resume();
	                                }, '', ''
	                            );
	                        }, '', ''
	                    );
                    } else {
                    	vanillaAjax( // Refresh the Klarna iFrame
                            klarnaHtml.value,
                            '',
                            function(results) {
                            	if (getCookie("klarnaDiscountShipping") == 1) {
	                                document.cookie = 'klarnaDiscountShipping=0; expires=0;';
	                                if (document.getElementById('s_method_matrixrate_matrixrate_free').innerHTML.length > 0 && document.getElementById('s_method_matrixrate_matrixrate_free').checked){
	                                    document.getElementById('s_method_matrixrate_matrixrate_free').checked = true;
	                                }
	                                updateCartKlarna("shipping");
	                            }
	
	                            //for (i=0;i<3;i++) { // "Highlight" the totals table
	                            //	fadeOut(document.getElementById("klarna_totals"));
	                                fadeIn(document.getElementById("klarna_totals"));
	                            //}
                                var objKlarnaHtml = JSON.parse(results);
                                var evaluatedObjKlarnaHtml = objKlarnaHtml.update_sections.html;
                                var textNode = document.createTextNode(objKlarnaHtml.update_sections.html);
                                var scriptToEval = textNode.substringData(evaluatedObjKlarnaHtml.search('<script')+31, evaluatedObjKlarnaHtml.search('</script')-evaluatedObjKlarnaHtml.search('<script')-31);
                                var js = document.createElement('script');
                                js.async = true;
                                js.innerHTML = scriptToEval;

                                klarnaCheckoutContainer.innerHTML = '';//eval(scriptToEval);
                                klarnaCheckoutContainer.appendChild(js);
                                bindCheckoutControls();

                                api.resume();
                            }, '', ''
                        );
                    }
                } else if (obj.error) {
                    klarnaMsg.innerHTML = obj.error;
                    klarnaMsg.className += "error";
                    fadeIn(klarnaMsg);
                    if (klarnaQtyInput) {
                        klarnaQtyInput.value = klarnaQty;
                    }
                    api.resume();
                }
            },
            function(data) {
                alert(data);
            },
            function(data) {
                alert(data);
            }
        );
    });
 	setTimeout(function() { // Fade out the "alert" after 3,5 seconds
		fadeOut(klarnaMsg);
	}, 3500)
};

/** Bindings * */

function bindCheckoutControls() {

	// Helpfull element variables
	var
        removeItemElement = document.getElementsByClassName('remove-item'),
        subtrackItemElement = document.getElementsByClassName('subtract-item'),
        addItemElement = document.getElementsByClassName('add-item'),
        qtyInputList = document.getElementsByClassName('qty-input'),
        shippingMethods = document.getElementsByName('shipping_method');

	// Bind newsletter checkbox
	if (document.getElementById('klarna-checkout-newsletter')) {
		document.getElementById('klarna-checkout-newsletter').onchange = function() {
		    var url = document.getElementById('klarna-checkout-newsletter-url').value;
		    var type = Number(document.getElementById('klarna-checkout-newsletter-type').value);
		    var checked = false;
		    switch (type) {
		        case 1:
		            checked = this.checked ? 1 : 0;
		            break;
		        case 2:
		            checked = this.checked ? 0 : 1;
		            break;
		    }
		    this.disabled = 'disabled';
		    vanillaAjax(url, 'subscribe_to_newsletter=checked', function(){
		    	document.getElementById('klarna-checkout-newsletter').disabled = '';
		    });
		};
	};

	// Reward
	if (document.getElementsByName('use_reward_points')[0]) {
		document.getElementsByName('use_reward_points')[0].onchange = function() {
			updateCartKlarna('reward');
		};
	};

	// Store Credit
	if (document.getElementsByName('use_customer_balance')[0]) {
		document.getElementsByName('use_customer_balance')[0].onchange = function() {
			updateCartKlarna('customer_balance');
		};
	};

	// Change shipping method
	if (shippingMethods) {
        for (var q=0; q<shippingMethods.length; q++) {
            shippingMethodItem = shippingMethods[q];
            shippingMethodItem.onchange = function() {
                updateCartKlarna("shipping");
                updateCartKlarna("cart");
                return false;
            };
        };
	};


	// Coupon
	if (document.querySelector('#klarna_coupon button')) {
		document.querySelector('#klarna_coupon button').onclick = function() {
			var couponRemove = document.getElementById('remove-coupone');
			var couponInput  = document.getElementById('coupon_code');
	
			if (this.className.match(/(?:^|\s)cancel-btn(?!\S)/)) {
				couponRemove.value = 1;
				document.cookie = 'klarnaDiscountShipping=1; expires=0;';
				updateCartKlarna("coupon");
	            updateCartKlarna("cart");
			} else if (!couponInput.value) {
				couponInput.focus();
				for(i=0;i<3;i++) {
					fadeOut(couponInput);
					fadeIn(couponInput);
				}
				setTimeout(function() {
					couponInput.className = couponInput.className.replace( /(?:^|\s)error(?!\S)/g , '' )
				}, 6000)
			} else {
				document.cookie = 'klarnaDiscountShipping=1; expires=0;';
				updateCartKlarna('coupon');
	            updateCartKlarna("cart");
			}
		};
	}

    if (document.getElementById('coupon_code')) {
		document.getElementById('coupon_code').onkeydown = function(e) {
			if (e.which == 13) {
				e.preventDefault();
				updateCartKlarna("coupon");
			}
		};
	}
    

	// Giftcard
    if (document.querySelector('#giftcard-form button')) {
		document.querySelector('#giftcard-form button').onclick = function(e) {
			e.preventDefault();
			var giftcardInput = document.getElementById('giftcard_code');
			
			if (!giftcardInput.value) {
				giftcardInput.focus();
				for (i = 0; i < 3; i++) {
					fadeOut(giftcardInput);
					fadeIn(giftcardInput);
				}
				setTimeout(function() {
					giftcardInput.className = couponInput.className.replace(
							/(?:^|\s)error(?!\S)/g, '')
				}, 6000)
			} else {
				updateCartKlarna('giftcard');
				updateCartKlarna('cart');
			}
		};
	}

	if (document.getElementById('giftcard_code')) {
		document.getElementById('giftcard_code').onkeydown = function(e) {
			if (e.which == 13) {
				e.preventDefault();
				updateCartKlarna("giftcard");
			}
		};
	}
	
	// Giftcard remove on Klarna
	if (document.querySelector('#applied-gift-cards .btn-remove')) {
		document.querySelector('#applied-gift-cards .btn-remove').onclick = function(e) {
			e.preventDefault();
			updateCartKlarna('giftcard-remove', this.getAttribute('href'));
			updateCartKlarna('cart');
		};
	}

    for (var q=0; q<removeItemElement.length; q++) {
        removeItem = removeItemElement[q];
        removeItem.onclick = function() {
            var itemid = this.getAttribute('data-itemid');
            fadeOut(document.getElementById('cart_item_' + itemid));
            document.getElementById('cart_item_qty_' + itemid).value = 0;
            updateCartKlarna("cart");
            return false;
        };
    };

    for (var q=0; q<subtrackItemElement.length; q++) {
        subtrackItem = subtrackItemElement[q];
        subtrackItem.onclick = function() {
            var itemid = this.getAttribute('data-itemid'),
                qtyInput = document.getElementById('cart_item_qty_' + itemid),
                qtyCurrent = parseInt(qtyInput.value);

            qtyInput.value = (qtyCurrent - 1);
            if (qtyCurrent - 1 == 0) {
                fadeOut(document.getElementById('cart_item_' + itemid));
            }
            updateCartKlarna("cart", qtyInput, qtyCurrent);
            return false;
        };
    };

    for (var q=0; q<addItemElement.length; q++) {
        addItem = addItemElement[q];
        addItem.onclick = function() {
            var itemid = this.getAttribute('data-itemid'),
                qtyInput = document.getElementById('cart_item_qty_' + itemid),
                qtyCurrent = parseInt(qtyInput.value);

            qtyInput.value = (qtyCurrent + 1);
            updateCartKlarna("cart", qtyInput, qtyCurrent);
            return false;
        };
    };

	for (var q=0; q<qtyInputList.length; q++) {
		inputField = qtyInputList[q];

		inputField.onblur = function() {
			var itemid = this.getAttribute('data-itemid'),
				qtyInput = document.getElementById('cart_item_qty_' + itemid),
				qtyCurrent = parseInt(qtyInput.value),
				qtyOrgInput = document.getElementById('cart_item_qty_org_' + itemid),
				qtyOrgCurrent = parseInt(qtyOrgInput.value);

			if (qtyCurrent != qtyOrgCurrent) {
				updateCartKlarna("cart", qtyInput, qtyOrgCurrent);
			}
		};

		inputField.onkeydown = function(e) {
			if (e.which == 13) {
				e.preventDefault();
				var itemid = this.getAttribute('data-itemid'),
				qtyInput = document.getElementById('cart_item_qty_' + itemid),
				qtyCurrent = parseInt(qtyInput.value),
				qtyOrgInput = document.getElementById('cart_item_qty_org_' + itemid),
				qtyOrgCurrent = parseInt(qtyOrgInput.value);

				if (qtyCurrent != qtyOrgCurrent) {
					updateCartKlarna("cart", qtyInput, qtyOrgCurrent);
				}
			}
		};
	};

};

// If there's no shipping option selected when the document loads, then select
// the first option
docReady(function() {
	var shippingChecked = document.getElementsByClassName('.shipping-method-input-radio:checked');
	document.cookie = 'klarnaDiscountShipping=0; expires=0;';

	if (!shippingChecked) {
		document.querySelector("input[name=shipping_method]:first-child").checked = true
		document.cookie = 'klarnaAddShipping=1; expires=0;';
		updateCartKlarna("shipping");
	}

	bindCheckoutControls();
});

function klarnaCheckoutGo(url) {
    window.location.assign(url);
}

