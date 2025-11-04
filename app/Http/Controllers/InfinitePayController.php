namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Invoice;

class InfinitePayController extends Controller
{
    public function handleRedirect(Request $request, $hashed_id)
    {
        $invoice = Invoice::where('hashed_id', $hashed_id)->firstOrFail();

        $invoice->custom_value1 = $request->input('transaction_id');
        $invoice->custom_value2 = $request->input('slug');
        $invoice->custom_value3 = $request->input('receipt_url');
        $invoice->save();

        return view('infinitepay.success', ['invoice' => $invoice]);
    }
}
