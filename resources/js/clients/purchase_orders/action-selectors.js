/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license 
 */

class ActionSelectors {
    constructor() {
        this.parentElement = document.querySelector('.form-check-parent');
        this.parentForm = document.getElementById('bulkActions');
    }

    watchCheckboxes(parentElement) {
        document
            .querySelectorAll('.child-hidden-input')
            .forEach((element) => element.remove());

        document.querySelectorAll('.form-check-child').forEach((child) => {
            if (parentElement.checked) {
                child.checked = parentElement.checked;
                this.processChildItem(
                    child,
                    document.getElementById('bulkActions')
                );
            } else {
                child.checked = false;
                document
                    .querySelectorAll('.child-hidden-input')
                    .forEach((element) => element.remove());
            }
        });
    }

    processChildItem(element, parent, options = {}) {
        if (options.hasOwnProperty('single')) {
            document
                .querySelectorAll('.child-hidden-input')
                .forEach((element) => element.remove());
        }

        if (element.checked === false) {
            let inputs = document.querySelectorAll('input.child-hidden-input');

            for (let i of inputs) {
                if (i.value == element.dataset.value) i.remove();
            }

            return;
        }

        let _temp = document.createElement('INPUT');

        _temp.setAttribute('name', 'purchase_orders[]');
        _temp.setAttribute('value', element.dataset.value);
        _temp.setAttribute('class', 'child-hidden-input');
        _temp.hidden = true;

        parent.append(_temp);
    }

    handle() {
        this.parentElement.addEventListener('click', () => {
            this.watchCheckboxes(this.parentElement);
        });

        for (let child of document.querySelectorAll('.form-check-child')) {
            child.addEventListener('click', () => {
                this.processChildItem(child, this.parentForm);
            });
        }
    }
}

/** @handle **/
new ActionSelectors().handle();
