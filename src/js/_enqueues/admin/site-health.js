/**
 * Interactions used by the Site Health modules in WordPress.
 *
 * @output wp-admin/js/site-health.js
 */

/* global ajaxurl, SiteHealth, wp */

jQuery(document).ready(function($) {

	// Debug information copy section.
	$( '.health-check-copy-field' ).click(function( e ) {
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

	$( '.health-check-toggle-copy-section' ).click(function( e ) {
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
	var data;

	$( '.site-health-view-passed' ).on( 'click', function() {
		var goodIssuesWrapper = $( '#health-check-issues-good' );

		goodIssuesWrapper.toggleClass( 'hidden' );
		$( this ).attr( 'aria-expanded', ! goodIssuesWrapper.hasClass( 'hidden' ) );
	});

	function HCAppendIssue( issue ) {
		var htmlOutput,
			issueWrapper,
			issueCounter;

		SiteHealth.site_status.issues[ issue.status ]++;

		issueWrapper = $( '#health-check-issues-' + issue.status );

		issueCounter = $( '.issue-count', issueWrapper );

		htmlOutput = '<dt role="heading" aria-level="4">\n' +
			'                <button aria-expanded="false" class="health-check-accordion-trigger" aria-controls="health-check-accordion-block-' + issue.test + '" id="health-check-accordion-heading-' + issue.test + '" type="button">\n' +
			'                    <span class="title">\n' +
			'                        ' + issue.label + '\n' +
			'                    </span>\n' +
			'                    <span class="badge ' + issue.badge.color + '">' + issue.badge.label + '</span>\n' +
			'                    <span class="icon"></span>\n' +
			'                </button>\n' +
			'            </dt>\n' +
			'            <dd id="health-check-accordion-block-' + issue.test + '" aria-labelledby="health-check-accordion-heading-' + issue.test + '" role="region" class="health-check-accordion-panel" hidden="hidden">\n' +
			'                ' + issue.description + '\n' +
			'                <div class="actions"><p>' + issue.actions + '</p></div>' +
			'            </dd>';

		issueCounter.text( SiteHealth.site_status.issues[ issue.status ] );
		$( '.issues', '#health-check-issues-' + issue.status ).append( htmlOutput );
	}

	function HCRecalculateProgression() {
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

		if ( isNaN( val ) ) {
			val = 100;
		}

		r = $circle.attr( 'r' );
		c = Math.PI * ( r * 2 );

		if ( val < 0 ) {
			val = 0;
		}
		if ( val > 100 ) {
			val = 100;
		}

		pct = ( ( 100 - val ) / 100 ) * c;

		$circle.css( { strokeDashoffset: pct } );

		if ( parseInt( SiteHealth.site_status.issues.critical, 0 ) < 1 ) {
			$( '#health-check-issues-critical' ).addClass( 'hidden' );
		}

		if ( parseInt( SiteHealth.site_status.issues.recommended, 0 ) < 1 ) {
			$( '#health-check-issues-recommended' ).addClass( 'hidden' );
		}

		if ( val >= 50 ) {
			$circle.addClass( 'orange' ).removeClass( 'red' );
		}

		if ( val >= 90 ) {
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

		if ( SiteHealth.site_status.async.length >= 1 ) {
			$.each( SiteHealth.site_status.async, function() {
				var data = {
					'action': 'health-check-site-status',
					'feature': this.test,
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
						HCAppendIssue( response.data );
						maybeRunNextAsyncTest();
					}
				);

				return false;
			} );
		}

		if ( doCalculation ) {
			HCRecalculateProgression();
		}
	}

	if ( 'undefined' !== typeof SiteHealth ) {
		if ( 0 === SiteHealth.site_status.direct.length && 0 === SiteHealth.site_status.async.length ) {
			HCRecalculateProgression();
		} else {
			SiteHealth.site_status.issues = {
				'good': 0,
				'recommended': 0,
				'critical': 0
			};
		}

		if ( SiteHealth.site_status.direct.length > 0 ) {
			$.each( SiteHealth.site_status.direct, function() {
				HCAppendIssue( this );
			});
		}

		if ( SiteHealth.site_status.async.length > 0 ) {
			data = {
				'action': 'health-check-site-status',
				'feature': SiteHealth.site_status.async[0].test,
				'_wpnonce': SiteHealth.nonce.site_status
			};

			SiteHealth.site_status.async[0].completed = true;

			$.post(
				ajaxurl,
				data,
				function( response ) {
					HCAppendIssue( response.data );
					maybeRunNextAsyncTest();
				}
			);
		} else {
			HCRecalculateProgression();
		}
	}

});
