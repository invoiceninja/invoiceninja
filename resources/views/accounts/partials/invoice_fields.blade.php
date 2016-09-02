<div class="col-md-4">

    {!! Former::select()
            ->placeholder(trans("texts.{$section}"))
            ->options($account->getInvoiceFields()[$section]) !!}

</div>
