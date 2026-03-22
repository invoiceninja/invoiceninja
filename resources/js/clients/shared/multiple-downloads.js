/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license 
 */

const appendToElement = (parent, value) => {
    let _parent = document.getElementById(parent);

    let _possibleElement = _parent.querySelector(`input[value="${value}"]`);

    if (_possibleElement) {
        return _possibleElement.remove();
    }

    let _temp = document.createElement('INPUT');

    _temp.setAttribute('name', 'file_hash[]');
    _temp.setAttribute('value', value);
    _temp.hidden = true;

    _parent.append(_temp);

};

window.appendToElement = appendToElement;
