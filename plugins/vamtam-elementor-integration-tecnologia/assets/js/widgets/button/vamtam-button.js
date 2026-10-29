class VamtamButton extends elementorModules.frontend.handlers.Base {
	getDefaultSettings() {
		return {
			selectors: {
				btnText: '.elementor-button-text',
			},
		};
	}

	getDefaultElements() {
		const selectors = this.getSettings( 'selectors' );
		return {
			$btnText: this.$element.find( selectors.btnText ),
		};
	}

	onInit( ...args ) {
		super.onInit( ...args );
		this.handleBtnUnderlineAnimation();
	}

	handleBtnUnderlineAnimation() {
		if ( ! this.$element.hasClass( 'vamtam-has-underline-anim' ) ) {
			return;
		}

		/*
			Because on buttons the text container is using flex, all its children are forced to block-level.
			We need inline for the underline animation to work properly on multiline text so we add a new
			nested span.

			TODO: Maybe do this on server.
		*/
		const btnText = this.elements.$btnText.text();
		this.elements.$btnText.text('');
		this.elements.$btnText.append('<span class="vamtam-btn-text">' + btnText + '</span>');

		// Add class on hover to trigger the animation.
		jQuery( this.$element ).on( 'mouseenter', '.elementor-button', (e) => {
			const $el = jQuery( e.target );
			if ( $el.hasClass( 'hovered' ) ) {
				return;
			}

			$el.addClass( 'hovered' );

			// This timeout is used as a guard to avoid flickering caused by very fast chnages of hover state.
			setTimeout(() => {
				$el.removeClass( 'hovered' );
			}, 600 );
		} );
	}
}


jQuery( window ).on( 'elementor/frontend/init', () => {
	if ( ! elementorFrontend.elementsHandler || ! elementorFrontend.elementsHandler.attachHandler ) {
		const addHandler = ( $element ) => {
			elementorFrontend.elementsHandler.addHandler( VamtamButton, {
				$element,
			} );
		};

		elementorFrontend.hooks.addAction( 'frontend/element_ready/button.default', addHandler, 100 );
	} else {
		elementorFrontend.elementsHandler.attachHandler( 'button', VamtamButton );
	}
} );
