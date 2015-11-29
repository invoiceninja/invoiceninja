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
.container select {
    font-weight: 300;
    font-family: 'Roboto', sans-serif;
    width: 100%;
    padding: 11px;
    color: #8c8c8c;
    background: #f9f9f9;
    border: 1px solid #ebe7e7;
    border-radius: 3px;
    font-size: 16px;
    min-height: 42px !important;
    font-weight: 400;
}

div.col-md-3,
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
    color: #36b855;
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



</style>
