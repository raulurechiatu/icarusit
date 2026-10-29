(function($, undefined) {
	"use strict";

	window.VAMTAM = window.VAMTAM || {};

	$(function() {
		$(document).on('change select', '[data-field-filter]', function() {
			var prefix = $(this).attr('data-field-filter');
			var selected = $(':checked', this).val();

			var others = $(this).closest('.vamtam-config-group').find('.' + prefix).filter(':not(.hidden)');
			others.show().filter(':not(.' + prefix + '-' + selected + ')').hide();
		});

		$('[data-field-filter]').change();

		$(document).on('change', '.num_shown', function() {
			var wrap = $(this).closest('p').siblings('.hidden_wrap');
			wrap.children('div').hide();
			$('.hidden_el:lt(' + $(this).val() + ')', wrap).show();
		});

		$('.metabox').each(function() {
			var meta_tabs = $('<ul>').addClass('vamtam-meta-tabs');

			$('.config-separator:first', this).before(meta_tabs);
			$('.config-separator', this).each(function() {
				var id = $(this).text().replace(/[\s\n]+/g, '').toLowerCase();
				$(this).nextUntil('.config-separator').wrapAll('<div class="vamtam-meta-part" id="tab-' + id + '"></div>');
				$(this).css('cursor', 'pointer');
				if ($(this).next().is('.vamtam-meta-part')) {
					meta_tabs.append('<li class="vamtam-meta-tab '+$(this).attr('data-tab-class')+'"><a href="#tab-' + id + '" title="">' + $(this).text() + '</a></li>');
				}
				$(this).remove();
			});

			if(meta_tabs.children().length > 1) {
				meta_tabs.closest('.metabox').tabs();
			} else {
				meta_tabs.hide();
			}
		});

		$('#vamtam-config').tabs({
			activate: function(event, ui) {
				var hash = ui.newTab.context.hash;
				var element = $(hash);
				element.attr('id', '');
				window.location.hash = hash;
				element.attr('id', hash.replace('#', ''));

				$('.save-vamtam-config').show();
				if (ui.newTab.hasClass('nosave')) $('.save-vamtam-config').hide();
			},
			create: function(event, ui) {
				if (ui.tab.hasClass('nosave')) $('.save-vamtam-config').hide();
			}
		});

		$('body').on('click', '.info-wrapper > a', function(e) {
			var other = $(this).attr('data-other');
			$(this).attr('data-other', $(this).text()).text(other);
			$(this).siblings('.desc').slideToggle(200);
			e.preventDefault();
		});

		// Asynchronously posts a given form using the default Settings API approach (options.php).
		function save_options_ajax( $formToSave, disableForm ) {
			$formToSave = $formToSave || $( 'form[method="post"][action="options.php"]' );
			if ( $formToSave ) {
				$formToSave.unbind( 'submit' );
				$formToSave.on( 'submit', function () {
					$('#vamtam-post-result span.spinner').addClass( 'is-active' );
					var b =  $(this).serialize();

					// Disable here so serialize can work properly.
					disableForm && $formToSave.find( ':input' ).attr("disabled", true);

					$.post( 'options.php', b )
						.error( function() {
							$('#vamtam-post-result .vamtam-post-msg-failure').show();
							$('#vamtam-post-result').show('slow');
							$('#vamtam-post-result span.spinner').removeClass( 'is-active' );
						})
						.success( function() {
							$('#vamtam-post-result .vamtam-post-msg-success').show();
							$('#vamtam-post-result').show('slow');
							$('#vamtam-post-result span.spinner').removeClass( 'is-active' );
						})
						.done( function () {
							setTimeout( function () {
								$('#vamtam-post-result').hide('slow');
								$('#vamtam-post-result > p').hide();
							}, 3000 );
							disableForm && $formToSave.find( ':input' ).attr("disabled", false);
						} );
						return false;
				});
				$formToSave.submit();
			}
		}

		const $licenseRadios = $( '#vamtam-envato-market-radios input[type="radio"]' );
		const $licenseInput = $( '#vamtam-envato-license-key' );
		const ownStoreKeyPattern = /^VAMTAM-[A-Z0-9]{5}(?:-[A-Z0-9]{5}){5}$/i;
		const envatoMarketPattern = /^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i;
		const envatoElementsPattern = /^[A-Z0-9]{10}$/i;

		const setRegistrationMode = function( mode ) {
			const $table = $( '#vamtam-register-form' ).children( 'table.form-table' );
			const $th = $table.find( 'tr:first > th' );
			const hasOwnStoreTab = $( '#vamtam-own-store-radio' ).length > 0;

			// A previous validation error names the old code type ("...vamtam.com purchase
			// code..."), so clear the message and the Invalid badge when the type changes.
			$( '#vamtam-check-license-result' ).empty();
			$( '#vamtam-license-result-wrap > .invalid' ).hide();

			if ( mode === 'vamtam' && ! hasOwnStoreTab ) {
				mode = 'market';
			}

			// Show the licensing-terms link matching the selected type (all three are
			// rendered in PHP). Only while the tabs are shown (registering); the registered
			// state keeps the server-rendered link that matches the saved license source.
			if ( $( '#vamtam-envato-market-radios' ).length ) {
				$( '.vamtam-licensing-terms-link' ).hide()
					.filter( '[data-license-type="' + mode + '"]' ).show();
			}

			if ( mode === 'elements' ) {
				$( '#vamtam-envato-elements-radio' ).prop( 'checked', true );
				$( '#vamtam-envato-logo' ).removeClass( 'hidden' );
				$( '#vamtam-envato-market-logo' ).addClass( 'hidden' );
				$( '#vamtam-own-store-logo' ).addClass( 'hidden' );
				$( '#vamtam-code-help' ).addClass( 'hidden' );
				$( '#vamtam-code-help-elements' ).removeClass( 'hidden' );
				$th.text( window.VAMTAM_ADMIN.elementsTxt );
				return;
			}

			if ( mode === 'vamtam' ) {
				$( '#vamtam-own-store-radio' ).prop( 'checked', true );
				$( '#vamtam-envato-logo' ).addClass( 'hidden' );
				$( '#vamtam-envato-market-logo' ).addClass( 'hidden' );
				$( '#vamtam-own-store-logo' ).removeClass( 'hidden' );
				$( '#vamtam-code-help' ).removeClass( 'hidden' );
				$( '#vamtam-help-envato' ).addClass( 'hidden' );
				$( '#vamtam-help-own-store' ).removeClass( 'hidden' );
				$( '#vamtam-code-help-elements' ).addClass( 'hidden' );
				$th.text( window.VAMTAM_ADMIN.ownStoreTxt );
				return;
			}

			$( '#vamtam-envato-market-radio' ).prop( 'checked', true );
			$( '#vamtam-envato-logo' ).addClass( 'hidden' );
			$( '#vamtam-envato-market-logo' ).removeClass( 'hidden' );
			$( '#vamtam-own-store-logo' ).addClass( 'hidden' );
			$( '#vamtam-code-help-elements' ).addClass( 'hidden' );
			$( '#vamtam-code-help' ).removeClass( 'hidden' );
			$( '#vamtam-help-own-store' ).addClass( 'hidden' );
			$( '#vamtam-help-envato' ).removeClass( 'hidden' );
			$th.text( window.VAMTAM_ADMIN.tfPcTxt );
		};

		const detectRegistrationMode = function( key ) {
			const normalized = ( key || '' ).trim();

			if ( ownStoreKeyPattern.test( normalized ) ) {
				return 'vamtam';
			}

			if ( envatoMarketPattern.test( normalized ) ) {
				return 'market';
			}

			if ( envatoElementsPattern.test( normalized ) ) {
				return 'elements';
			}

			return null;
		};

		$licenseRadios.on( 'change', function () {
			if ( $( this ).is( '#vamtam-envato-elements-radio:checked' ) ) {
				setRegistrationMode( 'elements' );
			} else if ( $( this ).is( '#vamtam-own-store-radio:checked' ) ) {
				setRegistrationMode( 'vamtam' );
			} else {
				setRegistrationMode( 'market' );
			}
		} );

		$licenseInput.on( 'input', function() {
			const detectedMode = detectRegistrationMode( $( this ).val() );
			if ( detectedMode ) {
				setRegistrationMode( detectedMode );
			}
		} );

		if ( $( '#vamtam-own-store-radio:checked' ).length > 0 ) {
			setRegistrationMode( 'vamtam' );
		} else if ( $( '#vamtam-envato-elements-radio:checked' ).length > 0 ) {
			setRegistrationMode( 'elements' );
		} else {
			setRegistrationMode( 'market' );
		}

		const initialDetectedMode = detectRegistrationMode( $licenseInput.val() );
		if ( initialDetectedMode ) {
			setRegistrationMode( initialDetectedMode );
		}


		$( document ).ready(function() {

			//help page, enable status gathering radios
			$( '#vamtam-ts-help form input[type="radio"]' ).each( function () {
				$(this).on( 'change', function() {
					save_options_ajax( $(this).closest( 'form' ), true );
				});
			} );

			//dashboard register copy button
			$( 'button#vamtam-check-license' ).on( 'click', function () {
				save_options_ajax( $(this).closest( 'form' ) );
			} );
		});
	});

})(jQuery);
