( function () {
	'use strict';

	var cfg = window.rpaData;
	var root = document.getElementById( 'rpa-addon' );
	if ( ! cfg || ! root ) {
		return;
	}

	var $ = function ( sel ) {
		return root.querySelector( sel );
	};
	var toggle = $( '.rpa-toggle' ),
		panel = $( '#rpa-panel' ),
		productEl = $( '#rpa-product' ),
		lenFt = $( '#rpa-length-ft' ),
		lenIn = $( '#rpa-length-in' ),
		widFt = $( '#rpa-width-ft' ),
		widIn = $( '#rpa-width-in' ),
		btn = $( '.rpa-add' ),
		summary = $( '.rpa-summary' ),
		totalEl = $( '.rpa-total' ),
		totalAmt = $( '.rpa-total-amount' ),
		msg = $( '.rpa-message' ),
		label = btn.textContent;

	function billable( ft, inch ) {
		return ft + ( inch > 0 ? 1 : 0 );
	}

	function money( n ) {
		var c = cfg.currency;
		var fixed = n.toFixed( c.decimals ).split( '.' );
		fixed[ 0 ] = fixed[ 0 ].replace( /\B(?=(\d{3})+(?!\d))/g, c.thouSep );
		var num = fixed.join( c.decSep );
		return c.format.replace( '%1$s', c.symbol ).replace( '%2$s', num );
	}

	function read() {
		return {
			lf: parseInt( lenFt.value, 10 ),
			li: parseInt( lenIn.value, 10 ) || 0,
			wf: parseInt( widFt.value, 10 ),
			wi: parseInt( widIn.value, 10 ) || 0,
		};
	}

	function update() {
		var v = read();
		msg.textContent = '';
		totalEl.style.display = 'none';
		summary.textContent = cfg.i18n.enter;
		btn.disabled = true;

		if ( isNaN( v.lf ) || isNaN( v.wf ) || v.lf < 0 || v.wf < 0 ) {
			return;
		}
		var L = billable( v.lf, v.li ),
			W = billable( v.wf, v.wi );
		if ( L < 1 || W < 1 ) {
			return;
		}
		if ( L > cfg.maxDim || W > cfg.maxDim ) {
			summary.textContent = cfg.i18n.tooBig.replace( '%d', cfg.maxDim );
			return;
		}

		var area = L * W;
		summary.textContent = cfg.i18n.summary.replace( '%1$s', L ).replace( '%2$s', W ).replace( '%3$s', area );
		totalAmt.textContent = money( area * cfg.rate );
		totalEl.style.display = '';
		btn.disabled = false;
	}

	/** Show the "Click Here" form. */
	function togglePanel( e ) {
		e.preventDefault();
		var open = panel.hasAttribute( 'hidden' );
		if ( open ) {
			panel.removeAttribute( 'hidden' );
		} else {
			panel.setAttribute( 'hidden', '' );
		}
		toggle.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
		if ( open ) {
			lenFt.focus();
		}
	}

	/** With several rug pad products, show the chosen product's image and description. */
	function showProduct() {
		var id = productEl.value;
		var items = root.querySelectorAll( '.rpa-product' );
		for ( var i = 0; i < items.length; i++ ) {
			if ( items[ i ].getAttribute( 'data-product' ) === id ) {
				items[ i ].removeAttribute( 'hidden' );
			} else {
				items[ i ].setAttribute( 'hidden', '' );
			}
		}
	}

	/** Refresh the cart / checkout so totals include the new pad. */
	function refresh() {
		try {
			var wp = window.wp;
			if ( wp && wp.data && wp.data.dispatch && wp.data.select( 'wc/store/cart' ) ) {
				// Cart / Checkout blocks: reload so totals, shipping and the empty-cart state all re-render.
				wp.data.dispatch( 'wc/store/cart' ).invalidateResolutionForStore();
				window.location.reload();
				return;
			}
		} catch ( e ) {}

		if ( window.jQuery && document.querySelector( 'form.checkout' ) ) {
			// Classic checkout: update totals and keep entered address details.
			window.jQuery( document.body ).trigger( 'update_checkout' );
			window.jQuery( document.body ).trigger( 'wc_fragment_refresh' );
			resetForm();
			return;
		}
		window.location.reload(); // Classic cart (including from an empty cart).
	}

	function resetForm() {
		lenFt.value = '';
		widFt.value = '';
		lenIn.value = '0';
		widIn.value = '0';
		update();
		msg.textContent = cfg.i18n.added || '';
	}

	function add() {
		var v = read();
		btn.disabled = true;
		btn.textContent = cfg.i18n.adding;

		var body = new URLSearchParams();
		body.append( 'nonce', cfg.nonce );
		body.append( 'product_id', productEl.value );
		body.append( 'length_ft', v.lf );
		body.append( 'length_in', v.li );
		body.append( 'width_ft', v.wf );
		body.append( 'width_in', v.wi );

		fetch( cfg.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: body.toString(),
		} )
			.then( function ( r ) {
				return r.json();
			} )
			.then( function ( res ) {
				btn.textContent = label;
				if ( res && res.success ) {
					cfg.i18n.added = res.data.message;
					refresh();
				} else {
					msg.textContent = ( res && res.data && res.data.message ) || cfg.i18n.error;
					btn.disabled = false;
				}
			} )
			.catch( function () {
				btn.textContent = label;
				btn.disabled = false;
				msg.textContent = cfg.i18n.error;
			} );
	}

	[ lenFt, lenIn, widFt, widIn ].forEach( function ( el ) {
		el.addEventListener( 'input', update );
		el.addEventListener( 'change', update );
	} );
	toggle.addEventListener( 'click', togglePanel );
	productEl.addEventListener( 'change', showProduct );
	btn.addEventListener( 'click', add );

	/**
	 * Cart/Checkout blocks: the section is printed after the block (printing it before breaks
	 * the block). Once the block has finished loading, move the section above it.
	 */
	function placeAboveBlock() {
		var block = root.previousElementSibling;
		if ( ! block || ! block.classList || ( ! block.classList.contains( 'wp-block-woocommerce-checkout' ) && ! block.classList.contains( 'wp-block-woocommerce-cart' ) ) ) {
			return; // Classic pages or shortcode: already in the right place.
		}
		var moved = false;
		function move() {
			if ( moved || block.classList.contains( 'is-loading' ) ) {
				return;
			}
			moved = true;
			observer.disconnect();
			block.parentNode.insertBefore( root, block );
		}
		var observer = new MutationObserver( move );
		observer.observe( block, { attributes: true, attributeFilter: [ 'class' ] } );
		window.setTimeout( function () {
			// Safety net: keep the section where it is if the block never finishes loading.
			observer.disconnect();
		}, 30000 );
		move();
	}
	placeAboveBlock();

	update();
} )();
