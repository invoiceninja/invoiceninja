//import * as Vue from 'vue';
import Vue from 'vue';
import axios from 'axios';
import Form from '../utils/form';

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
        remove(form: any, contact:any){
            let index = form.contacts.indexOf(contact);
            form.contacts.splice(index, 1);
        },
        add(form: any){
            console.dir('i will add a contact here')
            form.contacts.push({first_name: '', last_name: '', email: '', phone: '', id: -1});
            window.scrollTo(0, document.body.scrollHeight || document.documentElement.scrollHeight);
            form.$nextTick(() => {
                     let index = form.contacts.length - 1;
                     let input = form.$refs.first_name[index];
                     input.focus();
                  });
        },
        onSubmit() {
            this.form.put('/clients/' + hashed_id)
                .then(response => alert('Wahoo!'));
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