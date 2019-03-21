/**
 * Interactions used by the Site Health modules in WordPress.
 *
 * @output wp-admin/js/site-health.js
 */

/* global ajaxurl, SiteHealth, wp */

jQuery( document ).ready( function( $ ) {

	var data;

	// Debug information copy section.
	$( '.health-check-copy-field' ).click( function( e ) {
		var $textarea = $( '#system-information-' + $( this ).data( 'copy-field' ) + '-copy-field' ),
			$wrapper = $( this ).closest( 'div' );

		e.preventDefault();

		$textarea.select();

		if ( document.execCommand( 'copy' ) ) {
			$( '.copy-field-success', $wrapper ).addClass( 'visible' );
			$( this ).focus();

			wp.a11y.speak( SiteHealth.string.site_info_copied, 'polite' );
		}
	});

	$( '.health-check-toggle-copy-section' ).click( function( e ) {
		var $copySection = $( '.system-information-copy-wrapper' );

		e.preventDefault();

		if ( $copySection.hasClass( 'hidden' ) ) {
			$copySection.removeClass( 'hidden' );

			$( this ).text( SiteHealth.string.site_info_hide_copy );
		} else {
			$copySection.addClass( 'hidden' );

			$( this ).text( SiteHealth.string.site_info_show_copy );
		}
	});

	// Accordion handling in various areas.
	$( '.health-check-accordion' ).on( 'click', '.health-check-accordion-trigger', function() {
		var isExpanded = ( 'true' === $( this ).attr( 'aria-expanded' ) );

		if ( isExpanded ) {
			$( this ).attr( 'aria-expanded', 'false' );
			$( '#' + $( this ).attr( 'aria-controls' ) ).attr( 'hidden', true );
		} else {
			$( this ).attr( 'aria-expanded', 'true' );
			$( '#' + $( this ).attr( 'aria-controls' ) ).attr( 'hidden', false );
		}
	});

	$( '.health-check-accordion' ).on( 'keyup', '.health-check-accordion-trigger', function( e ) {
		if ( '38' === e.keyCode.toString() ) {
			$( '.health-check-accordion-trigger', $( this ).closest( 'dt' ).prevAll( 'dt' ) ).focus();
		} else if ( '40' === e.keyCode.toString() ) {
			$( '.health-check-accordion-trigger', $( this ).closest( 'dt' ).nextAll( 'dt' ) ).focus();
		}
	});

	// Site Health test handling.

	$( '.site-health-view-passed' ).on( 'click', function() {
		var goodIssuesWrapper = $( '#health-check-issues-good' );

		goodIssuesWrapper.toggleClass( 'hidden' );
		$( this ).attr( 'aria-expanded', ! goodIssuesWrapper.hasClass( 'hidden' ) );
	});

	function AppendIssue( issue ) {
		var htmlOutput = _.template( $( '#issue-template' ).html() )( issue ),
			issueWrapper = $( '#health-check-issues-' + issue.status ),
			issueCounter = $( '.issue-count', issueWrapper );

		SiteHealth.site_status.issues[ issue.status ]++;

		issueCounter.text( SiteHealth.site_status.issues[ issue.status ]);
		$( '.issues', '#health-check-issues-' + issue.status ).append( htmlOutput );
	}

	function RecalculateProgression() {
		var r, c, pct;
		var $progressBar = $( '#progressbar' );
		var $circle = $( '#progressbar svg #bar' );
		var totalTests = parseInt( SiteHealth.site_status.issues.good, 0 ) + parseInt( SiteHealth.site_status.issues.recommended, 0 ) + ( parseInt( SiteHealth.site_status.issues.critical, 0 ) * 1.5 );
		var failedTests = parseInt( SiteHealth.site_status.issues.recommended, 0 ) + ( parseInt( SiteHealth.site_status.issues.critical, 0 ) * 1.5 );
		var val = 100 - Math.ceil( ( failedTests / totalTests ) * 100 );

		if ( 0 === totalTests ) {
			$progressBar.addClass( 'hidden' );
			return;
		}

		$progressBar.removeClass( 'loading' );

		r = $circle.attr( 'r' );
		c = Math.PI * ( r * 2 );

		if ( 0 > val ) {
			val = 0;
		}
		if ( 100 < val ) {
			val = 100;
		}

		pct = ( ( 100 - val ) / 100 ) * c;

		$circle.css({ strokeDashoffset: pct });

		if ( 1 > parseInt( SiteHealth.site_status.issues.critical, 0 ) ) {
			$( '#health-check-issues-critical' ).addClass( 'hidden' );
		}

		if ( 1 > parseInt( SiteHealth.site_status.issues.recommended, 0 ) ) {
			$( '#health-check-issues-recommended' ).addClass( 'hidden' );
		}

		if ( 50 <= val ) {
			$circle.addClass( 'orange' ).removeClass( 'red' );
		}

		if ( 90 <= val ) {
			$circle.addClass( 'green' ).removeClass( 'orange' );
		}

		if ( 100 === val ) {
			$( '.site-status-all-clear' ).removeClass( 'hide' );
			$( '.site-status-has-issues' ).addClass( 'hide' );
		}

		$progressBar.attr( 'data-pct', val );
		$progressBar.attr( 'aria-valuenow', val );

		$( '.health-check-body' ).attr( 'aria-hidden', false );

		$.post(
			ajaxurl,
			{
				'action': 'health-check-site-status-result',
				'_wpnonce': SiteHealth.nonce.site_status_result,
				'counts': SiteHealth.site_status.issues
			}
		);

		wp.a11y.speak( SiteHealth.string.site_health_complete_screen_reader.replace( '%s', val + '%' ), 'polite' );
	}

	function maybeRunNextAsyncTest() {
		var doCalculation = true;

		if ( 1 <= SiteHealth.site_status.async.length ) {
			$.each( SiteHealth.site_status.async, function() {
				var data = {
					'action': 'health-check-' + this.test.replace( '_', '-' ),
					'_wpnonce': SiteHealth.nonce.site_status
				};

				if ( this.completed ) {
					return true;
				}

				doCalculation = false;

				this.completed = true;

				$.post(
					ajaxurl,
					data,
					function( response ) {
						AppendIssue( response.data );
						maybeRunNextAsyncTest();
					}
				);

				return false;
			});
		}

		if ( doCalculation ) {
			RecalculateProgression();
		}
	}

	if ( 'undefined' !== typeof SiteHealth ) {
		if ( 0 === SiteHealth.site_status.direct.length && 0 === SiteHealth.site_status.async.length ) {
			RecalculateProgression();
		} else {
			SiteHealth.site_status.issues = {
				'good': 0,
				'recommended': 0,
				'critical': 0
			};
		}

		if ( 0 < SiteHealth.site_status.direct.length ) {
			$.each( SiteHealth.site_status.direct, function() {
				AppendIssue( this );
			});
		}

		if ( 0 < SiteHealth.site_status.async.length ) {
			data = {
				'action': 'health-check-' + SiteHealth.site_status.async[0].test.replace( '_', '-' ),
				'_wpnonce': SiteHealth.nonce.site_status
			};

			SiteHealth.site_status.async[0].completed = true;

			$.post(
				ajaxurl,
				data,
				function( response ) {
					AppendIssue( response.data );
					maybeRunNextAsyncTest();
				}
			);
		} else {
			RecalculateProgression();
		}
	}

});
