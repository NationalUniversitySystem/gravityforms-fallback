( function( w, d ) {
	const forms = [ ...d.querySelectorAll( '.form--fallback' ) ];
	forms.forEach( form => {
		const formIdInputs = form.querySelectorAll( '.formID, .formid' );

		formIdInputs.forEach( input => {
			input.value = w.location.origin + w.location.pathname + '?fallback-form';
		} );
	} );
} )( window, document );
