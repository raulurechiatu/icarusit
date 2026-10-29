/*
 VamTam Import Buttons
 */

/*global jQuery*/

(function( $ ) {
	'use strict';

	$( function() {
		$( 'body' ).on( 'click', '.vamtam-import-button', function( e ) {
			e.preventDefault();

			var button = $( this );

			if ( ! button.hasClass( 'disabled' ) ) {
				button.addClass( 'disabled' );

				var spinner = $( '<span></span>' ).addClass( 'spinner' ).css( {
					visibility: 'visible',
					float: 'none',
					'vertical-align': 'top'
				} );

				button.siblings( '.import-success' ).remove();

				button.after( spinner );

				$.get( button.attr( 'href' ) )
					.done( function( result ) {
						spinner.remove();

						var result_wrap = $( '<span />' );

						if ( result.match( /all done\./i ) ) {
							//Update result msg
							result_wrap.html( button.data( 'success-msg' ) ).addClass( 'import-success' );
							//Update btn text
							button.text( 'Imported' ).addClass( 'done' );
							//Enable widget btn and update message
							var $widgetBtn = $('a#widget-import-button');
							if ( $widgetBtn.hasClass('disabled') && $($widgetBtn).siblings('span.spinner').length === 0 ) {
								$widgetBtn.removeClass('disabled');
								var $widgetDesc = $widgetBtn.parent().siblings('p.vamtam-description');
								$widgetDesc.addClass('warning').text($widgetDesc.data('warning-msg'));
							}

							if ( button.attr( 'id' ) === 'content-import-button' ) {
								button.closest( '.form-table' ).find( '.disabled.content-disabled' ).removeClass( 'disabled content-disabled' );
								start_polling( result_wrap );
								button.after( result_wrap );
							}

						} else {
							result_wrap.html( button.data( 'error-msg' ).replace( '{fullimport}', button.attr( 'href' ) ) ).addClass( 'import-fail' );
							button.closest( '.vamtam-box-wrap' ).addClass( 'import-fail' );
							button.after( result_wrap );
						}
					} )
					.fail( function( result ) {
						spinner.remove();

						var result_wrap = $( '<span />' );

						if ( result.status === 500 || result.status === 408 ) {
							result_wrap.html( button.data( 'timeout-msg' ).replace( '{fullimport}', button.attr( 'href' ) ) ).addClass( 'import-fail' );
						} else if ( result.status !== 404 ) {
							result_wrap.html( button.data( 'fail-msg' ).replace( '{fullimport}', button.attr( 'href' ) ).replace( '{statuscode}', result.status ) ).addClass( 'import-fail' );
						} else {
							result_wrap.html( button.data( 'error-msg' ).replace( '{fullimport}', button.attr( 'href' ) ) ).addClass( 'import-fail' );
						}

						button.closest( '.vamtam-box-wrap' ).addClass( 'import-fail' );
						button.after( result_wrap );
					} );
			}
		} );

		var alreadyImported = $( '#content-import-button[data-content-imported=1]' );

		if ( alreadyImported.length ) {
			var result_wrap = $( '<span />' );

			result_wrap.html( alreadyImported.data( 'success-msg' ) ).addClass( 'import-success' );

			start_polling( result_wrap );

			alreadyImported.after( result_wrap );
		}
	} );

	var POLL_INTERVAL = 2000;

	// Number of unchanged polls before we act. The queue only moves via the async loopback
	// request or the wp-cron healthcheck, so a frozen counter means neither is running.
	var AUTO_RESUME_AFTER = 30; // 60s - try resuming on our own first
	var ASK_USER_AFTER    = 75; // 150s - the auto attempt didn't help either, hand it to the user

	var resume_notice = function( wrap ) {
		// .import-btn-wrap is a flex row - keep the notice out of it so it gets its own line.
		return wrap.closest( '.import-btn-wrap' ).siblings( '.vamtam-import-resume' );
	};

	var show_resume_notice = function( wrap, resuming ) {
		var vars   = window.vamtamImportButtonsVars,
			notice = resume_notice( wrap );

		if ( ! notice.length ) {
			notice = $( '<div />' ).addClass( 'vamtam-import-resume' )
				.css( { 'margin-top': '10px' } )
				.insertAfter( wrap.closest( '.import-btn-wrap' ) );
		}

		if ( resuming ) {
			notice.text( vars.resumingmsg );
		} else {
			// The page was loaded through the resume URL, so a manual resume has already been
			// tried and didn't hold - only now is it worth pointing at WP-Cron.
			notice.empty()
				.append( $( '<p />' ).text( vars.resumed ? vars.stalledagain : vars.stalledmsg ) )
				.append( $( '<a />' ).addClass( 'button button-primary' ).attr( 'href', vars.resumeurl ).text( vars.resumelabel ) );
		}
	};

	var start_polling = function( wrap ) {
		var lastRemaining = null,
			stalledPolls  = 0;

		const pollTimer = setInterval( () => {
			$.ajax( {
				type: "POST",
				url: window.vamtamImportButtonsVars.ajaxurl,
				data: {
					action: 'vamtam_attachment_progress',
					_ajax_nonce: window.vamtamImportButtonsVars.nonce
				},
				success: function ( resp ) {
					// Update progress text.
					if ( resp.vamtam_attachment_import_progress ) {
						wrap.find( '.vamtam-image-import-progress' ).text( resp.vamtam_attachment_import_progress );
					}
					// Exit.
					if ( resp.done === true ) {
						clearInterval( pollTimer );
						resume_notice( wrap ).remove();
						return;
					}

					// Older plugin versions don't send `remaining` - no stall detection then.
					if ( typeof resp.remaining !== 'number' ) {
						return;
					}

					if ( resp.remaining !== lastRemaining ) {
						lastRemaining = resp.remaining;
						stalledPolls  = 0;
						resume_notice( wrap ).remove();
						return;
					}

					stalledPolls++;

					if ( stalledPolls === AUTO_RESUME_AFTER ) {
						show_resume_notice( wrap, true );
						// Safe to fire blind: process_attachment() dedupes via post_exists(),
						// so re-queuing never creates duplicate media.
						$.get( window.vamtamImportButtonsVars.resumeurl );
					} else if ( stalledPolls === ASK_USER_AFTER ) {
						show_resume_notice( wrap, false );
					}
				}
			} );
		}, POLL_INTERVAL );
	};
})( jQuery );
