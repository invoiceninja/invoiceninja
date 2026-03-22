<div class="flex flex-col justify-center items-center my-10">

    <form wire:submit="submit">
        @csrf
        @method('POST')
        <div class="shadow overflow-hidden rounded">
            <div class="px-4 py-5 bg-white sm:p-6">
                <div class="grid grid-cols-6 gap-6 max-w-4xl">
                    <div class="col-span-6 sm:col-span-3">
                        <label for="first_name" class="input-label">@lang('texts.first_name')</label>
                        <input id="first_name" class="input w-full" name="first_name" wire:model="first_name"/>
                        @error('first_name')
                        <div class="validation validation-fail">
                            {{ $message }}
                        </div>
                        @enderror
                    </div>

                    <div class="col-span-6 sm:col-span-3">
                        <label for="last_name" class="input-label">@lang('texts.last_name')</label>
                        <input id="last_name" class="input w-full" name="last_name" wire:model="last_name"/>
                        @error('last_name')
                        <div class="validation validation-fail">
                            {{ $message }}
                        </div>
                        @enderror
                    </div>

                    <div class="col-span-6 sm:col-span-4">
                        <label for="email_address" class="input-label">@lang('texts.email_address')</label>
                        <input id="email_address" class="input w-full" type="email" name="email"
                               wire:model="email" disabled="true"/>
                        @error('email')
                        <div class="validation validation-fail">
                            {{ $message }}
                        </div>
                        @enderror
                    </div>

                    <div class="col-span-6 sm:col-span-4">
                        <label for="company_name" class="input-label">@lang('texts.company_name')</label>
                        <input id="company_name" class="input w-full" name="company_name"
                               wire:model="company_name"/>
                        @error('company_name')
                        <div class="validation validation-fail">
                            {{ $message }}
                        </div>
                        @enderror
                    </div>

                    <div class="col-span-6 sm:col-span-4 flex items-center">
                        <label for="country" class="input-label mr-4">@lang('texts.country')</label>

                        <div class="radio mr-4">
                            <input class="form-radio cursor-pointer" type="radio" value="US" name="country" checked
                                   wire:model="country">
                            <span>{{ ctrans('texts.country_United States') }}</span>
                        </div>

                        <div class="radio mr-4">
                            <input class="form-radio cursor-pointer" type="radio" value="CA" name="country"
                                   wire:model="country">
                            <span>{{ ctrans('texts.country_Canada') }}</span>
                        </div>

                        <div class="radio mr-4">
                            <input class="form-radio cursor-pointer" type="radio" value="GB" name="country"
                                   wire:model="country">
                            <span>{{ ctrans('texts.country_United Kingdom') }}</span>
                        </div>

                    </div>

                    @if($country == 'CA')
                        <div class="col-span-6 sm:col-span-4 {{ $country != 'CA' ? 'hidden' : 'block' }}">
                            <label for="country" class="input-label">@lang('texts.debit_cards')</label>

                            <div class="checkbox">
                                <input class="form-checkbox cursor-pointer mr-2" type="checkbox" name="debit_cards" value="1" wire:model="debit_cards">
                                <span>{{ ctrans('texts.accept_debit_cards') }}</span>
                            </div>
                        </div>
                    @endif

                    
                    @if($country == 'US')
                    <div class="col-span-6 sm:col-span-4 {{ $country != 'US' ? 'hidden' : 'block' }}">
                        <label for="country" class="input-label">@lang('texts.ach')</label>
                        <div class="checkbox">
                            <input class="form-checkbox cursor-pointer mr-2" type="checkbox" name="ach" value="1" wire:model.live="ach">
                            <span>{{ ctrans('texts.enable_ach')}}</span>
                        </div>
                    </div>
                    @endif

                    <div class="col-span-6 sm:col-span-4">
                        <label for="country" class="input-label"></label>
                        <div class="checkbox">
                            <input class="form-checkbox cursor-pointer mr-2" type="checkbox" name="wepay_payment_tos_agree" value="1" wire:model="wepay_payment_tos_agree">
                            <span>{!! ctrans('texts.wepay_payment_tos_agree', ['terms' => $terms, 'privacy_policy' => $privacy_policy]) !!}</span>
                        </div>
                        @error('wepay_payment_tos_agree')
                        <div class="validation validation-fail">
                            {{ $message }}
                        </div>
                        @enderror
                    </div>

                    <div class="col-span-6 sm:col-span-4">
                        <span><i>{{ ctrans('texts.standard_fees_apply')}}</i></span>
                    </div>

                    <div class="col-span-6 {{ $country != 'CA' ? 'hidden' : 'block' }}">
                        <table id="canadaFees" width="100%" class="min-w-full"
                               style="border: 1px solid black; margin-bottom: 40px; display: table;">
                            <tbody>
                            <tr style="border: solid 1px black">
                                <th colspan="2" style="text-align:center;padding: 4px">
                                    Fees Disclosure Box
                                </th>
                            </tr>
                            <tr style="border: solid 1px black;vertical-align:top">
                                <td style="border-left: solid 1px black; padding: 8px">
                                    <h4>Payment Card Type</h4>
                                    (These are the most common domestically issued card types
                                    and processing methods. They do not represent all the
                                    possible fees and variations that are charged to the
                                    merchants.)
                                </td>
                                <td style="padding: 8px">
                                    <h4>Processing Method: Card Not Present</h4>
                                    (Means that the card/device was not
                                    electronically read. Generally, the card
                                    information is manually key-entered, e.g. online
                                    payment)
                                </td>
                            </tr>
                            <tr>
                                <td style="border-left: solid 1px black;padding-left:8px;padding-top:4px;">
                                    Visa Consumer Credit
                                </td>
                                <td style="text-align:center">
                                    2.9% + CA$0.30
                                </td>
                            </tr>
                            <tr>
                                <td style="border-left: solid 1px black;padding-left:8px;padding-top:4px;">
                                    Visa Infinite
                                </td>
                                <td style="text-align:center">
                                    2.9% + CA$0.30
                                </td>
                            </tr>
                            <tr>
                                <td style="border-left: solid 1px black;padding-left:8px;padding-top:4px;">
                                    Visa Infinite Privilege
                                </td>
                                <td style="text-align:center">
                                    2.9% + CA$0.30
                                </td>
                            </tr>
                            <tr>
                                <td style="border-left: solid 1px black;padding-left:8px;padding-top:4px;">
                                    Visa Business
                                </td>
                                <td style="text-align:center">
                                    2.9% + CA$0.30
                                </td>
                            </tr>
                            <tr>
                                <td style="border-left: solid 1px black;padding-left:8px;padding-top:4px;">
                                    Visa Business Premium
                                </td>
                                <td style="text-align:center">
                                    2.9% + CA$0.30
                                </td>
                            </tr>
                            <tr>
                                <td style="border-left: solid 1px black;padding-left:8px;padding-top:4px;">
                                    Visa Corporate
                                </td>
                                <td style="text-align:center">
                                    2.9% + CA$0.30
                                </td>
                            </tr>
                            <tr>
                                <td style="border-left: solid 1px black;padding-left:8px;padding-top:4px;">
                                    Visa Prepaid
                                </td>
                                <td style="text-align:center">
                                    2.9% + CA$0.30
                                </td>
                            </tr>
                            <tr>
                                <td style="border-left: solid 1px black;padding-left:8px;padding-top:4px;">
                                    Visa Debit
                                </td>
                                <td style="text-align:center">
                                    2.9% + CA$0.30
                                </td>
                            </tr>
                            <tr>
                                <td style="border-left: solid 1px black;padding-left:8px;padding-top:4px;">
                                    MasterCard Consumer Credit
                                </td>
                                <td style="text-align:center">
                                    2.9% + CA$0.30
                                </td>
                            </tr>
                            <tr>
                                <td style="border-left: solid 1px black;padding-left:8px;padding-top:4px;">
                                    MasterCard World
                                </td>
                                <td style="text-align:center">
                                    2.9% + CA$0.30
                                </td>
                            </tr>
                            <tr>
                                <td style="border-left: solid 1px black;padding-left:8px;padding-top:4px;">
                                    MasterCard World Elite
                                </td>
                                <td style="text-align:center">
                                    2.9% + CA$0.30
                                </td>
                            </tr>
                            <tr>
                                <td style="border-left: solid 1px black;padding-left:8px;padding-top:4px;">
                                    MasterCard Business/Corporate
                                </td>
                                <td style="text-align:center">
                                    2.9% + CA$0.30
                                </td>
                            </tr>
                            <tr>
                                <td style="border-left: solid 1px black;padding-left:8px;padding-top:4px;">
                                    MasterCard Debit
                                </td>
                                <td style="text-align:center">
                                    2.9% + CA$0.30
                                </td>
                            </tr>
                            <tr>
                                <td style="border-left: solid 1px black;padding-left:8px;padding-top:4px;">
                                    MasterCard Prepaid
                                </td>
                                <td style="text-align:center">
                                    2.9% + CA$0.30
                                </td>
                            </tr>
                            <tr>
                                <td style="border-left: solid 1px black;padding-left:8px;padding-top:4px;">
                                    American Express
                                </td>
                                <td style="text-align:center">
                                    2.9% + CA$0.30
                                </td>
                            </tr>
                            <tr style="border: solid 1px black;">
                                <th colspan="2" style="text-align:center;padding: 4px">
                                    Other Fees Disclosure Box

                                </th>
                            </tr>
                            <tr style="border: solid 1px black;">
                                <td style="border-left: solid 1px black;padding-left:8px;padding-top:4px;">
                                    Chargeback
                                </td>
                                <td style="text-align:center">
                                    CA$15.00
                                </td>
                            </tr>
                            </tbody>
                        </table>
                    </div>


                </div>
            </div>

            <div class="px-4 py-3 bg-gray-50 text-right sm:px-6">
                <button class="button button-primary bg-primary">{{ $saved }}</button>
            </div>
        </div>
    </form>
</div>
