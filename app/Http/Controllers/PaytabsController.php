<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Paytabscom\Laravel_paytabs\Facades\paypage;

class PaytabsController extends Controller
{
    public function return(Request $request) {
        return $request->all();
    }

    public function pay(Request $request, $planId)
    {
        $pay= paypage::sendPaymentCode('all')
            ->sendTransaction('sale')
            ->sendCart(10, 1000, 'GrandCalendar Package 1 Monthly') // Cart ID, Cart Amount, Cart Description
            ->sendCustomerDetails('Walaa Elsaeed', 'w.elsaeed@paytabs.com', '0101111111', 'test', 'Nasr City', 'Cairo', 'EG', '1234','100.279.20.10')
            ->sendHideShipping(true)
            ->sendTokinse(true)
            ->sendURLs(route('paytabs.return'), 'https://eoe7hlbuxdvibew.m.pipedream.net')
            ->sendLanguage('en')
            ->create_pay_page();
        return $pay;
    }
}
