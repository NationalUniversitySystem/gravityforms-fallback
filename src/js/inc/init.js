import axios from 'axios';

( function( d ) {
	axios.get( window.location.origin + '/wp-json/gravityforms-fallback/v0.0.4/faux/', {
		timeout: 2000,
	} )
		.then( response => {
			if ( 200 !== response.status ) {
				revealFallBackForms();
				activateFallBackForms();
			}
		} )
		.catch( () => {
			revealFallBackForms();
			activateFallBackForms();
		} );

	function revealFallBackForms() {
		const gforms = d.querySelectorAll( 'form[id^="gform"]:not([id$="fallback"])' );
		gforms.forEach( form => {
			form.style.display = 'none';
			const fallbackForm = d.querySelector( '#' + form.id + '_fallback' );

			if ( fallbackForm ) {
				fallbackForm.classList.remove( 'd-none' );
			}
		} );
	}

	function activateFallBackForms() {
		const fallbackForms = [ ...d.querySelectorAll( '.form--fallback' ) ];

		fallbackForms.forEach( form => {
			const formMessage = form.querySelector( '.form__message' );

			form.addEventListener( 'submit', event => {
				event.preventDefault();

				const formData = new FormData( form );
				const formNode = d.getElementById( form.id );
				const feedElements = formNode.querySelectorAll( 'input[name="feeds"]' );
				const formKeys = formData.keys();

				if ( feedElements ) {
					feedElements.forEach( feedElement => {
						const feedName = feedElement.dataset.feedName;
						const dataForFeed = new FormData();

						if ( 'eloqua' === feedName ) {
							dataForFeed.append( 'elqsiteid', feedElement.dataset.elqsiteid );
							dataForFeed.append( 'elqformname', feedElement.dataset.elqformname );
						}

						[ ...formKeys ].forEach( function( key ) {
							const element = d.querySelector( '#' + form.id + ' #' + key );

							if ( element ) {
								const dataKey = element.dataset[ 'key' + feedName.charAt( 0 ).toUpperCase() + feedName.slice( 1 ) ];

								if ( dataKey && formData.get( key ) ) {
									dataForFeed.append( dataKey, formData.get( key ) );
								}
							}
						} );

						axios( {
							method: feedElement.dataset.feedMethod,
							url: feedElement.dataset.feedAction,
							data: dataForFeed,
						} )
							.then( response => {
								if ( 200 !== response.status ) {
									formMessage.innerHTML = 'There was an error with your submission. Please try again.';
									form.querySelector( 'input[type="submit"' ).removeAttribute( 'disabled' );
								} else {
									formMessage.innerHTML = 'Thank you for your submission.';
								}
							} )
							.catch( () => {
								formMessage.innerHTML = 'There was an error with your submission. Please try again.';
								form.querySelector( 'input[type="submit"' ).removeAttribute( 'disabled' );
							} );
					} );
				}

				return false;
			} );
		} );
	}
} )( document );
