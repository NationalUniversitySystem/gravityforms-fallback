( function( d ) {
	const forms = [ ...d.querySelectorAll( '.form--fallback' ) ];
	forms.forEach( form => {
		const degreeSelect = form.querySelector( '.input--degree' );
		const programSelect = form.querySelector( '.input--program' );
		const programsDataElement = form.querySelector( '#programs-data' );

		if ( degreeSelect && programSelect && programsDataElement ) {
			const emptyOption = d.createElement( 'option' );
			emptyOption.value = '';
			emptyOption.label = ' ';
			emptyOption.selected = true;
			emptyOption.disabled = true;

			const programsData = JSON.parse( programsDataElement.value.replace( /\\'/g, "'" ) );

			degreeSelect.addEventListener( 'change', () => {
				const degreePrograms = programsData[ degreeSelect.value ].programs;
				programSelect.innerHTML = '';
				programSelect.appendChild( emptyOption );

				Object.keys( degreePrograms ).forEach( program => {
					const option = d.createElement( 'option' );
					option.value = degreePrograms[ program ].title;
					option.text = degreePrograms[ program ].title;
					programSelect.appendChild( option );
				} );
			} );
		}
	} );
} )( document );
