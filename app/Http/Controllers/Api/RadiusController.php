namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Mikrotik;

class RadiusController extends Controller
{
    public function clients()
    {
        $mikrotiks = Mikrotik::all(['name', 'host', 'radius_secret']);
        return response()->json($mikrotiks);
    }
}
