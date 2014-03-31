@extends('public.header') 

@section('content')

    
<section class="hero background hero5" data-speed="2" data-type="background">
  <div class="caption-side"></div>
  <div class="container">
    <div class="row" style="margin:0;">
      <div class="caption-wrap">
        <div class="caption">
          <h1>THE <span style="color:#2299c0">Faq's</span>
            </div>
          </div>
        </div>
      </div>
    </section>
    <section class="faq">
        <div class="container">
            <div class="row">
                <div class="col-md-7">
                    <div class="question">
                        <a class="expander" href="#">I know it isn’t standard
                        ninja practice to reveal too many identity details, but
                        who are you guys exactly?</a>

                        <div class="content">
                            <p>We’re a small team of highly skilled digital
                            journeymen based in Israel. We love open source, we
                            love disrupting the big business status quo, and we
                            love building helpful tools that are easy to use.
                            We believe that everyone else’s web-based cash flow
                            tools are unnecessarily expensive, clunky and
                            complicated - and we’re bent on proving these
                            beliefs with Invoice Ninja.</p>
                        </div>
                    </div>

                    <div class="question">
                        <a class="expander" href="#">How do I get started using
                        Invoice Ninja?</a>

                        <div class="content">
                            <p>Just click on the big, yellow “Invoice Now”
                            button on our homepage!</p>
                        </div>
                    </div>

                    <div class="question">
                        <a class="expander" href="#">Do you offer customer
                        support?</a>

                        <div class="content">
                            <p>We sure do. Support is super important to us.
                            Feel free to email us at <a href=
                            "mailto:support@invoiceninja.com">support@invoiceninja.com</a>
                            with any questions you might have. We almost always
                            reply within one business day.</p>
                        </div>
                    </div>

                    <div class="question">
                        <a class="expander" href="#">Is Invoice Ninja really
                        free? For how long?</a>

                        <div class="content">
                            <p>Yes, it is 100% free. Forever and ever. For
                            real.</p>
                        </div>
                    </div>

                    <div class="question">
                        <a class="expander" href="#">How is Invoice Ninja able
                        to offer this all for free? How are you making any
                        money?</a>

                        <div class="content">
                            <p>We’re mostly in this line of work because we
                            believe it’s high time that a good electronic
                            invoicing tools be available for free. There isn’t
                            much money in it - yet. When our users open up new
                            accounts with payment processor gateways by
                            clicking on links from our site, we make modest
                            commissions as a gateway affiliate. So if zillions
                            of freelancers and small businesses start running
                            credit card charges through Invoice Ninja, there’s
                            a decent chance we might recover our investment.
                            Maybe not.</p>
                        </div>
                    </div>

                    <div class="question">
                        <a class="expander" href="#">Really? So does that mean
                        you’re not collecting information about me so you can
                        sell me stuff or so that some other company can spam me
                        according to my interests?</a>

                        <div class="content">
                            <p>No way. We’re not mining your data, and we’re
                            not selling you out. That wouldn’t be very ninja of
                            us, would it?</p>
                        </div>
                    </div>

                    <div class="question">
                        <a class="expander" href="#">But don’t you have access
                        to my merchant and banking accounts?</a>

                        <div class="content">
                            <p>Actually, we don’t. When you link an account at
                            a third party financial institution with your
                            Invoice Ninja account, you’re essentially giving
                            our app permission to send money to you and nothing
                            more. This is all managed by the tech teams at your
                            financial service providers, who go to great
                            lengths to ensure their integrations can’t be
                            exploited or abused.</p>
                        </div>
                    </div>

                    <div class="question">
                        <a class="expander" href="#">Given that Invoice Ninja
                        is an open source app, how can I be sure that my
                        financial information is safe with you?</a>

                        <div class="content">
                            <p>There’s a big difference between “open source”
                            and “open data.” Anyone who wants to use the code
                            that drives Invoice Ninja to create their own
                            products or to make improvements to ours can do so.
                            It’s available for anyone who wants to download and
                            work with. But that’s just the source code -
                            totally separate from what happens with that code
                            on the Invoice Ninja servers. You’re the only one
                            who has full access to what you're doing with our
                            product. For more details on the security of our
                            servers and how we handle our users’ information,
                            please read the next question.</p>
                        </div>
                    </div>

                    <div class="question">
                        <a class="expander" href="#">So just how secure is this
                        app?</a>

                        <div class="content">
                            <p>Extremely. Data uploaded by our users runs
                            through connections with 256-bit encryption, which
                            is twice as many encryption bits that most bank
                            websites use. We use the TLS 1.0 cryptographic
                            protocol, AES_256_CBC string encryption, SHA1
                            message authentication and DHE_RSA key exchanges.
                            It’s fancy stuff that we put in place to make sure
                            no one can gain access to your information except
                            you.</p>
                        </div>
                    </div>

                    <div class="question">
                        <a class="expander" href="#">I’m interested in removing
                        the small "Created by Invoice Ninja” image from the
                        bottom of my invoices. Will you one day offer a
                        premium, non-branded or otherwise white label-able
                        version of Invoice Ninja?</a>

                        <div class="content">
                            <p>We are considering one day exploring optional
                            features like this and will be happy to hear from
                            you with any suggestions.</p>
                        </div>
                    </div>

                    <div class="question">
                        <a class="expander" href="#">My question wasn’t covered
                        by any of the content on this FAQ page. How can I get
                        in touch with you?</a>

                        <div class="content">
                            <p>Please email us at <a href=
                            "mailto:contact@invoiceninja.com">contact@invoiceninja.com</a>
                            with any questions or comments you have. We love
                            hearing from the people who use our app! We’ll do
                            our best to reply to your email within the business
                            day.</p>
                        </div>
                    </div>
                </div>

                <div class="col-md-4 col-md-offset-1">
                    <div class="contact-box">
                        <h2>Did we miss something?</h2>

                        <p>Please email us at <a href=
                        "mailto:contact@invoiceninja.com" style=
                        "font-weight: bold">contact@invoiceninja.com</a> with
                        any questions or comments you have. We love hearing
                        from the people who use our app! We’ll do our best to
                        reply to your email within the business day.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>@stop