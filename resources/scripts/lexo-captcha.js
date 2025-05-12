console.log('front');

class LEXO_Captcha {
    #interacted = null;

    #InteractionEvents = [
        'keydown',
        'mousemove',
        'touchmove',
    ];

    #recordInteraction = () => {
        if (this.#interacted) {
            return;
        }

        this.#interacted = Date.now();

        for (const interaction_event of this.#InteractionEvents) {
            document.removeEventListener(interaction_event, this.#recordInteraction);
        }
    }

    /**
     * @type null|Promise
     */
    #tokenReady = null;

    async requestToken() {
        const Data = new FormData();

        Data.append('action', 'lexo_captcha_request_token');

        this.#tokenReady = new Promise(async resolve => {
            const Response = await fetch(LEXO_CAPTCHA_AJAX_URL, {
                method: 'POST',
                body: Data,
            });

            localStorage.setItem('lexo_captcha_token', await Response.text());
            localStorage.setItem('lexo_captcha_token_recieval_timestamp', Date.now());

            resolve();

            this.#tokenReady = null;
        });
    }

    #requestSubmit() {
        return new Promise(async (resolve, reject) => {
            if (!localStorage.getItem('lexo_captcha_token_recieval_timestamp')) {
                if (!this.#tokenReady)
                    return reject('No token requested.');

                await this.#tokenReady;
            }

            setTimeout(
                () => resolve(),
                Number(localStorage.getItem('lexo_captcha_token_recieval_timestamp')) + 15000 - Date.now(),
            );
        });
    }

    async compileData() {
        await this.#requestSubmit();

        const data = JSON.stringify({
            'interacted': this.#interacted,
            'token': localStorage.getItem('lexo_captcha_token'),
        });

        localStorage.removeItem('lexo_captcha_token');
        localStorage.removeItem('lexo_captcha_token_recieval_timestamp');

        return data;
    }

    #notify(message) {
        if (!message.length) {
            message = 'Error: Message not send! Please try again.';
        }

        const Notification = document.createElement('div');

        Notification.id = 'l-notify';
        Notification.innerHTML = message;

        document.body.append(Notification);

        setTimeout(() => Notification.classList.add('active'), 100);

        setTimeout(() => {
            Notification.classList.remove('active');

            setTimeout(() => Notification.remove(), 250);
        }, 5000);
    }

    #addFormEvents(Form) {
        Form.addEventListener('submit', event => {
            event.preventDefault();

            return false;
        });

        Form.addEventListener('mouseover', event => {
            const Target = event?.target?.closest?.('em');

            if (!Target)
                return;

            if (!Target.classList.contains('hover-active')) {
                for (const ErrorElem of Form.querySelectorAll('em, em span.error')) {
                    ErrorElem.classList.remove('hover-active');
                }

                for (const ErrorSpan of Target.querySelectorAll('span.error')) {
                    ErrorSpan.classList.add('hover-active');
                }

                Target.classList.add('hover-active');
            }
        });

        Form.addEventListener('mouseout', event => {
            const Target = event?.target?.closest?.('em, span.error');

            if (!Target)
                return;

            if (Target.classList.contains('hover-active')) {
                for (const ErrorElem of Form.querySelectorAll('em, em span.error')) {
                    ErrorElem.classList.remove('hover-active');
                }
            }
        });
    }

    handleGenericForm(GenericForm, field_selector) {
        let spam = false;

        this.#addFormEvents(GenericForm);

        jQuery(GenericForm).validate({
            errorElement: 'span',
            wrapper: 'em',
            highlight: (element, errorClass, validClass) => {
                jQuery(element).addClass(errorClass).removeClass(validClass);
            },
            submitHandler: async () => {
                if (spam) {
                    return;
                }

                for (const SubmitButton of GenericForm.querySelectorAll('[type="submit"]')) {
                    SubmitButton.disabled = true;
                }

                spam = true;

                const Data = new FormData();

                Data.append(
                    'form_fields',
                    JSON.stringify(
                        jQuery(GenericForm).find(field_selector).serializeArray()
                    ),
                );

                Data.append(
                    'action',
                    GenericForm.dataset.action,
                );

                if (typeof currentLang !== 'undefined') {
                    Data.append(
                        'sender_language',
                        currentLang,
                    );
                }

                Data.append(
                    'page',
                    window.location.href,
                );

                Data.append(
                    'lexo_captcha_data',
                    await this.compileData(),
                );

                const Response = await fetch(LEXO_CAPTCHA_AJAX_URL, {
                    method: 'POST',
                    body: Data,
                });

                this.#notify(await Response.text());

                GenericForm.reset();

                for (const FieldWrapper of GenericForm.querySelectorAll('.cf-field-wrapper')) {
                    FieldWrapper.classList.remove('hasValue');
                }

                spam = false;

                for (const SubmitButton of GenericForm.querySelectorAll('[type="submit"]')) {
                    SubmitButton.disabled = false;
                }

                this.requestToken();
            }
        });
    }

    handleAllGenericForms(field_selector = '.send_field') {
        for (const GenericForm of document.querySelectorAll('form[data-action]')) {
            this.handleGenericForm(GenericForm, field_selector);
        }
    }

    handleAdvancedForm(AdvancedForm) {
        let spam = false;

        this.#addFormEvents(AdvancedForm);

        jQuery(AdvancedForm).validate({
            errorElement: 'span',
            wrapper: 'em',
            highlight: (element, errorClass, validClass) => {
                jQuery(element).addClass(errorClass).removeClass(validClass);
            },
            submitHandler: async () => {
                if (spam) {
                    return;
                }

                for (const SubmitButton of AdvancedForm.querySelectorAll('[type="submit"]')) {
                    SubmitButton.disabled = true;
                }

                spam = true;

                const Data = new FormData(AdvancedForm);

                Data.append(
                    'action',
                    AdvancedForm.dataset.action,
                );

                if (typeof currentLang !== 'undefined') {
                    Data.append(
                        'sender_language',
                        currentLang,
                    );
                }

                Data.append(
                    'page',
                    window.location.href,
                );

                Data.append(
                    'lexo_captcha_data',
                    await this.compileData(),
                );

                const Response = await fetch(LEXO_CAPTCHA_AJAX_URL, {
                    method: 'POST',
                    body: Data,
                });

                this.#notify(await Response.text());

                AdvancedForm.reset();

                for (const FieldWrapper of AdvancedForm.querySelectorAll('.cf-field-wrapper')) {
                    FieldWrapper.classList.remove('hasValue');
                }

                spam = false;

                for (const SubmitButton of AdvancedForm.querySelectorAll('[type="submit"]')) {
                    SubmitButton.disabled = false;
                }

                this.requestToken();
            }
        });
    }

    handleAllAdvancedForms() {
        for (const AdvancedForm of document.querySelectorAll('form[data-action]')) {
            this.handleAdvancedForm(AdvancedForm);
        }
    }

    constructor() {
        for (const interaction_event of this.#InteractionEvents) {
            document.addEventListener(interaction_event, this.#recordInteraction);
        }

        this.requestToken();

        Object.seal(this);
    }
};
