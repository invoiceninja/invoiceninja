
<div class="col-lg-12">
    <ul class="nav nav-pills nav-justified" id="pills-tab" role="tablist">
        <li class="nav-item">
            <a class="nav-link active" id="pills-address-tab" data-toggle="pill" href="#pills-address" role="tab" aria-controls="pills-address" aria-selected="true">Address</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="pills-contact-tab" data-toggle="pill" href="#pills-contact" role="tab" aria-controls="pills-contact" aria-selected="false">Contacts</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="pills-notes-tab" data-toggle="pill" href="#pills-notes" role="tab" aria-controls="pills-notes" aria-selected="false">Notes</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="pills-settings-tab" data-toggle="pill" href="#pills-settings" role="tab" aria-controls="pills-settings" aria-selected="false">Settings</a>
        </li>
    </ul>
    <div class="tab-content" id="pills-tabContent">
        <div class="tab-pane fade show active" id="pills-address" role="tabpanel" aria-labelledby="pills-address-tab">

            <div class="row">
                <div class="col-lg-6">
                    @include('client.partial.client_location', ['location' => $client->primary_billing_location->first(), 'address' => 'Billing'])
                </div>
                <div class="col-lg-6">
                    @include('client.partial.client_location', ['location' => $client->primary_billing_location->first(), 'address' => 'Shipping'])
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="pills-contact" role="tabpanel" aria-labelledby="pills-contact-tab">
            @foreach($client->contacts as $contact)
                @include('client.partial.contact_details', ['contact' => $contact])
            @endforeach
        </div>
        <div class="tab-pane fade" id="pills-notes" role="tabpanel" aria-labelledby="pills-notes-tab">
            <link href="https://cdn.quilljs.com/1.0.0/quill.snow.css" rel="stylesheet">

            <!-- Create the toolbar container -->
            <div id="toolbar">
                <button class="ql-bold">Bold</button>
                <button class="ql-italic">Italic</button>
            </div>

            <!-- Create the editor container -->
            <div id="editor">
                <p>Hello World!</p>
            </div>

            <!-- Include the Quill library -->
            <script src="https://cdn.quilljs.com/1.0.0/quill.js"></script>

            <!-- Initialize Quill editor -->
            <script>
                var editor = new Quill('#editor', {
                    modules: { toolbar: '#toolbar' },
                    theme: 'snow'
                });
            </script>
        </div>
        <div class="tab-pane fade" id="pills-settings" role="tabpanel" aria-labelledby="pills-settings-tab">

            @include('client.partial.client_settings', $client)

        </div>
    </div>
</div>
