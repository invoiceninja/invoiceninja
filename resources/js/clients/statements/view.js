/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

class Statement {
    constructor() {
        this.url = new URL(
            document.querySelector('meta[name=pdf-url]').content
        );
        this.startDate = '';
        this.endDate = '';
        this.showPaymentsTable = false;
        this.showAgingTable = false;
        this.status = '';
    }

    bindEventListeners() {
        [
            '#date-from',
            '#date-to',
            '#show-payments-table',
            '#show-aging-table',
            '#status',
        ].forEach((selector) => {
            document
                .querySelector(selector)
                .addEventListener('change', (event) =>
                    this.handleValueChange(event)
                );
        });
    }

    handleValueChange(event) {
        if (event.target.type === 'checkbox') {
            this[event.target.dataset.field] = event.target.checked;
        } else {
            this[event.target.dataset.field] = event.target.value;
        }

        this.updatePdf();
    }

    get composedUrl() {
        this.url.search = '';

        if (this.startDate.length > 0) {
            this.url.searchParams.append('start_date', this.startDate);
        }

        if (this.endDate.length > 0) {
            this.url.searchParams.append('end_date', this.endDate);
        }

            this.url.searchParams.append('status', document.getElementById("status").value);

        this.url.searchParams.append(
            'show_payments_table',
            +this.showPaymentsTable
        );
        this.url.searchParams.append('show_aging_table', +this.showAgingTable);

        return this.url.href;
    }

    updatePdf() {
        document.querySelector('meta[name=pdf-url]').content = this.composedUrl;

        let iframe = document.querySelector('#pdf-iframe');

        if (iframe) {
            iframe.src = this.composedUrl;
        }

        document
            .querySelector('meta[name=pdf-url]')
            .dispatchEvent(new Event('change'));
    }

    handle() {
        this.bindEventListeners();

        document
            .querySelector('#pdf-download')
            .addEventListener('click', () => {
                let url = new URL(this.composedUrl);
                url.searchParams.append('download', 1);

                window.location.href = url.href;
            })
    }
}

new Statement().handle();
