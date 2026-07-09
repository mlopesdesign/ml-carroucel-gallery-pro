/**
 * ML Carousel Gallery Pro — Frontend JS v1.9.2
 *
 * Features:
 *  - Infinite loop (clone ring)
 *  - Autoplay configurable
 *  - Arrow navigation
 *  - Touch / swipe (mobile)
 *  - Center mode (active slide scaled up, neighbors visible)
 *  - Card width/height from CSS vars
 *  - Responsive: 3 desktop / 2 tablet / 1 mobile
 */

( function () {
	'use strict';

	function getVisibleCount( wrapper ) {
		var style = getComputedStyle( wrapper );
		var w = window.innerWidth;
		var desktop = parseFloat( wrapper.dataset.visibleDesktop || style.getPropertyValue( '--mlcgp-visible-desktop' ) || '3' ) || 3;
		var tablet  = parseFloat( wrapper.dataset.visibleTablet  || style.getPropertyValue( '--mlcgp-visible-tablet' )  || '2' ) || 2;
		var mobile  = parseFloat( wrapper.dataset.visibleMobile  || style.getPropertyValue( '--mlcgp-visible-mobile' )  || '1' ) || 1;
		if ( w <= 600 ) return mobile;
		if ( w <= 900 ) return tablet;
		return desktop;
	}

	function applyCardSize( wrapper ) {
		var style = getComputedStyle( wrapper );
		var cardW = parseInt( style.getPropertyValue( '--mlcgp-card-width' ), 10 ) || 0;
		var cardH = parseInt( style.getPropertyValue( '--mlcgp-card-height' ), 10 ) || 0;
		var cards = wrapper.querySelectorAll( '.mlcgp-card' );

		cards.forEach( function ( card ) {
			if ( cardH > 0 ) {
				card.style.height = cardH + 'px';
				card.style.aspectRatio = 'unset';
			} else {
				card.style.height = '';
				card.style.aspectRatio = '';
			}

			if ( cardW > 0 ) {
				card.style.width = cardW + 'px';
				card.style.maxWidth = cardW + 'px';
			} else {
				card.style.width = '';
				card.style.maxWidth = '';
			}
		} );
	}

	function updateCenterClasses( wrapper, track, cloneCount, currentIdx, visible ) {
		if ( ! wrapper.classList.contains( 'mlcgp-center-mode' ) ) return;

		var all = Array.prototype.slice.call( track.querySelectorAll( '.mlcgp-slide' ) );
		var centerDomIdx = cloneCount + currentIdx;

		all.forEach( function ( slide, i ) {
			if ( i === centerDomIdx ) {
				slide.classList.add( 'is-center' );
			} else {
				slide.classList.remove( 'is-center' );
			}
		} );
	}

	function initCarousel( wrapper ) {
		var track      = wrapper.querySelector( '.mlcgp-track' );
		var btnPrev    = wrapper.querySelector( '.mlcgp-nav--prev' );
		var btnNext    = wrapper.querySelector( '.mlcgp-nav--next' );
		var autoplay   = wrapper.dataset.autoplay === 'true';
		var speed      = parseInt( wrapper.dataset.speed, 10 ) || 4000;
		var centerMode = wrapper.dataset.center === 'true';

		var origSlides = Array.prototype.slice.call( track.querySelectorAll( '.mlcgp-slide' ) );
		var totalOrig  = origSlides.length;

		if ( totalOrig === 0 ) return;

		applyCardSize( wrapper );

		var visible    = getVisibleCount( wrapper );
		var currentIdx = 0;
		var isAnimating = false;
		var autoTimer  = null;

		// ── Clone ring ──────────────────────────────────────────────────────
		var cloneCount = Math.min( Math.max( 1, Math.ceil( visible ) ), totalOrig );

		for ( var i = cloneCount - 1; i >= 0; i-- ) {
			var tailClone = origSlides[ ( totalOrig - cloneCount + i ) % totalOrig ].cloneNode( true );
			tailClone.setAttribute( 'aria-hidden', 'true' );
			tailClone.classList.add( 'mlcgp-slide--clone' );
			track.insertBefore( tailClone, track.firstChild );
		}

		for ( var j = 0; j < cloneCount; j++ ) {
			var headClone = origSlides[ j % totalOrig ].cloneNode( true );
			headClone.setAttribute( 'aria-hidden', 'true' );
			headClone.classList.add( 'mlcgp-slide--clone' );
			track.appendChild( headClone );
		}

		// ── Slide width ──────────────────────────────────────────────────────

		function slideWidthPct() {
			var gap     = parseInt( getComputedStyle( wrapper ).getPropertyValue( '--mlcgp-gap' ), 10 ) || 0;
			var total   = track.parentElement.offsetWidth;
			var allGaps = gap * ( visible - 1 );
			return ( ( total - allGaps ) / visible ) / total * 100;
		}

		// ── Position setter ──────────────────────────────────────────────────

		function goTo( logicalIndex, instant ) {
			var domIndex   = cloneCount + logicalIndex;
			var gap        = parseInt( getComputedStyle( wrapper ).getPropertyValue( '--mlcgp-gap' ), 10 ) || 0;
			var totalW     = track.parentElement.offsetWidth;
			var slideW     = ( totalW - ( gap * ( visible - 1 ) ) ) / visible;
			var offsetPx   = -( domIndex * ( slideW + gap ) );

			if ( instant ) {
				track.style.transition = 'none';
			}
			track.style.transform = 'translateX(' + offsetPx + 'px)';

			updateCenterClasses( wrapper, track, cloneCount, logicalIndex, visible );
		}

		// ── Transition end → infinite wrap ───────────────────────────────────

		track.addEventListener( 'transitionend', function () {
			isAnimating = false;

			if ( currentIdx < 0 ) {
				currentIdx = totalOrig - 1;
				goTo( currentIdx, true );
			} else if ( currentIdx >= totalOrig ) {
				currentIdx = 0;
				goTo( currentIdx, true );
			}

			track.getBoundingClientRect();
			track.style.transition = '';
		} );

		// ── Init ─────────────────────────────────────────────────────────────

		(function () {
			track.style.transition = 'none';
			goTo( currentIdx, true );
			track.getBoundingClientRect();
			track.style.transition = '';
		}());

		// ── Navigation ───────────────────────────────────────────────────────

		function prev() {
			if ( isAnimating ) return;
			isAnimating = true;
			currentIdx--;
			goTo( currentIdx, false );
		}

		function next() {
			if ( isAnimating ) return;
			isAnimating = true;
			currentIdx++;
			goTo( currentIdx, false );
		}

		if ( btnPrev ) btnPrev.addEventListener( 'click', function () { resetAutoplay(); prev(); } );
		if ( btnNext ) btnNext.addEventListener( 'click', function () { resetAutoplay(); next(); } );

		// ── Autoplay ─────────────────────────────────────────────────────────

		function startAutoplay() {
			if ( ! autoplay ) return;
			autoTimer = setInterval( function () { next(); }, speed );
		}

		function resetAutoplay() {
			if ( ! autoplay ) return;
			clearInterval( autoTimer );
			startAutoplay();
		}

		wrapper.addEventListener( 'mouseenter', function () { clearInterval( autoTimer ); } );
		wrapper.addEventListener( 'mouseleave', function () { startAutoplay(); } );

		document.addEventListener( 'visibilitychange', function () {
			if ( document.hidden ) {
				clearInterval( autoTimer );
			} else {
				startAutoplay();
			}
		} );

		startAutoplay();

		// ── Touch / Swipe ─────────────────────────────────────────────────────

		var touchStartX = 0;
		var touchStartY = 0;
		var isDragging  = false;

		wrapper.addEventListener( 'touchstart', function ( e ) {
			if ( e.changedTouches.length !== 1 ) return;
			touchStartX = e.changedTouches[0].clientX;
			touchStartY = e.changedTouches[0].clientY;
			isDragging  = false;
		}, { passive: true } );

		wrapper.addEventListener( 'touchmove', function ( e ) {
			if ( e.changedTouches.length !== 1 ) return;
			var dx = e.changedTouches[0].clientX - touchStartX;
			var dy = e.changedTouches[0].clientY - touchStartY;
			if ( Math.abs( dx ) > Math.abs( dy ) && Math.abs( dx ) > 6 ) {
				isDragging = true;
				e.preventDefault();
			}
		}, { passive: false } );

		wrapper.addEventListener( 'touchend', function ( e ) {
			if ( ! isDragging || e.changedTouches.length !== 1 ) return;
			var delta = e.changedTouches[0].clientX - touchStartX;
			if ( Math.abs( delta ) > 40 ) {
				if ( delta < 0 ) { next(); } else { prev(); }
				resetAutoplay();
			}
			isDragging = false;
		} );

		// ── Keyboard ─────────────────────────────────────────────────────────

		wrapper.setAttribute( 'tabindex', '0' );
		wrapper.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'ArrowLeft'  ) { e.preventDefault(); prev(); resetAutoplay(); }
			if ( e.key === 'ArrowRight' ) { e.preventDefault(); next(); resetAutoplay(); }
		} );

		// ── Resize ───────────────────────────────────────────────────────────

		var resizeTimer;
		window.addEventListener( 'resize', function () {
			clearTimeout( resizeTimer );
			resizeTimer = setTimeout( function () {
				var newVisible = getVisibleCount( wrapper );
				if ( newVisible === visible ) {
					// Just recalculate position without full reinit.
					goTo( currentIdx, true );
					return;
				}

				visible = newVisible;
				clearInterval( autoTimer );

				// Remove clones.
				wrapper.querySelectorAll( '.mlcgp-slide--clone' ).forEach( function ( c ) {
					track.removeChild( c );
				} );

				initCarousel( wrapper );
			}, 200 );
		} );
	}

	function boot() {
		document.querySelectorAll( '.mlcgp-wrapper' ).forEach( function ( w ) {
			initCarousel( w );
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}

}() );
