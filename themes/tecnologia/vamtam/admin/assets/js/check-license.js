/*
 VamTam Check License
 */

/*global jQuery*/

(function( $ ) {
	'use strict';

	$('body').on('click', '#vamtam-check-license', function(e) {
		e.preventDefault();

		var self = $(this);

		if ( self.hasClass('disabled' ) ) return false;

		var result = $('#vamtam-check-license-result').html('').css( 'display', 'block' );
		var $validMsg = $('#vamtam-license-result-wrap > .valid');
		var $invalidMsg = $('#vamtam-license-result-wrap > .invalid');
		var isUnregister = self.hasClass('unregister');
		var $licenseInput = $('#vamtam-envato-license-key');
		var keyValue = $licenseInput.val();
		var ownStoreKeyPattern = /^VAMTAM-[A-Z0-9]{5}(?:-[A-Z0-9]{5}){5}$/i;
		var envatoMarketPattern = /^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i;
		var envatoElementsPattern = /^[A-Z0-9]{10}$/i;
		// Elements codes used to share the Envato Market format. Users who already have a
		// registered Elements token (this is a "1" flag) keep working with it; the new
		// format is only enforced for new registrations. Unregistering clears the flag,
		// so afterwards they too must use the new format.
		var hasSavedElementsToken = ! ! window.VAMTAM_ADMIN.isElementsToken;
		var valAttempt = 0;

		var getSelectedKeySource = function() {
			if ( $('#vamtam-envato-elements-radio').is(':checked') ) {
				return 'elements';
			}

			if ( $('#vamtam-own-store-radio').is(':checked') ) {
				return 'vamtam';
			}

			return 'market';
		};

		var getAttemptOrder = function( selectedSource, key ) {
			var normalized = ( key || '' ).trim();

			// A VAMTAM-* code picked as ThemeForest or Elements is an obvious mistake:
			// it is a vamtam.com code. Move it to the vamtam.com field and validate it
			// there, before even hitting the ThemeForest / Elements checks.
			if ( ( selectedSource === 'market' || selectedSource === 'elements' ) && ownStoreKeyPattern.test( normalized ) ) {
				var $ownStore = $('#vamtam-own-store-radio');
				if ( $ownStore.length ) {
					$ownStore.prop('checked', true).trigger('change');
				}
				return [ 'vamtam' ];
			}

			// The user explicitly picked a store (vamtam.com or ThemeForest). Trust
			// that it is not an Elements token and never silently fall back to
			// Elements — Elements now validates any input, so a wrong code must be
			// reported invalid rather than accepted under a source the user didn't pick.
			if ( selectedSource === 'vamtam' || selectedSource === 'market' ) {
				return [ selectedSource ];
			}

			// Elements selected: the Elements API is gone and any token is trusted, so
			// verify ThemeForest first and only accept it as an Elements token if that
			// rejects it. vamtam.com isn't worth checking here — a VAMTAM-* code would
			// already have been redirected above, so this code can't be a vamtam.com one.
			return [ 'market', 'elements' ];
		};

		var keySource = getSelectedKeySource();
		var attemptOrder = getAttemptOrder( keySource, keyValue );

		var sourceToIsToken = function( source ) {
			return source === 'elements';
		};

		var showInvalid = function( message ) {
			result.append( $('<p />').addClass('vamtam-check-license-response').html( message ) );
			$('#vamtam-license-result-wrap > .invalid').css('display', 'flex');
		};

		var trimmedKey = keyValue.trim();

		// When the vamtam.com tab is selected, the code must match the exact
		// VAMTAM-* format issued by vamtam.com, so users don't accidentally paste
		// an Envato Market / Elements code into the vamtam.com field.
		if ( ! isUnregister && keySource === 'vamtam' && ! ownStoreKeyPattern.test( trimmedKey ) ) {
			showInvalid( window.VAMTAM_ADMIN.invalidOwnStoreTxt );
			return false;
		}

		// When the Envato Market tab is selected, the code must be an Envato Market
		// purchase code (a UUID). A VAMTAM-* code is allowed through — getAttemptOrder
		// redirects it to the vamtam.com field instead.
		if ( ! isUnregister && keySource === 'market' && ! envatoMarketPattern.test( trimmedKey ) && ! ownStoreKeyPattern.test( trimmedKey ) ) {
			showInvalid( window.VAMTAM_ADMIN.invalidMarketTxt );
			return false;
		}

		// When the Envato Elements tab is selected, a new registration must use the current
		// Envato Elements license code format (10-char alphanumeric, e.g. AVUF6MZXN7). A
		// VAMTAM-* code is allowed through — getAttemptOrder redirects it to the vamtam.com
		// field instead. Existing token holders (hasSavedElementsToken) are exempt, so an
		// older-format token that is still registered is never flagged.
		if ( ! isUnregister && keySource === 'elements' &&
			! hasSavedElementsToken &&
			! envatoElementsPattern.test( trimmedKey ) &&
			! ownStoreKeyPattern.test( trimmedKey ) ) {
			showInvalid( window.VAMTAM_ADMIN.invalidElementsTxt );
			return false;
		}

		if ( isUnregister ) {
			var isElementsToken = window.VAMTAM_ADMIN.isElementsToken;
			var ownStoreKey = ownStoreKeyPattern.test( keyValue );
			var unregisterText = isElementsToken ?
				( keyValue ? window.VAMTAM_ADMIN.unRegTokenTxt : window.VAMTAM_ADMIN.unRegInvalidTokenTxt ) :
				( ownStoreKey ? window.VAMTAM_ADMIN.unRegOwnStoreTxt : window.VAMTAM_ADMIN.unRegPcTxt );

			if ( confirm( unregisterText ) ) {
				$licenseInput.attr('value', '');
			} else {
				return;
			}
		}
		$licenseInput.prop('disabled', true);

		$validMsg.hide();
		$invalidMsg.hide();

		self.css('display', 'inline-block').addClass('disabled');

		// Place the spinner next to the button (not after the input) — the input lives in the
		// boxed field which has overflow:hidden and would clip it.
		var spinner = $('#vamtam-check-license ~ span.spinner');
		if ( spinner.length > 0 ) {
			spinner.show();
		} else {
			$('#vamtam-check-license').after('<span class="spinner is-active" style="display:inline-block;float:none;vertical-align:middle;" />');
			spinner = $('#vamtam-check-license ~ span.spinner');
		}

		const postData = {
			action: 'vamtam-check-license',
			'license-key': keyValue,
			nonce: self.attr('data-nonce'),
			unregister: isUnregister ? true : false,
			key_source: attemptOrder[ valAttempt ],
			is_token: sourceToIsToken( attemptOrder[ valAttempt ] ),
		};

		const do_post = ( postData ) => {
			$.ajax({
				type: 'POST',
				url: ajaxurl,
				data: postData,
				success: function(data) {
					if ( data.includes( 'Valid Purchase Key' ) ) {
						window.location = window.location.href;
					} else if ( data.includes( 'Incorrect Purchase Key' ) ) {
						if ( isUnregister ) {
							window.location = window.location.href;
						} else {
							if ( valAttempt < attemptOrder.length - 1 ) {
								valAttempt++;
								postData.key_source = attemptOrder[ valAttempt ];
								postData.is_token = sourceToIsToken( postData.key_source );
								do_post( postData );
							} else {
								$invalidMsg.css('display', 'flex');
								self.removeClass('disabled');
								$licenseInput.prop('disabled', false);
								spinner.hide();
							}
						}
					} else if ( data.includes( 'Unregistered Key' ) ) {
						window.location = window.location.href;
					} else if ( isUnregister && data.indexOf( 'id="fail"' ) === -1 ) {
						// A non-token unregister clears the key locally and returns no recognized
						// message; reload so the form reflects the cleared state instead of still
						// showing "Valid". A genuine failure (id="fail") falls through and is shown.
						window.location = window.location.href;
					} else {
						result.append( $('<p />').addClass('vamtam-check-license-response').append(data) );
						spinner.hide();
						self.removeClass('disabled');
						$licenseInput.prop('disabled', false);
					}
				}
			});
		};
		do_post( postData );
	});
})( jQuery );
