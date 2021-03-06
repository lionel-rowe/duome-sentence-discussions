// ==UserScript==
// @name			Duolingo Duome Sentence Discussions
// @namespace		http://tampermonkey.net/
// @version			0.1.18
// @description		Sentence discussions on Duome
// @author			https://forum.duome.eu/memberlist.php?mode=viewprofile&u=66-luo-ning
// @match			https://www.duolingo.com/
// @match			https://www.duolingo.com/*
// @run-at			document-start
// @icon			https://www.google.com/s2/favicons?sz=64&domain=duolingo.com
// @grant			none
// ==/UserScript==

;(() => {
	/** @typedef {{ type: string }} Challenge */

	/**
	 * @typedef {{
	 * 	challengeGeneratorId: string,
	 * 	learningSentence: string,
	 * 	fromSentence: string,
	 * 	learningSentenceAlternatives: string[],
	 * 	fromSentenceAlternatives: string[],
	 * 	pathname: string,
	 * 	learningLang: string,
	 * 	fromLang: string,
	 * 	duolingoForumTopicId: number | null,
	 * 	sentenceDiscussionId: string,
	 * }} DuomeApiDto
	 */

	const console = (/** @param {Console} c */ (c) => {
		for (const k in window.console) {
			c[k] = window.console[k]
		}

		return c
	})({})

	/** @param {string} dataStr */
	const getDuomeUrl = (dataStr) => `${Boolean(localStorage.duomeDebugMode)
		? 'http://localhost/forum'
		: 'https://forum.duome.eu'}/sentence-discussions/${dataStr}`

	let idx = 0
	let hasMadeMistakeThisRound = false
	let sdIsAvailable = false

	/**
	 * @type {{
	 * 	challenges: Challenge[],
	 * 	adaptiveChallenges?: Challenge[],
	 * 	adaptiveInterleavedChallenges: {
	 * 		challenges: Challenge[],
	 * 		speakOrListenReplacementIndices: (number | null)[]
	 * } | null}
	 */
	let session = null

	/**
	 * @typedef {{
	 * 	url: string,
	 * 	status: number,
	 * 	ok: boolean,
	 * 	getHeaders: () => Promise<Headers>,
	 * 	getBody: (type: 'json' | 'text' = 'json') => Promise<any>,
	 * }} AjaxResponse
	 *
	 * Generic AJAX response type - agnostic as to fetch vs XHR
	 */

	/**
	 * @param {(response: AjaxResponse) => void} listener
	 *
	 * modified from https://stackoverflow.com/questions/24555370/how-can-i-catch-and-process-the-data-from-the-xhr-responses-using-casperjs/58168312#58168312
	 * and https://blog.logrocket.com/intercepting-javascript-fetch-api-requests-responses/
	 */
	const addAjaxListener = (listener) => {
		// fetch

		const originalFetch = window.fetch

		window.fetch = async (...args) => {
			const res = await originalFetch(...args)

			const { url, status, ok, headers } = res
			const clone = res.clone()

			const getBody = (type = 'json') => clone[type]()
			const getHeaders = () => Promise.resolve(headers)

			listener({ url, status, ok, headers, getBody, getHeaders })

			return res
		}

		// XHR

		/** @this {XMLHttpRequest} */
		const onLoadHandler = function () {
			const xhr = this

			if (xhr.readyState === 4) {
				const { responseURL: url, status } = xhr
				const getBody = () => Promise.resolve(xhr.response)
				const getHeaders = () => Promise.resolve(
					new Headers(xhr.getAllResponseHeaders()
						.trim()
						.split(/[\r\n]+/)
						.map(line => line.split(/: /))
						.map(([k, v]) => [k.trim(), v.trim()]))
				)

				listener({ url, ok: status < 400, status, getBody, getHeaders })
			}
		}

		const { open, send } = XMLHttpRequest.prototype

		XMLHttpRequest.prototype.open = function (...args) {
			this.requestUrl = args[1]
			open.apply(this, args)
		}

		XMLHttpRequest.prototype.send = function (...args) {
			const xhr = this

			if (xhr.addEventListener) {
				xhr.removeEventListener('readystatechange', onLoadHandler)
				xhr.addEventListener('readystatechange', onLoadHandler, false)
			} else {
				let handler

				const readyStateChange = (...args) => {
					if (handler) {
						if (handler.handleEvent) {
							handler.handleEvent.apply(xhr, args)
						} else {
							handler.apply(xhr, args)
						}
					}
					onLoadHandler.apply(xhr, args)
					setReadyStateChange()
				}

				const setReadyStateChange = () => {
					setTimeout(() => {
						if (xhr.onreadystatechange !== readyStateChange) {
							handler = xhr.onreadystatechange
							xhr.onreadystatechange = readyStateChange
						}
					}, 1)
				}

				setReadyStateChange()
			}
			send.apply(xhr, args)
		}
	}

	/* === check element colors === */
	/* for determining if success, failure, or skip  */

	/** @param {HTMLElement} el */
	const toRgb = (el) =>
		(getComputedStyle(el).color.match(/\d+/g) ?? []).map(Number)

	/**
	 * @param {number} n
	 * @param {number} m
	 */
	const mod = (n, m) => ((n % m) + m) % m

	/** @param {[number, number, number] | [[number, number, number]]} rgb */
	const rgbToHue = (...rgb) => {
		const xs = rgb.flat().map(x => x / 255)

		const max = Math.max(...xs)
		const min = Math.min(...xs)

		const i = xs.indexOf(max)

		return max === min ? null : mod(
			((i * 2) + (xs[(i + 1) % 3] - xs[(i + 2) % 3]) / (max - min)) * 60,
			360,
		)
	}

	/** @typedef {[number, number]} Fenceposts */

	/**
	 * @param {number} hue
	 * @param {Fenceposts} fenceposts
	 */
	const isColor = (hue, [min, max]) => {
		if (hue == null) {
			return false
		}

		if (min > max) {
			return hue >= min || hue <= max
		}

		return hue >= min && hue <= max
	}

	/** @type {Record<'red' | 'yellow' | 'green', Fenceposts>} */
	const colors = {
		red: [320, 10],
		yellow: [10, 75],
		green: [75, 140],
	}

	/* === main logic === */

	const Selector = {
		NextButton: '[data-test="player-next"]',
		Blame: '[data-test*="blame"]',
		ChallengeControls: `#${CSS.escape('session/PlayerFooter')}`,
	}

	const sessionsUrl = 'https://www.duolingo.com/2017-06-30/sessions'
	const sessionsUrlMatcher =
		new RegExp(`^${sessionsUrl.replace(/\d/g, '\\d')}\/?$`)

	/** @param {Challenge} c */
	const isSpeak = (c) => c.type.startsWith('speak')
	/** @param {Challenge} c */
	const isListen = (c) => c.type.startsWith('listen')

	const observer = new MutationObserver((mutations) => {
		if (!session) return

		const {
			challenges,
			adaptiveChallenges,
			adaptiveInterleavedChallenges: a,
		} = session

		for (const mutation of mutations) {
			if (mutation.type === 'childList') {
				const addedBlame = [...mutation.addedNodes]
					.map(x => x instanceof HTMLElement
						&& x.querySelector(Selector.Blame)).filter(Boolean)[0]
				const removedBlame = [...mutation.removedNodes]
					.map(x => x instanceof HTMLElement
						&& x.querySelector(Selector.Blame)).filter(Boolean)[0]

				if (addedBlame) {
					const color = rgbToHue(toRgb(addedBlame))

					if (isColor(color, colors.yellow)) {
						// triggered by clicking "can't listen now" etc
						const challenge = challenges[idx]
						let recognizedReplacementType = false

						for (const isChallengeType of [isSpeak, isListen]) {
							if (isChallengeType(challenge)) {
								recognizedReplacementType = true

								for (const [i, ch] of challenges.entries()) {
									if (isChallengeType(ch)) {
										challenges.splice(i, 1, a.challenges[
											a.speakOrListenReplacementIndices[i]
										])
									}
								}

								--idx
								break
							}
						}

						if (!recognizedReplacementType) {
							console.error(
								'no known replacement strategy for',
								challenge,
							)
						}

						console.log(challenges)
					} else {
						const sibling = addedBlame.querySelector('button')
						renderBtnForCurrentChallenge(sibling)
						sdIsAvailable = true
					}

					if (isColor(color, colors.green)) {
						const hardChallenges = (adaptiveChallenges ?? [])
							.filter(x => x.indicatorType === 'HARD_CHALLENGE')

						// is correct
						if (!hasMadeMistakeThisRound && idx ===
							challenges.length - hardChallenges.length - 1) {
							// will use hard questions for the rest of the round
							challenges.splice(
								idx + 1,
								hardChallenges.length,
								...hardChallenges,
							)
						}
					}

					if (isColor(color, colors.red)) {
						// is incorrect
						if (isSpeak(challenges[idx])) {
							// decrement idx, as speaking challenges are simply
							// repeated if incorrect
							--idx
						} else {
							// Current challenge will be replayed at end
							hasMadeMistakeThisRound = true
							challenges.push(challenges[idx])
						}
					}
				} else if (removedBlame) {
					// is next challenge
					++idx
					console.log(challenges[idx])
					sdIsAvailable = false
				}
			}
		}
	})

	;(async () => {
		let tries = 0

		while (++tries <= 100) {
			const root = document.body

			if (root) {
				observer.observe(root, { childList: true, subtree: true })
				break
			} else {
				await new Promise(res => setTimeout(res, 10))
			}
		}

		if (tries > 1) {
			console.warn(`tried to find root element ${tries} times`)
		}
	})()

	addAjaxListener(async (res) => {
		if (sessionsUrlMatcher.test(res.url)) {
			session = window.session = await res.getBody()

			idx = 0
			hasMadeMistakeThisRound = false
			console.log(session.challenges[idx])

			// trigger console warnings for unknown challenge types
			session.challenges.forEach(getSentences)
		}
	})

	/** @param {Challenge} x */
	const joinTokens = (x) => x.displayTokens.map(y => y.text).join('')

	/** @param {string} str */
	const encodeB64UrlSafe = (str) => btoa([...new TextEncoder().encode(str)]
		.map((n) => String.fromCharCode(n)).join(''))
		.replace(/=/g, '').replace(/\+/g, '-').replace(/\//g, '_')

	/**
	 * @param {Challenge} x
	 * @returns {[string | string[] | null, string | string[] | null]}
	 */
	const getSrcAndTrgSentences = (x) => {
		switch (x.type) {
			case 'translate':
				return [
					x.prompt,
					[x.correctSolutions[0], ...x.compactTranslations],
				]

			case 'judge':
				return [x.prompt, x.correctIndices.map(i => x.choices[i])]

			case 'listen':
			case 'listenTap':
				return [x.prompt, x.solutionTranslation]

			case 'dialogue':
				return [x.choices[x.correctIndex], x.solutionTranslation]

			case 'speak':
				return [x.prompt, x.solutionTranslation]

			case 'completeReverseTranslation':
				return [joinTokens(x), x.prompt]

			case 'typeCloze':
			case 'tapCloze':
			case 'definition':
				return [joinTokens(x), null]

			case 'tapComplete':
			case 'gapFill':
				return [joinTokens(x), x.solutionTranslation]

			case 'form':
				return [
					x.promptPieces.join(x.choices[x.correctIndex]),
					x.solutionTranslation,
				]

			case 'listenComprehension':
				return [x.prompt, null]

			case 'readComprehension':
				return [x.passage, null]

			default: {
				const ignorables = [
					'tapCompleteTable',
					'typeClozeTable',
					'match',
					'assist',
					'select',
					'name',
					'selectPronunciation',
					'characterSelect',
					'characterMatch',
					'characterIntro',
					'characterTrace',
					'selectTranscription',
				]

				if (!ignorables.includes(x.type)) {
					console.warn(`unknown challenge type '${x.type}'`, x)
				}

				return [null, null]
			}
		}
	}

	/**
	 * @param {Challenge} challenge
	 * @returns {{
	 * 	learningSentence: string,
	 * 	fromSentence: string,
	 * 	learningSentenceAlternatives: string[],
	 * 	fromSentenceAlternatives: string[],
	 * } | null}
	 */
	const getSentences = (challenge) => {
		const [src, trg] = getSrcAndTrgSentences(challenge)

		const [from, learning] = challenge.metadata.from_language
			=== challenge.metadata.source_language
				? [trg, src]
				: [src, trg]

		const [
			[learningSentence, ...learningSentenceAlternatives],
			[fromSentence, ...fromSentenceAlternatives],
		] = [from, learning].map((x) =>
			(Array.isArray(x) ? [...new Set(x)] : [x]).filter(Boolean))

		if (!learningSentence) {
			return null
		}

		return {
			learningSentence,
			fromSentence,
			learningSentenceAlternatives,
			fromSentenceAlternatives,
		}
	}

	/** @param {DuomeApiDto} data */
	const toDuomeApiHref = (data) => {
		let dataStr = ''

		// clone to avoid side effects
		data = JSON.parse(JSON.stringify(data))

		// ensure within URL length limits, as determined from testing
		while (true) {
			dataStr = encodeB64UrlSafe(JSON.stringify(
				data,
				(_, v) => v == null ? null : v,
			).slice(1, -1))

			if (dataStr.length <= 4000) {
				return getDuomeUrl(dataStr)
			}

			if (!data.learningSentenceAlternatives.length
				&& !data.fromSentenceAlternatives.length) {
				// is impossible to further reduce URL length
				return null
			}

			data.learningSentenceAlternatives.pop()
			data.fromSentenceAlternatives.pop()
		}
	}

	/**
	 * @param {DuomeApiDto} data
	 * @returns {Promise<number | null>}
	 */
	const getDuolingoDiscussionId = async (data) => {
		const discussion = !data.sentenceDiscussionId
			? null
			: await fetch(
				Object.assign(
					new URL('https://www.duolingo.com/sentence/'
						+ data.sentenceDiscussionId), {
					search: new URLSearchParams(Object.entries({
						learning_language: data.learningLang,
						ui_language: data.fromLang,
						_: new Date().valueOf()
					})).toString()
				}
				).href, {
				'headers': {
					'accept': 'application/json, text/plain, */*',
				},
			}).then(x => x.json())

		return discussion?.comment?.id ?? null
	}

	/** @param {Challenge} challenge */
	const getDuomeApiDtoForChallenge = (challenge) => {
		const url = new URL(location.href)
		const { pathname } = url

		const {
			metadata: {
				from_language: fromLang,
				learning_language: learningLang,
			},
		} = challenge

		if (![learningLang, fromLang].every(Boolean)) {
			return null
		}

		const { challengeGeneratorIdentifier, sentenceDiscussionId } = challenge
		const challengeGeneratorId = challengeGeneratorIdentifier.generatorId

		const sentences = getSentences(challenge)

		if (!sentences) {
			return null
		}

		/** @type {Omit<DuomeApiDto, 'duolingoForumTopicId'>} */
		const data = {
			pathname,
			learningLang,
			fromLang,
			sentenceDiscussionId,
			challengeGeneratorId,
			...sentences,
		}

		return data
	}

	/** @param {number} idx */
	const openDuomeSdByIdx = async (idx) => {
		const challenge = session.challenges[idx]

		// https://stackoverflow.com/questions/2587677/avoid-browser-popup-blockers/25050893#25050893
		const tab = window.open('', '_blank')
		tab.document.write('Loading...')

		const _data = getDuomeApiDtoForChallenge(challenge)

		const data = {
			..._data,
			duolingoForumTopicId: await getDuolingoDiscussionId(_data)
		}

		const href = toDuomeApiHref(data)

		if (!href) {
			tab.close()

			setTimeout(() => {
				alert('Failed to get challenge data')
			}, 100);

			console.error('Failed to get challenge data', { challenge })
		} else {
			console.log({ data })

			tab.location.href = href
		}
	}

	const openCurrentDuomeSd = () => openDuomeSdByIdx(idx)

	/** @param {HTMLButtonElement} sibling */
	const renderBtnForCurrentChallenge = (sibling) => {
		const challenge = session.challenges[idx]

		// check challenge type is supported and can be used to create an SD
		if (!getDuomeApiDtoForChallenge(challenge)) {
			return null
		}

		const button = document.createElement('button')
		button.innerHTML = sibling.innerHTML
		button.className = sibling.className

		const [icon, text] = button.querySelectorAll('span span')

		try {
			icon.innerHTML = svgIcon.xml
			// remove image from cloned button
			icon.style = 'background: none'

			text.textContent = 'Duome'
		} catch {
			// in case HTML structure is changed in future
			button.textContent = 'Duome'
		}

		button.addEventListener('click', openCurrentDuomeSd)

		sibling.parentElement.appendChild(button)
	}

	window.originalConsole = console
	window.session = session
	window.openDuomeSdByIdx = openDuomeSdByIdx
	window.getSentences = getSentences
	window.getDuomeApiDtoForChallenge = getDuomeApiDtoForChallenge

	const svgIcon = {
		xml: `<svg xmlns="http://www.w3.org/2000/svg" width="61" height="61" viewBox="0 0 61 61" style="stroke: currentColor; stroke-width: 5.5; fill: none; height: 100%; width: 100%;">
			<path transform="translate(2.707,1.26)" d="m 25.47,50.06 0.08789,0 c -1.406,-0.2046 -2.718,0.6929 -3.084,2.043 a 0.6137,0.6137 0 0 0 -0.1504,0.3105 c -0.25,1.528 0.802,2.994 2.33,3.244 a 0.6137,0.6137 0 0 0 0,0 l 3.539,0.543 a 0.6137,0.6137 0 0 0 0,0 c 1.537,0.2196 2.982,-0.8305 3.23,-2.352 a 0.6137,0.6137 0 0 0 0,-0.002 c 0.2477,-1.541 -0.8115,-3.001 -2.34,-3.225 h -0.002 l -3.463,-0.5625 a 0.6137,0.6137 0 0 0 -0.09766,-0.0078 z" />
			<path d="m 32.36,3.086 c -1.107,-8.684e-4 -2.236,0.3209 -3.235,0.9926 0,0 -0.02171,0.0152 -0.02171,0.02605 -0.6296,0.4559 -1.172,1.01 -1.607,1.639 -2.627,4.001 -4.535,6.45 -5.936,7.429 -1.398,0.9813 -4.379,1.858 -9.099,2.727 -0.7729,0.1824 -1.502,0.4993 -2.167,0.9335 -2.631,1.782 -3.328,5.356 -1.561,7.987 l 4.123,6.101 -8.421,3.105 c -0.2084,0.06513 -0.3995,0.1737 -0.5688,0.3039 l -0.07164,0.06513 h -0.00434 c -0.8482,0.6947 -0.9615,1.954 -0.254,2.822 4.077,5.124 10.93,7.099 17.12,4.993 5.367,6.057 14.44,7.62 21.63,3.322 7.75,-4.646 10.51,-14.44 6.535,-22.38 4.125,-4.854 5.037,-11.93 1.737,-17.82 -0.1085,-0.1802 -0.2388,-0.3408 -0.4125,-0.4798 l -0.04342,-0.04993 c -0.8467,-0.6991 -2.084,-0.5992 -2.801,0.2193 l -6.014,6.676 -4.103,-6.055 c -1.129,-1.661 -2.953,-2.556 -4.82,-2.558 z" />
			<path transform="rotate(108,45.25,57.36)" d="m 25.47,50.06 0.08789,0 c -1.406,-0.2046 -2.718,0.6929 -3.084,2.043 a 0.6137,0.6137 0 0 0 -0.1504,0.3105 c -0.25,1.528 0.802,2.994 2.33,3.244 a 0.6137,0.6137 0 0 0 0,0 l 3.539,0.543 a 0.6137,0.6137 0 0 0 0,0 c 1.537,0.2196 2.982,-0.8305 3.23,-2.352 a 0.6137,0.6137 0 0 0 0,-0.002 c 0.2477,-1.541 -0.8115,-3.001 -2.34,-3.225 h -0.002 l -3.463,-0.5625 a 0.6137,0.6137 0 0 0 -0.09766,-0.0078 z" />
		</svg>`,
	}

	let modalOpen = false

	/** @param {(string | undefined)[]} combo */
	const normalizeCombo = (combo) =>
		combo.filter(Boolean).map(x => {
			const normalized = x.trim().toLowerCase()

			return normalized === 'plus' ? '+' : normalized
		}).sort().join('\0')

	const bugReport = () => {
		modalOpen = true

		const div = document.body.appendChild(document.createElement('div'))

		div.style = `
			position: fixed;
			inset: 0;
			background: #000e;
			padding: 50px;
			color: #fff;
			z-index: ${Number.MAX_SAFE_INTEGER};
		`

		document.body.appendChild(div)

		const filename = `debug-${Date.now()}.log`

		div.innerHTML = `
			<div style="display: flex; justify-content: flex-end;">
				<div class="close" style="font-size: 50px; cursor: pointer;">??</div>
			</div>
			<h2 style="color: #fff">Report a bug ????</h2>
			<p>To report a bug:</p>
			<ol style="list-style: auto; padding: 0 0 18px 40px;">
				<li>Click the button below to download debug logs.</li>
				<li><a style="color: #64b5f6; text-decoration: underline;" href="https://github.com/lionel-rowe/duome-sentence-discussions/issues/new?assignees=lionel-rowe&labels=bug&template=bug_report.yml" target="_blank">Create a new GitHub issue</a>, uploading the debug log file and one or more screenshots if necessary, along with a description of the bug and how to reproduce it.</li>
			</ol>
			<p><a download="${filename}" role="button" style="display: inline-block; border-radius: 15px; border: 3px solid #8f0a23; padding: 10px; color: white; background: crimson; cursor: pointer; font-weight: bold;">Download debug logs</a></p>
			<p>You???ll need to create a GitHub account first if you don???t already have one.</p>
		`

		const ignorables = [
			'character',
			'grader',
			'vertices',
			'challengeResponseTrackingProperties',
		]

		const href = URL.createObjectURL(new Blob([JSON.stringify({
			scriptVersion: GM_info.script.version,
			userAgent: navigator.userAgent,
			url: window.location.href,
			visibleText: document.querySelector('#root').innerText,
			currentIndex: idx,
			challenges: session?.challenges,
			adaptiveChallenges: session?.adaptiveChallenges,
			adaptiveInterleavedChallenges: session?.adaptiveInterleavedChallenges,
		}, (k, v) => ignorables.includes(k) ? undefined : v)]))

		div.querySelector('a[download]').setAttribute('href', href)

		/** @param {MouseEvent | KeyboardEvent} e */
		const closeModal = (e) => {
			if (e instanceof MouseEvent || e.key === 'Escape') {
				div.remove()
				modalOpen = false
				URL.revokeObjectURL(href)

				window.removeEventListener('keydown', closeModal)
			}
		}

		div.querySelector('.close').addEventListener('click', closeModal)
		window.addEventListener('keydown', closeModal)
	}

	/**
	 * @param {KeyboardEvent} e
	 * @param {string | (string | undefined)[]} shortcut
	 */
	const isShortcut = (e, shortcut) => normalizeCombo(
			Array.isArray(shortcut) ? shortcut : shortcut.split('+'),
		) === normalizeCombo([
			e.ctrlKey && 'ctrl',
			e.altKey && 'alt',
			e.shiftKey && 'shift',
			// for example: "S" if e.code is "KeyS"
			e.code.slice(3),
		])

	const isInputMode = () => ((el) => Boolean(el
		&& (['INPUT', 'TEXTAREA'].includes(el.nodeName)
			|| el.closest('[contenteditable]'))
	))(document.activeElement)

	/** @param {KeyboardEvent} e */
	window.addEventListener('keyup', (e) => {
		if (isShortcut(e, 'M') && sdIsAvailable && !isInputMode()) {
			e.preventDefault()

			openCurrentDuomeSd()
		} else if (isShortcut(e, 'Shift+Alt+R') && !modalOpen) {
			e.preventDefault()

			bugReport()
		}
	})
})()
