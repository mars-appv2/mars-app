// app/Http/Controllers/BillingInvoiceController.php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BillingInvoiceController extends Controller
{
    public function index(Request $request)
    {
        // Filter opsional
        $period = $request->input('period'); // format: YYYY-MM

        $invoices = DB::table('invoices as i')
            ->leftJoin('subscriptions as s', 's.id', '=', 'i.subscription_id')
            ->leftJoin('users as u', 'u.id', '=', 'i.user_id')
            ->when($period, function ($q) use ($period) {
                $q->where('i.period', $period);
            })
            ->select([
                'i.id',
                'i.number',
                'i.period',
                'i.due_date',
                'i.status',
                'i.total',
                // PENTING: pastikan subscription_id ikut dipilih
                'i.subscription_id',
                DB::raw('COALESCE(s.name, s.package_name) as subscription_name'),
                'u.name as user_name',
                'u.email as user_email',
            ])
            ->orderByDesc('i.created_at')
            ->paginate(50);

        return view('billing.invoices', compact('invoices', 'period'));
    }
}
