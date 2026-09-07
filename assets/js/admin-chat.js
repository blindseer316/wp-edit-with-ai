(function () {
	const messagesEl = document.getElementById( 'wp-edit-with-ai-messages' );
	const inputEl = document.getElementById( 'wp-edit-with-ai-input' );
	const sendBtn = document.getElementById( 'wp-edit-with-ai-send' );

	// In-memory only — resets on page reload. Sent back with every message
	// so the model has context of earlier turns in this chat.
	const history = [];

	function escapeHtml( str ) {
		const div = document.createElement( 'div' );
		div.textContent = str;
		return div.innerHTML;
	}

	/**
	 * Minimal markdown-to-HTML: bold, italic, inline code, bullet/numbered
	 * lists, and paragraph breaks. Input is escaped first so only markdown
	 * syntax we explicitly convert becomes HTML — nothing else the model
	 * outputs is trusted as markup.
	 */
	function markdownToHtml( text ) {
		const escaped = escapeHtml( text );
		const lines = escaped.split( '\n' );
		let html = '';
		let inList = false;

		lines.forEach( function ( line ) {
			const bulletMatch = line.match( /^\s*[-*]\s+(.*)/ );
			const numberedMatch = line.match( /^\s*\d+\.\s+(.*)/ );

			if ( bulletMatch || numberedMatch ) {
				if ( ! inList ) {
					html += '<ul>';
					inList = true;
				}
				html += '<li>' + inline( ( bulletMatch || numberedMatch )[ 1 ] ) + '</li>';
				return;
			}

			if ( inList ) {
				html += '</ul>';
				inList = false;
			}

			if ( line.trim() === '' ) {
				html += '<br>';
			} else {
				html += '<p>' + inline( line ) + '</p>';
			}
		} );

		if ( inList ) {
			html += '</ul>';
		}

		return html;
	}

	function inline( str ) {
		return str
			.replace( /\*\*(.+?)\*\*/g, '<strong>$1</strong>' )
			.replace( /`(.+?)`/g, '<code>$1</code>' )
			.replace( /\*(.+?)\*/g, '<em>$1</em>' );
	}

	function appendMessage( role, text ) {
		const el = document.createElement( 'div' );
		el.className = 'wp-edit-with-ai-message wp-edit-with-ai-message--' + role;
		if ( role === 'assistant' ) {
			el.innerHTML = markdownToHtml( text );
		} else {
			el.textContent = text;
		}
		messagesEl.appendChild( el );
		messagesEl.scrollTop = messagesEl.scrollHeight;
		return el;
	}

	function appendActions( actions ) {
		if ( ! actions || ! actions.length ) {
			return;
		}
		const el = document.createElement( 'div' );
		el.className = 'wp-edit-with-ai-message wp-edit-with-ai-message--actions';
		el.innerHTML = actions
			.map( function ( a ) {
				const ok = a.result && a.result.error ? '❌' : '✅';
				return ok + ' <strong>' + escapeHtml( a.tool ) + '</strong>(' + escapeHtml( JSON.stringify( a.args ) ) + ')';
			} )
			.join( '<br>' );
		messagesEl.appendChild( el );
		messagesEl.scrollTop = messagesEl.scrollHeight;
	}

	function appendPending() {
		const el = document.createElement( 'div' );
		el.className = 'wp-edit-with-ai-message wp-edit-with-ai-message--pending';
		el.innerHTML = 'Thinking<span class="wp-edit-with-ai-dots"><span>.</span><span>.</span><span>.</span></span>';
		messagesEl.appendChild( el );
		messagesEl.scrollTop = messagesEl.scrollHeight;
		return el;
	}

	async function sendMessage() {
		const message = inputEl.value.trim();
		if ( ! message ) {
			return;
		}
		appendMessage( 'user', message );
		inputEl.value = '';
		sendBtn.disabled = true;

		const pendingEl = appendPending();

		try {
			const response = await fetch( window.wpEditWithAI.restUrl, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': window.wpEditWithAI.nonce,
				},
				body: JSON.stringify( { message, history } ),
			} );
			const data = await response.json();
			pendingEl.remove();
			appendActions( data.actions );
			const reply = data.reply || 'No response.';
			appendMessage( 'assistant', reply );
			history.push( { role: 'user', text: message } );
			history.push( { role: 'assistant', text: reply } );
		} catch ( err ) {
			pendingEl.remove();
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
