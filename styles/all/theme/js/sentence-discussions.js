$(() => {
	const isPostingPage = location.pathname.split(/[./]/).at(-2) === 'posting'

	if (!isPostingPage) {
		return
	}

	// Necessary to allow emojis in username, e.g. "sentence bot 🤖",
	// e.g. on topic edit by mods

	const fenceposts = {
		// https://docs.microsoft.com/en-us/globalization/encoding/surrogate-pairs
		high: [0xD800, 0xDBFF],
		low: [0xDC00, 0xDFFF],
		// https://en.wikipedia.org/wiki/Private_Use_Areas
		privateUse: [0xE000, 0xF8FF],
	}

	const diff = fenceposts.privateUse[0] - fenceposts.high[0]

	const _convertChar = diff => char => char.split('').map(surrogate =>
		String.fromCharCode(surrogate.charCodeAt(0) + diff)
	).join('')

	const encodeChar = _convertChar(diff)
	const decodeChar = _convertChar(-diff)

	const _regexSrc = (diff) => ['high', 'low'].map(k => '[' + fenceposts[k].map(
		n => '\\u' + (n + diff).toString(16).padStart(4, '0')
	).join('-') + ']').join('')

	const encodeRegexSrc = _regexSrc(0)
	const decodeRegexSrc = _regexSrc(diff)

	const _convert = (regexSrc, convertChar) => (str) => {
		const surrogatePairRe = new RegExp(regexSrc, 'g')

		return str.replace(surrogatePairRe, convertChar)
	}

	const encode = _convert(encodeRegexSrc, encodeChar)
	const decode = _convert(decodeRegexSrc, decodeChar)

	const $usernameField = $('#username')

	if ($usernameField.length === 1) {
		$usernameField.val(decode($usernameField.val()))
	}

	$('.submit-buttons input[type=submit]').on('click', () => {
		const $usernameField = $('#username')
		const val = $usernameField.val()

		if (val) {
			$usernameField.val(encode(val))
			$usernameField.attr('type', 'hidden')

			$usernameField.parent().append(
				$('<input>').attr('type', 'text')
					.attr('class', $usernameField.attr('class'))
					.attr('size', $usernameField.attr('size'))
					.val(val)
			)
		}
	})
})
