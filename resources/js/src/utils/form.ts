import axios from 'axios';
import FormErrors from '../utils/form-errors';

export default class Form {

    errors:any;

    originalData:any;

    /**
     * Create a new Form instance.
     *
     * @param {object} data
     */
    constructor(data) {

        this.originalData = data;

        for (let field in data) {
            this[field] = data[field];
        }

        this.errors = new FormErrors();

    }

    /**
     * Fetch all relevant data for the form.
     */
    data() {

        let data = {};

        for (let property in this.originalData) {
            data[property] = this[property];
        }

        return data;

    }

    /**
     * Reset the form fields.
     */
    reset() {

        for (let field in this.originalData) {
            this[field] = '';
        }

        this.errors.clear();
        
    }

    /**
     * Send a POST request to the given URL.
     * .
     * @param {string} url
     */
    post(url) {

        return this.submit('post', url);

    }

    /**
     * Send a PUT request to the given URL.
     * .
     * @param {string} url
     */
    put(url:string) {

        return this.submit('put', url);

    }

    /**
     * Send a PATCH request to the given URL.
     * .
     * @param {string} url
     */
    patch(url:string) {

        return this.submit('patch', url);

    }

    /**
     * Send a DELETE request to the given URL.
     * .
     * @param {string} url
     */
    delete(url:string) {

        return this.submit('delete', url);

    }

    /**
     * Submit the form.
     *
     * @param {string} requestType
     * @param {string} url
     */
    submit(requestType:string, url:string) {

        return new Promise((resolve, reject) => {

            axios[requestType](url, this.data())
                .then(response => {

                    this.onSuccess(response.data);

                    resolve(response.data);

                })
                .catch(error => {


                    if (error.response.status === 422) {

                        this.onFail(error.response.data.errors);

                    }
                    else if(error.response.status === 419) {

                        //csrf token has expired, we'll need to force a page reload

                    }

                    reject(error.response.data);

                                      
                });
        });

    }

    /**
    * Update form data  on success
    *
    * @param {object} data
    */
    update(data)
    {
        this.originalData = data;

        for (let field in data) {
            this[field] = data[field];
        }
    }

    /**
     * Handle a successful form submission.
     *
     * @param {object} data
     */
    onSuccess(data) {
        this.update(data);
        this.errors.clear();

    }

    /**
     * Handle a failed form submission.
     *
     * @param {object} errors
     */
    onFail(errors) {

        this.errors.record(errors);

    }
    
}