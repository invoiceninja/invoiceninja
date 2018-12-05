//import * as Vue from 'vue';
import Vue from 'vue';
import axios from 'axios';
import Form from '../utils/form';
// import Toastr
import Toastr from 'vue-toastr';
// import toastr scss file: need webpack sass-loader
require('vue-toastr/src/vue-toastr.scss');
// Register vue component
Vue.component('vue-toastr',Toastr);

declare var client_object: any;
declare var hashed_id: string;
//declare var axios: any;
//declare var Vue: any;

 new Vue({
    el : '#client_edit',
    data: function () {
        return {
            form: new Form(client_object)
        }
    },
    mounted(this: any) {
        console.log('mounted')
    },
    beforeMount: function () {
    	console.log('before mount')
    },
    created:function() {
    	console.dir('created')
    },
    updated:function() {
        console.dir('updated')
    },
    methods:{
        remove(this:any, contact:any){
            let index = this.form.contacts.indexOf(contact);
            this.form.contacts.splice(index, 1);
        },
        add(this: any){
            console.dir('i will add a contact here')
            this.form.contacts.push({first_name: '', last_name: '', email: '', phone: '', id: -1});
            window.scrollTo(0, document.body.scrollHeight || document.documentElement.scrollHeight);
            this.$nextTick(() => {
                     let index = this.form.contacts.length - 1;
                     let input = this.$refs.first_name[index];
                     input.focus();
                  });
        },
        onSubmit() {
            this.form.put('/clients/' + hashed_id)
                .then(response => this.$root.$refs.toastr.s("Saved client"))
                .catch(error => {

                    this.$root.$refs.toastr.e("Error saving client");

                });
        },
    	copy(type: any) {
            if(type.includes('copy_billing')){
                this.form.shipping_address1 = this.form.address1; 
                this.form.shipping_address2 = this.form.address2; 
                this.form.shipping_city = this.form.city; 
                this.form.shipping_state = this.form.state; 
                this.form.shipping_postal_code = this.form.postal_code; 
                this.form.shipping_country_id = this.form.country_id; 
            	}else {
                this.form.address1 = this.form.shipping_address1; 
                this.form.address2 = this.form.shipping_address2; 
                this.form.city = this.form.shipping_city; 
                this.form.state = this.form.shipping_state; 
                this.form.postal_code = this.form.shipping_postal_code; 
                this.form.country_id = this.form.shipping_country_id; 
            }
        }
	}
});