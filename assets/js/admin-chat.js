(function () {
	const messagesEl = document.getElementById( 'wp-edit-with-ai-messages' );
	const inputEl = document.getElementById( 'wp-edit-with-ai-input' );
	const sendBtn = document.getElementById( 'wp-edit-with-ai-send' );

	function appendMessage( role, text ) {
		const el = document.createElement( 'div' );
		el.className = 'wp-edit-with-ai-message wp-edit-with-ai-message--' + role;
		el.textContent = text;
		messagesEl.appendChild( el );
		messagesEl.scrollTop = messagesEl.scrollHeight;
	}

	async function sendMessage() {
		const message = inputEl.value.trim();
		if ( ! message ) {
			return;
		}
		appendMessage( 'user', message );
		inputEl.value = '';
		sendBtn.disabled = true;

		try {
			const response = await fetch( window.wpEditWithAI.restUrl, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': window.wpEditWithAI.nonce,
				},
				body: JSON.stringify( { message } ),
			} );
			const data = await response.json();
			appendMessage( 'assistant', data.reply || 'No response.' );
		} catch ( err ) {
			appendMessage( 'assistant', 'Error: could not reach the server.' );
		} finally {
			sendBtn.disabled = false;
		}
	}

	sendBtn.addEventListener( 'click', sendMessage );
	inputEl.addEventListener( 'keydown', function ( e ) {
		if ( e.key === 'Enter' && ! e.shiftKey ) {
			e.preventDefault();
			sendMessage();
		}
	} );
})();
