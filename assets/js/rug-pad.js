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
	var lenFt = $( '#rpa-length-ft' ),
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

	/** Refresh the cart / checkout so totals include the new pad. */
	function refresh() {
		try {
			var wp = window.wp;
			if ( wp && wp.data && wp.data.dispatch && wp.data.select( 'wc/store/cart' ) ) {
				// Cart / Checkout blocks.
				wp.data.dispatch( 'wc/store/cart' ).invalidateResolutionForStore();
				window.location.reload(); // Re-renders the section's visibility rules too.
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
		window.location.reload(); // Classic cart.
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
	btn.addEventListener( 'click', add );

	update();
} )();
