/**
 * Start with a Self-Executing Anonymous Function (IIFE) to avoid polluting and conflicting with the global namespace (encapsulation).
 * @see https://developer.mozilla.org/en-US/docs/Glossary/IIFE
 *
 * This won't be necessary if you're using a build system like webpack.
 */

const {__, _x, _n, _nx} = wp.i18n;

(() => {
    const placholders = {
        MTN: __('Mobile Money Number', 'mesomb-for-woocommerce'),
        ORANGE: __('Orange Money Number', 'mesomb-for-woocommerce'),
        AIRTEL: __('Airtel Money Number', 'mesomb-for-woocommerce'),
    };
    /**
     * MeSomb of a gateway api.
     */
    const onsiteMeSombGatewayApi = {
        clientKey: "",
        secureData: {
            service: '',
            payer: '',
        },
        async submit() {
            // if (!this.clientKey) {
            //   return {
            //     error: "OnsiteMeSombGatewayApi clientKey is required.",
            //   };
            // }
            if (this.secureData.service.length === 0) {
                return {
                    error: __("Please select a service provider.", "mesomb-for-give"),
                };
            }
            if (this.secureData.payer.length === 0) {
                return {
                    error: __("Please enter your phone number.", "mesomb-for-give"),
                };
            }

            document.getElementById('mesomb-alert').style.display = 'block';
            setTimeout(function(){ document.getElementById('mesomb-alert').style.display = 'none'; }, 6000);

            return {
                transactionId: `oeg_transaction-${Date.now()}`,
                service: this.secureData.service,
                payer: this.secureData.payer,
            };
        },
    };

    /**
     * MeSomb of rendering gateway fields (without jsx).
     *
     * This renders a simple div with a label and input.
     *
     * @see https://react.dev/reference/react/createElement
     */
    function OnsiteMeSombGatewayFields() {
        return [
            window.wp.element.createElement(
                'div',
                {class: 'form-row form-row-wide validate-required'},
                window.wp.element.createElement(
                    "label",
                    {
                        htmlFor: "mesomb-for-give-provider",
                        style: {display: "block", border: "none"},
                        class: 'form-label'
                    },
                    "Operator",
                ),
                window.wp.element.createElement(
                    "div",
                    {
                        id: "providers",
                        style: {display: "flex", 'flex-direction': "row", 'flex-wrap': "wrap"},
                    },
                    ...onsiteMeSombGatewayApi.providers.filter(p => p.countries.includes('CM')).map((provider) => {
                        return window.wp.element.createElement(
                            "div",
                            {
                                class: 'form-row provider-row',
                                style: {'margin-right': "5px", 'margin-bottom': "5px"},
                            },
                            window.wp.element.createElement(
                                "label",
                                {
                                    class: "kt-option",
                                },
                                window.wp.element.createElement(
                                    "span",
                                    {
                                        class: "kt-option__label",
                                    },
                                    window.wp.element.createElement(
                                        "span",
                                        {
                                            class: "kt-option__head",
                                        },
                                        window.wp.element.createElement(
                                            "span",
                                            {
                                                class: "kt-option__control",
                                            },
                                            window.wp.element.createElement(
                                                "span",
                                                {
                                                    class: "kt-radio",
                                                },
                                                window.wp.element.createElement(
                                                    "input",
                                                    {
                                                        class: "input-radio",
                                                        name: 'service',
                                                        value: provider.key,
                                                        type: 'radio',
                                                        onChange(e) {
                                                            onsiteMeSombGatewayApi.secureData.service = e.target.value;
                                                            window.document.getElementById('mesomb-for-give-payer').placeholder = placholders[e.target.value];
                                                        },
                                                    },
                                                ),
                                                window.wp.element.createElement(
                                                    "span",
                                                    {},
                                                ),
                                            ),
                                        ),
                                        window.wp.element.createElement(
                                            "span",
                                            {
                                                class: "kt-option__title",
                                            },
                                            provider.name,
                                        ),
                                        window.wp.element.createElement(
                                            "img",
                                            {
                                                height: 25,
                                                width: 25,
                                                alt: provider.key,
                                                src: provider.icon,
                                                class: "kt-option__title",
                                                style: {
                                                    width: '25px',
                                                    height: '25px',
                                                    'border-radius': '13px',
                                                    position: 'relative',
                                                    top: '-0.75em',
                                                    right: '-0.75em'
                                                }
                                            },
                                        )
                                    ),
                                    window.wp.element.createElement(
                                        "span",
                                        {
                                            class: "kt-option__body",
                                        },
                                        `${__('Pay with your', 'mesomb-for-woocommerce')} ${provider.name}`,
                                    ),
                                ),
                            ),
                        );
                    }),
                ),
            ),
            window.wp.element.createElement(
                "div",
                {class: 'form-row form-row-wide validate-required'},
                window.wp.element.createElement(
                    "label",
                    {
                        htmlFor: "mesomb-for-give-payer",
                        style: {display: "block", border: "none"},
                        class: 'form-label'
                    },
                    "Phone Number",
                ),
                window.wp.element.createElement("input", {
                    placeholder: 'Expl: 670000000',
                    className: "input-text",
                    type: "text",
                    name: "payer",
                    id: "mesomb-for-give-payer",
                    onChange(e) {
                        onsiteMeSombGatewayApi.secureData.payer = e.target.value;
                    },
                })
            ),
            window.wp.element.createElement(
                "div",
                {class: 'alert alert-success', role: 'alert', id: 'mesomb-alert', style: {display: 'none', 'margin-top': '10px'}},
                window.wp.element.createElement(
                    "h4",
                    {
                        class: 'alert-heading'
                    },
                    __('Check your phone', 'mesomb-for-woocommerce')
                ),
                window.wp.element.createElement("p", {},__('Please check your phone to validate payment from Hachther SARL or MeSomb', 'mesomb-for-woocommerce'))
            ),
        ];
    }

    /**
     * MeSomb of a front-end gateway object.
     */
    const OnsiteMeSombGateway = {
        id: "mesomb-for-give",
        initialize() {
            const {clientKey, providers} = this.settings;

            onsiteMeSombGatewayApi.clientKey = clientKey;
            onsiteMeSombGatewayApi.providers = providers;
        },
        async beforeCreatePayment() {
            // Trigger form validation and wallet collection
            const {transactionId, service, payer, error: submitError} =
                await onsiteMeSombGatewayApi.submit();

            if (submitError) {
                throw new Error(submitError);
            }

            return {
                "mesomb-for-give": transactionId,
                service,
                payer
            };
        },
        Fields() {
            return window.wp.element.createElement(OnsiteMeSombGatewayFields);
        },
    };

    /**
     * The final step is to register the front-end gateway with GiveWP.
     */
    window.givewp.gateways.register(OnsiteMeSombGateway);
})();
