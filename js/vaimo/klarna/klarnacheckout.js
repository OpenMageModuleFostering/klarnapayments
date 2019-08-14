/** Actual Klarna Checkout functions * */
/** ================================ * */
var klarnaResponsive;

function getCookie(name) {
	var re = new RegExp(name + "=([^;]+)");
	var value = re.exec(document.cookie);
	return (value != null) ? unescape(value[1]) : null;
};

function updateCartKlarna(type, input, quantity) {
	var klarnaCart     			= document.getElementById("klarna_sidebar"), //"klarna_wrapper");
		klarnaContainer     	= document.getElementById("klarna_container"),
		klarnaLoader   			= document.getElementById("klarna_loader"),
		klarnaMsg	   			= document.getElementById("klarna_msg"),
		klarnaMsgContent	   	= document.getElementById("klarna_msg_content"),
		klarnaCartHtml 			= document.getElementById("klarna_cart_reload"),
		klarnaHtml     			= document.getElementById("klarna_checkout_reload"),
		klarnaCheckout 			= document.getElementById("klarna_checkout"),
		klarnaTotals   			= document.getElementById("klarna_totals"),
		klarnaCheckoutContainer = document.getElementById('klarna-checkout-container'),
		klarnaQtyInput 			= typeof input != 'undefined'    ? input : null,
		klarnaQty      			= typeof quantity != 'undefined' ? quantity : null;

	klarnaMsg.style.display = 'none';
	klarnaMsg.className = klarnaMsg.className.replace( /(?:^|\s)error(?!\S)/g , '' );
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
                        klarnaMsgContent.innerHTML = obj.success;
                        fadeIn(klarnaMsg);
                    } else {
                        document.cookie = 'klarnaAddShipping=0; expires=-1;';
                    }
                    
                    var klarnaCartValue = '';
                    if (klarnaCartHtml) {
                        klarnaCartValue = klarnaCartHtml.value;
                    }

                    if (klarnaCartValue) {
                    	// Reload the Klarna iFrame.
	                    vanillaAjax(
	                        klarnaCartValue,
	                        '',
	                        function (results) {
	                            var objHtml = JSON.parse(results),
									matrixRateFeeEl = document.getElementById('s_method_matrixrate_matrixrate_free');

								// Redraw layout if is in responsive mode
								if(klarnaResponsive.getLayout() === 'two-column') {
									// Create a node for the fetched block
									var tempEl = document.createElement('span');
									tempEl.innerHTML = objHtml.update_sections.html;

									klarnaResponsive.drawLayoutForElement(tempEl.firstChild);
								}
								else { // Otherwise just replace the old sidebar with the new one
									document.getElementById('klarna_default').innerHTML = objHtml.update_sections.html;
								}

	                            if (getCookie("klarnaDiscountShipping") == 1) {
	                                document.cookie = 'klarnaDiscountShipping=0; expires=0;';
	                                if (matrixRateFeeEl && matrixRateFeeEl.innerHTML.length > 0 && matrixRateFeeEl.checked){
										matrixRateFeeEl.checked = true;
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
                    klarnaMsgContent.innerHTML = obj.error;
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

	for (var q = 0; q < removeItemElement.length; q++) {
		var removeItem = removeItemElement[q];
		removeItem.addEventListener('click', function (e) {
			e.preventDefault();

			var itemid = this.getAttribute('data-itemid');
			fadeOut(document.getElementById('cart_item_' + itemid));
			document.getElementById('cart_item_qty_' + itemid).value = 0;
			updateCartKlarna("cart");
		});
	}

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

var KlarnaLogin = (function () {
	"use strict";

	var me = function (config) {
		var cfg = config || {};

		this.form = cfg.form || document.getElementById('klarna_form-login');
		this.registerListeners();
	};

	me.prototype.registerListeners = function () {
		if(this.form) {
			this.form.addEventListener('submit', this.doLogin.bind(this));
		}
	};

	me.prototype.doLogin = function (e) {
		e.preventDefault();

		var form = e.target,
			data = form.serialize(false),
			url = form.action;

		vanillaAjax(url, data, this.successCallback.bind(this), this.errorCallback.bind(this));
	};

	me.prototype.showMessage = function (message) {
		var messageEl = document.getElementById('klarna_msg'),
			messageContentEl = messageEl.querySelector('.klarna_msg-content');

		messageContentEl.textContent = message;
		fadeIn(messageEl);
	};

	me.prototype.successCallback = function (response) {
		var data = JSON.parse(response),
			messageEl = document.getElementById('klarna_msg');

		// Show message if we get a response code
		if(!isNaN(data['r_code'])) {

			if (data['r_code'] < 0 || messageEl.classList.contains('error')) { // Error
				messageEl.classList.add('error');
				this.showMessage(data.message);
			} else {
    				messageEl.classList.remove('error'); // Success

				this.showMessage(data.message);

				/**
				 * Reload the page so that the Klarna iframe is updated with
				 * the user's email, address etc.
				 */
				window.location.reload();
			}
		}
	};

	me.prototype.errorCallback = function (response) {
		try {
			var data = JSON.parse(response);
			this.showMessage(data.message);
		}
		catch (e) {
			var loginFailedText = Translator.translate("Could not log in. Please try again");
			this.showMessage(loginFailedText);
		}

		console.log("Login failed! Here's the data:");
		console.log(data);
	};

	return me;
})();

var KlarnaResponsive = (function () {
	var me = function (config) {
		var cfg = config || {};

		this.element = cfg.element || document.getElementById('klarna_container');
		this.isRunning = false;
		this.storedSidebarEl = document.createDocumentFragment();
		this.mobileBreakPoint = 992;

		// Only run init functions if the site admin has set the Klarna module to use the responsive layout
		if(this.getLayout() === 'two-column') {
			this.registerListeners();
			this.updateLayout();
		}
	};

	me.prototype.registerListeners = function () {
		window.addEventListener('resize', resize.bind(this));
	};

	function resize(e) {
		if (!this.isRunning) {
			this.isRunning = true;

			if (window.requestAnimationFrame) {
				window.requestAnimationFrame(this.updateLayout.bind(this));
			} else {
				setTimeout(this.updateLayout.bind(this), 66);
			}
		}
	}

	me.prototype.getLayout = function () {
		var layoutVal = parseInt(this.element.getAttribute('data-layout'));

		if(layoutVal === 0) {
			return 'default';
		}
		else if (layoutVal === 1) {
			return 'two-column';
		}

		return '';
	};

	me.prototype.getDesktopLayout = function (el) {
		var sidebarEls = getSidebarElements(el),
			docFragment = document.createDocumentFragment(),
			fragmentSidebarEl,
			sidebarEl = el || this.storedSidebarEl;

		docFragment.appendChild(sidebarEl);
		fragmentSidebarEl = docFragment.querySelector('#klarna_sidebar');

		// Add all sidebar items to the temporary sidebar fragment
		fragmentSidebarEl.appendChild(sidebarEls.payment);
		fragmentSidebarEl.appendChild(sidebarEls.shipping);
		fragmentSidebarEl.appendChild(sidebarEls.cart);
		fragmentSidebarEl.appendChild(sidebarEls.discount);

		return docFragment;
	};

	me.prototype.setMobileLayout = function (el) {
		var groupedEls = getSidebarElements(el, true),
			sidebarEls = getSidebarElements(el),
			mainContentEl = document.getElementById('klarna_main'),
			iframeEl = document.getElementById('klarna_checkout'),
			tempEl = document.createDocumentFragment();

		for(var key in groupedEls) {
			if(groupedEls.hasOwnProperty(key)) {
				tempEl.appendChild(groupedEls[key]);
			}
		}

		mainContentEl.insertBefore(tempEl, iframeEl);
		mainContentEl.appendChild(sidebarEls.payment);
	};

	/**
	 * Gets the sidebar children elements as an object
	 * @param sidebarEl (optional)
	 * @param getGroup
	 * @returns {{cart: HTMLElement, shipping: HTMLElement, discount: HTMLElement}}
	 */
	function getSidebarElements (sidebarEl, getGroup) {
		var ref = sidebarEl || document,
			cartEl = ref.querySelector('#klarna_cart-container'),
			shippingEl = ref.querySelector('#klarna_shipping'),
			discountEl = ref.querySelector('#klarna_discount'),
			groupedEls = {
				cart: cartEl,
				shipping: shippingEl,
				discount: discountEl
			},
			sidebarEls = groupedEls;

		sidebarEls.payment = ref.querySelector('#klarna_methods');

		return getGroup ? groupedEls : sidebarEls;
	}

	/**
	 * Checks if the current viewport width corresponds to the predefined mobile breakpoint
	 * and if so changes the layout to mobile. Otherwise the layout is set to desktop.
	 */
	me.prototype.updateLayout = function () {
		var sidebarEl = document.getElementById('klarna_sidebar'),
			klarnaContainer = document.getElementById('klarna_container'),
			mainContentEl = document.getElementById('klarna_main'),
			cartEl = document.getElementById('klarna_cart-container');

		if(this.getMode() === 'mobile' && sidebarEl && !mainContentEl.contains(cartEl)) {
			this.storedSidebarEl = sidebarEl.cloneNode(false);

			this.setMobileLayout();

			sidebarEl.parentNode.removeChild(sidebarEl);
		}
		else if(this.getMode() === 'desktop' && !sidebarEl && mainContentEl.contains(cartEl)) {
			klarnaContainer.appendChild(this.getDesktopLayout());
		}

		this.isRunning = false;
	};

	me.prototype.getMode = function () {
		var viewportWidth = window.innerWidth;

		if(viewportWidth < this.mobileBreakPoint) {
			return 'mobile';
		}
		else if(viewportWidth >= this.mobileBreakPoint) {
			return 'desktop';
		}
		else {
			return false;
		}
	};

	/**
	 * Renders the given element as a sidebar or as a part of the main content depending
	 * on whether the browser window is in "mobile" or "desktop" mode. This is mostly intended to be used
	 * when the cart is updated through AJAX as the AJAX response will typically be an html view.
	 * @param el {HTMLElement}
	 */
	me.prototype.drawLayoutForElement = function (el) {
		if(!el) {
			return false;
		}

		var klarnaContainer = document.getElementById('klarna_container'),
			mainContentEl = document.getElementById('klarna_main');

		if(this.getMode() === 'mobile') {
			var sidebarEls = getSidebarElements(null, true);

			// Remove all the current sidebar items inside the main content area
			for(var key in sidebarEls) {
				if(sidebarEls.hasOwnProperty(key)) {
					mainContentEl.removeChild(sidebarEls[key]);
				}
			}

			this.setMobileLayout(el);
		}
		else {
			var newSidebar = this.getDesktopLayout(el);
			klarnaContainer.replaceChild(newSidebar, klarnaContainer.querySelector('#klarna_sidebar'));
		}
	};

	return me;
})();



// If there's no shipping option selected when the document loads, then select
// the first option
docReady(function() {
	// Enable responsive mode if layout is 2-column-right
	//var isDefaultLayout = document.getElementById('klarna_container').getAttribute('data-layout');
	//if(!isDefaultLayout && isDefaultLayout !== '') {
		klarnaResponsive = new KlarnaResponsive();
	//}

	// Add login functionality if the form exists
	if (document.getElementById('klarna_form-login')) {
		new KlarnaLogin();
	}

	var shippingChecked = document.getElementsByClassName('.shipping-method-input-radio:checked');
	document.cookie = 'klarnaDiscountShipping=0; expires=0;';

	if (!shippingChecked) {
		document.querySelector("input[name=shipping_method]:first-child").checked = true;
		document.cookie = 'klarnaAddShipping=1; expires=0;';
		updateCartKlarna("shipping");
	}

	bindCheckoutControls();

});

function klarnaCheckoutGo(url) {
    window.location.assign(url);
}

