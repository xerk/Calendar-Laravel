<?php

namespace App\Http\Controllers;

use App\Http\Resources\TransactionResource;
use App\Models\Transaction;
use Illuminate\Http\Request;
use LaravelDaily\Invoices\Invoice;
use LaravelDaily\Invoices\Classes\Buyer;
use LaravelDaily\Invoices\Classes\InvoiceItem;

class TransactionController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        $transactions = Transaction::where('user_id', $user->id)
            ->whereIn('status', ['A', 'auto_refunded'])
            ->orderBy('created_at', 'desc')->paginate(15);

        return TransactionResource::collection($transactions);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $transaction = Transaction::findOrFail($id);

        return new TransactionResource($transaction);
    }

    /**
     * Get the invoice.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function invoice($id)
    {
        $transaction = Transaction::findOrFail($id);
        $title = $transaction->subscription->plan_data['name'].' '.($transaction->subscription->period === 1 ? 'Monthly' : 'Yearly');
        $amount = ($transaction->subscription->period === 1 ? $transaction->subscription->plan_data['monthly_price'] : $transaction->subscription->plan_data['yearly_price']);
        
        $customer = new Buyer([
            'name' => $transaction->user->name,
            'custom_fields' => [
                'email' => $transaction->user->email,
            ],
        ]);

        $item = (new InvoiceItem())->title($title)->pricePerUnit($amount);

        $invoice = Invoice::make()
            ->series($transaction->id)
            ->buyer($customer)
            ->discountByPercent(0)
            ->taxRate(0)
            ->shipping(0)
            ->currencySymbol('$')
            ->currencyCode($transaction->subscription->plan_data['currency'])
            ->addItem($item);

        return $invoice->stream();
    }
}
