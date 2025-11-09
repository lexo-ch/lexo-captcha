window.LEXO_Captcha = new (class {
	/**
	 * @type {number?}
	 */
	#interacted = null;

  /**
   * @type {string?}
   */
  #lchp_field = null;

  /**
   * Behavioral tracking data
   */
  #behavior = {
    mouse_moves: 0,
    mouse_positions: [],
    key_presses: 0,
    scroll_events: 0,
    start_time: Date.now(),
  };

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
   * Track mouse movements for behavioral analysis
   */
  #track_mouse_move = (event) => {
    this.#behavior.mouse_moves++;

    // Sample mouse positions (max 50 to avoid excessive data)
    if (this.#behavior.mouse_positions.length < 50) {
      this.#behavior.mouse_positions.push({
        x: event.clientX,
        y: event.clientY,
        time: Date.now()
      });
    }
  };

  /**
   * Track keyboard events
   */
  #track_key_press = () => {
    this.#behavior.key_presses++;
  };

  /**
   * Track scroll events
   */
  #track_scroll = () => {
    this.#behavior.scroll_events++;
  };

  /**
   * Calculate mouse movement variance (bots have linear paths)
   */
  #calculate_mouse_variance = () => {
    if (this.#behavior.mouse_positions.length < 2) {
      return 0;
    }

    const positions = this.#behavior.mouse_positions;
    let total_variance = 0;

    for (let i = 1; i < positions.length; i++) {
      const dx = positions[i].x - positions[i - 1].x;
      const dy = positions[i].y - positions[i - 1].y;
      const distance = Math.sqrt(dx * dx + dy * dy);
      total_variance += distance;
    }

    return total_variance / positions.length;
  };

  /**
   * Collect browser fingerprint data
   */
  #collect_fingerprint = () => {
    const screen_data = `${window.screen.width}x${window.screen.height}x${window.screen.colorDepth}`;
    const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
    const language = navigator.language || navigator.userLanguage;
    const platform = navigator.platform;

    // Hardware concurrency (CPU cores)
    const hardware = navigator.hardwareConcurrency || 'unknown';

    return {
      screen: screen_data,
      timezone: timezone,
      language: language,
      platform: platform,
      hardware: hardware.toString(),
    };
  };

	/**
     * @type {Promise?}
     */
	#token_ready = null;

  /**
     * @type {Promise?}
     */
  #nonce_ready = null;

  async requestNonce() {
    if (this.#nonce_ready) {
      await this.#nonce_ready;

      return;
    }

    this.#nonce_ready = new Promise(async (resolve, reject) => {
      const data = new FormData();

      data.append('action', 'lexo_captcha_request_nonce');

      try {
        const response = await fetch(lexocaptchaFrontLocalized.ajax_url, {
          method: 'POST',
          body: data,
        });

        const result = await response.json();

        if (result && result.success && result.data?.nonce) {
          lexocaptchaFrontLocalized.token_nonce = result.data.nonce;
          resolve();
        } else {
          console.error('Failed to get nonce:', result.data?.message);
          reject(new Error(result.data?.message || 'Nonce request failed'));
        }
      } catch (error) {
        console.error('Nonce request error:', error);
        reject(error);
      }

      this.#nonce_ready = null;
    });

    await this.#nonce_ready;
  }

	async requestToken() {
		if (this.#token_ready) {
			await this.#token_ready;

			return;
		}

    if (!lexocaptchaFrontLocalized.token_nonce) {
      await this.requestNonce();
    }

		localStorage.removeItem('lexo_captcha_token');
		localStorage.removeItem('lexo_captcha_token_recieval_timestamp');

		const data = new FormData();

		data.append('action', 'lexo_captcha_request_token');
    data.append('token_nonce', lexocaptchaFrontLocalized.token_nonce || '');

		this.#token_ready = new Promise(async (resolve, reject) => {
			const response = await fetch(lexocaptchaFrontLocalized.ajax_url, {
				method: 'POST',
				body: data,
			});

      const result = await response.json();

      if (result?.data?.next_nonce) {
        lexocaptchaFrontLocalized.token_nonce = result.data.next_nonce;
      }

			if (result && result.success) {
        localStorage.setItem('lexo_captcha_token', result.data.token);
        localStorage.setItem('lexo_captcha_token_recieval_timestamp', Date.now());

        if (result.data.lchp_field) {
          this.#lchp_field = result.data.lchp_field;
          this.#inject_lchp_fields();
        }

        resolve();
      } else {
        console.error('Failed to get token:', result.data?.message);
        reject(new Error(result.data?.message || 'Token request failed'));
      }

			this.#token_ready = null;
		});

		await this.#token_ready;
	}

  #inject_lchp_fields() {
    if (!this.#lchp_field) {
      return;
    }

    const forms = document.querySelectorAll('form[data-action]');

    for (const form of forms) {
      const existing = form.querySelector('.lchp-form-field');
      if (existing) {
        existing.name = this.#lchp_field;
        existing.id = this.#lchp_field;
        continue;
      }

      const wrapper = document.createElement('div');
      wrapper.className = 'lchp-form-field';
      wrapper.setAttribute('aria-hidden', 'true');
      wrapper.style.cssText = 'position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden;';

      // Create the actual input field
      const input = document.createElement('input');
      input.type = 'text';
      input.name = this.#lchp_field;
      input.id = this.#lchp_field;
      input.value = '';
      input.tabIndex = -1;
      input.autocomplete = 'off';
      input.setAttribute('aria-hidden', 'true');

      const label = document.createElement('label');
      label.htmlFor = this.#lchp_field;
      label.textContent = 'This field is required.';

      wrapper.appendChild(label);
      wrapper.appendChild(input);

      form.insertBefore(wrapper, form.firstChild);
    }
  }

	#request_submit() {
		return new Promise(async resolve => {
			if (!localStorage.getItem('lexo_captcha_token_recieval_timestamp')) {
				await this.requestToken();
			}

			const recieval_timestmap = Number(localStorage.getItem('lexo_captcha_token_recieval_timestamp'));

			const submit_cooldown = Number(lexocaptchaFrontLocalized.submit_cooldown);

			setTimeout(
				() => resolve(),
				recieval_timestmap + submit_cooldown - Date.now(),
			);
		});
	}

  async compileData(formContext = null) {
		await this.#request_submit();

    // Compile behavioral data
    const behavior_data = {
      mouse_moves: this.#behavior.mouse_moves,
      mouse_variance: this.#calculate_mouse_variance(),
      key_presses: this.#behavior.key_presses,
      scroll_events: this.#behavior.scroll_events,
      interaction_duration: Date.now() - this.#behavior.start_time,
    };

    const lchp_data = {};
    if (this.#lchp_field) {
      const searchContext = formContext || document;
      const lchp_input = searchContext.querySelector(`input[name="${this.#lchp_field}"]`);
      const lchp_value = lchp_input ? lchp_input.value : '';

      lchp_data[this.#lchp_field] = lchp_value;
    }

		const data = JSON.stringify({
			'interacted': this.#interacted,
			'token': localStorage.getItem('lexo_captcha_token'),
      'fingerprint': this.#collect_fingerprint(),
      'behavior': behavior_data,
      'lchp': lchp_data,
		});

		localStorage.removeItem('lexo_captcha_token');
		localStorage.removeItem('lexo_captcha_token_recieval_timestamp');

		return data;
	}

	/**
	 * @param {string|object} message
	 */
  #notify(message, $success = true) {
    // Handle object responses (e.g., error responses with message property)
    if (typeof message === 'object' && message !== null) {
      message = message.message || 'Error: Message not sent! Please try again.';
    }

    // Ensure message is a string
    if (typeof message !== 'string' || !message.length) {
      message = 'Error: Message not sent! Please try again.';
		}

		const notification = document.createElement('div');

		notification.id = 'l-notify';
		notification.innerHTML = message;

		document.body.append(notification);

    if ($success) {
      notification.classList.add('success');
    } else {
      notification.classList.add('error');
    }

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

    this.#inject_lchp_fields();

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
          await this.compileData(form),
				);

				const response = await (await fetch(lexocaptchaFrontLocalized.ajax_url, {
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

    this.#inject_lchp_fields();

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
          await this.compileData(form),
				);

				const response = await (await fetch(lexocaptchaFrontLocalized.ajax_url, {
					method: 'POST',
					body: body,
				})).json();

        const responseEvent = new CustomEvent('lexocaptcha:response', {
          detail: {
            form: form,
            success: response.success,
            data: Object.fromEntries(body.entries())
          },
          bubbles: true,
          cancelable: false
        });
        form.dispatchEvent(responseEvent);

        this.#notify(response.data, response.success);

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
    jQuery.validator.methods.email = function (value, element) {
      return this.optional(element) || /^(([^<>()[\]\\.,;:\s@\"]+(\.[^<>()[\]\\.,;:\s@\"]+)*)|(\".+\"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/.test(value);
    };

    // Set up interaction event listeners
		for (const interaction_event of this.#interaction_events) {
			document.addEventListener(interaction_event, this.#record_interaction);
		}

    // Set up behavioral tracking event listeners
    document.addEventListener('mousemove', this.#track_mouse_move);
    document.addEventListener('keydown', this.#track_key_press);
    document.addEventListener('scroll', this.#track_scroll, { passive: true });

		this.requestToken();

		Object.seal(this);
	}
});
