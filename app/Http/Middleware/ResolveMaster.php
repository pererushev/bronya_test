<?php
// app/Http/Middleware/ResolveMaster.php
namespace App\Http\Middleware;

use App\Models\Master;
use Closure;
use Illuminate\Http\Request;

class ResolveMaster
{
    public function handle(Request $request, Closure $next)
    {
        $id = $request->header('X-Master-Id');
        $master = $id ? Master::find($id) : null;

        if (!$master) {
            return response()->json(['error' => 'Master not found'], 401);
        }

        $request->attributes->set('master', $master);

        return $next($request);
    }
}