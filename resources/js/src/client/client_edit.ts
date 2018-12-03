//import * as Vue from 'vue';
import Vue from 'vue';
import axios from 'axios';


declare var client_object: any;
declare var hashed_id: string;
//declare var axios: any;
//declare var Vue: any;

 new Vue({
    el : '#client_edit',
    data: function () {
        return {
            'client': [],
            'errors': [],
        }
    },
    mounted(this: any) {
    	//this.client = {!! $client !!};
    	this.client = client_object;
        console.dir(this.client);
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
        remove(this: any, contact:any){
            let index = this.client.contacts.indexOf(contact);
            this.client.contacts.splice(index, 1);
        },
        add(this: any){
            console.dir('i will add a contact here')
            this.client.contacts.push({first_name: '', last_name: '', email: '', phone: '', id: -1});
            window.scrollTo(0, document.body.scrollHeight || document.documentElement.scrollHeight);
            this.$nextTick(() => {
                     let index = this.client.contacts.length - 1;
                     let input = this.$refs.first_name[index];
                     input.focus();
                  });
        },
        submit(this: any) {
            this.errors = {};
            
            axios.put('/clients/' + hashed_id, this.client).then(response => {
//                axios.put('/clients/' + {{ $client->present()->id }}, this.client).then(response => {
                this.client = response.data;
            }).catch(error => {
                if (error.response.status === 422) {
                this.errors = error.response.data.errors || {};
                }
                else if(error.response.status === 419) {
                    //csrf token has expired, we'll need to force a page reload
                }
            });
        },
    	copy(type: any) {
            if(type.includes('copy_billing')){
                this.client.shipping_address1 = this.client.address1; 
                this.client.shipping_address2 = this.client.address2; 
                this.client.shipping_city = this.client.city; 
                this.client.shipping_state = this.client.state; 
                this.client.shipping_postal_code = this.client.postal_code; 
                this.client.shipping_country_id = this.client.country_id; 
            	}else {
                this.client.address1 = this.client.shipping_address1; 
                this.client.address2 = this.client.shipping_address2; 
                this.client.city = this.client.shipping_city; 
                this.client.state = this.client.shipping_state; 
                this.client.postal_code = this.client.shipping_postal_code; 
                this.client.country_id = this.client.shipping_country_id; 
            }
        }
	}
});