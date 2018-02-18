<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SAMLController extends Controller {

    public function logout() {
        //recover sessionIndex and nameId from session
        $sessionIndex = session()->get('sessionIndex');
        $nameId = session()->get('nameId');
        //get the logout route from saml2 config
        $returnTo = config('saml2_settings.logoutRoute');
        //pass parameters into the url
        return redirect()->route('saml_logout', [
                    'returnTo' => $returnTo,
                    'nameId' => $nameId,
                    'sessionIndex' => $sessionIndex
        ]);
    }

}
