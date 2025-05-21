const LEXO_Captcha = new (class {
	/**
	 * @type {number?}
	 */
	#interacted = null;

	#interaction_events = [
		'keydown',
		'mousemove',
		'touchmove',
	];

	#record_interaction = () => {
		if (this.#interacted) {
			return;
		}

		this.#interacted = Date.now();

		for (const interaction_event of this.#interaction_events) {
			document.removeEventListener(interaction_event, this.#record_interaction);
		}
	};

	/**
     * @type {Promise?}
     */
	#token_ready = null;

	async requestToken() {
		if (this.#token_ready) {
			await this.#token_ready;

			return;
		}

		localStorage.removeItem('lexo_captcha_token');
		localStorage.removeItem('lexo_captcha_token_recieval_timestamp');

		const data = new FormData();

		data.append('action', 'lexo_captcha_request_token');

		this.#token_ready = new Promise(async resolve => {
			const response = await fetch(lexocaptcha_globals.ajax_url, {
				method: 'POST',
				body: data,
			});

			localStorage.setItem('lexo_captcha_token', await response.text());
			localStorage.setItem('lexo_captcha_token_recieval_timestamp', Date.now());

			resolve();

			this.#token_ready = null;
		});

		await this.#token_ready;
	}

	#request_submit() {
		return new Promise(async (resolve, reject) => {
			if (!localStorage.getItem('lexo_captcha_token_recieval_timestamp')) {
				await this.requestToken();
			}

			setTimeout(
				() => resolve(),
				Number(localStorage.getItem('lexo_captcha_token_recieval_timestamp')) + lexocaptcha_globals.submit_cooldown - Date.now(),
			);
		});
	}

	async compileData() {
		await this.#request_submit();

		const data = JSON.stringify({
			'interacted': this.#interacted,
			'token': localStorage.getItem('lexo_captcha_token'),
		});

		localStorage.removeItem('lexo_captcha_token');
		localStorage.removeItem('lexo_captcha_token_recieval_timestamp');

		return data;
	}

	/**
	 * @param {string} message
	 */
	#notify(message) {
		if (!message.length) {
			message = 'Error: Message not send! Please try again.';
		}

		const notification = document.createElement('div');

		notification.id = 'l-notify';
		notification.innerHTML = message;

		document.body.append(notification);

		setTimeout(() => notification.classList.add('active'), 100);

		setTimeout(() => {
			notification.classList.remove('active');

			setTimeout(() => notification.remove(), 250);
		}, 5000);
	}

	/**
	 * @param {HTMLFormElement} form
	 */
	#add_form_events(form) {
		form.addEventListener('submit', event => {
			event.preventDefault();

			return false;
		});

		form.addEventListener('mouseover', event => {
			const target = event?.target?.closest?.('em');

			if (!target) {
				return;
			}

			if (!target.classList.contains('hover-active')) {
				for (const error_element of form.querySelectorAll('em, em span.error')) {
					error_element.classList.remove('hover-active');
				}

				for (const error_span of target.querySelectorAll('span.error')) {
					error_span.classList.add('hover-active');
				}

				target.classList.add('hover-active');
			}
		});

		form.addEventListener('mouseout', event => {
			const target = event?.target?.closest?.('em, span.error');

			if (!target) {
				return;
			}

			if (target.classList.contains('hover-active')) {
				for (const error_element of form.querySelectorAll('em, em span.error')) {
					error_element.classList.remove('hover-active');
				}
			}
		});
	}

	/**
	 * @deprecated use `initialise_legacy_form()` instead.
	 */
	handleGenericForm(form, field_selector) {
		this.initialise_legacy_form(form, field_selector);
	}

	/**
	 * Initialise a legacy captcha form, using **JSON** to transfer data.
	 *
	 * @param {HTMLFormElement} form
	 * @param {keyof HTMLElementTagNameMap} field_selector
	 */
	initialise_legacy_form(form, field_selector) {
		let spam = false;

		this.#add_form_events(form);

		jQuery(form).validate({
			errorElement: 'span',
			wrapper: 'em',
			highlight: (element, error_class, valid_class) => {
				jQuery(element).addClass(error_class).removeClass(valid_class);
			},
			submitHandler: async () => {
				if (spam) {
					return;
				}

				const submit_buttons = form.querySelectorAll('[type="submit"]');

				for (const submit_button of submit_buttons) {
					submit_button.disabled = true;
				}

				spam = true;

				const body = new FormData();

				body.append(
					'form_fields',
					JSON.stringify(
						jQuery(form).find(field_selector).serializeArray()
					),
				);

				body.append(
					'action',
					form.dataset.action,
				);

				if (typeof currentLang !== 'undefined') {
					body.append(
						'sender_language',
						currentLang,
					);
				}

				body.append(
					'page',
					window.location.href,
				);

				body.append(
					'lexo_captcha_data',
					await this.compileData(),
				);

				const response = await (await fetch(lexocaptcha_globals.ajax_url, {
					method: 'POST',
					body: body,
				})).text();

				this.#notify(response);

				form.reset();

				for (const field_wrapper of form.querySelectorAll('.cf-field-wrapper')) {
					field_wrapper.classList.remove('hasValue');
				}

				spam = false;

				for (const submit_button of submit_buttons) {
					submit_button.disabled = false;
				}

				this.requestToken();
			}
		});
	}

	/**
	 * @deprecated use `initialise_all_legacy_forms()` instead.
	 */
	handleAllGenericForms(field_selector = '.send_field') {
		this.initialise_all_legacy_forms(field_selector);
	}

	/**
	 * @param {keyof HTMLElementTagNameMap} field_selector
	 */
	initialise_all_legacy_forms(field_selector = '.send_field') {
		for (const form of document.querySelectorAll('form[data-action]')) {
			this.initialise_legacy_form(form, field_selector);
		}
	}

	/**
	 * @deprecated use `initialise_form()` intsead.
	 */
	handleAdvancedForm(form) {
		this.initialise_form(form);
	}

	/**
	 * Initialise a captcha form, using `FormData` to transfer data.
	 *
	 * @param {HTMLFormElement} form
	 */
	initialise_form(form) {
		let spam = false;

		this.#add_form_events(form);

		jQuery(form).validate({
			errorElement: 'span',
			wrapper: 'em',
			highlight: (element, error_class, valid_class) => {
				jQuery(element).addClass(error_class).removeClass(valid_class);
			},
			submitHandler: async () => {
				if (spam) {
					return;
				}

				const submit_buttons = form.querySelectorAll('[type="submit"]');

				for (const submit_button of submit_buttons) {
					submit_button.disabled = true;
				}

				spam = true;

				const body = new FormData(form);

				body.append(
					'action',
					form.dataset.action,
				);

				if (typeof currentLang !== 'undefined') {
					body.append(
						'sender_language',
						currentLang,
					);
				}

				body.append(
					'page',
					window.location.href,
				);

				body.append(
					'lexo_captcha_data',
					await this.compileData(),
				);

				const response = await (await fetch(lexocaptcha_globals.ajax_url, {
					method: 'POST',
					body: body,
				})).json();

				this.#notify(response.data);

				if (response.success) {
					form.reset();

					for (const field_wrapper of form.querySelectorAll('.cf-field-wrapper')) {
						field_wrapper.classList.remove('hasValue');
					}
				}

				spam = false;

				for (const submit_button of submit_buttons) {
					submit_button.disabled = false;
				}

				this.requestToken();
			}
		});
	}

	/**
	 * @deprecated use `initialise_all_forms()` instead.
	 */
	handleAllAdvancedForms() {
		this.initialise_all_forms();
	}

	initialise_all_forms() {
		for (const form of document.querySelectorAll('form[data-action]')) {
			this.initialise_form(form);
		}
	}

	constructor() {
		for (const interaction_event of this.#interaction_events) {
			document.addEventListener(interaction_event, this.#record_interaction);
		}

		this.requestToken();

		Object.seal(this);
	}
});
