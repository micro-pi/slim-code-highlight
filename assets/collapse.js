/**
 * Long code blocks: shows the first few lines and a button that opens the
 * rest. Without this script a long block is simply cut at the same height
 * and scrolls inside itself (see assets/style.css), so nothing is hidden
 * from a visitor whose script did not run.
 *
 * Each long <pre class="sch-long" style="--sch-lines:N"> is followed by a
 * <button class="sch-toggle" hidden> that this script reveals.
 */
( function () {
	'use strict';

	function px( value ) {
		return parseFloat( value ) || 0;
	}

	function preOf( button ) {
		var before = button.previousElementSibling;
		// Prism's copy button wraps the <pre> in a div.code-toolbar.
		if ( before && before.tagName !== 'PRE' ) {
			before = before.querySelector( 'pre.sch-long' );
		}
		return before && before.classList.contains( 'sch-long' ) ? before : null;
	}

	function collapsedHeight( pre ) {
		var lines = parseInt( pre.style.getPropertyValue( '--sch-lines' ), 10 );
		var code = pre.querySelector( 'code' ) || pre;
		var style = window.getComputedStyle( code );
		var preStyle = window.getComputedStyle( pre );
		var line = px( style.lineHeight );
		if ( ! line ) {
			line = px( style.fontSize ) * 1.5;
		}
		return Math.round(
			lines * line + px( preStyle.paddingTop ) + px( preStyle.paddingBottom ) + px( preStyle.borderTopWidth ) + px( preStyle.borderBottomWidth )
		);
	}

	function setup( button ) {
		var pre = preOf( button );
		if ( ! pre ) {
			return;
		}

		var moreLabel = button.textContent;
		var lessLabel = button.getAttribute( 'data-less' ) || moreLabel;

		pre.style.setProperty( '--sch-bg', window.getComputedStyle( pre ).backgroundColor );

		function collapse() {
			pre.classList.add( 'sch-collapsed' );
			pre.style.maxHeight = collapsedHeight( pre ) + 'px';
			button.textContent = moreLabel;
			button.setAttribute( 'aria-expanded', 'false' );
		}

		function expand() {
			pre.classList.remove( 'sch-collapsed' );
			pre.style.maxHeight = 'none';
			button.textContent = lessLabel;
			button.setAttribute( 'aria-expanded', 'true' );
		}

		button.addEventListener( 'click', function () {
			if ( pre.classList.contains( 'sch-collapsed' ) ) {
				expand();
				return;
			}
			collapse();
			// Collapsing a long block can leave the page far below its top.
			var top = pre.getBoundingClientRect().top;
			if ( top < 0 ) {
				pre.scrollIntoView( { block: 'start' } );
			}
		} );

		collapse();
		button.hidden = false;
	}

	function init() {
		Array.prototype.forEach.call( document.querySelectorAll( 'button.sch-toggle' ), setup );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
