<style type="text/css">

body {
    background-color: #f8f8f8;
    color: #1b1a1a;
}

.panel-body {
    padding-bottom: 50px;
}


.container input[type=text],
.container input[type=email],
.container select,
.braintree-hosted {
    @if(!empty($account))
        {!! $account->getBodyFontCss() !!}
    @else
        font-weight: 300;
        font-family: 'Roboto', sans-serif;
    @endif
    width: 100%;
    padding: 11px;
    color: #444444;
    background: #f9f9f9;
    border: 1px solid #ebe7e7;
    border-radius: 3px;
    font-size: 16px;
    min-height: 42px !important;
    font-weight: 400;
}

.container select {
    color: #999999;
}

.form-control.braintree-hosted-fields-focused{
    border-color: #66afe9;
    outline: 0;
    box-shadow: inset 0 1px 1px rgba(0,0,0,.075), 0 0 8px rgba(102, 175, 233, 0.6);
}

div.col-md-3,
div.col-md-4,
div.col-md-5,
div.col-md-6,
div.col-md-7,
div.col-md-9,
div.col-md-12 {
    margin: 6px 0 6px 0;
}

span.dropdown-toggle {
    border-color: #ebe7e7;
}

.dropdown-toggle {
    margin: 0px !important;
}

.container input[placeholder],
.container select[placeholder] {
   color: #444444;
}

div.row {
    padding-top: 8px;
}

header {
    margin: 0px !important
}

@media screen and (min-width: 700px) {
    header {
        margin: 20px 0 75px;
        float: left;
    }

    .panel-body {
        padding-left: 150px;
        padding-right: 150px;
    }

}

h2 {
    font-weight: 300;
    font-size: 30px;
    color: #2e2b2b;
    line-height: 1;
}

h3 {
    font-weight: 900;
    margin-top: 10px;
    font-size: 15px;
}

h3 .help {
    font-style: italic;
    font-weight: normal;
    color: #888888;
}

header h3 {
    text-transform: uppercase;
}

header h3 span {
    display: inline-block;
    margin-left: 8px;
}

header h3 em {
    font-style: normal;
    color: #eb8039;
}

.secure {
    text-align: right;
    float: right;
    background: url({{ asset('/images/icon-shield.png') }}) right 22px no-repeat;
    padding: 17px 55px 10px 0;
    }

.secure h3 {
    color: #5cb85c;
    font-size: 30px;
    margin-bottom: 8px;
    margin-top: 0px;
    }

.secure div {
    color: #acacac;
    font-size: 15px;
    font-weight: 900;
    text-transform: uppercase;
}

#plaid_link_button img {
    height:30px;
    vertical-align:-7px;
    margin-right:5px;
}

#plaid_link_button:hover img,
#plaid_link_button .hoverimg{
    display:none;
}

#plaid_link_button:hover .hoverimg{
    display:inline;
}

#plaid_link_button {
    width:425px;
    border-color:#2A5A74;
    color:#2A5A74;
}

#plaid_link_button:hover {
    width:425px;
    background-color:#2A5A74;
    color:#fff;
}

#plaid_or,
#plaid_container {
    text-align:center
}

#plaid_or span{
    background:#fff;
    position:relative;
    bottom:-11px;
    font-size:125%;
    padding:0 10px;
}

#plaid_or {
    border-bottom:1px solid #000;
    margin:10px 0 30px;
}

#secured_by_plaid{
    position:fixed;
    z-index:999999999;
    bottom:5px;
    left:5px;
    color:#fff;
    border:1px solid #fff;
    padding:3px 7px 3px 3px;
    border-radius:3px;
    vertical-align:-5px;
    text-decoration: none!important;
}
#secured_by_plaid img{
    height:20px;
    margin-right:5px;
}

#secured_by_plaid:hover{
    background-color:#2A5A74;
}

#plaid_linked{
    margin:40px 0;
    display:none;
}

#plaid_linked_status {
    margin-bottom:10px;
    font-size:150%;
}

#bank_name {
    margin:5px 0 -5px;
}

</style>
